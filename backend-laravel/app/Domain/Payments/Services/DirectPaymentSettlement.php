<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Payments\Data\PaymentEventType;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Tickets\Actions\TransitionTicket;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Wallet\Data\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Règle un paiement direct une fois qu'il est confirmé (ADR-0047).
 *
 * Le technicien a reçu la totalité de la main du client — en espèces ou sur son
 * propre numéro Mobile Money : le portefeuille n'a jamais tenu cet argent. On
 * inscrit donc **uniquement** la commission, en négatif : c'est sa dette envers
 * la plateforme (#1). Puis l'intervention est clôturée, sans séquestre.
 *
 * Appelé à l'intérieur d'une transaction, le paiement déjà verrouillé.
 */
final class DirectPaymentSettlement
{
    public function __construct(
        private readonly TransitionTicket $transition,
        private readonly SendNotification $notifier,
        private readonly PaymentJournal $journal,
    ) {}

    /** Rôle d'un utilisateur dans le paiement d'une intervention, ou `null`. */
    public static function roleDe(Ticket $ticket, User $utilisateur): ?ActorType
    {
        if ($ticket->estLeClient($utilisateur)) {
            return ActorType::CLIENT;
        }

        return $ticket->estLeTechnicien($utilisateur) ? ActorType::TECHNICIEN : null;
    }

    public function regler(Payment $paiement, Ticket $ticket, ActorType $acteur, ?int $acteurId): void
    {
        $commission = (int) $ticket->commission_gnf;

        if ($commission > 0) {
            $technicienId = (int) $ticket->technician_id;
            $solde = Transaction::balanceFor($technicienId);

            Transaction::query()->create([
                'user_id' => $technicienId,
                'ticket_id' => $ticket->getKey(),
                'type' => TransactionType::COMMISSION,
                'amount_gnf' => -$commission,
                'balance_after_gnf' => $solde - $commission,
                'description' => sprintf(
                    'Commission due (%s, paiement direct) — %s',
                    mb_strtolower($paiement->method->label()),
                    $ticket->reference,
                ),
            ]);

            $this->journal->noter($paiement, PaymentEventType::COMMISSION, ActorType::SYSTEME, null, [
                'taux' => (float) $ticket->commission_rate,
            ], $commission);
        }

        $paiement->forceFill([
            'status' => PaymentStatus::CONFIRMEE->value,
            'captured_at' => now(),
            'released_at' => now(),
        ])->save();

        $meta = ['paiement' => $paiement->provider_ref, 'moyen' => $paiement->method->value];

        if ($ticket->state->peutAllerVers(TicketState::PAYEE)) {
            $this->transition->execute($ticket, TicketState::PAYEE, $acteur, $acteurId, $meta);
        }

        if ($ticket->state->peutAllerVers(TicketState::CLOTUREE)) {
            $this->transition->execute($ticket, TicketState::CLOTUREE, $acteur, $acteurId, $meta);
        }

        $ticket->loadMissing(['client', 'technician']);

        DB::afterCommit(function () use ($ticket, $paiement, $commission): void {
            if ($ticket->technician !== null) {
                $this->notifier->execute(
                    $ticket->technician,
                    NotificationType::PAIEMENT_CONFIRME_TECH,
                    ['reference' => $ticket->reference, 'commission' => Money::format($commission)],
                    ['ticket_id' => $ticket->getKey(), 'commission_gnf' => $commission],
                );
            }

            if ($ticket->client !== null) {
                $this->notifier->execute(
                    $ticket->client,
                    NotificationType::PAIEMENT_RECU,
                    ['reference' => $ticket->reference, 'total' => Money::format($paiement->amount_gnf)],
                    ['ticket_id' => $ticket->getKey()],
                );
            }
        });
    }
}
