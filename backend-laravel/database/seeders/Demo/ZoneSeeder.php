<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Domain\Zones\Models\Zone;
use App\Support\Geo;
use Illuminate\Database\Seeder;

/**
 * Trois zones de déploiement dans la commune de Ratoma, à Conakry (§11).
 * Les emprises sont des rectangles : suffisant pour le pilote, et le
 * back-office permettra ensuite de dessiner des polygones libres (phase B4).
 *
 * La grille tarifaire varie d'une zone à l'autre — c'est précisément ce que le
 * §6 demande de pouvoir régler sans livraison.
 */
final class ZoneSeeder extends Seeder
{
    /**
     * Emprises approximatives : [latitude min, longitude min, latitude max, longitude max].
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'RAT-CENTRE',
                'name' => 'Kipé — Nongo — Taouyah',
                'box' => [9.575, -13.665, 9.615, -13.620],
                'base_travel_fee_gnf' => 0,
                'price_per_km_gnf' => 3_000,
                'included_km' => 3,
            ],
            [
                'code' => 'RAT-NORD',
                'name' => 'Kaporo — Sonfonia — Lambanyi',
                'box' => [9.610, -13.650, 9.660, -13.585],
                'base_travel_fee_gnf' => 0,
                'price_per_km_gnf' => 3_500,
                'included_km' => 3,
            ],
            [
                'code' => 'RAT-SUD',
                'name' => 'Hamdallaye — Cosa — Koloma',
                'box' => [9.545, -13.670, 9.580, -13.615],
                'base_travel_fee_gnf' => 0,
                'price_per_km_gnf' => 2_500,
                'included_km' => 3,
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::definitions() as $definition) {
            [$minLat, $minLng, $maxLat, $maxLng] = $definition['box'];

            Zone::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'commune' => 'Ratoma',
                    'boundary' => Geo::boundingBox($minLat, $minLng, $maxLat, $maxLng),
                    'base_travel_fee_gnf' => $definition['base_travel_fee_gnf'],
                    'price_per_km_gnf' => $definition['price_per_km_gnf'],
                    'included_km' => $definition['included_km'],
                    'is_active' => true,
                ],
            );
        }
    }
}
