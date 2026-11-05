<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\User;
use App\Domain\Tickets\Actions\TransitionTicket;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Events\TicketEtatChange;
use App\Domain\Tickets\Events\TicketTransitioned;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Temps réel de l'application (D4)
|--------------------------------------------------------------------------
|
| L'application s'authentifie par jeton Bearer, pas par session : elle a sa
| propre route d'autorisation des canaux, qui applique les règles de
| `routes/channels.php` sans les recopier.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);

    // Les tests tournent avec le diffuseur `null`, qui accepte tout sans rien
    // signer. Pour juger l'autorisation, il faut le vrai pilote — qui signe en
    // local, sans réseau — et y réinscrire les canaux.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'cle-de-test',
        'broadcasting.connections.reverb.secret' => 'secret-de-test',
        'broadcasting.connections.reverb.app_id' => 'depanne-test',
    ]);
    app(BroadcastManager::class)->forgetDrivers();
    require base_path('routes/channels.php');
});

function autoriser(string $canal): TestResponse
{
    return test()->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-'.$canal,
    ]);
}

it('ouvre à l’utilisateur son propre canal', function (): void {
    $client = User::factory()->create();
    Sanctum::actingAs($client);

    autoriser('utilisateur.'.$client->id)
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('ferme le canal personnel d’un autre utilisateur', function (): void {
    $autre = User::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    autoriser('utilisateur.'.$autre->id)->assertForbidden();
});

it('ouvre le canal du ticket aux seules parties', function (): void {
    $ticket = ticketPublie();
    $technicien = User::factory()->technician()->create();
    $ticket->forceFill(['technician_id' => $technicien->id])->save();

    Sanctum::actingAs($ticket->client);
    autoriser('ticket.'.$ticket->id)->assertOk();

    Sanctum::actingAs($technicien);
    autoriser('ticket.'.$ticket->id)->assertOk();

    Sanctum::actingAs(User::factory()->create());
    autoriser('ticket.'.$ticket->id)->assertForbidden();
});

it('n’ouvre pas le canal de supervision à un compte mobile', function (): void {
    Sanctum::actingAs(User::factory()->create());

    autoriser('back-office')->assertForbidden();
});

it('exige un jeton', function (): void {
    autoriser('utilisateur.1')->assertUnauthorized();
});

it('signale chaque changement d’état sur le canal du ticket, sans montant', function (): void {
    // Le signal du back-office est neutralisé aussi : avec le vrai pilote, il
    // partirait vers un serveur Reverb que les tests ne démarrent pas.
    Event::fake([TicketEtatChange::class, TicketTransitioned::class]);

    $ticket = ticketPublie();

    app(TransitionTicket::class)->execute($ticket, TicketState::ANNULEE_CLIENT);

    Event::assertDispatched(TicketEtatChange::class, function (TicketEtatChange $signal) use ($ticket): bool {
        $charge = $signal->broadcastWith();

        return $signal->broadcastOn()[0]->name === 'private-ticket.'.$ticket->id
            && $charge['etat'] === 'ANNULEE_CLIENT'
            // Un signal, pas une donnée : l'application relit le ticket.
            && array_keys($charge) === ['id', 'etat', 'horodatage'];
    });
});
