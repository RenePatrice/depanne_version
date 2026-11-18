<?php

declare(strict_types=1);

namespace App\Domain\Payments\Jobs;

use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Payments\Data\PaymentEventType;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\PaymentJournal;
use App\Domain\Tickets\Data\ActorType;
use App\Support\Money;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Relance la partie qui n'a pas confirmé un paiement direct (ADR-0047).
 *
 * Planifiée à la déclaration. Il n'y a **pas** de confirmation automatique : le
 * client doit confirmer lui-même. La relance est envoyée une seule fois ; au-delà,
 * c'est le back-office qui voit le paiement en souffrance et appelle.
 *
 * Idempotente : sans effet sur un paiement réglé, contesté, annulé ou déjà relancé.
 */
final class RemindPaymentConfirmationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(private readonly int $paiementId) {}

    public function handle(SendNotification $notifier, PaymentJournal $journal): void
    {
        /** @var Payment|null $paiement */
        $paiement = Payment::query()->with(['ticket.client', 'ticket.technician'])->find($this->paiementId);

        if ($paiement === null || $paiement->reminded_at !== null) {
            return;
        }

        $attente = $paiement->attenteDe();

        if ($attente === null) {
            return;
        }

        $destinataire = $attente === ActorType::CLIENT ? $paiement->ticket->client : $paiement->ticket->technician;

        if ($destinataire === null) {
            return;
        }

        $notifier->execute(
            $destinataire,
            NotificationType::PAIEMENT_RELANCE,
            ['total' => Money::format($paiement->amount_gnf), 'reference' => $paiement->ticket->reference],
            ['ticket_id' => $paiement->ticket_id],
        );

        $paiement->forceFill(['reminded_at' => now()])->save();
        $journal->noter($paiement, PaymentEventType::RELANCE, ActorType::SYSTEME, null, ['destinataire' => $attente->value]);
    }
}
