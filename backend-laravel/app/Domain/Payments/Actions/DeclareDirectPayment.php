<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Payments\Data\PaymentChannel;
use App\Domain\Payments\Data\PaymentEventType;
use App\Domain\Payments\Data\PaymentMethod;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Jobs\RemindPaymentConfirmationJob;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\DirectPaymentSettlement;
use App\Domain\Payments\Services\PaymentJournal;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclarer un paiement direct (ADR-0047).
 *
 * **Le client choisit le moyen** : espèces, Orange Money ou MTN MoMo, payés au
 * technicien lui-même. Le technicien, lui, ne peut déclarer que des espèces
 * reçues — c'est la seule chose qu'il constate sans le client.
 *
 * Déclarer, c'est confirmer de son côté : la date de confirmation du déclarant
 * est posée tout de suite. Le paiement n'est réglé que lorsque l'autre partie
 * confirme à son tour (`ConfirmDirectPayment`). Il n'y a pas de confirmation
 * automatique : sans réponse, une relance part, et le back-office voit le
 * paiement en souffrance.
 */
final class DeclareDirectPayment
{
    /** Délai avant de relancer la partie qui n'a pas confirmé. */
    public const RELANCE_APRES_MINUTES = 120;

    public function __construct(
        private readonly SendNotification $notifier,
        private readonly PaymentJournal $journal,
    ) {}

    /** @throws DomainException */
    public function execute(
        Ticket $ticket,
        User $auteur,
        PaymentMethod $moyen,
        ?string $referenceTransaction = null,
    ): Payment {
        $role = DirectPaymentSettlement::roleDe($ticket, $auteur);

        if ($role === null) {
            throw new DomainException('Cette intervention ne te concerne pas.');
        }

        if ($role === ActorType::TECHNICIEN && $moyen !== PaymentMethod::ESPECES) {
            throw new DomainException('Le moyen de paiement est choisi par le client. Tu peux seulement déclarer des espèces reçues.');
        }

        if ($ticket->is_warranty) {
            throw new DomainException('Un retour sous garantie est gratuit : aucun paiement à déclarer.');
        }

        if ($ticket->state->etat() !== TicketState::TERMINEE) {
            throw new DomainException('Le paiement se déclare une fois l\'intervention terminée.');
        }

        $reference = $moyen->estMobile() ? $this->nettoyer($referenceTransaction) : null;

        $paiement = DB::transaction(function () use ($ticket, $auteur, $role, $moyen, $reference): Payment {
            // Le verrou sur le ticket sérialise deux déclarations simultanées
            // (client et technicien au même instant) : une seule passe.
            Ticket::query()->whereKey($ticket->getKey())->lockForUpdate()->first();

            $ouvert = Payment::query()
                ->where('ticket_id', $ticket->getKey())
                ->whereIn('status', array_map(static fn (PaymentStatus $s): string => $s->value, Payment::STATUTS_OUVERTS))
                ->exists();

            if ($ouvert) {
                throw new DomainException('Un paiement est déjà en cours sur cette intervention.');
            }

            $ticket->loadMissing('client');
            $maintenant = now();

            /** @var Payment $paiement */
            $paiement = Payment::query()->create([
                'ticket_id' => $ticket->getKey(),
                'provider' => 'direct',
                'provider_ref' => ($moyen->estMobile() ? 'MOB-' : 'CASH-').$ticket->reference.'-'.Str::upper(Str::random(6)),
                'channel' => PaymentChannel::DIRECT->value,
                'method' => $moyen->value,
                'payer_phone' => (string) $ticket->client?->phone,
                'amount_gnf' => (int) $ticket->total_gnf,
                'status' => PaymentStatus::ATTENTE_CONFIRMATION->value,
                'declared_by' => $role->value,
                'transaction_ref' => $reference,
                'client_confirmed_at' => $role === ActorType::CLIENT ? $maintenant : null,
                'technician_confirmed_at' => $role === ActorType::TECHNICIEN ? $maintenant : null,
            ]);

            $this->journal->noter($paiement, PaymentEventType::DECLARE, $role, (int) $auteur->getKey(), array_filter([
                'moyen' => $moyen->value,
                'reference_transaction' => $reference,
            ]));

            return $paiement;
        });

        $this->prevenirLAutrePartie($ticket, $paiement, $role);

        RemindPaymentConfirmationJob::dispatch((int) $paiement->getKey())
            ->delay(now()->addMinutes(self::RELANCE_APRES_MINUTES));

        return $paiement;
    }

    private function prevenirLAutrePartie(Ticket $ticket, Payment $paiement, ActorType $role): void
    {
        $ticket->loadMissing(['client', 'technician']);
        $destinataire = $role === ActorType::CLIENT ? $ticket->technician : $ticket->client;

        if ($destinataire === null) {
            return;
        }

        $this->notifier->execute(
            $destinataire,
            NotificationType::PAIEMENT_A_CONFIRMER,
            [
                'auteur' => $role === ActorType::CLIENT ? 'Le client' : 'Le technicien',
                'total' => Money::format($paiement->amount_gnf),
                'moyen' => mb_strtolower($paiement->method->label()),
                'reference' => $ticket->reference,
            ],
            ['ticket_id' => $ticket->getKey(), 'reference' => $ticket->reference],
        );
    }

    /** Référence d'opérateur : lettres, chiffres, points et tirets, 60 caractères. */
    private function nettoyer(?string $reference): ?string
    {
        $propre = preg_replace('/[^A-Za-z0-9.\-]/', '', (string) $reference) ?? '';

        return $propre === '' ? null : mb_substr($propre, 0, 60);
    }
}
