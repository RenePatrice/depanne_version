<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\AdminUser;
use Database\Seeders\Demo\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Gestion des comptes du back-office (#9)
|--------------------------------------------------------------------------
|
| Ce que ces tests protègent : qu'un compte non-ADMIN ne voie même pas le
| module (cloisonnement), qu'on ne puisse pas se verrouiller soi-même, et que
| le dernier administrateur ne disparaisse jamais.
|
*/

beforeEach(function (): void {
    $this->seed(AdminUserSeeder::class);
    $this->admin = adminAvecRole('ADMIN');
});

it('réserve le module aux administrateurs', function (): void {
    foreach (['SUPPORT', 'FINANCE'] as $role) {
        $this->actingAs(adminAvecRole($role), 'admin')
            ->get(route('administrateurs'))
            ->assertForbidden();
    }

    $this->actingAs($this->admin, 'admin')
        ->get(route('administrateurs'))
        ->assertOk()
        ->assertSee('Comptes du back-office');
});

it('ne montre pas le lien Administrateurs à un compte support', function (): void {
    $this->actingAs(adminAvecRole('SUPPORT'), 'admin')
        ->get(route('tableau-de-bord'))
        ->assertOk()
        ->assertDontSee(route('administrateurs'));
});

it('crée un compte avec un mot de passe haché', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->post(route('administrateurs.creer'), [
            'email' => 'nouveau@parema.gn',
            'full_name' => 'Nouveau Gestionnaire',
            'role' => 'SUPPORT',
            'password' => 'MotDePasseSolide1',
            'password_confirmation' => 'MotDePasseSolide1',
        ])
        ->assertRedirect(route('administrateurs'));

    $cree = AdminUser::query()->where('email', 'nouveau@parema.gn')->sole();

    expect($cree->hasRole('SUPPORT'))->toBeTrue()
        ->and($cree->password)->not->toBe('MotDePasseSolide1')
        ->and(Hash::check('MotDePasseSolide1', $cree->password))->toBeTrue();
});

it('refuse un rôle inconnu', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->post(route('administrateurs.creer'), [
            'email' => 'x@parema.gn',
            'full_name' => 'X',
            'role' => 'SUPERADMIN',
            'password' => 'MotDePasseSolide1',
            'password_confirmation' => 'MotDePasseSolide1',
        ])
        ->assertSessionHasErrors('role');
});

it('empêche de se désactiver soi-même', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->post(route('administrateurs.statut', $this->admin), ['actif' => 0])
        ->assertSessionHasErrors('actif');

    expect($this->admin->fresh()->is_active)->toBeTrue();
});

it('empêche de désactiver le dernier administrateur actif', function (): void {
    // Un second ADMIN, puis on tente de désactiver l'autre : il resterait
    // celui-ci, donc c'est permis. On le désactive, puis le dernier ne l'est plus.
    $autre = AdminUser::query()->create([
        'email' => 'admin2@parema.gn',
        'full_name' => 'Second Admin',
        'password' => 'MotDePasseSolide1',
        'is_active' => true,
    ]);
    $autre->syncRoles(['ADMIN']);

    // Depuis `$autre`, on désactive le premier admin : OK, il reste `$autre`.
    $this->actingAs($autre, 'admin')
        ->post(route('administrateurs.statut', $this->admin), ['actif' => 0])
        ->assertRedirect();
    expect($this->admin->fresh()->is_active)->toBeFalse();

    // Maintenant `$autre` est le dernier ADMIN actif : un autre compte ne peut
    // pas le désactiver.
    $this->actingAs(adminAvecRole('SUPPORT'), 'admin'); // support n'a pas la permission
    $this->actingAs($autre, 'admin')
        ->post(route('administrateurs.statut', $autre), ['actif' => 0])
        ->assertSessionHasErrors('actif');
});

it('réinitialise le mot de passe d’un autre compte', function (): void {
    $support = adminAvecRole('SUPPORT');

    $this->actingAs($this->admin, 'admin')
        ->post(route('administrateurs.mot-de-passe', $support), [
            'password' => 'NouveauSecret2026',
            'password_confirmation' => 'NouveauSecret2026',
        ])
        ->assertRedirect();

    expect(Hash::check('NouveauSecret2026', $support->fresh()->password))->toBeTrue();
});

// ------------------------------------------------------------- mon profil --

it('change son propre mot de passe avec l’actuel', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->post(route('profil.mot-de-passe'), [
            'actuel' => 'DepanneMoi2026',
            'nouveau' => 'MonNouveauSecret1',
            'nouveau_confirmation' => 'MonNouveauSecret1',
        ])
        ->assertRedirect(route('profil'));

    expect(Hash::check('MonNouveauSecret1', $this->admin->fresh()->password))->toBeTrue();
});

it('refuse de changer son mot de passe sans l’actuel correct', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->post(route('profil.mot-de-passe'), [
            'actuel' => 'MauvaisMotDePasse',
            'nouveau' => 'MonNouveauSecret1',
            'nouveau_confirmation' => 'MonNouveauSecret1',
        ])
        ->assertSessionHasErrors('actuel');

    expect(Hash::check('DepanneMoi2026', $this->admin->fresh()->password))->toBeTrue();
});
