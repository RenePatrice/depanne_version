<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Providers;

use App\Domain\Pricing\Contracts\MapProvider;
use App\Domain\Pricing\Data\Distance;
use App\Domain\Settings\Models\AppSetting;
use App\Support\Geo;
use Clickbar\Magellan\Data\Geometries\Point;

/**
 * Distance à vol d'oiseau multipliée par un facteur de sinuosité (§8.2).
 *
 * C'est à la fois le fournisseur par défaut tant qu'aucune clé Google n'est
 * fournie, et le repli permanent des autres. Le facteur est un paramètre
 * back-office : le tracé de Conakry n'a pas la même sinuosité qu'ailleurs, et
 * la valeur devra être recalée sur les premières courses réelles.
 */
final class HaversineMapProvider implements MapProvider
{
    public function distance(Point $depart, Point $arrivee): Distance
    {
        $facteur = (float) AppSetting::get(AppSetting::HAVERSINE_ROAD_FACTOR, Geo::ROAD_FACTOR);

        $volDOiseau = Geo::haversineKm(
            $depart->getLatitude(),
            $depart->getLongitude(),
            $arrivee->getLatitude(),
            $arrivee->getLongitude(),
        );

        return Distance::estimee($volDOiseau * $facteur);
    }
}
