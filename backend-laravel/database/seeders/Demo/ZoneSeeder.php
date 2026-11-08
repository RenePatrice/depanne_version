<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Domain\Zones\Models\Zone;
use App\Support\Geo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Découpage de Conakry par commune, puis par quartier (§11, décision du
 * 8 novembre 2026). L'unité de travail est le QUARTIER : chaque zone représente
 * un quartier et porte sa commune de rattachement. Le back-office regroupe les
 * quartiers par commune ; le matching et la tarification continuent de
 * raisonner par zone, sans code dédié.
 *
 * Les emprises sont des rectangles approximatifs centrés sur le quartier :
 * suffisant pour le pilote, et le back-office permet ensuite d'affiner le tracé
 * au polygone libre. La grille tarifaire varie par commune — c'est précisément
 * ce que le §6 demande de pouvoir régler sans livraison.
 *
 * Ratoma est la commune pilote : ses quartiers reprennent exactement les
 * coordonnées des adresses de démonstration (UserSeeder), si bien que chaque
 * client tombe dans son propre quartier. Les quatre autres communes sont
 * ouvertes à la couverture, sans client de démonstration pour l'instant.
 */
final class ZoneSeeder extends Seeder
{
    /** Demi-côté de l'emprise d'un quartier, en degrés (~0,9 km). */
    private const DEMI = 0.008;

    /**
     * Communes de Conakry et leurs quartiers : [nom, latitude, longitude].
     * `prix_km` : prix au kilomètre au-delà des 3 km inclus, réglable ensuite.
     *
     * @var array<string, array{prefixe: string, prix_km: int, quartiers: array<int, array{0: string, 1: float, 2: float}>}>
     */
    private const COMMUNES = [
        'Kaloum' => [
            'prefixe' => 'KAL',
            'prix_km' => 2_500,
            'quartiers' => [
                ['Boulbinet', 9.5110, -13.7130],
                ['Sandervalia', 9.5140, -13.7080],
                ['Almamya', 9.5170, -13.7050],
                ['Coronthie', 9.5080, -13.7175],
                ['Tombo', 9.5040, -13.7110],
                ['Téminétaye', 9.5200, -13.7020],
                ['Manquepas', 9.5155, -13.7000],
            ],
        ],
        'Dixinn' => [
            'prefixe' => 'DIX',
            'prix_km' => 2_500,
            'quartiers' => [
                ['Camayenne', 9.5310, -13.6920],
                ['Dixinn Centre', 9.5400, -13.6830],
                ['Belle-Vue', 9.5270, -13.6970],
                ['Hafia', 9.5480, -13.6760],
                ['Minière', 9.5520, -13.6700],
                ['Landréah', 9.5360, -13.6880],
                ['Cameroun', 9.5440, -13.6790],
            ],
        ],
        'Matam' => [
            'prefixe' => 'MAT',
            'prix_km' => 2_800,
            'quartiers' => [
                ['Matam Centre', 9.5300, -13.6520],
                ['Coléah', 9.5250, -13.6600],
                ['Bonfi', 9.5350, -13.6480],
                ['Hermakono', 9.5400, -13.6550],
                ['Touguiwondy', 9.5450, -13.6450],
                ['Madina', 9.5380, -13.6620],
            ],
        ],
        'Ratoma' => [
            'prefixe' => 'RAT',
            'prix_km' => 3_000,
            'quartiers' => [
                ['Hamdallaye', 9.5580, -13.6480],
                ['Cosa', 9.5710, -13.6390],
                ['Koloma', 9.5650, -13.6300],
                ['Taouyah', 9.5820, -13.6360],
                ['Nongo', 9.5890, -13.6510],
                ['Ratoma Centre', 9.5900, -13.6250],
                ['Kipé', 9.5980, -13.6430],
                ['Lambanyi', 9.6280, -13.6150],
                ['Kaporo', 9.6210, -13.6280],
                ['Sonfonia', 9.6420, -13.6020],
            ],
        ],
        'Matoto' => [
            'prefixe' => 'MTO',
            'prix_km' => 3_200,
            'quartiers' => [
                ['Matoto Centre', 9.5850, -13.6000],
                ['Gbessia', 9.5750, -13.6100],
                ['Dabondy', 9.5800, -13.5950],
                ['Kissosso', 9.5950, -13.5850],
                ['Sangoyah', 9.6000, -13.5950],
                ['Yimbaya', 9.5850, -13.6150],
                ['Cité de l\'Air', 9.5700, -13.6030],
                ['Tanéné', 9.6050, -13.5800],
            ],
        ],
    ];

    /**
     * Définitions à plat, une par quartier. Consommée aussi par TicketSeeder
     * pour rattacher chaque adresse de démonstration à sa zone.
     *
     * @return array<int, array{code: string, name: string, commune: string, box: array{0: float, 1: float, 2: float, 3: float}, base_travel_fee_gnf: int, price_per_km_gnf: int, included_km: int}>
     */
    public static function definitions(): array
    {
        $definitions = [];

        foreach (self::COMMUNES as $commune => $donnees) {
            foreach ($donnees['quartiers'] as [$nom, $lat, $lng]) {
                $definitions[] = [
                    'code' => $donnees['prefixe'].'-'.mb_strtoupper(Str::slug($nom, '-')),
                    'name' => $nom,
                    'commune' => $commune,
                    'box' => [$lat - self::DEMI, $lng - self::DEMI, $lat + self::DEMI, $lng + self::DEMI],
                    'base_travel_fee_gnf' => 0,
                    'price_per_km_gnf' => $donnees['prix_km'],
                    'included_km' => 3,
                ];
            }
        }

        return $definitions;
    }

    public function run(): void
    {
        foreach (self::definitions() as $definition) {
            [$minLat, $minLng, $maxLat, $maxLng] = $definition['box'];

            Zone::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'commune' => $definition['commune'],
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
