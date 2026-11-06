<?php

declare(strict_types=1);

use App\Domain\Settings\Models\AppSetting;
use App\Domain\Site\Data\TypeEvenement;
use App\Domain\Site\Models\SiteEvenement;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\SiteMembreSeeder;

/*
 * Le site vitrine est la porte d'entrée grand public : il s'affiche sans
 * authentification, présente l'équipe, et mesure sa fréquentation par des
 * routes serveur qui enregistrent puis redirigent.
 */

beforeEach(function (): void {
    $this->seed(AppSettingsSeeder::class);
    $this->seed(SiteMembreSeeder::class);
});

it('affiche la page d\'accueil publique sans connexion', function (): void {
    $this->get(route('site.accueil'))
        ->assertOk()
        ->assertSee('Parema', escape: false)
        ->assertSee('René Patrice MILLIMOUNO', escape: false);
});

it('autorise l\'indexation du site, jamais du back-office', function (): void {
    $this->get(route('site.accueil'))->assertHeader('X-Robots-Tag', 'index, follow');
    $this->get(route('connexion'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('enregistre une visite à l\'affichage', function (): void {
    $this->get(route('site.accueil'))->assertOk();

    expect(SiteEvenement::query()->where('type', TypeEvenement::VISITE->value)->count())->toBe(1);
});

it('enregistre le clic puis redirige vers l\'APK quand il est configuré', function (): void {
    AppSetting::put(AppSetting::SITE_APK_URL, 'https://example.test/parema.apk');

    $this->get(route('site.telecharger'))
        ->assertRedirect('https://example.test/parema.apk');

    expect(SiteEvenement::query()->where('type', TypeEvenement::CLIC_TELECHARGEMENT->value)->count())->toBe(1);
});

it('renvoie à l\'accueil quand aucun APK n\'est configuré, mais compte le clic', function (): void {
    $this->get(route('site.telecharger'))
        ->assertRedirect(route('site.accueil').'#telecharger');

    expect(SiteEvenement::query()->where('type', TypeEvenement::CLIC_TELECHARGEMENT->value)->count())->toBe(1);
});

it('garde la page vitrine hors du guard admin', function (): void {
    // Aucune redirection vers la connexion : le site est public.
    $this->get(route('site.accueil'))->assertOk();
    $this->get(route('site.playstore'))->assertRedirect();
});
