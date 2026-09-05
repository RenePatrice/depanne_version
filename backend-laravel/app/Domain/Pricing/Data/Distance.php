<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Data;

/**
 * Distance routière entre deux points, avec sa provenance.
 *
 * `estimee` n'est pas un détail : quand elle vaut vrai, le kilométrage vient
 * du repli à vol d'oiseau et non d'un vrai itinéraire (§8.2). Le ticket la
 * conserve dans `distance_is_estimated`, ce qui permet au support de savoir,
 * des mois plus tard, si un prix contesté reposait sur une estimation.
 */
final readonly class Distance
{
    /** Distance recopiée depuis un ticket : figée, plus rien à recalculer. */
    public const SOURCE_FIGEE = 'ticket';

    public function __construct(
        public float $km,
        public bool $estimee,
        public string $source,
    ) {}

    public static function estimee(float $km, string $source = 'haversine'): self
    {
        return new self(round($km, 2), true, $source);
    }

    public static function mesuree(float $km, string $source): self
    {
        return new self(round($km, 2), false, $source);
    }

    /** Vrai quand le kilométrage vient d'un ticket déjà publié. */
    public function estFigee(): bool
    {
        return $this->source === self::SOURCE_FIGEE;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'km' => $this->km,
            'estimee' => $this->estimee,
            'source' => $this->source,
        ];
    }
}
