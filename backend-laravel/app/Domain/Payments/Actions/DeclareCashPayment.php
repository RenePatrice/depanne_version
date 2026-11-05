<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Payments\Data\PaymentMethod;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Str;

/**
 * Le technicien déclare avoir été payé **en espèces** (#1).
 *
 * Première moitié de la double confirmation : le technicien propose, le client
 * confirme (`ConfirmCashReceipt`). Tant que le client n'a pas confirmé, rien
 * n'est inscrit au grand livre et le ticket reste TERMINEE — déclarer ne suffit
 * pas à clore.
 */
final class DeclareCashPayment
{
    public function __construct(private readonly SendNotification $notifier) {}

    /** @throws DomainException */
    public function execute(Ticket $ticket, User $technicien): Payment
    {
        if ((int) $ticket->technician_id !== (int) $technicien->getKey()) {
            throw new DomainException('Cette intervention ne t\'est pas attribuée.');
        }

        if ($ticket->is_warranty) {
            throw new DomainException('Un retour sous garantie est gratuit : aucun paiement à déclarer.');
        }

        if ($ticket->state->etat() !== TicketState::TERMINEE) {
            throw new DomainException('Le paiement en espèces se déclare une fois l\'intervention terminée.');
        }

        if ($this->paiementEnCours($ticket)) {
            throw new DomainException('Un paiement est déjà en cours sur cette intervention.');
        }

        $ticket->loadMissing('client');

        /** @var Payment $paiement */
        $paiement = Payment::query()->create([
            'ticket_id' => $ticket->getKey(),
            'provider' => 'cash',
            'provider_ref' => 'CASH-'.$ticket->reference.'-'.Str::upper(Str::random(6)),
            'method' => PaymentMethod::ESPECES->value,
            'payer_phone' => (string) $ticket->client?->phone,
            'amount_gnf' => (int) $ticket->total_gnf,
            'status' => PaymentStatus::ATTENTE_CONFIRMATION->value,
        ]);

        $this->notifier->execute(
            $ticket->client,
            NotificationType::PAIEMENT_A_CONFIRMER,
            ['reference' => $ticket->reference, 'total' => Money::format((int) $ticket->total_gnf)],
            ['ticket_id' => $ticket->getKey(), 'reference' => $ticket->reference],
        );

        return $paiement;
    }

    private function paiementEnCours(Ticket $ticket): bool
    {
        return Payment::query()
            ->where('ticket_id', $ticket->getKey())
            ->whereIn('status', [
                PaymentStatus::ATTENTE_CONFIRMATION->value,
                PaymentStatus::CONFIRMEE->value,
                PaymentStatus::CAPTUREE->value,
                PaymentStatus::LIBEREE->value,
            ])
            ->exists();
    }
}
