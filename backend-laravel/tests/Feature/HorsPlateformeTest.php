<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Geo;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Signalement hors-plateforme (#3, décision du 5 novembre 2026)
|--------------------------------------------------------------------------
|
| Parade au contournement : le client (ou le technicien) nomme une tentative
| de régler en dehors de l'application. On ne bloque rien, on trace dans le
| journal de sécurité du back-office.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
});

/** Intervention en cours, client et technicien en relation. */
function interventionEnRelation(): array
{
    $client = User::factory()->create();
    $technicien = User::factory()->technician()->create();

    $ticket = Model::unguarded(fn (): Ticket => Ticket::query()->create([
        'reference' => 'DM-OFF-'.str_pad((string) (Ticket::query()->count() + 1), 4, '0', STR_PAD_LEFT),
        'client_id' => $client->id,
        'technician_id' => $technicien->id,
        'service_id' => Service::query()->value('id'),
        'state' => TicketState::EN_ROUTE->value,
        'address_snapshot' => ['formatted_address' => 'Kipé, Ratoma'],
        'location' => Geo::point(9.595, -13.640),
        'base_price_gnf' => 100_000, 'travel_fee_gnf' => 0, 'extra_fee_gnf' => 0,
        'short_trip_uplift_gnf' => 0, 'total_gnf' => 100_000,
        'commission_gnf' => 10_000, 'technician_net_gnf' => 90_000, 'commission_rate' => 0.10,
        'accepted_at' => now()->subMinutes(10), 'en_route_at' => now()->subMinutes(5),
    ]));

    return [$ticket->fresh(), $client, $technicien];
}

it('trace un signalement hors-plateforme contre l’autre partie', function (): void {
    [$ticket, $client, $technicien] = interventionEnRelation();

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/signaler-hors-plateforme", [
        'description' => 'Le technicien me demande d’annuler pour payer en dehors de l’application.',
    ])->assertCreated();

    $trace = DB::table('activity_log')
        ->where('log_name', 'securite')
        ->latest('id')
        ->first();

    expect($trace)->not->toBeNull()
        // Le signalé est le technicien : c'est son compte que le support regardera.
        ->and((int) $trace->subject_id)->toBe((int) $technicien->id)
        ->and($trace->description)->toContain($ticket->reference);
});

it('répond 404 à qui n’est pas partie au ticket', function (): void {
    [$ticket] = interventionEnRelation();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/tickets/{$ticket->id}/signaler-hors-plateforme", [
        'description' => 'Je signale un ticket qui ne me concerne pas.',
    ])->assertNotFound();
});

it('exige une description', function (): void {
    [$ticket, $client] = interventionEnRelation();

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/signaler-hors-plateforme", [
        'description' => 'court',
    ])->assertStatus(422);
});
