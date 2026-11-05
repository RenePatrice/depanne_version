<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Tickets\Actions\TransitionTicket;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Wallet\Data\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Le client confirme avoir payé en espèces (#1).
 *
 * Seconde moitié de la double confirmation, et la seule qui engage : c'est elle
 * qui inscrit la **commission due** au grand livre et clôt l'intervention.
 * Réserver la confirmation au client — pas au technicien — est ce qui donne du
 * poids à la garantie : un technicien seul ne peut pas se déclarer payé.
 *
 * Pour un paiement en espèces, le technicien a encaissé la totalité : on
 * n'inscrit **que** la commission, en négatif (sa dette). Aucun `EARNING` — le
 * portefeuille n'a jamais tenu cet argent. Le ticket va directement en CLOTUREE,
 * sans séquestre, puisqu'il n'y a rien à libérer.
 */
final class ConfirmCashReceipt
{
    public function __construct(
        private readonly TransitionTicket $transition,
        private readonly SendNotification $notifier,
    ) {}

    /** @throws DomainException */
    public function execute(Ticket $ticket, User $client): Payment
    {
        if (! $ticket->estLeClient($client)) {
            throw new DomainException('Cette demande ne t\'appartient pas.');
        }

        return DB::transaction(function () use ($ticket, $client): Payment {
            /** @var Payment|null $paiement */
            $paiement = Payment::query()
                ->where('ticket_id', $ticket->getKey())
                ->where('status', PaymentStatus::ATTENTE_CONFIRMATION->value)
                ->lockForUpdate()
                ->first();

            if ($paiement === null) {
                throw new DomainException('Aucun paiement en espèces à confirmer sur cette intervention.');
            }

            $commission = (int) $ticket->commission_gnf;
            $technicienId = (int) $ticket->technician_id;

            // Dette : commission négative, sans gain en face. Les paiements
            // Mobile Money du technicien compenseront cette dette.
            if ($commission > 0) {
                $this->ecrire(
                    $technicienId,
                    (int) $ticket->getKey(),
                    -$commission,
                    sprintf('Commission due (espèces) — %s', $ticket->reference),
                );
            }

            $paiement->forceFill([
                'status' => PaymentStatus::CONFIRMEE->value,
                'captured_at' => now(),
                'released_at' => now(),
            ])->save();

            // Espèces : pas de séquestre à libérer, on clôt directement.
            if ($ticket->state->peutAllerVers(TicketState::PAYEE)) {
                $this->transition->execute($ticket, TicketState::PAYEE, ActorType::CLIENT, (int) $client->getKey(), [
                    'paiement' => $paiement->provider_ref,
                    'moyen' => 'especes',
                ]);
            }

            if ($ticket->state->peutAllerVers(TicketState::CLOTUREE)) {
                $this->transition->execute($ticket, TicketState::CLOTUREE, ActorType::CLIENT, (int) $client->getKey(), [
                    'paiement' => $paiement->provider_ref,
                ]);
            }

            DB::afterCommit(function () use ($ticket, $commission): void {
                $this->notifier->execute(
                    $ticket->technician,
                    NotificationType::PAIEMENT_CONFIRME_TECH,
                    ['reference' => $ticket->reference, 'commission' => Money::format($commission)],
                    ['ticket_id' => $ticket->getKey(), 'commission_gnf' => $commission],
                );
            });

            return $paiement;
        });
    }

    private function ecrire(int $userId, int $ticketId, int $montant, string $description): void
    {
        $solde = Transaction::balanceFor($userId);

        Transaction::query()->create([
            'user_id' => $userId,
            'ticket_id' => $ticketId,
            'type' => TransactionType::COMMISSION,
            'amount_gnf' => $montant,
            'balance_after_gnf' => $solde + $montant,
            'description' => $description,
        ]);
    }
}
