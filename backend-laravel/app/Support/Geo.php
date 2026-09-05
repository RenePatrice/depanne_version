<?php

declare(strict_types=1);

namespace App\Support;

use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Data\Geometries\Polygon;

/**
 * Aides géographiques transverses. Le calcul de distance routière réel passe
 * par l'interface MapProvider ; ce qui est ici ne dépend d'aucun fournisseur.
 */
final class Geo
{
    /** Rayon moyen de la Terre, en kilomètres. */
    private const EARTH_RADIUS_KM = 6371.0088;

    /**
     * Facteur appliqué à la distance à vol d'oiseau pour estimer la distance
     * routière quand l'API de cartographie est indisponible (§8.2).
     */
    public const ROAD_FACTOR = 1.3;

    public static function point(float $latitude, float $longitude): Point
    {
        return Point::makeGeodetic($latitude, $longitude);
    }

    /**
     * Polygone rectangulaire à partir de deux coins. Suffit à décrire les zones
     * du pilote ; le back-office permettra de dessiner des polygones libres.
     */
    public static function boundingBox(float $minLat, float $minLng, float $maxLat, float $maxLng): Polygon
    {
        $corners = [
            self::point($minLat, $minLng),
            self::point($minLat, $maxLng),
            self::point($maxLat, $maxLng),
            self::point($maxLat, $minLng),
            self::point($minLat, $minLng),   // un anneau doit être fermé
        ];

        return Polygon::make([LineString::make($corners)]);
    }

    /** Distance à vol d'oiseau entre deux points, en kilomètres. */
    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * asin(min(1.0, sqrt($a)));
    }

    /** Estimation de la distance routière, utilisée en repli (§8.2). */
    public static function estimatedRoadKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return round(self::haversineKm($lat1, $lng1, $lat2, $lng2) * self::ROAD_FACTOR, 2);
    }
}
