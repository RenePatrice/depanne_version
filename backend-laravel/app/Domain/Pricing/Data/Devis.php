<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Data;

use App\Support\Money;

/**
 * Résultat d'un calcul de prix (§8.2).
 *
 * Objet immuable et purement calculatoire : il ne touche à aucune table. C'est
 * lui qu'on montre au client, et c'est lui qu'on recopie colonne par colonne
 * sur le ticket. Le prix devient alors un instantané figé (ADR-0013) : rejouer
 * le calcul six mois plus tard, après une hausse de la grille, ne changera pas
 * ce qui a été facturé.
 *
 * `ferme` dit si le montant engage la plateforme. Un devis calculé avant qu'un
 * technicien n'ait accepté ne le peut pas : la distance facturée est celle qui
 * sépare le technicien du client, et il n'y a pas encore de technicien
 * (ADR-0026). L'application mobile doit présenter les deux différemment — « à
 * partir de », puis « total ».
 */
final readonly class Devis
{
    public function __construct(
        public int $prixPrestationGnf,
        public int $majorationProximiteGnf,
        public int $fraisDeplacementGnf,
        public int $supplementGnf,
        public int $totalGnf,
        public int $commissionGnf,
        public int $netTechnicienGnf,
        public float $tauxCommission,
        public Distance $distance,
        public int $kmInclus,
        public float $kmFactures,
        public bool $ferme,
    ) {}

    /**
     * Colonnes du ticket. Volontairement nommées comme la table : la
     * publication est une recopie, sans transformation intermédiaire où une
     * erreur pourrait se glisser.
     *
     * @return array<string, mixed>
     */
    public function colonnesTicket(): array
    {
        return [
            'base_price_gnf' => $this->prixPrestationGnf,
            'short_trip_uplift_gnf' => $this->majorationProximiteGnf,
            'travel_fee_gnf' => $this->fraisDeplacementGnf,
            'extra_fee_gnf' => $this->supplementGnf,
            'total_gnf' => $this->totalGnf,
            'commission_gnf' => $this->commissionGnf,
            'technician_net_gnf' => $this->netTechnicienGnf,
            'commission_rate' => $this->tauxCommission,
            'distance_km' => $this->distance->km,
            'distance_is_estimated' => $this->distance->estimee,
        ];
    }

    /**
     * Détail affiché au client. Chaque ligne est formatée côté serveur pour que
     * l'application mobile n'ait aucune règle de mise en forme monétaire à
     * réimplémenter — et donc aucune occasion d'afficher un montant faux.
     *
     * La commission n'y figure pas : c'est une affaire entre la plateforme et
     * le technicien, le client paie un total.
     *
     * @return array<string, mixed>
     */
    public function pourClient(): array
    {
        return [
            'total_gnf' => $this->totalGnf,
            'total_formate' => Money::format($this->totalGnf),
            'ferme' => $this->ferme,
            'distance_km' => $this->distance->km,
            'distance_estimee' => $this->distance->estimee,
            'lignes' => array_filter([
                [
                    'libelle' => 'Prestation',
                    'montant_gnf' => $this->prixPrestationGnf,
                    'montant_formate' => Money::format($this->prixPrestationGnf),
                    'detail' => null,
                ],
                $this->majorationProximiteGnf > 0 ? [
                    'libelle' => 'Intervention de proximité',
                    'montant_gnf' => $this->majorationProximiteGnf,
                    'montant_formate' => Money::format($this->majorationProximiteGnf),
                    'detail' => $this->detailProximite(),
                ] : null,
                $this->fraisDeplacementGnf > 0 ? [
                    'libelle' => 'Déplacement',
                    'montant_gnf' => $this->fraisDeplacementGnf,
                    'montant_formate' => Money::format($this->fraisDeplacementGnf),
                    'detail' => $this->detailDeplacement(),
                ] : null,
                $this->supplementGnf > 0 ? [
                    'libelle' => 'Supplément après diagnostic',
                    'montant_gnf' => $this->supplementGnf,
                    'montant_formate' => Money::format($this->supplementGnf),
                    'detail' => null,
                ] : null,
            ]),
        ];
    }

    /**
     * Pourquoi le déplacement n'est pas facturé. Le dire explicitement évite
     * qu'un client n'y voie un oubli — et qu'un technicien n'y voie une course
     * qu'on lui aurait retirée.
     */
    private function detailProximite(): string
    {
        return sprintf(
            'Technicien à moins de %d km : le déplacement n\'est pas facturé.',
            $this->kmInclus,
        );
    }

    /**
     * Explication du déplacement, en toutes lettres : un prix opaque se
     * conteste. Rien à expliquer quand le montant vient d'un ticket déjà
     * publié — il a été détaillé au moment où le client l'a accepté, et le
     * redétailler à partir d'une grille qui a pu changer serait trompeur.
     */
    private function detailDeplacement(): ?string
    {
        if ($this->distance->estFigee()) {
            return null;
        }

        return sprintf(
            '%s km, dont %s km au-delà des %d km inclus',
            number_format($this->distance->km, 1, ',', ' '),
            number_format($this->kmFactures, 1, ',', ' '),
            $this->kmInclus,
        );
    }
}
