<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Data;

use Carbon\CarbonImmutable;

/**
 * Fenêtre d'analyse du tableau de bord et des écrans financiers.
 *
 * Les bornes sont calculées dans le fuseau de l'application (Africa/Conakry)
 * puis comparées telles quelles : les horodatages sont stockés en UTC, et
 * Conakry est à UTC+0 toute l'année — aucune conversion n'est nécessaire, mais
 * la règle est explicitée ici pour que le jour où une seconde ville s'ajoute,
 * le problème saute aux yeux.
 */
final readonly class Periode
{
    private function __construct(
        public string $cle,
        public string $libelle,
        public CarbonImmutable $debut,
        public CarbonImmutable $fin,
    ) {}

    public static function depuisCle(?string $cle): self
    {
        return match ($cle) {
            '7j' => self::derniersJours(7, '7 derniers jours'),
            '90j' => self::derniersJours(90, '90 derniers jours'),
            'annee' => self::anneeCourante(),
            default => self::derniersJours(30, '30 derniers jours'),
        };
    }

    public static function derniersJours(int $jours, ?string $libelle = null): self
    {
        $fin = CarbonImmutable::now()->endOfDay();

        return new self(
            cle: $jours.'j',
            libelle: $libelle ?? $jours.' derniers jours',
            debut: $fin->subDays($jours - 1)->startOfDay(),
            fin: $fin,
        );
    }

    public static function anneeCourante(): self
    {
        $maintenant = CarbonImmutable::now();

        return new self(
            cle: 'annee',
            libelle: 'Année '.$maintenant->year,
            debut: $maintenant->startOfYear(),
            fin: $maintenant->endOfDay(),
        );
    }

    /**
     * Fenêtre de même durée immédiatement antérieure, pour calculer une
     * évolution. Le décalage se fait en jours entiers : raisonner en secondes
     * fait déborder d'un jour à cause de la fraction de seconde de `endOfDay`.
     */
    public function precedente(): self
    {
        $jours = $this->jours();

        return new self(
            cle: $this->cle.'-precedente',
            libelle: 'Période précédente',
            debut: $this->debut->subDays($jours)->startOfDay(),
            fin: $this->debut->subSecond(),
        );
    }

    public function jours(): int
    {
        return (int) $this->debut->startOfDay()->diffInDays($this->fin->startOfDay()) + 1;
    }

    /**
     * Les choix proposés dans l'en-tête du tableau de bord.
     *
     * @return array<int, array{cle: string, libelle: string}>
     */
    public static function choix(): array
    {
        return [
            ['cle' => '7j', 'libelle' => '7 jours'],
            ['cle' => '30j', 'libelle' => '30 jours'],
            ['cle' => '90j', 'libelle' => '90 jours'],
            ['cle' => 'annee', 'libelle' => 'Cette année'],
        ];
    }
}
