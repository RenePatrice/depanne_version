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
use App\Domain\Tickets\Models\Ticket;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Retirer sa propre déclaration tant que l'autre partie n'a pas répondu
 * (ADR-0047) — mauvais moyen choisi, mauvaise touche. Le retrait reste écrit au
 * journal ; une nouvelle déclaration devient possible.
 */
final class CancelDirectPayment
{
    public function __construct(private readonly PaymentJournal $journal) {}

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
                throw new DomainException('Aucune déclaration en cours à annuler.');
            }

            if ($paiement->declared_by !== $role) {
                throw new DomainException('Seule la personne qui a déclaré ce paiement peut l\'annuler. Tu peux le contester.');
            }

            $paiement->forceFill(['status' => PaymentStatus::ANNULEE->value])->save();
            $this->journal->noter($paiement, PaymentEventType::ANNULE, $role, (int) $acteur->getKey());

            return $paiement;
        });
    }
}
