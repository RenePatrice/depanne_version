<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Service;
use App\Domain\Catalog\Models\ServiceCategory;
use App\Domain\Settings\Actions\UpdateSettings;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Zones\Actions\UpsertZone;
use App\Domain\Zones\Models\Zone;
use Database\Seeders\Demo\AdminUserSeeder;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Spatie\Activitylog\Models\Activity;

/*
 * L'écran de configuration commande le prix, le matching et la répartition
 * financière. Une valeur aberrante enregistrée sans broncher se découvrirait en
 * production, au moment où un ticket est publié : ces tests fixent les gardes.
 */

beforeEach(function (): void {
    $this->seed(AppSettingsSeeder::class);
    $this->seed(AdminUserSeeder::class);
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);

    $this->admin = adminAvecRole('ADMIN');
    $this->parametres = app(UpdateSettings::class);
    $this->zones = app(UpsertZone::class);
});

// ------------------------------------------------------------- configuration --

it('enregistre un taux de commission valide et le trace', function (): void {
    $modifiees = $this->parametres->execute([AppSetting::COMMISSION_RATE => '0,12'], $this->admin->id);

    expect($modifiees)->toBe([AppSetting::COMMISSION_RATE])
        ->and(AppSetting::get(AppSetting::COMMISSION_RATE))->toBe(0.12);

    $trace = Activity::query()->where('log_name', 'configuration')->latest()->first();

    expect($trace)->not->toBeNull()
        ->and($trace->properties['avant'])->toBe(0.10)
        ->and($trace->properties['apres'])->toBe(0.12);
});

it('refuse une commission hors bornes', function (): void {
    expect(fn () => $this->parametres->execute([AppSetting::COMMISSION_RATE => '0.8'], $this->admin->id))
        ->toThrow(DomainException::class);

    // La valeur d'origine ne doit pas avoir bougé.
    expect(AppSetting::get(AppSetting::COMMISSION_RATE))->toBe(0.10);
});

it('refuse des pondérations de score qui ne totalisent pas 1', function (): void {
    expect(fn () => $this->parametres->execute([
        AppSetting::SCORE_WEIGHTS => [
            'proximity' => 0.5, 'rating' => 0.5, 'acceptance' => 0.3, 'cancellation' => -0.1,
        ],
    ], $this->admin->id))->toThrow(DomainException::class);
});

it('refuse une pénalité d\'annulation transformée en bonus', function (): void {
    // Inverser ce signe récompenserait les techniciens qui annulent le plus.
    expect(fn () => $this->parametres->execute([
        AppSetting::SCORE_WEIGHTS => [
            'proximity' => 0.4, 'rating' => 0.3, 'acceptance' => 0.3, 'cancellation' => 0.1,
        ],
    ], $this->admin->id))->toThrow(DomainException::class);
});

it('refuse un rayon initial supérieur au rayon maximum', function (): void {
    expect(fn () => $this->parametres->execute([
        AppSetting::MATCH_INITIAL_RADIUS_KM => 30,
    ], $this->admin->id))->toThrow(DomainException::class);
});

it('ne trace rien quand la valeur est inchangée', function (): void {
    $modifiees = $this->parametres->execute([AppSetting::COMMISSION_RATE => 0.10], $this->admin->id);

    expect($modifiees)->toBe([])
        ->and(Activity::query()->where('log_name', 'configuration')->count())->toBe(0);
});

it('affiche et enregistre la configuration depuis le back-office', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(route('configuration'))
        ->assertOk()
        ->assertSee('Pondérations du score de matching')
        ->assertSee('Conditions générales');

    $this->actingAs($this->admin, 'admin')
        ->post(route('configuration.enregistrer'), [
            'parametres' => [AppSetting::MATCH_RESPONSE_SECONDS => 60],
        ])
        ->assertRedirect(route('configuration'));

    expect(AppSetting::get(AppSetting::MATCH_RESPONSE_SECONDS))->toBe(60);
});

// ------------------------------------------------------------------ catalogue --

it('crée une prestation et journalise sa création', function (): void {
    $categorie = ServiceCategory::query()->firstOrFail();

    $this->actingAs($this->admin, 'admin')
        ->post(route('catalogue.prestation.creer'), [
            'category_id' => $categorie->id,
            'name' => 'Détartrage de chauffe-eau',
            'description' => 'Détartrage complet de la cuve et de la résistance.',
            'included' => "Déplacement\nMain-d'œuvre",
            'excluded' => 'Pièces de rechange',
            'base_price_gnf' => 130_000,
            'estimated_duration_min' => 90,
            'is_active' => 1,
        ])
        ->assertRedirect(route('catalogue'));

    $prestation = Service::query()->where('name', 'Détartrage de chauffe-eau')->firstOrFail();

    expect($prestation->base_price_gnf)->toBe(130_000)
        ->and($prestation->included)->toBe(['Déplacement', "Main-d'œuvre"])
        ->and($prestation->slug)->toBe('detartrage-de-chauffe-eau')
        ->and(Activity::query()->where('log_name', 'catalogue')->count())->toBeGreaterThan(0);
});

it('journalise un changement de prix avec l\'avant et l\'après', function (): void {
    $prestation = Service::query()->firstOrFail();
    $ancien = $prestation->base_price_gnf;

    $this->actingAs($this->admin, 'admin')
        ->post(route('catalogue.prestation.modifier', $prestation), [
            'category_id' => $prestation->category_id,
            'name' => $prestation->name,
            'base_price_gnf' => $ancien + 25_000,
            'estimated_duration_min' => $prestation->estimated_duration_min,
            'is_active' => 1,
        ])
        ->assertRedirect(route('catalogue'));

    // Le seeder du catalogue a déjà écrit une vingtaine de créations à la même
    // seconde : on cible la modification, et on ordonne sur l'identifiant.
    $trace = Activity::query()
        ->where('log_name', 'catalogue')
        ->where('event', 'updated')
        ->orderByDesc('id')
        ->firstOrFail();

    $changements = $trace->attribute_changes->toArray();

    expect($changements['old']['base_price_gnf'])->toBe($ancien)
        ->and($changements['attributes']['base_price_gnf'])->toBe($ancien + 25_000);
});

it('désactive une prestation sans la supprimer', function (): void {
    $prestation = Service::query()->firstOrFail();

    $this->actingAs($this->admin, 'admin')
        ->post(route('catalogue.prestation.basculer', $prestation))
        ->assertRedirect(route('catalogue'));

    expect($prestation->fresh()->is_active)->toBeFalse()
        ->and(Service::query()->find($prestation->id))->not->toBeNull();
});

// ---------------------------------------------------------------------- zones --

it('crée une zone à partir des sommets tracés', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->post(route('zones.creer'), [
            'name' => 'Dixinn test',
            'commune' => 'Dixinn',
            'base_travel_fee_gnf' => 18_000,
            'price_per_km_gnf' => 3_500,
            'included_km' => 2,
            'sommets' => [
                ['lat' => 9.54, 'lng' => -13.68],
                ['lat' => 9.54, 'lng' => -13.65],
                ['lat' => 9.57, 'lng' => -13.65],
                ['lat' => 9.57, 'lng' => -13.68],
            ],
        ])
        ->assertRedirect(route('zones'));

    $zone = Zone::query()->where('name', 'Dixinn test')->firstOrFail();

    expect($zone->code)->toBe('DIXINN-TEST')
        ->and($zone->boundary)->not->toBeNull()
        ->and($zone->base_travel_fee_gnf)->toBe(18_000);
});

it('refuse un tracé de moins de trois sommets', function (): void {
    expect(fn () => $this->zones->execute(
        ['name' => 'Trop courte', 'base_travel_fee_gnf' => 10_000, 'price_per_km_gnf' => 2_000],
        [['lat' => 9.5, 'lng' => -13.6], ['lat' => 9.6, 'lng' => -13.6]],
    ))->toThrow(DomainException::class);
});

it('refuse un sommet manifestement hors de Guinée', function (): void {
    // Une latitude de 48° place la zone à Paris : c'est une faute de saisie.
    expect(fn () => $this->zones->execute(
        ['name' => 'Ailleurs', 'base_travel_fee_gnf' => 10_000, 'price_per_km_gnf' => 2_000],
        [
            ['lat' => 48.85, 'lng' => 2.35],
            ['lat' => 48.86, 'lng' => 2.36],
            ['lat' => 48.87, 'lng' => 2.34],
        ],
    ))->toThrow(DomainException::class);
});

it('ferme l\'anneau du polygone même si le tracé ne le fait pas', function (): void {
    $zone = $this->zones->execute(
        ['name' => 'Anneau ouvert', 'base_travel_fee_gnf' => 10_000, 'price_per_km_gnf' => 2_000],
        [
            ['lat' => 9.54, 'lng' => -13.68],
            ['lat' => 9.54, 'lng' => -13.65],
            ['lat' => 9.57, 'lng' => -13.65],
        ],
    );

    $points = $zone->boundary->getLineStrings()[0]->getPoints();

    // PostGIS refuse un anneau ouvert : le premier point est recopié à la fin.
    expect($points)->toHaveCount(4)
        ->and(round($points[0]->getLatitude(), 6))->toBe(round($points[3]->getLatitude(), 6));
});

it('désactive une zone plutôt que de la supprimer', function (): void {
    $zone = Zone::query()->firstOrFail();

    $this->actingAs($this->admin, 'admin')
        ->post(route('zones.basculer', $zone))
        ->assertRedirect(route('zones'));

    expect($zone->fresh()->is_active)->toBeFalse()
        ->and(Zone::query()->find($zone->id))->not->toBeNull();
});

// --------------------------------------------------------------------- journal --

it('sert le journal d\'audit en lecture seule', function (): void {
    $this->parametres->execute([AppSetting::MATCH_MAX_CYCLES => 4], $this->admin->id);

    $this->actingAs($this->admin, 'admin')
        ->get(route('journal-audit'))
        ->assertOk()
        ->assertSee('lecture seule', escape: false);

    $reponse = $this->actingAs($this->admin, 'admin')
        ->getJson(route('journal-audit.donnees', [
            'draw' => 1, 'start' => 0, 'length' => 10,
            'columns' => [
                0 => ['data' => 'created_at', 'name' => 'created_at', 'searchable' => 'false', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                1 => ['data' => 'description', 'name' => 'description', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
            ],
            'search' => ['value' => '', 'regex' => 'false'],
        ]))
        ->assertOk();

    expect($reponse->json('recordsTotal'))->toBeGreaterThan(0);

    // Aucune route d'écriture n'existe sur le journal.
    $this->actingAs($this->admin, 'admin')
        ->post(route('journal-audit'))
        ->assertStatus(405);
});

it('interdit à SUPPORT de modifier le catalogue ou la configuration', function (): void {
    $support = adminAvecRole('SUPPORT');
    $prestation = Service::query()->firstOrFail();

    $this->actingAs($support, 'admin')->get(route('catalogue'))->assertOk();

    $this->actingAs($support, 'admin')
        ->post(route('catalogue.prestation.basculer', $prestation))
        ->assertForbidden();

    $this->actingAs($support, 'admin')->get(route('configuration'))->assertForbidden();
});
