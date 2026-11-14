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

it('ne sert jamais une clé d\'objet privée en lien brut (PC-03)', function (): void {
    // L'APK vit dans un bucket PRIVÉ : le réglage porte une clé d'objet, pas
    // une URL. Sans stockage objet gréé (environnement de test), le lien signé
    // ne peut être produit : on dégrade vers l'accueil plutôt que d'exposer le
    // chemin, et jamais on ne redirige vers une URL publique permanente.
    AppSetting::put(AppSetting::SITE_APK_URL, 'parema-connect.apk');

    $reponse = $this->get(route('site.telecharger'));

    $reponse->assertRedirect(route('site.accueil').'#telecharger');
    expect($reponse->headers->get('Location'))->not->toContain('parema-connect.apk');
});

it('ferme le téléchargement quand il est désactivé en back-office', function (): void {
    // Le lien de l'APK reste enregistré : seul l'interrupteur est coupé.
    AppSetting::put(AppSetting::SITE_APK_URL, 'https://example.test/parema.apk');
    AppSetting::put(AppSetting::SITE_TELECHARGEMENT_ACTIF, false);

    // L'adresse tapée à la main ne mène plus à l'APK…
    $this->get(route('site.telecharger'))
        ->assertRedirect(route('site.accueil').'#telecharger');

    // …et la page n'affiche plus aucun lien actif vers lui, pied de page compris.
    $this->get(route('site.accueil'))
        ->assertOk()
        ->assertDontSee('href="'.route('site.telecharger').'"', escape: false)
        ->assertSee('Lancement imminent', escape: false);
});

it('garde la page vitrine hors du guard admin', function (): void {
    // Aucune redirection vers la connexion : le site est public.
    $this->get(route('site.accueil'))->assertOk();
    $this->get(route('site.playstore'))->assertRedirect();
});
