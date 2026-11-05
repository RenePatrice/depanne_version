<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Tarifs du technicien (#2, place de marché)
|--------------------------------------------------------------------------
|
| Le catalogue devient un défaut : le technicien fixe son prix par prestation,
| borné pour éviter cadeau et abus. Sans tarif, le prix catalogue fait foi.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(AppSettingsSeeder::class);

    $this->technicien = User::factory()->technician()->create();
    TechnicianProfile::query()->updateOrCreate(['user_id' => $this->technicien->id], [
        'specialties' => ['PLOMBERIE'],
        'verification_status' => VerificationStatus::VALIDE,
        'is_online' => true,
    ]);

    Sanctum::actingAs($this->technicien);
});

it('liste les prestations de ses spécialités avec le prix catalogue par défaut', function (): void {
    $this->getJson('/api/v1/technicien/tarifs')
        ->assertOk()
        ->assertJsonPath('tarifs.0.mon_prix_gnf', null)
        ->assertJsonStructure([
            'tarifs' => [['service_id', 'prestation', 'prix_catalogue_gnf', 'min_gnf', 'max_gnf']],
        ]);
});

it('enregistre un tarif dans les bornes', function (): void {
    $service = Service::query()->where('is_active', true)->first();
    $prix = (int) round($service->base_price_gnf * 1.2);

    $this->postJson('/api/v1/technicien/tarifs', [
        'tarifs' => [['service_id' => $service->id, 'prix_gnf' => $prix]],
    ])->assertOk();

    expect($service->fresh()->priceForTechnician($this->technicien->id))->toBe($prix);
});

it('refuse un tarif hors des bornes', function (): void {
    $service = Service::query()->where('is_active', true)->first();

    $this->postJson('/api/v1/technicien/tarifs', [
        'tarifs' => [['service_id' => $service->id, 'prix_gnf' => $service->base_price_gnf * 10]],
    ])->assertStatus(422);

    expect($service->fresh()->priceForTechnician($this->technicien->id))
        ->toBe((int) $service->base_price_gnf);
});

it('revient au prix catalogue quand le tarif est remis à null', function (): void {
    $service = Service::query()->where('is_active', true)->first();
    $prix = (int) round($service->base_price_gnf * 1.1);

    $this->postJson('/api/v1/technicien/tarifs', [
        'tarifs' => [['service_id' => $service->id, 'prix_gnf' => $prix]],
    ])->assertOk();

    $this->postJson('/api/v1/technicien/tarifs', [
        'tarifs' => [['service_id' => $service->id, 'prix_gnf' => null]],
    ])->assertOk();

    expect($service->fresh()->priceForTechnician($this->technicien->id))
        ->toBe((int) $service->base_price_gnf);
});

it('réserve la tarification aux techniciens', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/technicien/tarifs')->assertForbidden();
});
