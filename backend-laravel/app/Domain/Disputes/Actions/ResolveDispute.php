<?php

declare(strict_types=1);

namespace App\Domain\Disputes\Actions;

use App\Domain\Disputes\Data\DisputeStatus;
use App\Domain\Disputes\Models\Dispute;
use App\Domain\Disputes\Models\DisputeMessage;
use App\Domain\Wallet\Data\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Décision sur un litige (§6).
 *
 * Le remboursement est **enregistré au grand livre** ici — le client est
 * crédité, la part correspondante est reprise au technicien — mais le
 * versement effectif vers Mobile Money relève du fournisseur de paiement
 * (phase C5). L'écran le dit explicitement : décider n'est pas rembourser.
 */
final class ResolveDispute
{
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
            if ($remboursementGnf > 0) {
                $this->ecrireRemboursement($litige, $ticket->id, $ticket->client_id, $ticket->technician_id, $remboursementGnf);
            }

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
    }

    /**
     * Deux mouvements symétriques : le client est crédité, le technicien est
     * débité de la même somme. Le grand livre reste équilibré, et le solde de
     * chacun se recalcule à partir de ses seuls mouvements (ADR-0004).
     */
    private function ecrireRemboursement(
        Dispute $litige,
        int $ticketId,
        int $clientId,
        ?int $technicienId,
        int $montant,
    ): void {
        $this->mouvement($clientId, $ticketId, $montant, 'Remboursement litige '.$litige->reference);

        if ($technicienId !== null) {
            $this->mouvement($technicienId, $ticketId, -$montant, 'Reprise sur litige '.$litige->reference);
        }
    }

    private function mouvement(int $userId, int $ticketId, int $montant, string $description): void
    {
        $solde = Transaction::balanceFor($userId);

        Transaction::query()->create([
            'user_id' => $userId,
            'ticket_id' => $ticketId,
            'type' => TransactionType::REFUND,
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
