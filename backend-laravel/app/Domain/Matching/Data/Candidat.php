<?php

declare(strict_types=1);

namespace App\Domain\Matching\Data;

/**
 * Un technicien retenu par la présélection géographique, avec les quatre
 * grandeurs dont le scoring a besoin (§8.3, étape 3).
 *
 * Objet plat et immuable : le scoring n'a aucune raison de retoucher la base,
 * et le passer un modèle Eloquent l'exposerait à des requêtes paresseuses au
 * milieu d'une boucle de dix candidats.
 */
final readonly class Candidat
{
    public function __construct(
        public int $technicienId,
        public string $nom,
        public float $distanceKm,
        public float $note,
        public float $tauxAcceptation,
        public float $tauxAnnulation,
        public int $interventions,
        public float $latitude,
        public float $longitude,
    ) {}

    /**
     * Note retenue par le scoring. Un technicien qui n'a pas atteint le seuil
     * d'interventions reçoit la note neutre du §8.3 : sans elle, un nouveau
     * venu partirait de zéro et ne serait jamais sollicité — donc n'atteindrait
     * jamais le seuil.
     */
    public function noteEffective(float $noteNeutre, int $seuil): float
    {
        return $this->interventions < $seuil ? $noteNeutre : $this->note;
    }

    public function estNouveau(int $seuil): bool
    {
        return $this->interventions < $seuil;
    }
}
