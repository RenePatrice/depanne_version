<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Data\Candidat;
use App\Domain\Matching\Models\MatchAttempt;
use App\Domain\Matching\Services\MatchScorer;
use App\Domain\Matching\Services\TechnicianFinder;
use App\Domain\Pricing\Services\PricingService;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Geo;
use App\Support\Money;

/**
 * Les techniciens disponibles pour une demande, avec leur prix (#2).
 *
 * Le cœur de la place de marché côté client : au lieu d'un technicien poussé
 * par le système, le client **voit plusieurs techniciens** — chacun avec son
 * prix (son tarif + le déplacement depuis sa position), sa note et sa
 * distance — et en choisit un.
 *
 * Le prix affiché est **déjà ferme** pour ce technicien : il est calculé depuis
 * sa position réelle et son tarif, exactement comme il le sera à l'acceptation.
 * Un technicien déjà sollicité pour cette demande n'y figure plus (ADR-0028) :
 * la liste sert à trouver quelqu'un de nouveau, pas à insister.
 */
final class ListAvailableTechnicians
{
    /** En deçà de ce nombre d'interventions, le technicien est « nouveau ». */
    private const SEUIL_NOUVEAU = 5;

    /** Vitesse moyenne retenue pour l'estimation du délai, en km/h (moto, trafic de Conakry). */
    private const VITESSE_KMH = 18.0;

    public function __construct(
        private readonly TechnicianFinder $recherche,
        private readonly PricingService $tarification,
        private readonly MatchScorer $scoring,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(Ticket $ticket, ?int $rayonKm = null): array
    {
        if ($ticket->state->etat() !== TicketState::PUBLIEE) {
            return [];
        }

        $ticket->loadMissing(['service.rates', 'zone']);

        if ($ticket->location === null || $ticket->zone === null || $ticket->service === null) {
            return [];
        }

        $rayon = $rayonKm ?? (int) AppSetting::get(AppSetting::MATCH_MAX_RADIUS_KM, 15);
        $limite = (int) AppSetting::get(AppSetting::MATCH_CANDIDATES_PER_CYCLE, 10);

        $candidats = $this->recherche->autour($ticket, $rayon, $this->dejaSollicites($ticket), max($limite, 20));

        // Les meilleurs d'abord : le client reste libre de choisir, mais le
        // scoring (proximité, note, fiabilité) décide de l'ordre d'affichage.
        $classes = $this->scoring->classer($candidats, $rayon);

        return array_map(fn (array $c): array => $this->presenter($ticket, $c['candidat']), $classes);
    }

    /**
     * @return array<string, mixed>
     */
    private function presenter(Ticket $ticket, Candidat $candidat): array
    {
        $prixPrestation = $ticket->service->priceForTechnician($candidat->technicienId);

        $devis = $this->tarification->pourTechnicien(
            $prixPrestation,
            $ticket->zone,
            Geo::point($candidat->latitude, $candidat->longitude),
            $ticket->location,
        );

        return [
            'technicien_id' => $candidat->technicienId,
            'nom' => $candidat->nom,
            'note' => round($candidat->note, 1),
            'interventions' => $candidat->interventions,
            'nouveau' => $candidat->estNouveau(self::SEUIL_NOUVEAU),
            'distance_km' => round($candidat->distanceKm, 1),
            'eta_min' => max(1, (int) round($candidat->distanceKm / self::VITESSE_KMH * 60)),
            'prix_total_gnf' => $devis->totalGnf,
            'prix_formate' => Money::format($devis->totalGnf),
            'distance_estimee' => $devis->distance->estimee,
        ];
    }

    /** @return array<int, int> */
    private function dejaSollicites(Ticket $ticket): array
    {
        return MatchAttempt::query()
            ->where('ticket_id', $ticket->getKey())
            ->pluck('technician_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
