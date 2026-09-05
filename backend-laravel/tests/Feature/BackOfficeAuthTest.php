<?php

declare(strict_types=1);

use App\Domain\Accounts\Actions\AuthenticateAdmin;
use Database\Seeders\Demo\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/*
 * Le back-office est la porte d'entrée de toutes les données de la plateforme :
 * ce fichier verrouille qui entre, comment, et ce que chacun voit une fois entré.
 */

beforeEach(function (): void {
    $this->seed(AdminUserSeeder::class);
    RateLimiter::clear(AuthenticateAdmin::throttleKey(request(), 'admin@depanne-moi.gn'));
});

it('renvoie un visiteur non connecté vers l\'écran de connexion', function (): void {
    $this->get('/')->assertRedirect(route('connexion'));
    $this->get('/tickets')->assertRedirect(route('connexion'));
});

it('affiche l\'écran de connexion', function (): void {
    $this->get(route('connexion'))
        ->assertOk()
        ->assertSee('Connexion')
        ->assertSee('Back-office', escape: false);
});

it('connecte un administrateur et horodate sa visite', function (): void {
    $admin = adminAvecRole();

    expect($admin->last_login_at)->toBeNull();

    $this->post(route('connexion'), [
        'email' => $admin->email,
        'password' => 'DepanneMoi2026',
    ])->assertRedirect(route('tableau-de-bord'));

    $this->assertAuthenticatedAs($admin, 'admin');
    expect($admin->fresh()->last_login_at)->not->toBeNull();
});

it('refuse un mot de passe erroné sans révéler si le compte existe', function (): void {
    $this->post(route('connexion'), [
        'email' => 'admin@depanne-moi.gn',
        'password' => 'mauvais',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('admin');
});

it('verrouille la connexion après cinq tentatives infructueuses', function (): void {
    for ($i = 0; $i < AuthenticateAdmin::MAX_ATTEMPTS; $i++) {
        $this->post(route('connexion'), [
            'email' => 'admin@depanne-moi.gn',
            'password' => 'mauvais',
        ]);
    }

    // La sixième tentative est refusée avant même de vérifier le mot de passe,
    // y compris s'il est correct.
    $reponse = $this->post(route('connexion'), [
        'email' => 'admin@depanne-moi.gn',
        'password' => 'DepanneMoi2026',
    ]);

    $reponse->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Trop de tentatives');
    $this->assertGuest('admin');
});

it('refuse un compte désactivé', function (): void {
    $admin = adminAvecRole();
    $admin->forceFill(['is_active' => false])->save();

    $this->post(route('connexion'), [
        'email' => $admin->email,
        'password' => 'DepanneMoi2026',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('admin');
});

it('déconnecte et invalide la session', function (): void {
    $this->actingAs(adminAvecRole(), 'admin')
        ->post(route('deconnexion'))
        ->assertRedirect(route('connexion'));

    $this->assertGuest('admin');
});

it('affiche le tableau de bord avec des chiffres lus en base', function (): void {
    $this->actingAs(adminAvecRole(), 'admin')
        ->get(route('tableau-de-bord'))
        ->assertOk()
        ->assertSee('Tableau de bord')
        ->assertSee('Commissions plateforme', escape: false);
});

it('hache les mots de passe des comptes du back-office', function (): void {
    $admin = adminAvecRole();

    expect($admin->password)->not->toBe('DepanneMoi2026')
        ->and(Hash::check('DepanneMoi2026', $admin->password))->toBeTrue();
});
