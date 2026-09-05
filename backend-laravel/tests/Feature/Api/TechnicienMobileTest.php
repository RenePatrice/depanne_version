<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\UserStatus;
use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Events\TechnicianPositionUpdated;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use App\Domain\Matching\Actions\SolicitNextTechnician;
use App\Domain\Matching\Data\MatchResponse;
use App\Domain\Matching\Models\MatchAttempt;
use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Notifications\Models\AppNotification;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Geo;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| API mobile — côté technicien et notifications (§7.3, §7.4, §8.3)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);
    Queue::fake();
});

/** Technicien authentifié, à la position voulue. */
function technicienConnecte(float $lat = 9.596, float $lng = -13.641, array $attributs = []): User
{
    $user = User::factory()->technician()->create(['status' => UserStatus::ACTIF]);

    TechnicianProfile::query()->updateOrCreate(
        ['user_id' => $user->id],
        array_merge([
            'specialties' => ['PLOMBERIE', 'ELECTRICITE'],
            'verification_status' => VerificationStatus::VALIDE,
            'is_online' => true,
            'last_known_location' => Geo::point($lat, $lng),
            'last_position_at' => now(),
            'base_location' => Geo::point($lat, $lng),
            'rating_avg' => 4.5,
            'reviews_count' => 10,
            'jobs_completed' => 20,
            'acceptance_rate' => 0.9,
            'cancellation_rate' => 0.05,
        ], $attributs),
    );

    $user = $user->fresh();
    Sanctum::actingAs($user);

    return $user;
}

// ------------------------------------------------------------- disponibilité --

it('met un technicien en ligne', function (): void {
    technicienConnecte(attributs: ['is_online' => false]);

    $this->postJson('/api/v1/technicien/disponibilite', ['en_ligne' => true])
        ->assertOk()
        ->assertJsonPath('en_ligne', true);
});

it('refuse la mise en ligne d’un dossier non validé', function (): void {
    technicienConnecte(attributs: [
        'is_online' => false,
        'verification_status' => VerificationStatus::EN_ATTENTE_VALIDATION,
    ]);

    $this->postJson('/api/v1/technicien/disponibilite', ['en_ligne' => true])
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'pas encore validé'));
});

it('refuse la mise hors ligne pendant une intervention', function (): void {
    $technicien = technicienConnecte();

    Ticket::query()->create([
        'reference' => 'DM-TEST-000900',
        'client_id' => User::factory()->create()->id,
        'technician_id' => $technicien->id,
        'service_id' => Service::query()->value('id'),
        'state' => TicketState::EN_ROUTE->value,
        'address_snapshot' => ['formatted_address' => 'Kipé'],
        'location' => Geo::point(9.595, -13.640),
        'base_price_gnf' => 50_000, 'travel_fee_gnf' => 0,
        'extra_fee_gnf' => 0, 'total_gnf' => 50_000,
    ]);

    $this->postJson('/api/v1/technicien/disponibilite', ['en_ligne' => false])
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'intervention en cours'));
});

it('interdit ces routes à un client', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/technicien/disponibilite', ['en_ligne' => true])->assertForbidden();
    $this->getJson('/api/v1/technicien/sollicitations')->assertForbidden();
});

// ------------------------------------------------------------------ position --

it('enregistre une position et la diffuse au back-office', function (): void {
    Event::fake([TechnicianPositionUpdated::class]);

    $technicien = technicienConnecte();

    $this->postJson('/api/v1/technicien/position', ['latitude' => 9.60, 'longitude' => -13.65])
        ->assertOk()
        ->assertJsonPath('ok', true);

    Event::assertDispatched(TechnicianPositionUpdated::class);

    expect($technicien->fresh()->technicianProfile->last_position_at)->not->toBeNull();
});

it('rejette une position hors de Guinée', function (): void {
    technicienConnecte();

    $this->postJson('/api/v1/technicien/position', ['latitude' => 48.85, 'longitude' => 2.35])
        ->assertStatus(422);
});

// ------------------------------------------------------------ sollicitations --

it('présente la sollicitation vivante avec son compte à rebours', function (): void {
    $technicien = technicienConnecte();
    $ticket = ticketPublie();

    app(SolicitNextTechnician::class)->execute($ticket);
    Sanctum::actingAs($technicien);

    $reponse = $this->getJson('/api/v1/technicien/sollicitations')->assertOk();

    expect($reponse->json('sollicitations'))->toHaveCount(1)
        ->and($reponse->json('sollicitations.0.expire_dans_s'))->toBeGreaterThan(0)
        ->and($reponse->json('sollicitations.0.net_technicien_gnf'))->toBeInt();
});

it('ne livre pas l’adresse exacte avant acceptation', function (): void {
    $technicien = technicienConnecte();
    app(SolicitNextTechnician::class)->execute(ticketPublie());
    Sanctum::actingAs($technicien);

    $sollicitation = $this->getJson('/api/v1/technicien/sollicitations')->json('sollicitations.0');

    expect($sollicitation['adresse'])->toBeNull()
        ->and($sollicitation['quartier'])->not->toBeNull();
});

it('accepte une demande et attribue le ticket', function (): void {
    $technicien = technicienConnecte();
    $ticket = ticketPublie();

    app(SolicitNextTechnician::class)->execute($ticket);
    Sanctum::actingAs($technicien);

    $id = MatchAttempt::query()->value('id');

    $this->postJson('/api/v1/sollicitations/'.$id.'/accepter')
        ->assertOk()
        ->assertJsonPath('ticket.etat', 'ACCEPTEE')
        ->assertJsonPath('ticket.prix.ferme', true);

    expect($ticket->fresh()->technician_id)->toBe($technicien->id);
});

it('renvoie un conflit quand la demande a déjà été prise', function (): void {
    $technicien = technicienConnecte();
    $ticket = ticketPublie();

    app(SolicitNextTechnician::class)->execute($ticket);
    Sanctum::actingAs($technicien);

    $id = MatchAttempt::query()->value('id');

    $this->postJson('/api/v1/sollicitations/'.$id.'/accepter')->assertOk();
    $this->postJson('/api/v1/sollicitations/'.$id.'/accepter')->assertStatus(409);
});

it('refuse une demande et la fait repartir', function (): void {
    $technicien = technicienConnecte();
    $ticket = ticketPublie();

    app(SolicitNextTechnician::class)->execute($ticket);
    Sanctum::actingAs($technicien);

    $this->postJson('/api/v1/sollicitations/'.MatchAttempt::query()->value('id').'/refuser', [
        'motif' => 'Déjà pris ailleurs',
    ])->assertOk();

    expect(MatchAttempt::query()->first()->response)->toBe(MatchResponse::REFUSE)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::SANS_REPONSE);
});

// -------------------------------------------------------------- intervention --

it('fait avancer l’intervention étape par étape', function (): void {
    $technicien = technicienConnecte();
    $ticket = ticketPublie();

    app(SolicitNextTechnician::class)->execute($ticket);
    Sanctum::actingAs($technicien);

    $this->postJson('/api/v1/sollicitations/'.MatchAttempt::query()->value('id').'/accepter')->assertOk();

    foreach ([['en-route', 'EN_ROUTE'], ['sur-place', 'SUR_PLACE'], ['demarrer', 'EN_COURS']] as [$etape, $etat]) {
        $this->postJson('/api/v1/tickets/'.$ticket->id.'/avancer', ['etape' => $etape])
            ->assertOk()
            ->assertJsonPath('ticket.etat', $etat);
    }

    $this->postJson('/api/v1/tickets/'.$ticket->id.'/avancer', [
        'etape' => 'terminer',
        'diagnostic' => 'Joint remplacé.',
    ])->assertOk()->assertJsonPath('ticket.etat', 'TERMINEE');

    expect($ticket->fresh()->diagnosis)->toBe('Joint remplacé.');
});

it('refuse un saut d’étape', function (): void {
    $technicien = technicienConnecte();
    $ticket = ticketPublie();

    app(SolicitNextTechnician::class)->execute($ticket);
    Sanctum::actingAs($technicien);

    $this->postJson('/api/v1/sollicitations/'.MatchAttempt::query()->value('id').'/accepter')->assertOk();

    // ACCEPTEE → TERMINEE n'existe pas dans la table des transitions.
    $this->postJson('/api/v1/tickets/'.$ticket->id.'/avancer', ['etape' => 'terminer'])
        ->assertStatus(422);
});

it('refuse de faire avancer l’intervention d’un autre', function (): void {
    $titulaire = technicienConnecte();
    $ticket = ticketPublie();

    app(SolicitNextTechnician::class)->execute($ticket);
    Sanctum::actingAs($titulaire);
    $this->postJson('/api/v1/sollicitations/'.MatchAttempt::query()->value('id').'/accepter')->assertOk();

    technicienConnecte(9.597, -13.642);
    // 404 et non 422 : ce technicien n'est partie à rien sur ce ticket, et
    // lui répondre autre chose lui confirmerait qu'il existe. La Policy
    // tranche avant même que le domaine ne soit appelé.

    $this->postJson('/api/v1/tickets/'.$ticket->id.'/avancer', ['etape' => 'en-route'])
        ->assertNotFound();
});

// ------------------------------------------------------------- notifications --

it('conserve une notification en base même sans push', function (): void {
    $technicien = technicienConnecte();
    $ticket = ticketPublie();

    app(SolicitNextTechnician::class)->execute($ticket);

    expect(AppNotification::query()->pour($technicien)->count())->toBe(1)
        ->and(AppNotification::query()->pour($technicien)->value('type'))
        ->toBe(NotificationType::NOUVELLE_DEMANDE->value);
});

it('prévient le client avec le montant devenu ferme', function (): void {
    $technicien = technicienConnecte();
    $ticket = ticketPublie();

    app(SolicitNextTechnician::class)->execute($ticket);
    Sanctum::actingAs($technicien);
    $this->postJson('/api/v1/sollicitations/'.MatchAttempt::query()->value('id').'/accepter')->assertOk();

    $notification = AppNotification::query()->pour($ticket->client)->firstOrFail();

    expect($notification->type)->toBe(NotificationType::TICKET_ACCEPTE->value)
        ->and($notification->data['total_ferme'])->toBeTrue();
});

it('sert le centre de notifications et sait tout marquer comme lu', function (): void {
    $technicien = technicienConnecte();
    app(SolicitNextTechnician::class)->execute(ticketPublie());
    Sanctum::actingAs($technicien);

    $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('non_lues', 1);

    $this->postJson('/api/v1/notifications/lues')->assertOk()->assertJsonPath('marquees', 1);

    $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('non_lues', 0);
});

it('ne montre pas les notifications d’un autre', function (): void {
    $technicien = technicienConnecte();
    app(SolicitNextTechnician::class)->execute(ticketPublie());

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('non_lues', 0);

    expect(AppNotification::query()->pour($technicien)->count())->toBe(1);
});

it('remplace une variable manquante par rien plutôt que par une accolade', function (): void {
    $client = User::factory()->create();

    $notification = app(SendNotification::class)->execute(
        $client,
        NotificationType::TICKET_ACCEPTE,
        ['reference' => 'DM-2026-000001'],   // {technicien} volontairement omis
    );

    expect($notification->data['corps'])->not->toContain('{')
        ->and($notification->data['corps'])->toContain('DM-2026-000001');
});
