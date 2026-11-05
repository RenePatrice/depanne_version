<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Garantie après intervention (#4, décision du 5 novembre 2026).
 *
 * Quand la panne revient dans la fenêtre de garantie, le client déclenche le
 * retour du **même technicien**, sans nouveau paiement. Ce retour est un ticket
 * à part entière — il a sa conversation, sa position sur la carte du support et
 * sa machine à états — mais rattaché à l'intervention d'origine et marqué
 * `is_warranty`. Il part en ACCEPTEE : le technicien est déjà désigné, la place
 * de marché ne joue pas.
 *
 * La garantie n'existe qu'ici, dans l'application : c'est la première parade au
 * contournement (#3). Un technicien qui pousse à régler « en dehors » prive le
 * client de ce recours, et le client le sait.
 */
final class OpenWarranty
{
    public function __construct(
        private readonly TransitionTicket $transition,
        private readonly SendNotification $notifier,
    ) {}

    /** @throws DomainException */
    public function execute(Ticket $parent, User $client, string $description): Ticket
    {
        if (! $parent->estLeClient($client)) {
            throw new DomainException('Cette intervention ne te concerne pas.');
        }

        if (! $parent->garantieActive()) {
            throw new DomainException(
                'Cette intervention n\'est plus sous garantie, ou ne l\'a jamais été.'
            );
        }

        if ($parent->technician_id === null) {
            throw new DomainException('L\'intervention d\'origine n\'a pas de technicien assigné.');
        }

        // Un seul retour sous garantie vivant à la fois : le précédent doit être
        // clôturé ou annulé avant d'en rouvrir un. Sinon un client pourrait en
        // empiler plusieurs et mobiliser le technicien sur des doublons.
        $dejaOuvert = $parent->warranties()
            ->whereNotIn('state', TicketState::finalStates())
            ->exists();

        if ($dejaOuvert) {
            throw new DomainException('Un retour sous garantie est déjà en cours sur cette intervention.');
        }

        $this->refuserSiAutreEnCours($client);

        return DB::transaction(function () use ($parent, $client, $description): Ticket {
            /** @var Ticket $garantie */
            $garantie = Ticket::query()->create([
                'reference' => $this->reference(),
                'parent_ticket_id' => $parent->getKey(),
                'is_warranty' => true,
                'client_id' => $parent->client_id,
                'technician_id' => $parent->technician_id,
                'service_id' => $parent->service_id,
                'zone_id' => $parent->zone_id,
                'state' => TicketState::BROUILLON->value,
                'address_id' => $parent->address_id,
                'address_snapshot' => $parent->address_snapshot,
                'location' => $parent->location,
                'problem_description' => 'Garantie — '.$description,
                // Un retour sous garantie est gratuit : aucune assiette, aucune
                // commission. Le technicien revient achever ce qui était dû.
                'distance_km' => 0,
                'distance_is_estimated' => false,
                'base_price_gnf' => 0,
                'short_trip_uplift_gnf' => 0,
                'travel_fee_gnf' => 0,
                'extra_fee_gnf' => 0,
                'total_gnf' => 0,
                'commission_gnf' => 0,
                'technician_net_gnf' => 0,
                'commission_rate' => 0,
            ]);

            // BROUILLON → PUBLIEE → ACCEPTEE : le même détour que toute
            // publication, pour que l'historique parte proprement. Le technicien
            // est pré-désigné, donc l'acceptation est portée par le client qui
            // déclenche le retour.
            $meta = ['garantie_de' => $parent->reference];

            $garantie = $this->transition->execute(
                $garantie, TicketState::PUBLIEE, ActorType::CLIENT, (int) $client->getKey(), $meta
            );
            $garantie = $this->transition->execute(
                $garantie, TicketState::ACCEPTEE, ActorType::CLIENT, (int) $client->getKey(), $meta
            );

            /** @var User $technicien */
            $technicien = $garantie->technician()->firstOrFail();

            $this->notifier->execute(
                $technicien,
                NotificationType::GARANTIE_OUVERTE,
                ['reference' => $parent->reference, 'garantie' => $garantie->reference],
                ['ticket_id' => $garantie->getKey()],
            );

            activity('litiges')
                ->performedOn($garantie)
                ->withProperties(['origine' => $parent->reference])
                ->log('Retour sous garantie ouvert : '.$garantie->reference);

            return $garantie;
        });
    }

    /**
     * Un client ne doit pas avoir deux interventions actives à la fois : le
     * retour sous garantie en est une.
     *
     * @throws DomainException
     */
    private function refuserSiAutreEnCours(User $client): void
    {
        $enCours = Ticket::query()
            ->where('client_id', $client->getKey())
            ->where(fn ($q) => $q->active()->orWhere('state', TicketState::PUBLIEE->value))
            ->first();

        if ($enCours !== null) {
            throw new DomainException(sprintf(
                'Ta demande %s est encore en cours. Termine-la avant d\'ouvrir une garantie.',
                $enCours->reference,
            ));
        }
    }

    private function reference(): string
    {
        $numero = DB::selectOne("SELECT nextval('ticket_reference_seq') AS n");

        return sprintf('DM-%s-%s', now()->format('Y'), str_pad((string) ($numero->n ?? 1), 6, '0', STR_PAD_LEFT));
    }
}
