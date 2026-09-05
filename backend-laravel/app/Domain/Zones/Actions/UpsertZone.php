<?php

declare(strict_types=1);

namespace App\Domain\Zones\Actions;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Zones\Models\Zone;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Data\Geometries\Polygon;
use DomainException;
use Illuminate\Support\Str;

/**
 * Création et modification d'une zone de déploiement (§6).
 *
 * L'emprise arrive du back-office sous forme de sommets tracés à la souris.
 * Trois vérifications avant d'écrire : au moins trois sommets, des coordonnées
 * plausibles, et un anneau fermé — PostGIS refuse un polygone ouvert, et
 * l'erreur qu'il renvoie alors est incompréhensible pour l'utilisateur.
 */
final class UpsertZone
{
    /** Bornes larges autour de la Guinée : une faute de frappe est arrêtée ici. */
    private const LAT_MIN = 7.0;

    private const LAT_MAX = 13.0;

    private const LNG_MIN = -15.5;

    private const LNG_MAX = -7.5;

    /**
     * @param  array<string, mixed>  $donnees
     * @param  array<int, array{lat: float, lng: float}>  $sommets
     */
    public function execute(array $donnees, array $sommets, ?Zone $zone = null): Zone
    {
        $attributs = [
            'name' => trim((string) $donnees['name']),
            'commune' => trim((string) ($donnees['commune'] ?? 'Ratoma')),
            'boundary' => $this->polygone($sommets),
            'base_travel_fee_gnf' => (int) $donnees['base_travel_fee_gnf'],
            'price_per_km_gnf' => (int) $donnees['price_per_km_gnf'],
            'included_km' => (int) ($donnees['included_km'] ?? 0),
            'is_active' => (bool) ($donnees['is_active'] ?? true),
        ];

        if ($attributs['base_travel_fee_gnf'] < 0 || $attributs['price_per_km_gnf'] < 0) {
            throw new DomainException('Les tarifs de déplacement ne peuvent pas être négatifs.');
        }

        if ($zone !== null) {
            $zone->fill($attributs)->save();

            return $zone;
        }

        return Zone::query()->create([...$attributs, 'code' => $this->codeUnique($attributs['name'])]);
    }

    /**
     * Désactive une zone. La suppression n'est pas proposée : des tickets y sont
     * rattachés et leur historique doit rester lisible.
     */
    public function basculer(Zone $zone): Zone
    {
        $zone->forceFill(['is_active' => ! $zone->is_active])->save();

        return $zone;
    }

    /** Nombre de tickets rattachés — affiché avant toute désactivation. */
    public function ticketsRattaches(Zone $zone): int
    {
        return Ticket::query()->where('zone_id', $zone->id)->count();
    }

    /** @param  array<int, array{lat: float, lng: float}>  $sommets */
    private function polygone(array $sommets): Polygon
    {
        if (count($sommets) < 3) {
            throw new DomainException('Une zone doit avoir au moins trois sommets.');
        }

        $points = [];

        foreach ($sommets as $sommet) {
            $lat = (float) $sommet['lat'];
            $lng = (float) $sommet['lng'];

            if ($lat < self::LAT_MIN || $lat > self::LAT_MAX || $lng < self::LNG_MIN || $lng > self::LNG_MAX) {
                throw new DomainException(
                    'Un sommet sort largement de la Guinée : vérifie le tracé avant d\'enregistrer.'
                );
            }

            $points[] = Point::makeGeodetic($lat, $lng);
        }

        // PostGIS exige un anneau fermé : le dernier point doit être le premier.
        $premier = $sommets[0];
        $dernier = $sommets[count($sommets) - 1];

        if (abs((float) $premier['lat'] - (float) $dernier['lat']) > 1e-9
            || abs((float) $premier['lng'] - (float) $dernier['lng']) > 1e-9) {
            $points[] = Point::makeGeodetic((float) $premier['lat'], (float) $premier['lng']);
        }

        return Polygon::make([LineString::make($points)]);
    }

    private function codeUnique(string $nom): string
    {
        $base = mb_strtoupper(Str::slug($nom, '-'));
        $base = mb_substr($base, 0, 24);
        $code = $base;
        $suffixe = 1;

        while (Zone::query()->where('code', $code)->exists()) {
            $code = $base.'-'.(++$suffixe);
        }

        return $code;
    }
}
