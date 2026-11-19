<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Events\TechnicienPositionSuivie;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Geo;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Suivi du trajet du technicien
|--------------------------------------------------------------------------
|
| Le client voit son technicien avancer dès l'acceptation, et seulement
| pendant le trajet. Personne d'autre que lui ne lit cette position.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);
});

function trajet(TicketState $etat = TicketState::EN_ROUTE): array
{
    $client = User::factory()->create();
    $technicien = User::factory()->technician()->create();
    TechnicianProfile::query()->updateOrCreate(['user_id' => $technicien->id], [
        'specialties' => ['PLOMBERIE'],
        'verification_status' => VerificationStatus::VALIDE,
        'is_online' => true,
    ]);

    $ticket = Model::unguarded(fn (): Ticket => Ticket::query()->create([
        'reference' => 'DM-SUI-'.str_pad((string) (Ticket::query()->count() + 1), 4, '0', STR_PAD_LEFT),
        'client_id' => $client->id,
        'technician_id' => $technicien->id,
        'service_id' => servicePlomberie(),
        'state' => $etat->value,
        'address_snapshot' => ['formatted_address' => 'Kipé, Ratoma', 'latitude' => 9.595, 'longitude' => -13.640],
        'location' => Geo::point(9.595, -13.640),
        'base_price_gnf' => 100_000,
        'travel_fee_gnf' => 0,
        'extra_fee_gnf' => 0,
        'total_gnf' => 100_000,
        'accepted_at' => now()->subMinutes(5),
    ]));

    return [$ticket->fresh(), $client, $technicien];
}

it('diffuse la position au canal du ticket pendant le trajet', function (): void {
    Event::fake([TechnicienPositionSuivie::class]);
    [$ticket, , $technicien] = trajet();

    Sanctum::actingAs($technicien);
    $this->postJson('/api/v1/technicien/position', ['latitude' => 9.60, 'longitude' => -13.65])
        ->assertSuccessful();

    Event::assertDispatched(
        TechnicienPositionSuivie::class,
        fn (TechnicienPositionSuivie $e): bool => $e->ticketId === $ticket->id
            && $e->broadcastOn()[0]->name === 'private-ticket.'.$ticket->id,
    );
});

it('ne diffuse plus rien une fois le technicien sur place', function (): void {
    Event::fake([TechnicienPositionSuivie::class]);
    [, , $technicien] = trajet(TicketState::SUR_PLACE);

    Sanctum::actingAs($technicien);
    $this->postJson('/api/v1/technicien/position', ['latitude' => 9.60, 'longitude' => -13.65])
        ->assertSuccessful();

    Event::assertNotDispatched(TechnicienPositionSuivie::class);
});

it('donne au client la dernière position fraîche', function (): void {
    [$ticket, $client, $technicien] = trajet();
    $technicien->technicianProfile->forceFill([
        'last_known_location' => Geo::point(9.61, -13.66),
        'last_position_at' => now()->subMinute(),
    ])->save();

    Sanctum::actingAs($client);
    $this->getJson("/api/v1/tickets/{$ticket->id}/position-technicien")
        ->assertOk()
        ->assertJsonPath('suivi_actif', true)
        ->assertJsonPath('position.latitude', 9.61)
        ->assertJsonPath('position.longitude', -13.66);
});

it('tait une position trop ancienne pour être vraie', function (): void {
    [$ticket, $client, $technicien] = trajet();
    $technicien->technicianProfile->forceFill([
        'last_known_location' => Geo::point(9.61, -13.66),
        'last_position_at' => now()->subHour(),
    ])->save();

    Sanctum::actingAs($client);
    $this->getJson("/api/v1/tickets/{$ticket->id}/position-technicien")
        ->assertOk()
        ->assertJsonPath('position', null);
});

it('ne montre rien hors du trajet', function (): void {
    [$ticket, $client, $technicien] = trajet(TicketState::EN_COURS);
    $technicien->technicianProfile->forceFill([
        'last_known_location' => Geo::point(9.61, -13.66),
        'last_position_at' => now(),
    ])->save();

    Sanctum::actingAs($client);
    $this->getJson("/api/v1/tickets/{$ticket->id}/position-technicien")
        ->assertOk()
        ->assertJsonPath('suivi_actif', false)
        ->assertJsonPath('position', null);
});

it('répond 404 à tout autre que le client du ticket', function (): void {
    [$ticket, , $technicien] = trajet();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/tickets/{$ticket->id}/position-technicien")->assertNotFound();

    Sanctum::actingAs($technicien);
    $this->getJson("/api/v1/tickets/{$ticket->id}/position-technicien")->assertNotFound();
});
