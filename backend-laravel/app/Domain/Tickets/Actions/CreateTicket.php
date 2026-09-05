<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Actions;

use App\Domain\Accounts\Models\Address;
use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use App\Domain\Pricing\Services\PricingService;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Zones\Services\ZoneService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Publication d'une demande d'intervention (§8.1, §8.2).
 *
 * Le ticket naît en BROUILLON puis passe immédiatement en PUBLIEE par la
 * machine à états : ce détour n'est pas décoratif. Il garantit qu'un ticket
 * publié a toujours une ligne `ticket_events` et un `published_at`, exactement
 * comme n'importe quelle autre transition — l'historique n'a pas de trou au
 * point de départ.
 *
 * Le prix est calculé ici, puis **figé** sur le ticket (ADR-0013). Le client
 * s'engage sur ce qu'il a vu ; une hausse de la grille le lendemain ne peut
 * plus le rattraper.
 */
final class CreateTicket
{
    /** Le §8.1 limite le client à trois photos. */
    public const MAX_PHOTOS = 3;

    public function __construct(
        private readonly PricingService $tarification,
        private readonly ZoneService $zones,
        private readonly TransitionTicket $transition,
    ) {}

    /**
     * @param  array{service_id: int, address_id: int, problem_description?: string|null, photos?: array<int, string>|null}  $donnees
     *
     * @throws DomainException
     */
    public function execute(User $client, array $donnees): Ticket
    {
        $service = $this->service($donnees['service_id']);
        $adresse = $this->adresse($client, $donnees['address_id']);

        $point = $adresse->location;

        if ($point === null) {
            throw new DomainException('Cette adresse n\'a pas de position GPS : impossible de calculer le prix.');
        }

        $zone = $this->zones->pour($point);

        if ($zone === null) {
            throw new DomainException(
                'Cette adresse est hors de notre zone de couverture. '
                .'Nous intervenons pour le moment à Ratoma uniquement.'
            );
        }

        $this->refuserSiDejaEnCours($client);

        // Estimation seulement : le montant ferme sera calculé à
        // l'acceptation, depuis la position réelle du technicien (ADR-0026).
        $devis = $this->tarification->estimation($service, $zone, $point);

        $photos = array_slice($donnees['photos'] ?? [], 0, self::MAX_PHOTOS);

        return DB::transaction(function () use ($client, $service, $adresse, $zone, $point, $devis, $donnees, $photos): Ticket {
            /** @var Ticket $ticket */
            $ticket = Ticket::query()->create(array_merge([
                'reference' => self::reference(),
                'client_id' => $client->getKey(),
                'service_id' => $service->getKey(),
                'zone_id' => $zone->getKey(),
                'state' => TicketState::BROUILLON->value,
                'address_id' => $adresse->getKey(),
                'address_snapshot' => $adresse->toSnapshot(),
                'location' => $point,
                'problem_description' => $donnees['problem_description'] ?? null,
                'photos' => $photos === [] ? null : $photos,
            ], $devis->colonnesTicket()));

            return $this->transition->execute(
                $ticket,
                TicketState::PUBLIEE,
                ActorType::CLIENT,
                (int) $client->getKey(),
                ['total_gnf' => $devis->totalGnf, 'distance_km' => $devis->distance->km],
            );
        });
    }

    /**
     * Un client ne peut pas avoir deux demandes ouvertes en même temps.
     *
     * Sans cette garde, un client agacé par l'attente republie la même panne :
     * deux techniciens se déplacent, un seul est payé, et le second impute
     * l'annulation à son propre taux.
     */
    private function refuserSiDejaEnCours(User $client): void
    {
        $enCours = Ticket::query()
            ->where('client_id', $client->getKey())
            ->where(fn ($q) => $q->active()->orWhere('state', TicketState::PUBLIEE->value))
            ->first();

        if ($enCours !== null) {
            throw new DomainException(sprintf(
                'Ta demande %s est encore en cours. Termine-la ou annule-la avant d\'en créer une autre.',
                $enCours->reference,
            ));
        }
    }

    private function service(int $id): Service
    {
        /** @var Service|null $service */
        $service = Service::query()->where('is_active', true)->find($id);

        if ($service === null) {
            throw new DomainException('Cette prestation n\'est plus disponible.');
        }

        return $service;
    }

    private function adresse(User $client, int $id): Address
    {
        /** @var Address|null $adresse */
        $adresse = Address::query()->where('user_id', $client->getKey())->find($id);

        if ($adresse === null) {
            throw new DomainException('Adresse introuvable.');
        }

        return $adresse;
    }

    /** Numéro lisible au téléphone, tiré d'une séquence PostgreSQL. */
    private static function reference(): string
    {
        $numero = DB::selectOne("SELECT nextval('ticket_reference_seq') AS n");

        return sprintf('DM-%s-%s', now()->format('Y'), str_pad((string) ($numero->n ?? 1), 6, '0', STR_PAD_LEFT));
    }
}
