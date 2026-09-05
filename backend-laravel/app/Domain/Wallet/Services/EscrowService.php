<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Tickets\Actions\TransitionTicket;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Wallet\Data\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Séquestre et répartition (§8.4, étapes 3 à 5).
 *
 * Entre la capture et la libération, l'argent est **encaissé mais pas acquis**.
 * C'est ce qui donne au client un levier réel : ouvrir une réclamation dans les
 * 72 heures suspend une libération qui n'a pas encore eu lieu. Un versement
 * immédiat au technicien réduirait le litige à une négociation.
 *
 * La libération arrive par deux chemins : le client valide, ou le délai passe
 * — vingt-quatre heures par défaut, paramétrable. Le second n'est pas une
 * commodité : sans lui, un client qui n'ouvre plus l'application bloquerait
 * indéfiniment l'argent d'un technicien qui a fait son travail.
 *
 * ### La convention du grand livre
 *
 * Deux mouvements, pas un : `EARNING` du **total** puis `COMMISSION` en
 * négatif. Le solde du technicien fait bien son net, mais la table garde trace
 * de ce qu'il a facturé *et* de ce que la plateforme a prélevé. Un seul
 * mouvement net rendrait le chiffre d'affaires irrécupérable depuis le grand
 * livre. C'est la convention posée par le jeu de démonstration en A1, et elle
 * ne change pas.
 */
final class EscrowService
{
    public function __construct(
        private readonly TransitionTicket $transition,
        private readonly SendNotification $notifier,
    ) {}

    public function delaiLiberationHeures(): int
    {
        return (int) AppSetting::get(AppSetting::ESCROW_AUTO_RELEASE_HOURS, 24);
    }

    /**
     * Libère les fonds et écrit la répartition au grand livre.
     *
     * Rejouable sans dommage : un paiement déjà libéré ressort tel quel, sans
     * second mouvement. C'est indispensable — le job différé, la validation du
     * client et une éventuelle reprise manuelle peuvent arriver dans n'importe
     * quel ordre.
     *
     * @param  ActorType  $acteur  qui a déclenché la libération
     */
    public function liberer(Payment $paiement, ActorType $acteur = ActorType::SYSTEME, ?int $acteurId = null): bool
    {
        return DB::transaction(function () use ($paiement, $acteur, $acteurId): bool {
            /** @var Payment $verrouille */
            $verrouille = Payment::query()->whereKey($paiement->getKey())->lockForUpdate()->firstOrFail();

            if ($verrouille->status !== PaymentStatus::CAPTUREE) {
                return false;
            }

            $ticket = $verrouille->ticket;

            // Un litige ouvert gèle la libération : c'est tout l'intérêt du
            // séquestre. Le job différé repassera après la résolution.
            if ($ticket->state->etat() === TicketState::LITIGE_OUVERT) {
                Log::info('Séquestre : libération suspendue par un litige.', [
                    'ticket' => $ticket->reference,
                ]);

                return false;
            }

            $commission = (int) $ticket->commission_gnf;
            $total = (int) $ticket->total_gnf;
            $technicienId = (int) $ticket->technician_id;

            $this->ecrire(
                $technicienId,
                (int) $ticket->getKey(),
                TransactionType::EARNING,
                $total,
                'Intervention '.$ticket->reference,
            );

            $this->ecrire(
                $technicienId,
                (int) $ticket->getKey(),
                TransactionType::COMMISSION,
                -$commission,
                sprintf('Commission plateforme (%d %%) — %s', (int) round((float) $ticket->commission_rate * 100), $ticket->reference),
            );

            $verrouille->forceFill([
                'status' => PaymentStatus::LIBEREE,
                'released_at' => now(),
            ])->save();

            if ($ticket->state->peutAllerVers(TicketState::CLOTUREE)) {
                $this->transition->execute($ticket, TicketState::CLOTUREE, $acteur, $acteurId, [
                    'paiement' => $verrouille->provider_ref,
                    'net_technicien_gnf' => $total - $commission,
                ]);
            }

            DB::afterCommit(function () use ($ticket, $total, $commission): void {
                $this->notifier->execute(
                    $ticket->technician,
                    NotificationType::PAIEMENT_RECU,
                    [
                        'reference' => $ticket->reference,
                        'total' => Money::format($total - $commission),
                        'moyen' => 'portefeuille',
                    ],
                    ['ticket_id' => $ticket->getKey(), 'net_gnf' => $total - $commission],
                );
            });

            return true;
        });
    }

    /**
     * Écrit un mouvement en recalculant le solde **dans la transaction**.
     *
     * `balance_after_gnf` est une commodité de lecture, pas une source de
     * vérité : le solde reste la somme des mouvements (ADR-0004). Le calculer
     * ici sous verrou évite qu'une colonne d'affichage ne raconte autre chose
     * que la somme.
     */
    private function ecrire(int $userId, ?int $ticketId, TransactionType $type, int $montant, string $description): void
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
}
