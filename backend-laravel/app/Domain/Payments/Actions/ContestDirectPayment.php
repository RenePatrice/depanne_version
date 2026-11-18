<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
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
 * Contester un paiement direct déclaré par l'autre partie (ADR-0047).
 *
 * « Je n'ai rien reçu », « j'ai payé par Orange Money, pas en espèces », « le
 * montant n'est pas le bon » : la contestation fige le paiement et l'envoie au
 * support, qui tranche en ayant tout le journal sous les yeux. Rien n'est
 * réglé, aucune commission n'est inscrite tant que le support n'a pas décidé.
 */
final class ContestDirectPayment
{
    public const MOTIF_MIN = 10;

    public function __construct(
        private readonly PaymentJournal $journal,
        private readonly SendNotification $notifier,
    ) {}

    /** @throws DomainException */
    public function execute(Ticket $ticket, User $acteur, string $motif): Payment
    {
        $role = DirectPaymentSettlement::roleDe($ticket, $acteur);

        if ($role === null) {
            throw new DomainException('Cette intervention ne te concerne pas.');
        }

        $motif = trim($motif);

        if (mb_strlen($motif) < self::MOTIF_MIN) {
            throw new DomainException('Explique en quelques mots ce qui ne va pas (10 caractères minimum).');
        }

        $paiement = DB::transaction(function () use ($ticket, $acteur, $role, $motif): Payment {
            /** @var Payment|null $paiement */
            $paiement = Payment::query()
                ->where('ticket_id', $ticket->getKey())
                ->where('channel', PaymentChannel::DIRECT->value)
                ->where('status', PaymentStatus::ATTENTE_CONFIRMATION->value)
                ->lockForUpdate()
                ->first();

            if ($paiement === null) {
                throw new DomainException('Aucun paiement à contester sur cette intervention.');
            }

            $dejaConfirme = $role === ActorType::CLIENT
                ? $paiement->client_confirmed_at !== null
                : $paiement->technician_confirmed_at !== null;

            if ($dejaConfirme) {
                throw new DomainException('Tu as toi-même déclaré ce paiement : annule-le plutôt que de le contester.');
            }

            $paiement->forceFill([
                'status' => PaymentStatus::CONTESTEE->value,
                'contested_at' => now(),
                'contested_by' => $role->value,
                'contest_reason' => mb_substr($motif, 0, 500),
            ])->save();

            $this->journal->noter($paiement, PaymentEventType::CONTESTE, $role, (int) $acteur->getKey(), ['motif' => $motif]);

            activity('finances')
                ->performedOn($paiement)
                ->withProperties(['ticket' => $ticket->reference, 'par' => $role->value, 'motif' => $motif])
                ->log('Paiement direct contesté');

            return $paiement;
        });

        $ticket->loadMissing(['client', 'technician']);
        $autre = $role === ActorType::CLIENT ? $ticket->technician : $ticket->client;

        if ($autre !== null) {
            $this->notifier->execute(
                $autre,
                NotificationType::PAIEMENT_CONTESTE,
                ['auteur' => $role === ActorType::CLIENT ? 'Le client' : 'Le technicien', 'reference' => $ticket->reference],
                ['ticket_id' => $ticket->getKey()],
            );
        }

        return $paiement;
    }
}
