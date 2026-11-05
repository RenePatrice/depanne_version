<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use App\Domain\Reporting\Services\PredictionService;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Geo;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Prévision de la demande (#10) — lecture sur données réelles
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
});

function ticketLe(CarbonImmutable $date, int $clientId, int $serviceId): void
{
    Model::unguarded(function () use ($date, $clientId, $serviceId): void {
        Ticket::query()->create([
            'reference' => 'DM-PRE-'.$date->format('md').'-'.str_pad((string) (Ticket::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'state' => TicketState::CLOTUREE->value,
            'address_snapshot' => ['formatted_address' => 'Kipé'],
            'location' => Geo::point(9.595, -13.640),
            'base_price_gnf' => 100_000, 'travel_fee_gnf' => 0, 'extra_fee_gnf' => 0,
            'short_trip_uplift_gnf' => 0, 'total_gnf' => 100_000,
            'commission_gnf' => 10_000, 'technician_net_gnf' => 90_000, 'commission_rate' => 0.10,
            'created_at' => $date, 'updated_at' => $date,
        ]);
    });
}

it('rend une prévision alignée sur l’historique, prête pour le graphe', function (): void {
    $client = User::factory()->create();
    $serviceId = (int) Service::query()->value('id');

    // Deux demandes par jour sur les 14 derniers jours.
    for ($i = 0; $i < 14; $i++) {
        $jour = CarbonImmutable::now()->subDays($i)->setTime(10, 0);
        ticketLe($jour, $client->id, $serviceId);
        ticketLe($jour, $client->id, $serviceId);
    }

    $prevision = app(PredictionService::class)->previsionDemande(28, 7);

    // L'axe couvre l'historique (28 jours) plus les 7 jours prédits.
    expect($prevision['categories'])->toHaveCount(35)
        ->and($prevision['series'])->toHaveCount(2)
        ->and($prevision['series'][0]['data'])->toHaveCount(35)
        ->and($prevision['series'][1]['data'])->toHaveCount(35)
        ->and($prevision['fiabilite'])->toBeIn(['bonne', 'moyenne', 'faible']);

    // La prévision est nulle pendant tout l'historique sauf le point d'ancrage
    // (le dernier jour connu), puis porte une valeur sur chacun des 7 jours.
    $futur = array_slice($prevision['series'][1]['data'], 28);
    foreach ($futur as $valeur) {
        expect($valeur)->toBeInt()->toBeGreaterThanOrEqual(0);
    }

    // L'historique n'a pas de valeur sur les jours futurs.
    $historiqueFutur = array_slice($prevision['series'][0]['data'], 28);
    foreach ($historiqueFutur as $valeur) {
        expect($valeur)->toBeNull();
    }
});

it('sert l’affluence prévue au technicien', function (): void {
    $client = User::factory()->create();
    $serviceId = (int) Service::query()->value('id');

    // De l'historique pour que l'estimation ne soit pas « inconnue ».
    for ($i = 0; $i < 20; $i++) {
        ticketLe(CarbonImmutable::now()->subDays($i % 14)->setTime(10, 0), $client->id, $serviceId);
    }

    Sanctum::actingAs(User::factory()->technician()->create());

    $this->getJson('/api/v1/technicien/affluence')
        ->assertOk()
        ->assertJsonStructure(['niveau', 'libelle', 'message', 'fiabilite']);
});

it('refuse l’affluence à un non-technicien', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/technicien/affluence')->assertStatus(403);
});
