<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\User;
use App\Domain\Tickets\Models\Ticket;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;

/*
 * Un numéro = un compte. La seule façon de réutiliser un numéro est de retirer
 * le compte de la base (commande `compte:retirer`, phases de test) ; un compte
 * simplement mis à la corbeille garde son numéro.
 */

beforeEach(function (): void {
    $this->seed(AppSettingsSeeder::class);
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
});

function inscription(string $telephone): Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/auth/inscription', [
        'full_name' => 'Mariama Diallo',
        'phone' => $telephone,
        'password' => 'MotDePasse2026',
        'password_confirmation' => 'MotDePasse2026',
    ]);
}

it('s\'inscrit sans aucun code de vérification', function (): void {
    inscription('620 11 22 33')
        ->assertCreated()
        ->assertJsonStructure(['utilisateur', 'jetons' => ['access_token', 'refresh_token']]);
});

it('garde le numéro pris tant que le compte est seulement à la corbeille', function (): void {
    inscription('620112233')->assertCreated();
    User::query()->where('phone', '+224620112233')->firstOrFail()->delete();

    inscription('+224 620 11 22 33')->assertStatus(422)->assertJsonValidationErrors('phone');
});

it('libère le numéro une fois le compte retiré de la base', function (): void {
    inscription('620112233')->assertCreated();
    inscription('00224620112233')->assertStatus(422);

    $this->artisan('compte:retirer', ['telephone' => '620112233', '--force' => true])->assertSuccessful();

    expect(User::withTrashed()->where('phone', '+224620112233')->exists())->toBeFalse();
    inscription('620112233')->assertCreated();
});

it('retire aussi un compte qui a déjà fait des demandes', function (): void {
    $ticket = ticketPublie();
    $client = $ticket->client;

    $this->artisan('compte:retirer', ['telephone' => $client->phone, '--force' => true])->assertSuccessful();

    expect(User::withTrashed()->whereKey($client->id)->exists())->toBeFalse()
        ->and(Ticket::query()->whereKey($ticket->id)->exists())->toBeFalse();
});

it('ne touche à rien sans confirmation', function (): void {
    inscription('620112233')->assertCreated();

    $this->artisan('compte:retirer', ['telephone' => '620112233'])
        ->expectsConfirmation('Retirer ce compte définitivement ?', 'no')
        ->assertSuccessful();

    expect(User::query()->where('phone', '+224620112233')->exists())->toBeTrue();
});

it('refuse de retirer un compte en production', function (): void {
    inscription('620112233')->assertCreated();
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('compte:retirer', ['telephone' => '620112233', '--force' => true])->assertFailed();

    expect(User::query()->where('phone', '+224620112233')->exists())->toBeTrue();
});
