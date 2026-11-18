<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Data\PaymentEventType;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use App\Domain\Tickets\Data\ActorType;

/**
 * Écrit le journal d'un paiement (ADR-0047).
 *
 * Un seul point d'écriture, pour que toutes les étapes aient la même forme —
 * paiement direct comme paiement via l'application. Le journal est en ajout
 * seul : il n'existe aucune méthode pour corriger ou effacer une ligne.
 */
final class PaymentJournal
{
    /** @param  array<string, mixed>  $details */
    public function noter(
        Payment $paiement,
        PaymentEventType $type,
        ActorType $acteur,
        ?int $acteurId = null,
        array $details = [],
        ?int $montantGnf = null,
    ): PaymentEvent {
        /** @var PaymentEvent */
        return PaymentEvent::query()->create([
            'payment_id' => $paiement->getKey(),
            'ticket_id' => $paiement->ticket_id,
            'type' => $type->value,
            'actor_type' => $acteur->value,
            'actor_id' => $acteurId,
            'amount_gnf' => $montantGnf ?? $paiement->amount_gnf,
            'details' => $details === [] ? null : $details,
        ]);
    }
}
