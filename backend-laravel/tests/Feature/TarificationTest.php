<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Service;
use App\Domain\Pricing\Contracts\MapProvider;
use App\Domain\Pricing\Data\Distance;
use App\Domain\Pricing\Providers\HaversineMapProvider;
use App\Domain\Pricing\Services\PricingService;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Zones\Models\Zone;
use App\Domain\Zones\Services\ZoneService;
use App\Support\Geo;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;

/*
|--------------------------------------------------------------------------
| Tarification (§8.2)
|--------------------------------------------------------------------------
|
| Le calcul du prix est la partie du code où une erreur se voit tout de suite
| en argent. Ces tests l'attaquent par les bords : distance nulle, distance
| sous le forfait, distance juste au-dessus, arrondi déjà pile, taux de
| commission extrêmes.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(AppSettingsSeeder::class);
});

/** Zone d'essai avec une grille de déplacement lisible à l'œil nu. */
function zoneEssai(int $base = 10_000, int $parKm = 2_000, int $inclus = 5): Zone
{
    /** @var Zone */
    return Zone::query()->create([
        'code' => 'TEST-'.Zone::query()->count(),
        'name' => 'Zone de test',
        'commune' => 'Ratoma',
        'boundary' => Geo::boundingBox(9.55, -13.70, 9.65, -13.60),
        'base_travel_fee_gnf' => $base,
        'price_per_km_gnf' => $parKm,
        'included_km' => $inclus,
        'is_active' => true,
    ]);
}

/** Fournisseur de carte qui renvoie toujours la distance qu'on lui a donnée. */
function carteFigee(float $km, bool $estimee = false): MapProvider
{
    return new class($km, $estimee) implements MapProvider
    {
        public function __construct(private float $km, private bool $estimee) {}

        public function distance(Point $depart, Point $arrivee): Distance
        {
            return $this->estimee
                ? Distance::estimee($this->km)
                : Distance::mesuree($this->km, 'test');
        }
    };
}

function tarification(MapProvider $carte): PricingService
{
    return new PricingService($carte, new ZoneService);
}

// --------------------------------------------------------------- déplacement --

it('ne facture que le forfait de base sous le seuil de kilomètres inclus', function (): void {
    $frais = tarification(carteFigee(3.2))->fraisDeplacement(zoneEssai(), 3.2);

    // 10 000 de base, rien au-delà de 5 km inclus.
    expect($frais)->toBeGnf(10_000);
});

it('ne facture que le forfait quand la distance vaut exactement le seuil', function (): void {
    expect(tarification(carteFigee(5.0))->fraisDeplacement(zoneEssai(), 5.0))->toBeGnf(10_000);
});

it('facture les kilomètres au-delà du forfait', function (): void {
    // 10 000 + (8 − 5) × 2 000 = 16 000, déjà multiple de 1 000.
    expect(tarification(carteFigee(8.0))->fraisDeplacement(zoneEssai(), 8.0))->toBeGnf(16_000);
});

it('arrondit les frais de déplacement au millier supérieur', function (): void {
    // 10 000 + 2,3 × 2 000 = 14 600 → 15 000.
    expect(tarification(carteFigee(7.3))->fraisDeplacement(zoneEssai(), 7.3))->toBeGnf(15_000);
});

it('laisse intact un montant déjà multiple du pas d\'arrondi', function (): void {
    // 10 000 + 1 × 2 000 = 12 000 : l'arrondi supérieur ne doit pas ajouter 1 000.
    expect(tarification(carteFigee(6.0))->fraisDeplacement(zoneEssai(), 6.0))->toBeGnf(12_000);
});

it('suit le pas d\'arrondi défini en back-office', function (): void {
    AppSetting::put(AppSetting::TRAVEL_FEE_ROUNDING_GNF, 5_000);

    // 14 600 arrondi au multiple de 5 000 supérieur = 15 000.
    expect(tarification(carteFigee(7.3))->fraisDeplacement(zoneEssai(), 7.3))->toBeGnf(15_000);

    AppSetting::put(AppSetting::TRAVEL_FEE_ROUNDING_GNF, 1_000);
});

it('ne facture jamais de kilomètres négatifs', function (): void {
    expect(tarification(carteFigee(0.0))->fraisDeplacement(zoneEssai(), 0.0))->toBeGnf(10_000);
});

// --------------------------------------------------------------------- total --

it('compose le total à partir de la prestation, du déplacement et du supplément', function (): void {
    $service = Service::query()->firstOrFail();
    $service->forceFill(['base_price_gnf' => 85_000])->save();

    $devis = tarification(carteFigee(8.0))->devis(
        $service,
        zoneEssai(),
        Geo::point(9.60, -13.64),
        supplementGnf: 25_000,
    );

    expect($devis->prixPrestationGnf)->toBeGnf(85_000)
        ->and($devis->fraisDeplacementGnf)->toBeGnf(16_000)
        ->and($devis->supplementGnf)->toBeGnf(25_000)
        ->and($devis->totalGnf)->toBeGnf(126_000);
});

it('refuse un supplément négatif plutôt que de réduire le total', function (): void {
    $devis = tarification(carteFigee(6.0))->devis(
        Service::query()->firstOrFail(),
        zoneEssai(),
        Geo::point(9.60, -13.64),
        supplementGnf: -50_000,
    );

    expect($devis->supplementGnf)->toBeGnf(0);
});

// ---------------------------------------------------------------- commission --

it('répartit le total entre commission et net technicien sans perdre un franc', function (): void {
    AppSetting::put(AppSetting::COMMISSION_RATE, 0.10);

    [$commission, $net, $taux] = tarification(carteFigee(1.0))->repartir(100_000);

    expect($commission)->toBeGnf(10_000)
        ->and($net)->toBeGnf(90_000)
        ->and($taux)->toBe(0.10)
        ->and($commission + $net)->toBe(100_000);
});

it('garde la somme exacte même quand le taux tombe sur une fraction', function (): void {
    AppSetting::put(AppSetting::COMMISSION_RATE, 0.13);

    // 87 333 × 0,13 = 11 353,29 → 11 353, et le net est la soustraction.
    [$commission, $net] = tarification(carteFigee(1.0))->repartir(87_333);

    expect($commission + $net)->toBe(87_333)
        ->and($commission)->toBeGnf(11_353);

    AppSetting::put(AppSetting::COMMISSION_RATE, 0.10);
});

it('borne un taux de commission aberrant au lieu de produire un net négatif', function (): void {
    AppSetting::put(AppSetting::COMMISSION_RATE, 3.5);

    [$commission, $net] = tarification(carteFigee(1.0))->repartir(100_000);

    expect($commission)->toBeGnf(100_000)->and($net)->toBeGnf(0);

    AppSetting::put(AppSetting::COMMISSION_RATE, 0.10);
});

it('ajoute le supplément de diagnostic sans recalculer le déplacement figé', function (): void {
    AppSetting::put(AppSetting::COMMISSION_RATE, 0.10);

    $ticket = new Ticket;
    $ticket->forceFill([
        'base_price_gnf' => 85_000,
        'travel_fee_gnf' => 16_000,
        'distance_km' => 8.0,
        'distance_is_estimated' => false,
    ]);

    $devis = tarification(carteFigee(999.0))->avecSupplement($ticket, 30_000);

    expect($devis->fraisDeplacementGnf)->toBeGnf(16_000)   // le 999 km n'a pas été consulté
        ->and($devis->totalGnf)->toBeGnf(131_000)
        ->and($devis->commissionGnf)->toBeGnf(13_100)
        ->and($devis->netTechnicienGnf)->toBeGnf(117_900);
});

// ------------------------------------------------------------------ distance --

it('signale une distance estimée pour que le support la retrouve plus tard', function (): void {
    $devis = tarification(carteFigee(8.0, estimee: true))->devis(
        Service::query()->firstOrFail(),
        zoneEssai(),
        Geo::point(9.60, -13.64),
    );

    expect($devis->distance->estimee)->toBeTrue()
        ->and($devis->colonnesTicket()['distance_is_estimated'])->toBeTrue();
});

it('applique le facteur de sinuosité paramétré au repli à vol d\'oiseau', function (): void {
    AppSetting::put(AppSetting::HAVERSINE_ROAD_FACTOR, 2.0);

    $depart = Geo::point(9.60, -13.64);
    $arrivee = Geo::point(9.65, -13.64);

    $distance = (new HaversineMapProvider)->distance($depart, $arrivee);
    $volDOiseau = Geo::haversineKm(9.60, -13.64, 9.65, -13.64);

    expect($distance->km)->toBe(round($volDOiseau * 2.0, 2))
        ->and($distance->estimee)->toBeTrue();

    AppSetting::put(AppSetting::HAVERSINE_ROAD_FACTOR, 1.3);
});

// -------------------------------------------------------------------- devis --

it('détaille le déplacement en toutes lettres pour un prix contestable', function (): void {
    $devis = tarification(carteFigee(8.0))->devis(
        Service::query()->firstOrFail(),
        zoneEssai(),
        Geo::point(9.60, -13.64),
    );

    $deplacement = collect($devis->pourClient()['lignes'])->firstWhere('libelle', 'Déplacement');

    expect($deplacement['detail'])->toContain('8,0 km')
        ->and($deplacement['detail'])->toContain('5 km');
});

it('masque la commission dans le détail montré au client', function (): void {
    $devis = tarification(carteFigee(8.0))->devis(
        Service::query()->firstOrFail(),
        zoneEssai(),
        Geo::point(9.60, -13.64),
    );

    $libelles = array_column($devis->pourClient()['lignes'], 'libelle');

    expect($libelles)->not->toContain('Commission')
        ->and($devis->pourClient())->not->toHaveKey('commission_gnf');
});

it('n\'affiche pas de ligne de supplément quand il n\'y en a pas', function (): void {
    $devis = tarification(carteFigee(8.0))->devis(
        Service::query()->firstOrFail(),
        zoneEssai(),
        Geo::point(9.60, -13.64),
    );

    expect($devis->pourClient()['lignes'])->toHaveCount(2);
});

// --------------------------------------------------------------------- zones --

it('rattache une adresse à la zone qui la contient', function (): void {
    $zone = zoneEssai();

    expect((new ZoneService)->pour(Geo::point(9.60, -13.65))?->id)->toBe($zone->id);
});

it('ne rattache aucune zone à une adresse hors couverture', function (): void {
    zoneEssai();

    // Kaloum, hors du rectangle de test.
    expect((new ZoneService)->pour(Geo::point(9.51, -13.71)))->toBeNull();
});

it('ignore une zone désactivée', function (): void {
    zoneEssai()->forceFill(['is_active' => false])->save();

    expect((new ZoneService)->pour(Geo::point(9.60, -13.65)))->toBeNull();
});

it('place le point de référence au centre de la zone', function (): void {
    $centre = (new ZoneService)->pointDeReference(zoneEssai());

    expect(round($centre->getLatitude(), 3))->toBe(9.600)
        ->and(round($centre->getLongitude(), 3))->toBe(-13.650);
});
