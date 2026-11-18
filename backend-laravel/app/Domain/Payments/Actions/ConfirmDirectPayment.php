<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Payments\Data\PaymentChannel;
use App\Domain\Payments\Data\PaymentEventType;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\DirectPaymentSettlement;
use App\Domain\Payments\Services\PaymentJournal;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Models\Ticket;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Confirmer un paiement direct déclaré par l'autre partie (ADR-0047).
 *
 * Le paiement n'est réglé qu'avec les **deux** confirmations : le client dit
 * qu'il a payé, le technicien dit qu'il a reçu. Personne ne confirme pour
 * l'autre, ni deux fois pour soi.
 */
final class ConfirmDirectPayment
{
    public function __construct(
        private readonly PaymentJournal $journal,
        private readonly DirectPaymentSettlement $reglement,
    ) {}

    /** @throws DomainException */
    public function execute(Ticket $ticket, User $acteur): Payment
    {
        $role = DirectPaymentSettlement::roleDe($ticket, $acteur);

        if ($role === null) {
            throw new DomainException('Cette intervention ne te concerne pas.');
        }

        return DB::transaction(function () use ($ticket, $acteur, $role): Payment {
            /** @var Payment|null $paiement */
            $paiement = Payment::query()
                ->where('ticket_id', $ticket->getKey())
                ->where('channel', PaymentChannel::DIRECT->value)
                ->where('status', PaymentStatus::ATTENTE_CONFIRMATION->value)
                ->lockForUpdate()
                ->first();

            if ($paiement === null) {
                throw new DomainException('Aucun paiement à confirmer sur cette intervention.');
            }

            $colonne = $role === ActorType::CLIENT ? 'client_confirmed_at' : 'technician_confirmed_at';

            if ($paiement->getAttribute($colonne) !== null) {
                throw new DomainException($role === ActorType::CLIENT
                    ? 'Tu as déjà confirmé ce paiement. Il attend la confirmation du technicien.'
                    : 'Tu as déjà confirmé ce paiement. Il attend la confirmation du client.');
            }

            $paiement->forceFill([$colonne => now()])->save();
            $this->journal->noter($paiement, PaymentEventType::CONFIRME, $role, (int) $acteur->getKey());

            if ($paiement->client_confirmed_at !== null && $paiement->technician_confirmed_at !== null) {
                $this->reglement->regler($paiement, $ticket, $role, (int) $acteur->getKey());
            }

            return $paiement;
        });
    }
}
