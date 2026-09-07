<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Notifications\Models\AppNotification;
use App\Domain\Tickets\Actions\ProposeDiagnostic;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Supplément après diagnostic (§8.1)
|--------------------------------------------------------------------------
|
| Le seul endroit où le total d'un ticket accepté peut augmenter. Ces tests
| protègent la règle qui le rend acceptable : le technicien propose, le
| client décide, et rien ne bouge entre les deux.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);
    Queue::fake();
});

/** Intervention où le technicien est sur place, prêt à diagnostiquer. */
function interventionSurPlace(int $prestation = 100_000): array
{
    $ticket = ticketPublie();

    $technicien = User::factory()->technician()->create();
    TechnicianProfile::query()->updateOrCreate(['user_id' => $technicien->id], [
        'specialties' => ['PLOMBERIE'],
        'verification_status' => VerificationStatus::VALIDE,
        'is_online' => true,
    ]);

    $ticket->forceFill([
        'technician_id' => $technicien->id,
        'state' => TicketState::SUR_PLACE->value,
        'base_price_gnf' => $prestation,
        'short_trip_uplift_gnf' => 0,
        'travel_fee_gnf' => 0,
        'extra_fee_gnf' => 0,
        'total_gnf' => $prestation,
        'commission_gnf' => (int) round($prestation * 0.10),
        'technician_net_gnf' => $prestation - (int) round($prestation * 0.10),
        'commission_rate' => 0.10,
        'accepted_at' => now()->subHour(),
        'arrived_at' => now()->subMinutes(5),
    ])->save();

    return [$ticket->fresh(), $ticket->client, $technicien];
}

/** @return array<string, mixed> */
function propositionValide(int $supplement = 40_000): array
{
    return [
        'diagnostic' => 'Le flexible est percé et le robinet d’arrêt ne ferme plus. '
            .'Les deux sont à remplacer.',
        'supplement_gnf' => $supplement,
    ];
}

// ------------------------------------------------------------- proposition --

it('met le ticket en attente sans toucher au total', function (): void {
    [$ticket, , $technicien] = interventionSurPlace();

    Sanctum::actingAs($technicien);

    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic", propositionValide())
        ->assertCreated()
        ->assertJsonPath('ticket.etat', 'DIAGNOSTIC_EN_ATTENTE');

    $frais = $ticket->fresh();

    // Proposé n'est pas appliqué : le total reste celui que le client a validé.
    expect($frais->total_gnf)->toBe(100_000)
        ->and($frais->extra_fee_gnf)->toBe(0)
        ->and($frais->proposed_extra_fee_gnf)->toBe(40_000);
});

it('exige un diagnostic écrit', function (): void {
    [$ticket, , $technicien] = interventionSurPlace();

    Sanctum::actingAs($technicien);

    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic", [
        'diagnostic' => 'cassé',
        'supplement_gnf' => 40_000,
    ])->assertStatus(422)->assertJsonValidationErrors('diagnostic');
});

it('plafonne le supplément au double de la prestation', function (): void {
    [$ticket, , $technicien] = interventionSurPlace(100_000);

    Sanctum::actingAs($technicien);

    $this->postJson(
        "/api/v1/tickets/{$ticket->id}/diagnostic",
        propositionValide(200_001),
    )->assertStatus(422)->assertJsonPath(
        'message',
        fn (string $m): bool => str_contains($m, 'ne peut pas dépasser'),
    );

    // Le plafond lui-même passe : la borne écarte l'abus, pas le cas limite.
    $this->postJson(
        "/api/v1/tickets/{$ticket->id}/diagnostic",
        propositionValide(200_000),
    )->assertCreated();
});

it('refuse une proposition avant l’arrivée sur place', function (): void {
    [$ticket, , $technicien] = interventionSurPlace();
    $ticket->forceFill(['state' => TicketState::EN_ROUTE->value])->save();

    Sanctum::actingAs($technicien);

    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic", propositionValide())
        ->assertStatus(422);
});

it('refuse un supplément nul', function (): void {
    [$ticket, , $technicien] = interventionSurPlace();

    Sanctum::actingAs($technicien);

    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic", [
        'diagnostic' => 'Rien de plus que prévu, tout est conforme au devis initial.',
        'supplement_gnf' => 0,
    ])->assertStatus(422);
});

it('n’ouvre la proposition qu’au technicien assigné', function (): void {
    [$ticket] = interventionSurPlace();

    Sanctum::actingAs(User::factory()->technician()->create());

    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic", propositionValide())
        ->assertNotFound();
});

it('prévient le client avec le nouveau total', function (): void {
    [$ticket, $client, $technicien] = interventionSurPlace();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic", propositionValide())
        ->assertCreated();

    $notification = AppNotification::query()->pour($client)->firstOrFail();

    expect($notification->type)->toBe(NotificationType::DIAGNOSTIC_PROPOSE->value)
        ->and($notification->data['nouveau_total_gnf'])->toBe(140_000)
        ->and($notification->data['urgente'])->toBeTrue();
});

// ------------------------------------------------------------------ aperçu --

it('montre au client ce que coûterait l’acceptation, avant qu’il décide', function (): void {
    [$ticket, $client, $technicien] = interventionSurPlace();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic", propositionValide())
        ->assertCreated();

    Sanctum::actingAs($client);

    $this->getJson("/api/v1/tickets/{$ticket->id}/diagnostic")
        ->assertOk()
        ->assertJsonPath('supplement_gnf', 40_000)
        ->assertJsonPath('total_actuel_gnf', 100_000)
        ->assertJsonPath('devis.total_gnf', 140_000);
});

it('ne montre rien quand aucun supplément n’attend', function (): void {
    [$ticket, $client] = interventionSurPlace();

    Sanctum::actingAs($client);

    $this->getJson("/api/v1/tickets/{$ticket->id}/diagnostic")->assertNotFound();
});

// ----------------------------------------------------------------- réponse --

it('applique le supplément et reventile la commission à l’acceptation', function (): void {
    [$ticket, $client, $technicien] = interventionSurPlace();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic", propositionValide())
        ->assertCreated();

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic/reponse", ['accepte' => true])
        ->assertOk()
        ->assertJsonPath('ticket.etat', 'DIAGNOSTIC_VALIDE');

    $frais = $ticket->fresh();

    // La commission porte sur le **nouveau** total, pas sur l'ancien.
    expect($frais->total_gnf)->toBe(140_000)
        ->and($frais->extra_fee_gnf)->toBe(40_000)
        ->and($frais->commission_gnf)->toBe(14_000)
        ->and($frais->technician_net_gnf)->toBe(126_000)
        ->and($frais->priceIsCoherent())->toBeTrue()
        ->and($frais->proposed_extra_fee_gnf)->toBeNull();
});

it('laisse le total intact au refus, sans annuler l’intervention', function (): void {
    [$ticket, $client, $technicien] = interventionSurPlace();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic", propositionValide())
        ->assertCreated();

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic/reponse", ['accepte' => false])
        ->assertOk()
        ->assertJsonPath('ticket.etat', 'DIAGNOSTIC_REFUSE');

    $frais = $ticket->fresh();

    expect($frais->total_gnf)->toBe(100_000)
        ->and($frais->extra_fee_gnf)->toBe(0);

    // Le technicien est sur place : il peut faire ce qui était prévu.
    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/avancer", ['etape' => 'demarrer'])
        ->assertOk()
        ->assertJsonPath('ticket.etat', 'EN_COURS');
});

it('n’ouvre la décision qu’au client', function (): void {
    [$ticket, , $technicien] = interventionSurPlace();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic", propositionValide())
        ->assertCreated();

    // Le technicien ne valide pas son propre supplément.
    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic/reponse", ['accepte' => true])
        ->assertNotFound();
});

it('consomme la proposition : on ne répond pas deux fois', function (): void {
    [$ticket, $client, $technicien] = interventionSurPlace();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic", propositionValide())
        ->assertCreated();

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic/reponse", ['accepte' => true])
        ->assertOk();
    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic/reponse", ['accepte' => true])
        ->assertStatus(422);

    expect($ticket->fresh()->total_gnf)->toBe(140_000);
});

it('mène le supplément accepté jusqu’au paiement', function (): void {
    [$ticket, $client, $technicien] = interventionSurPlace();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic", propositionValide())
        ->assertCreated();

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic/reponse", ['accepte' => true])
        ->assertOk();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/avancer", ['etape' => 'demarrer'])->assertOk();
    $this->postJson("/api/v1/tickets/{$ticket->id}/avancer", ['etape' => 'terminer'])->assertOk();

    Sanctum::actingAs($client);

    // Le montant à payer est bien le total révisé.
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")
        ->assertCreated()
        ->assertJsonPath('paiement.montant_gnf', 140_000);
});

// --------------------------------------------------------------- plafond --

it('expose le plafond sous forme de multiple, pas de montant en dur', function (): void {
    // La règle est « le double de la prestation », pas « 200 000 GNF » : elle
    // doit suivre le prix de la prestation, quelle qu'elle soit.
    expect(ProposeDiagnostic::MULTIPLE_MAX)->toBe(2);

    [$ticket, , $technicien] = interventionSurPlace(50_000);

    Sanctum::actingAs($technicien);

    $this->postJson("/api/v1/tickets/{$ticket->id}/diagnostic", propositionValide(100_001))
        ->assertStatus(422);
});
