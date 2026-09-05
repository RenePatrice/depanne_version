<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Services;

use App\Domain\Catalog\Models\Service;
use App\Domain\Pricing\Contracts\MapProvider;
use App\Domain\Pricing\Data\Devis;
use App\Domain\Pricing\Data\Distance;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Zones\Models\Zone;
use App\Domain\Zones\Services\ZoneService;
use App\Support\Money;
use Clickbar\Magellan\Data\Geometries\Point;

/**
 * Calcul du prix d'une intervention (§8.2).
 *
 * La distance qui compte est celle **entre le technicien et le client**, et le
 * seuil est le nombre de kilomètres inclus de la zone — 3 km pour le pilote :
 *
 *     sous le seuil    majoration  = prix_prestation × taux_proximité
 *                      déplacement = 0
 *
 *     au-delà          majoration  = 0
 *                      déplacement = arrondi_sup( forfait_zone
 *                                      + (distance − seuil) × prix_par_km )
 *
 *     total = prix_prestation + majoration + déplacement + supplément
 *
 * La majoration de proximité revient **en totalité au technicien** : elle
 * compense un déplacement qui ne lui est pas facturé. La commission de la
 * plateforme se calcule donc sur le total *diminué* de cette majoration.
 *
 * Le barème ne fait pas de marche au passage du seuil : à 2,9 km le client
 * paie 1 % de la prestation, à 3,1 km il paie un millier arrondi. Un forfait de
 * zone non nul recréerait cette marche — c'est pourquoi il vaut zéro pour le
 * pilote, sans pour autant disparaître du back-office.
 *
 * Un seul endroit dans tout le projet produit un montant à payer. Le
 * back-office, l'API mobile et les jeux de démonstration appellent tous cette
 * classe : deux implémentations du même barème finiraient par diverger, et la
 * divergence se lirait directement dans la caisse.
 *
 * Aucune valeur n'est codée en dur : le taux de commission, le taux de
 * proximité et le pas d'arrondi viennent des paramètres, la grille de
 * déplacement vient de la zone, le prix de la prestation vient du catalogue.
 */
final readonly class PricingService
{
    public function __construct(
        private MapProvider $carte,
        private ZoneService $zones,
    ) {}

    /**
     * Estimation affichée avant publication.
     *
     * Le prix dépend de la position du technicien, et aucun technicien n'est
     * assigné à ce moment-là : la distance part donc du point de référence de
     * la zone (ADR-0026). Le devis se déclare explicitement provisoire — c'est
     * `pourTechnicien()` qui produira le montant ferme, à l'acceptation.
     */
    public function estimation(Service $service, Zone $zone, Point $adresse, int $supplementGnf = 0): Devis
    {
        $distance = $this->carte->distance($this->zones->pointDeReference($zone), $adresse);

        return $this->composer($service->base_price_gnf, $zone, $distance, $supplementGnf, ferme: false);
    }

    /**
     * Prix ferme, une fois le technicien connu (§8.2).
     *
     * C'est la distance réelle entre lui et le client qui est facturée. Le
     * technicien n'a aucun moyen de saisir ce montant : il est calculé ici, à
     * partir de sa position et de la grille de la zone, et aucune route de
     * l'API ne lui permet de l'influencer.
     */
    public function pourTechnicien(
        Service $service,
        Zone $zone,
        Point $technicien,
        Point $adresse,
        int $supplementGnf = 0,
    ): Devis {
        $distance = $this->carte->distance($technicien, $adresse);

        return $this->composer($service->base_price_gnf, $zone, $distance, $supplementGnf, ferme: true);
    }

    /**
     * Recalcule le total d'un ticket après acceptation d'un supplément de
     * diagnostic (§8.1, état DIAGNOSTIC_VALIDE).
     *
     * Ni le déplacement ni la majoration de proximité ne sont recalculés : ils
     * ont été figés quand le technicien a accepté. Seul le supplément s'ajoute,
     * et la commission est reventilée sur le nouveau total.
     */
    public function avecSupplement(Ticket $ticket, int $supplementGnf): Devis
    {
        $supplementGnf = max(0, $supplementGnf);
        // Cast défensif : la colonne a un défaut à zéro, mais un ticket
        // construit en mémoire — ou antérieur à la migration — peut la porter
        // à null, et le partage doit rester calculable.
        $majoration = (int) $ticket->short_trip_uplift_gnf;

        $total = $ticket->base_price_gnf + $majoration + $ticket->travel_fee_gnf + $supplementGnf;

        [$commission, $net, $taux] = $this->repartir($total, $majoration);

        return new Devis(
            prixPrestationGnf: $ticket->base_price_gnf,
            majorationProximiteGnf: $majoration,
            fraisDeplacementGnf: $ticket->travel_fee_gnf,
            supplementGnf: $supplementGnf,
            totalGnf: $total,
            commissionGnf: $commission,
            netTechnicienGnf: $net,
            tauxCommission: $taux,
            distance: new Distance(
                (float) $ticket->distance_km,
                (bool) $ticket->distance_is_estimated,
                Distance::SOURCE_FIGEE,
            ),
            // Le déplacement est figé : la grille de la zone n'est plus
            // consultée, et il n'y a donc plus de forfait à détailler.
            kmInclus: 0,
            kmFactures: 0.0,
            ferme: true,
        );
    }

    /**
     * Frais de déplacement seuls. Vaut zéro sous le seuil : c'est la majoration
     * de proximité qui prend le relais.
     */
    public function fraisDeplacement(Zone $zone, float $distanceKm): int
    {
        if ($distanceKm <= $zone->included_km) {
            return 0;
        }

        $brut = $zone->base_travel_fee_gnf
            + (int) round(($distanceKm - $zone->included_km) * $zone->price_per_km_gnf);

        return Money::arrondiSuperieur(
            $brut,
            (int) AppSetting::get(AppSetting::TRAVEL_FEE_ROUNDING_GNF, 1_000),
        );
    }

    /**
     * Majoration due quand le technicien est déjà tout près. Vaut zéro au-delà
     * du seuil, où le kilométrage est facturé à la place.
     */
    public function majorationProximite(Zone $zone, float $distanceKm, int $prixPrestationGnf): int
    {
        if ($distanceKm > $zone->included_km) {
            return 0;
        }

        $taux = (float) AppSetting::get(AppSetting::SHORT_TRIP_UPLIFT_RATE, 0.01);
        $taux = max(0.0, min(1.0, $taux));

        return (int) round($prixPrestationGnf * $taux);
    }

    /**
     * Partage plateforme / technicien (§8.3).
     *
     * La majoration de proximité est **retirée de l'assiette** avant le calcul
     * de la commission, puis rendue au technicien : elle lui revient en entier,
     * puisqu'elle remplace un déplacement qu'on ne lui facture pas.
     *
     * Le net est ensuite obtenu par **soustraction**, jamais par un second
     * produit : `total × (1 − taux)` et `total − total × taux` ne donnent pas
     * toujours le même entier après arrondi, et l'écart d'un franc se
     * retrouverait dans le grand livre.
     *
     * @return array{int, int, float} commission, net technicien, taux appliqué
     */
    public function repartir(int $totalGnf, int $majorationGnf = 0): array
    {
        $taux = (float) AppSetting::get(AppSetting::COMMISSION_RATE, 0.10);
        $taux = max(0.0, min(1.0, $taux));

        $assiette = max(0, $totalGnf - $majorationGnf);

        $commission = (int) round($assiette * $taux);
        $commission = max(0, min($assiette, $commission));

        return [$commission, $totalGnf - $commission, $taux];
    }

    private function composer(
        int $prixPrestation,
        Zone $zone,
        Distance $distance,
        int $supplement,
        bool $ferme,
    ): Devis {
        $supplement = max(0, $supplement);
        $deplacement = $this->fraisDeplacement($zone, $distance->km);
        $majoration = $this->majorationProximite($zone, $distance->km, $prixPrestation);

        $total = $prixPrestation + $majoration + $deplacement + $supplement;

        [$commission, $net, $taux] = $this->repartir($total, $majoration);

        return new Devis(
            prixPrestationGnf: $prixPrestation,
            majorationProximiteGnf: $majoration,
            fraisDeplacementGnf: $deplacement,
            supplementGnf: $supplement,
            totalGnf: $total,
            commissionGnf: $commission,
            netTechnicienGnf: $net,
            tauxCommission: $taux,
            distance: $distance,
            kmInclus: $zone->included_km,
            kmFactures: round(max(0.0, $distance->km - $zone->included_km), 2),
            ferme: $ferme,
        );
    }
}
