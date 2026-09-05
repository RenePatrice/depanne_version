<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Contracts;

use App\Domain\Pricing\Data\Distance;
use Clickbar\Magellan\Data\Geometries\Point;

/**
 * Source de distance routière (§3.2, ADR-0005).
 *
 * Le contrat impose une règle que toute implémentation doit tenir : **ne
 * jamais lever d'exception**. Un devis ne peut pas échouer parce qu'une API
 * tierce est lente ou hors service ; il doit se rabattre sur une estimation et
 * le dire. C'est pour cela que `Distance` porte le drapeau `estimee`.
 */
interface MapProvider
{
    public function distance(Point $depart, Point $arrivee): Distance;
}
