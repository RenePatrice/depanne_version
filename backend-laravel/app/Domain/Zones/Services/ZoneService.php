<?php

declare(strict_types=1);

namespace App\Domain\Zones\Services;

use App\Domain\Zones\Models\Zone;
use App\Support\Geo;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Rattachement géographique d'une adresse à sa zone (§8.2).
 *
 * Le test d'appartenance est fait par PostGIS et pas en PHP : le back-office
 * permet de dessiner des polygones libres, et réimplémenter un point-dans-
 * polygone correct côté application serait une erreur gratuite.
 */
final class ZoneService
{
    /** Une seule zone couvre le pilote ; le cache évite d'y revenir à chaque devis. */
    private const CACHE_SECONDES = 3600;

    /**
     * La zone active qui contient ce point, ou null si l'adresse est hors
     * couverture — cas normal et attendu : le pilote ne dessert que Ratoma.
     */
    public function pour(Point $point): ?Zone
    {
        $id = DB::connection()->selectOne(
            <<<'SQL'
                SELECT id
                FROM zones
                WHERE is_active = true
                  AND ST_Covers(boundary, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)
                ORDER BY ST_Area(boundary) ASC
                LIMIT 1
            SQL,
            [$point->getLongitude(), $point->getLatitude()],
        );

        if ($id === null) {
            return null;
        }

        /** @var Zone|null */
        return Zone::query()->find($id->id);
    }

    /**
     * Point depuis lequel les frais de déplacement sont mesurés (ADR-0024).
     *
     * C'est le centroïde de la zone. Le choix n'est pas anodin : au moment du
     * devis, aucun technicien n'est assigné, donc aucune position réelle n'est
     * connaissable. Mesurer depuis le centre de la zone donne un prix ferme,
     * identique pour deux clients de la même rue, et qui croît vers les bords
     * de la zone — là où le technicien devra effectivement rouler plus loin.
     *
     * Le résultat est mis en cache et invalidé par `updated_at` : redessiner
     * une zone en back-office déplace son centre dès l'enregistrement.
     */
    public function pointDeReference(Zone $zone): Point
    {
        $cle = sprintf('zone:%d:centroide:%s', $zone->getKey(), (int) $zone->updated_at?->timestamp);

        /** @var array{lat: float, lng: float} $coordonnees */
        $coordonnees = Cache::remember($cle, self::CACHE_SECONDES, function () use ($zone): array {
            $ligne = DB::connection()->selectOne(
                <<<'SQL'
                    SELECT ST_Y(centre) AS lat, ST_X(centre) AS lng
                    FROM (
                        SELECT ST_Centroid(boundary::geometry) AS centre
                        FROM zones WHERE id = ?
                    ) AS c
                SQL,
                [$zone->getKey()],
            );

            return ['lat' => (float) ($ligne->lat ?? 0), 'lng' => (float) ($ligne->lng ?? 0)];
        });

        return Geo::point($coordonnees['lat'], $coordonnees['lng']);
    }
}
