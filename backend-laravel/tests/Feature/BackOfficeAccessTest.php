<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\AdminUser;
use App\Support\Navigation;
use Database\Seeders\Demo\AdminUserSeeder;

/*
 * Les trois rôles n'ont pas les mêmes droits, et la barre latérale doit refléter
 * exactement ce que chacun peut ouvrir : un lien visible qui renvoie un 403 est
 * un défaut d'interface autant qu'un défaut de sécurité.
 */

beforeEach(function (): void {
    $this->seed(AdminUserSeeder::class);
});

it('laisse ADMIN ouvrir tous les modules', function (): void {
    $admin = adminAvecRole('ADMIN');

    foreach (['tableau-de-bord', 'tickets', 'finances', 'retraits', 'configuration', 'journal-audit'] as $route) {
        $this->actingAs($admin, 'admin')->get(route($route))->assertOk();
    }
});

it('interdit à FINANCE les modules qui ne le concernent pas', function (): void {
    $finance = adminAvecRole('FINANCE');

    $this->actingAs($finance, 'admin')->get(route('finances'))->assertOk();
    $this->actingAs($finance, 'admin')->get(route('retraits'))->assertOk();

    // La configuration, les zones et les litiges relèvent d'ADMIN ou de SUPPORT.
    $this->actingAs($finance, 'admin')->get(route('configuration'))->assertForbidden();
    $this->actingAs($finance, 'admin')->get(route('zones'))->assertForbidden();
    $this->actingAs($finance, 'admin')->get(route('litiges'))->assertForbidden();
});

it('interdit à SUPPORT les écrans financiers', function (): void {
    $support = adminAvecRole('SUPPORT');

    $this->actingAs($support, 'admin')->get(route('tickets'))->assertOk();
    $this->actingAs($support, 'admin')->get(route('litiges'))->assertOk();

    $this->actingAs($support, 'admin')->get(route('finances'))->assertForbidden();
    $this->actingAs($support, 'admin')->get(route('retraits'))->assertForbidden();
    $this->actingAs($support, 'admin')->get(route('configuration'))->assertForbidden();
});

it('ne propose dans la navigation que ce que le rôle peut ouvrir', function (): void {
    $liens = static function (AdminUser $admin): array {
        $cles = [];

        foreach (Navigation::forAdmin($admin) as $groupe) {
            foreach ($groupe['items'] as $item) {
                $cles[] = $item['cle'];
            }
        }

        return $cles;
    };

    expect($liens(adminAvecRole('FINANCE')))
        ->toContain('finances', 'retraits')
        ->not->toContain('configuration', 'zones', 'litiges');

    expect($liens(adminAvecRole('SUPPORT')))
        ->toContain('tickets', 'techniciens', 'litiges')
        ->not->toContain('finances', 'retraits');

    expect($liens(adminAvecRole('ADMIN')))
        ->toContain('tableau-de-bord', 'tickets', 'finances', 'configuration', 'journal-audit');
});

it('ouvre chaque lien de la barre latérale sans jamais renvoyer une erreur', function (): void {
    // Tous les modules du bloc B sont livrés : un lien affiché doit répondre.
    $admin = adminAvecRole('ADMIN');

    foreach (Navigation::forAdmin($admin) as $groupe) {
        foreach ($groupe['items'] as $item) {
            $this->actingAs($admin, 'admin')
                ->get(route($item['cle']))
                ->assertOk();
        }
    }
});

it('protège le back-office par des en-têtes de sécurité', function (): void {
    $this->actingAs(adminAvecRole('ADMIN'), 'admin')
        ->get(route('tableau-de-bord'))
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});
