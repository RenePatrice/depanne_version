<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Accounts\Models\AdminUser;
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
 * Le support tranche un paiement direct contesté ou resté sans réponse
 * (ADR-0047).
 *
 *  - **confirmer** : après avoir joint les deux parties, le support constate
 *    que le paiement a eu lieu. Il est réglé comme une double confirmation.
 *  - **annuler** : le paiement n'a pas eu lieu. La déclaration est annulée et
 *    une nouvelle peut être faite.
 *
 * La décision est toujours motivée, signée et écrite au journal comme au
 * journal d'audit : c'est une écriture qui engage de l'argent.
 */
final class ArbitratePayment
{
    public const MOTIF_MIN = 10;

    public function __construct(
        private readonly PaymentJournal $journal,
        private readonly DirectPaymentSettlement $reglement,
        private readonly SendNotification $notifier,
    ) {}

    /** @throws DomainException */
    public function execute(Payment $paiement, AdminUser $admin, string $decision, string $motif): Payment
    {
        if (! in_array($decision, ['confirmer', 'annuler'], true)) {
            throw new DomainException('Décision inconnue.');
        }

        $motif = trim($motif);

        if (mb_strlen($motif) < self::MOTIF_MIN) {
            throw new DomainException('La décision doit être motivée (10 caractères minimum).');
        }

        $resultat = DB::transaction(function () use ($paiement, $admin, $decision, $motif): Payment {
            /** @var Payment $verrouille */
            $verrouille = Payment::query()->whereKey($paiement->getKey())->lockForUpdate()->firstOrFail();

            $enSuspens = in_array($verrouille->status, [PaymentStatus::ATTENTE_CONFIRMATION, PaymentStatus::CONTESTEE], true);

            if ($verrouille->channel !== PaymentChannel::DIRECT || ! $enSuspens) {
                throw new DomainException('Ce paiement n\'attend aucune décision du support.');
            }

            /** @var Ticket $ticket */
            $ticket = Ticket::query()->whereKey($verrouille->ticket_id)->firstOrFail();

            $this->journal->noter($verrouille, PaymentEventType::ARBITRE, ActorType::ADMIN, (int) $admin->getKey(), [
                'decision' => $decision,
                'motif' => $motif,
                'statut_precedent' => $verrouille->status->value,
            ]);

            if ($decision === 'confirmer') {
                $this->reglement->regler($verrouille, $ticket, ActorType::ADMIN, (int) $admin->getKey());
            } else {
                $verrouille->forceFill(['status' => PaymentStatus::ANNULEE->value])->save();
            }

            activity('finances')
                ->causedBy($admin)
                ->performedOn($verrouille)
                ->withProperties(['ticket' => $ticket->reference, 'decision' => $decision, 'motif' => $motif])
                ->log($decision === 'confirmer' ? 'Paiement direct confirmé par le support' : 'Paiement direct annulé par le support');

            return $verrouille;
        });

        $ticket = Ticket::query()->with(['client', 'technician'])->find($resultat->ticket_id);

        foreach ([$ticket?->client, $ticket?->technician] as $partie) {
            if ($partie === null || $ticket === null) {
                continue;
            }

            $this->notifier->execute(
                $partie,
                NotificationType::PAIEMENT_ARBITRE,
                [
                    'decision' => $decision === 'confirmer' ? 'confirmé' : 'annulé',
                    'reference' => $ticket->reference,
                    'motif' => $motif,
                ],
                ['ticket_id' => $ticket->getKey()],
            );
        }

        return $resultat;
    }
}
