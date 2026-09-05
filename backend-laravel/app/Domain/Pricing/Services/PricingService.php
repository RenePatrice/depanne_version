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
 *     total            = prix_prestation + frais_déplacement + supplément
 *     frais_déplacement = arrondi_sup( tarif_base_zone
 *                                      + max(0, distance − km_inclus) × prix_par_km )
 *
 * Un seul endroit dans tout le projet produit un montant à payer. Le
 * back-office, l'API mobile et les jeux de démonstration appellent tous cette
 * classe : deux implémentations du même barème finiraient par diverger, et la
 * divergence se lirait directement dans la caisse.
 *
 * Aucune valeur n'est codée en dur. Le taux de commission et le pas d'arrondi
 * viennent des paramètres, la grille de déplacement vient de la zone, le prix
 * de la prestation vient du catalogue — tous pilotables depuis le back-office
 * sans déploiement.
 */
final readonly class PricingService
{
    public function __construct(
        private MapProvider $carte,
        private ZoneService $zones,
    ) {}

    /**
     * Devis d'une prestation à une adresse donnée.
     *
     * La distance est mesurée depuis le **point de référence de la zone** et
     * non depuis le technicien : au moment où le client voit le prix, aucun
     * technicien n'est encore assigné, et le prix doit être ferme (ADR-0024).
     */
    public function devis(Service $service, Zone $zone, Point $adresse, int $supplementGnf = 0): Devis
    {
        $distance = $this->carte->distance($this->zones->pointDeReference($zone), $adresse);

        return $this->composer($service->base_price_gnf, $zone, $distance, $supplementGnf);
    }

    /**
     * Recalcule le total d'un ticket après acceptation d'un supplément de
     * diagnostic (§8.1, état DIAGNOSTIC_VALIDE).
     *
     * Le déplacement et la prestation ne sont **pas** recalculés : ils ont été
     * figés à la publication. Seul le supplément s'ajoute, et la commission
     * est reventilée sur le nouveau total.
     */
    public function avecSupplement(Ticket $ticket, int $supplementGnf): Devis
    {
        $supplementGnf = max(0, $supplementGnf);
        $total = $ticket->base_price_gnf + $ticket->travel_fee_gnf + $supplementGnf;

        [$commission, $net, $taux] = $this->repartir($total);

        return new Devis(
            prixPrestationGnf: $ticket->base_price_gnf,
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
        );
    }

    /**
     * Frais de déplacement seuls, exposés pour la simulation du back-office et
     * les tests. Le résultat est déjà arrondi.
     */
    public function fraisDeplacement(Zone $zone, float $distanceKm): int
    {
        $auDela = max(0.0, $distanceKm - $zone->included_km);

        $brut = $zone->base_travel_fee_gnf + (int) round($auDela * $zone->price_per_km_gnf);

        return Money::arrondiSuperieur(
            $brut,
            (int) AppSetting::get(AppSetting::TRAVEL_FEE_ROUNDING_GNF, 1_000),
        );
    }

    /**
     * Partage plateforme / technicien (§8.3).
     *
     * Le net est obtenu par **soustraction**, jamais par un second produit :
     * `total × (1 − taux)` et `total − total × taux` ne donnent pas toujours
     * le même entier après arrondi, et l'écart d'un franc se retrouverait dans
     * le grand livre.
     *
     * @return array{int, int, float} commission, net technicien, taux appliqué
     */
    public function repartir(int $totalGnf): array
    {
        $taux = (float) AppSetting::get(AppSetting::COMMISSION_RATE, 0.10);
        $taux = max(0.0, min(1.0, $taux));

        $commission = (int) round($totalGnf * $taux);
        $commission = max(0, min($totalGnf, $commission));

        return [$commission, $totalGnf - $commission, $taux];
    }

    private function composer(int $prixPrestation, Zone $zone, Distance $distance, int $supplement): Devis
    {
        $supplement = max(0, $supplement);
        $deplacement = $this->fraisDeplacement($zone, $distance->km);
        $total = $prixPrestation + $deplacement + $supplement;

        [$commission, $net, $taux] = $this->repartir($total);

        return new Devis(
            prixPrestationGnf: $prixPrestation,
            fraisDeplacementGnf: $deplacement,
            supplementGnf: $supplement,
            totalGnf: $total,
            commissionGnf: $commission,
            netTechnicienGnf: $net,
            tauxCommission: $taux,
            distance: $distance,
            kmInclus: $zone->included_km,
            kmFactures: round(max(0.0, $distance->km - $zone->included_km), 2),
        );
    }
}
