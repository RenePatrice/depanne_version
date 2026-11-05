<?php

declare(strict_types=1);

namespace App\Domain\Disputes\Actions;

use App\Domain\Disputes\Data\DisputeStatus;
use App\Domain\Disputes\Models\Dispute;
use App\Domain\Disputes\Models\DisputeMessage;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Pricing\Services\PricingService;
use App\Domain\Tickets\Actions\TransitionTicket;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Wallet\Data\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Services\EscrowService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Décision sur un litige (§6).
 *
 * Trancher, c'est aussi **régler** : dans la même transaction que la décision,
 * l'argent et le ticket sortent du gel posé par la réclamation (ADR-0041).
 * Trois situations, selon où en était l'argent :
 *
 * - **encore en séquestre** : il est libéré, diminué du remboursement, et la
 *   commission ne porte que sur ce qui reste facturé (prorata) ;
 * - **déjà libéré** : le technicien rend le remboursement, la plateforme lui
 *   restitue la commission prélevée sur la part remboursée — le résultat est
 *   le même qu'en séquestre, quel que soit le moment du litige ;
 * - **jamais encaissé** : il n'y a rien à rembourser, le ticket revient en
 *   attente de paiement.
 *
 * Le crédit du client est écrit au grand livre. Le reversement sur son compte
 * Mobile Money reste un geste manuel : `PaymentProvider` n'expose pas de
 * remboursement, et l'écran le dit — décider n'est pas rembourser.
 */
final class ResolveDispute
{
    public function __construct(
        private readonly EscrowService $sequestre,
        private readonly PricingService $tarification,
        private readonly TransitionTicket $transition,
    ) {}

    public const RESOLUTIONS = [
        'REMBOURSEMENT_TOTAL' => 'Remboursement total',
        'REMBOURSEMENT_PARTIEL' => 'Remboursement partiel',
        'AVERTISSEMENT' => 'Avertissement au technicien',
        'SANCTION' => 'Sanction du technicien',
        'AUCUNE_ACTION' => 'Aucune action',
    ];

    public function prendreEnCharge(Dispute $litige, int $adminId): Dispute
    {
        if ($litige->status !== DisputeStatus::OUVERT) {
            throw new DomainException('Ce litige est déjà '.mb_strtolower($litige->status->label()).'.');
        }

        $litige->forceFill(['status' => DisputeStatus::EN_COURS])->save();

        activity('litiges')
            ->performedOn($litige)
            ->withProperties(['reference' => $litige->reference, 'admin_id' => $adminId])
            ->log('Litige pris en charge : '.$litige->reference);

        return $litige;
    }

    public function resoudre(
        Dispute $litige,
        string $resolution,
        string $note,
        int $remboursementGnf,
        int $adminId,
    ): Dispute {
        if (! $litige->status->isOpen()) {
            throw new DomainException('Ce litige est déjà clos.');
        }

        if (! array_key_exists($resolution, self::RESOLUTIONS)) {
            throw new DomainException('Décision inconnue : '.$resolution.'.');
        }

        $litige->loadMissing('ticket');
        $ticket = $litige->ticket;

        if ($ticket === null) {
            throw new DomainException('Ce litige n\'est plus rattaché à un ticket.');
        }

        if ($remboursementGnf < 0) {
            throw new DomainException('Un remboursement ne peut pas être négatif.');
        }

        if ($remboursementGnf > $ticket->total_gnf) {
            throw new DomainException(sprintf(
                'Le remboursement (%s GNF) dépasse le montant payé par le client (%s GNF).',
                number_format($remboursementGnf, 0, ',', ' '),
                number_format($ticket->total_gnf, 0, ',', ' '),
            ));
        }

        if ($resolution === 'REMBOURSEMENT_TOTAL' && $remboursementGnf !== $ticket->total_gnf) {
            throw new DomainException(
                'Un remboursement total doit porter sur la totalité du montant payé, soit '
                .number_format($ticket->total_gnf, 0, ',', ' ').' GNF.'
            );
        }

        if (str_starts_with($resolution, 'REMBOURSEMENT') && $remboursementGnf === 0) {
            throw new DomainException('Un remboursement doit porter sur un montant supérieur à zéro.');
        }

        return DB::transaction(function () use ($litige, $ticket, $resolution, $note, $remboursementGnf, $adminId): Dispute {
            $this->regler($litige, $ticket, $remboursementGnf, $adminId);

            $litige->forceFill([
                'status' => DisputeStatus::RESOLU,
                'resolution' => $resolution,
                'resolution_note' => $note,
                'refund_gnf' => $remboursementGnf,
                'resolved_by' => $adminId,
                'resolved_at' => now(),
            ])->save();

            activity('litiges')
                ->performedOn($litige)
                ->withProperties([
                    'reference' => $litige->reference,
                    'resolution' => $resolution,
                    'remboursement' => $remboursementGnf,
                ])
                ->log('Litige résolu : '.$litige->reference);

            return $litige;
        });
    }

    public function rejeter(Dispute $litige, string $note, int $adminId): Dispute
    {
        if (! $litige->status->isOpen()) {
            throw new DomainException('Ce litige est déjà clos.');
        }

        $litige->loadMissing('ticket');

        return DB::transaction(function () use ($litige, $note, $adminId): Dispute {
            if ($litige->ticket !== null) {
                $this->regler($litige, $litige->ticket, 0, $adminId);
            }

            $litige->forceFill([
                'status' => DisputeStatus::REJETE,
                'resolution' => 'AUCUNE_ACTION',
                'resolution_note' => $note,
                'refund_gnf' => 0,
                'resolved_by' => $adminId,
                'resolved_at' => now(),
            ])->save();

            activity('litiges')
                ->performedOn($litige)
                ->withProperties(['reference' => $litige->reference, 'note' => $note])
                ->log('Litige rejeté : '.$litige->reference);

            return $litige;
        });
    }

    /**
     * Sort l'argent et le ticket du gel posé par la réclamation.
     *
     * @throws DomainException si un remboursement vise un ticket jamais encaissé
     */
    private function regler(Dispute $litige, Ticket $ticket, int $remboursementGnf, int $adminId): void
    {
        /** @var Payment|null $sequestre */
        $sequestre = Payment::query()
            ->where('ticket_id', $ticket->getKey())
            ->where('status', PaymentStatus::CAPTUREE->value)
            ->lockForUpdate()
            ->first();

        if ($sequestre !== null) {
            $this->sequestre->reglerApresLitige($sequestre, $remboursementGnf, $litige->reference);
            $this->crediterLeClient($litige, $ticket, $remboursementGnf);
            $this->sortirDuLitige($litige, $ticket, TicketState::CLOTUREE, $remboursementGnf, $adminId);

            return;
        }

        if ($ticket->state->etat() === TicketState::LITIGE_OUVERT && ! $this->dejaLibere($ticket)) {
            if ($remboursementGnf > 0) {
                throw new DomainException(
                    'Aucun paiement n\'a été encaissé sur ce ticket : il n\'y a rien à rembourser.'
                );
            }

            $this->sortirDuLitige($litige, $ticket, TicketState::TERMINEE, 0, $adminId);

            return;
        }

        if ($remboursementGnf > 0) {
            $this->crediterLeClient($litige, $ticket, $remboursementGnf);
            $this->reprendreAuTechnicien($litige, $ticket, $remboursementGnf);
        }

        $this->sortirDuLitige($litige, $ticket, TicketState::CLOTUREE, $remboursementGnf, $adminId);
    }

    private function dejaLibere(Ticket $ticket): bool
    {
        return Payment::query()
            ->where('ticket_id', $ticket->getKey())
            ->whereIn('status', [PaymentStatus::LIBEREE->value, PaymentStatus::REMBOURSEE->value])
            ->exists();
    }

    private function crediterLeClient(Dispute $litige, Ticket $ticket, int $montant): void
    {
        if ($montant > 0) {
            $this->mouvement(
                (int) $ticket->client_id,
                (int) $ticket->getKey(),
                TransactionType::REFUND,
                $montant,
                'Remboursement litige '.$litige->reference,
            );
        }
    }

    /**
     * Les fonds sont déjà sur le portefeuille du technicien : il rend le
     * remboursement, et la plateforme lui restitue la commission prélevée sur
     * la part remboursée. Les deux gestes restent séparés au grand livre,
     * comme à la libération — gain d'un côté, commission de l'autre.
     */
    private function reprendreAuTechnicien(Dispute $litige, Ticket $ticket, int $montant): void
    {
        if ($ticket->technician_id === null) {
            return;
        }

        $technicienId = (int) $ticket->technician_id;

        $this->mouvement(
            $technicienId,
            (int) $ticket->getKey(),
            TransactionType::REFUND,
            -$montant,
            'Reprise sur litige '.$litige->reference,
        );

        [, $commissionRestante] = $this->tarification->apresRemboursement($ticket, $montant);
        $restitution = (int) $ticket->commission_gnf - $commissionRestante;

        if ($restitution > 0) {
            $this->mouvement(
                $technicienId,
                (int) $ticket->getKey(),
                TransactionType::COMMISSION,
                $restitution,
                'Commission restituée — litige '.$litige->reference,
            );
        }
    }

    private function sortirDuLitige(Dispute $litige, Ticket $ticket, TicketState $cible, int $remboursementGnf, int $adminId): void
    {
        if (! $ticket->state->peutAllerVers($cible)) {
            return;
        }

        $this->transition->execute($ticket, $cible, ActorType::ADMIN, $adminId, [
            'litige' => $litige->reference,
            'remboursement_gnf' => $remboursementGnf,
        ]);
    }

    private function mouvement(int $userId, int $ticketId, TransactionType $type, int $montant, string $description): void
    {
        $solde = Transaction::balanceFor($userId);

        Transaction::query()->create([
            'user_id' => $userId,
            'ticket_id' => $ticketId,
            'type' => $type,
            'amount_gnf' => $montant,
            'balance_after_gnf' => $solde + $montant,
            'description' => $description,
        ]);
    }

    /** Message de la messagerie interne (§6). */
    public function ecrire(Dispute $litige, string $contenu, string $audience, bool $interne, int $adminId): DisputeMessage
    {
        return DisputeMessage::query()->create([
            'dispute_id' => $litige->id,
            'author_type' => 'SUPPORT',
            'author_id' => $adminId,
            'audience' => $interne ? 'LES_DEUX' : $audience,
            'is_internal' => $interne,
            'content' => $contenu,
        ]);
    }
}
