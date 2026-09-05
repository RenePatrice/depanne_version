<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\AdminUser;
use Database\Seeders\Demo\AdminUserSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * Phase A0 — ces tests vérifient que le socle tient : sans PostGIS il n'y a pas
 * de matching, et sans verrou atomique un ticket peut être attribué deux fois.
 */

it('réserve la page de diagnostic aux administrateurs connectés', function (): void {
    $this->seed(AdminUserSeeder::class);

    // Elle annonce les versions et les pilotes branchés : rien à faire en public.
    $this->get(route('systeme'))->assertRedirect(route('connexion'));

    $this->actingAs(AdminUser::query()->firstOrFail(), 'admin')
        ->get(route('systeme'))
        ->assertOk()
        ->assertSee('Dépanne-Moi', escape: false)
        ->assertSee('PostGIS', escape: false);
});

it('expose une sonde de santé en bon état, sans détail pour un anonyme', function (): void {
    $reponse = $this->getJson('/health')->assertOk();

    expect($reponse->json('statut'))->toBe('ok')
        ->and($reponse->json('verifications.database'))->toBeTrue()
        ->and($reponse->json('verifications.postgis'))->toBeTrue()
        ->and($reponse->json('verifications.verrou'))->toBeTrue()
        // Un visiteur anonyme ne doit pas apprendre la version de PostgreSQL.
        ->and($reponse->json('verifications.database.detail'))->toBeNull();
});

it('détaille la sonde de santé pour un administrateur connecté', function (): void {
    $this->seed(AdminUserSeeder::class);

    $reponse = $this->actingAs(AdminUser::query()->firstOrFail(), 'admin')
        ->getJson('/health')
        ->assertOk();

    expect($reponse->json('verifications.database.ok'))->toBeTrue()
        ->and($reponse->json('verifications.postgis.detail'))->toContain('3.6');
});

it('dispose de PostGIS et sait mesurer une distance géodésique', function (): void {
    // Ratoma → Kaloum, environ 11,5 km à vol d'oiseau.
    $distance = (int) round((float) DB::selectOne(
        "select ST_Distance(
            ST_GeogFromText('SRID=4326;POINT(-13.640 9.585)'),
            ST_GeogFromText('SRID=4326;POINT(-13.712 9.509)')
        ) as m"
    )->m);

    expect($distance)->toBeGreaterThan(11_000)->toBeLessThan(12_000);
});

it('fournit des verrous atomiques pour le matching séquentiel', function (): void {
    $premier = Cache::lock('ticket:1:attribution', 10);
    expect($premier->get())->toBeTrue();

    // Un second technicien qui répond en même temps ne doit pas obtenir le verrou.
    expect(Cache::lock('ticket:1:attribution', 10)->get())->toBeFalse();

    $premier->release();
});

it('applique les réglages régionaux imposés', function (): void {
    expect(config('app.timezone'))->toBe('Africa/Conakry')
        ->and(config('app.locale'))->toBe('fr')
        ->and(config('app.fallback_locale'))->toBe('fr');
});

it('hache les mots de passe avec argon2id hors des tests', function (): void {
    // phpunit.xml force bcrypt pour la vitesse ; c'est le .env qui fait foi.
    $depuisEnv = trim((string) file_get_contents(base_path('.env')));

    expect($depuisEnv)->toContain('HASH_DRIVER=argon2id');
});
