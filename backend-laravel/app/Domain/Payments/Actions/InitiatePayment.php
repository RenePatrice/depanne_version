<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Data\PaymentMethod;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Telephone;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Demande d'encaissement (§8.4, étape 1).
 *
 * Le paiement est enregistré **en attente** dès que l'opérateur a accepté la
 * demande, et pas avant : une ligne créée avant l'appel resterait orpheline si
 * l'opérateur refusait, et le rapprochement comptable partirait avec des
 * fantômes.
 *
 * Le ticket, lui, ne change pas d'état ici. Il ne passera en PAYEE qu'à la
 * confirmation de l'opérateur, par le webhook. Croire un client sur parole
 * parce qu'il a appuyé sur « payer » serait exactement l'erreur que le
 * séquestre est censé empêcher.
 */
final class InitiatePayment
{
    public function __construct(private readonly PaymentProvider $operateur) {}

    /** @throws DomainException */
    public function execute(Ticket $ticket, User $client, ?string $telephone = null): Payment
    {
        if (! $ticket->estLeClient($client)) {
            throw new DomainException('Cette intervention ne t\'appartient pas.');
        }

        if ($ticket->state->etat() !== TicketState::TERMINEE) {
            throw new DomainException($ticket->state->etat() === TicketState::PAYEE
                ? 'Cette intervention est déjà payée.'
                : 'Le paiement s\'ouvrira quand le technicien aura terminé son intervention.');
        }

        $enCours = $this->paiementEnCours($ticket);

        if ($enCours !== null) {
            // Renvoyer le paiement en cours plutôt qu'en ouvrir un second :
            // deux demandes vivantes sur un même ticket, ce sont deux débits
            // possibles, et un remboursement à faire à la main.
            return $enCours;
        }

        $payeur = Telephone::normaliser($telephone ?? $client->phone);

        if ($payeur === null) {
            throw new DomainException('Le numéro de paiement n\'est pas valide.');
        }

        try {
            $intention = $this->operateur->initier(
                $ticket->total_gnf,
                $payeur,
                $ticket->reference,
                route('api.paiements.retour'),
            );
        } catch (RuntimeException $e) {
            throw new DomainException($e->getMessage());
        } catch (Throwable $e) {
            report($e);

            throw new DomainException('Le paiement n\'a pas pu être lancé. Réessaie dans un instant.');
        }

        /** @var Payment $paiement */
        $paiement = DB::transaction(fn (): Payment => Payment::query()->create([
            'ticket_id' => $ticket->getKey(),
            'provider' => $this->operateur->nom(),
            'provider_ref' => $intention->reference,
            'method' => PaymentMethod::ORANGE_MONEY->value,
            'payer_phone' => $payeur,
            'amount_gnf' => $ticket->total_gnf,
            'currency' => 'GNF',
            'status' => PaymentStatus::EN_ATTENTE->value,
            'webhook_payload' => ['initiation' => $intention->brut],
        ]));

        $paiement->setAttribute('instruction', $intention->instruction);
        $paiement->setAttribute('url_paiement', $intention->urlPaiement);

        return $paiement;
    }

    /** Un paiement encore ouvert sur ce ticket, s'il y en a un. */
    private function paiementEnCours(Ticket $ticket): ?Payment
    {
        /** @var Payment|null */
        return Payment::query()
            ->where('ticket_id', $ticket->getKey())
            ->where('status', PaymentStatus::EN_ATTENTE->value)
            ->where('created_at', '>', now()->subMinutes(30))
            ->latest('id')
            ->first();
    }
}
