<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Models\AppNotification;
use App\Domain\Tickets\Actions\TransitionTicket;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketEvent;
use App\Domain\Tickets\Services\CodeArrivee;
use App\Support\Geo;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| Code d'arrivée (lot 2)
|--------------------------------------------------------------------------
|
| Tiré à l'acceptation, montré au seul client, exigé du technicien à « je
| suis arrivé ». Cinq essais, puis seul le client peut confirmer l'arrivée.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);
});

/**
 * Un ticket publié puis accepté par la machine à états — c'est elle qui tire
 * le code — et éventuellement mis en route.
 *
 * @return array{0: Ticket, 1: User, 2: User}
 */
function interventionAvecCode(TicketState $jusqua = TicketState::EN_ROUTE): array
{
    $client = User::factory()->create();
    $technicien = User::factory()->technician()->create();
    TechnicianProfile::query()->updateOrCreate(['user_id' => $technicien->id], [
        'specialties' => ['PLOMBERIE'],
        'verification_status' => VerificationStatus::VALIDE,
        'is_online' => true,
    ]);

    $ticket = Model::unguarded(fn (): Ticket => Ticket::query()->create([
        'reference' => 'DM-ARR-'.str_pad((string) (Ticket::query()->count() + 1), 4, '0', STR_PAD_LEFT),
        'client_id' => $client->id,
        'technician_id' => $technicien->id,
        'service_id' => servicePlomberie(),
        'state' => TicketState::PUBLIEE->value,
        'address_snapshot' => ['formatted_address' => 'Kipé, Ratoma', 'latitude' => 9.595, 'longitude' => -13.640],
        'location' => Geo::point(9.595, -13.640),
        'base_price_gnf' => 100_000,
        'travel_fee_gnf' => 0,
        'extra_fee_gnf' => 0,
        'total_gnf' => 100_000,
    ]));

    $transition = app(TransitionTicket::class);
    $ticket = $transition->execute($ticket, TicketState::ACCEPTEE, ActorType::TECHNICIEN, $technicien->id);

    if ($jusqua === TicketState::EN_ROUTE) {
        $ticket = $transition->execute($ticket, TicketState::EN_ROUTE, ActorType::TECHNICIEN, $technicien->id);
    }

    return [$ticket->fresh(), $client, $technicien];
}

function arriver(Ticket $ticket, ?string $code = null): TestResponse
{
    return test()->postJson("/api/v1/tickets/{$ticket->id}/avancer", array_filter([
        'etape' => 'sur-place',
        'code' => $code,
    ], static fn ($v): bool => $v !== null));
}

function mauvaisCode(Ticket $ticket): string
{
    return $ticket->arrival_code === '0000' ? '1111' : '0000';
}

it('tire un code de quatre chiffres à l\'acceptation, chiffré en base', function (): void {
    [$ticket] = interventionAvecCode(TicketState::ACCEPTEE);

    expect($ticket->arrival_code)->toMatch('/^\d{4}$/')
        ->and($ticket->arrival_code_attempts)->toBe(0);

    $brut = DB::table('tickets')->where('id', $ticket->id)->value('arrival_code');
    expect($brut)->not->toBe($ticket->arrival_code)
        ->and(strlen((string) $brut))->toBeGreaterThan(20)
        ->and($ticket->toArray())->not->toHaveKey('arrival_code');
});

it('montre le code au client et jamais au technicien', function (): void {
    [$ticket, $client, $technicien] = interventionAvecCode();

    Sanctum::actingAs($client);
    $this->getJson("/api/v1/tickets/{$ticket->id}")
        ->assertOk()
        ->assertJsonPath('ticket.arrivee.code', $ticket->arrival_code)
        ->assertJsonPath('ticket.arrivee.code_requis', true)
        ->assertJsonPath('ticket.arrivee.confirmable', true);

    Sanctum::actingAs($technicien);
    $reponse = $this->getJson("/api/v1/tickets/{$ticket->id}")
        ->assertOk()
        ->assertJsonPath('ticket.arrivee.code_requis', true)
        ->assertJsonPath('ticket.arrivee.essais_restants', CodeArrivee::ESSAIS_MAX)
        ->assertJsonMissingPath('ticket.arrivee.code');

    expect($reponse->json('ticket.arrivee'))->not->toContain($ticket->arrival_code);
});

it('cache le code une fois le technicien arrivé', function (): void {
    [$ticket, $client, $technicien] = interventionAvecCode();

    Sanctum::actingAs($technicien);
    arriver($ticket, $ticket->arrival_code)->assertOk();

    Sanctum::actingAs($client);
    $this->getJson("/api/v1/tickets/{$ticket->id}")
        ->assertJsonPath('ticket.arrivee.code', null)
        ->assertJsonPath('ticket.arrivee.code_requis', false);
});

it('refuse « je suis arrivé » sans code, sans compter d\'essai', function (): void {
    [$ticket, , $technicien] = interventionAvecCode();

    Sanctum::actingAs($technicien);
    arriver($ticket)->assertStatus(422)->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'code d\'arrivée'));

    expect($ticket->fresh()->arrival_code_attempts)->toBe(0)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::EN_ROUTE);
});

it('compte les essais manqués puis accepte le bon code', function (): void {
    [$ticket, , $technicien] = interventionAvecCode();

    Sanctum::actingAs($technicien);
    arriver($ticket, mauvaisCode($ticket))
        ->assertStatus(422)
        ->assertJsonPath('message', 'Code incorrect. Il te reste 4 essais.');

    arriver($ticket, ' '.substr($ticket->arrival_code, 0, 2).' '.substr($ticket->arrival_code, 2))
        ->assertOk()
        ->assertJsonPath('ticket.etat', TicketState::SUR_PLACE->value);

    $evenement = TicketEvent::query()->where('ticket_id', $ticket->id)->latest('id')->firstOrFail();
    expect($evenement->metadata)->toBe(['code_arrivee' => 'verifie']);
});

it('ferme la porte après cinq essais et prévient le client', function (): void {
    [$ticket, $client, $technicien] = interventionAvecCode();

    Sanctum::actingAs($technicien);
    foreach (range(1, CodeArrivee::ESSAIS_MAX) as $_) {
        arriver($ticket, mauvaisCode($ticket))->assertStatus(422);
    }

    // Même le bon code ne passe plus.
    arriver($ticket, $ticket->arrival_code)
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'Trop d\'essais'));

    expect($ticket->fresh()->arrival_code_attempts)->toBe(CodeArrivee::ESSAIS_MAX)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::EN_ROUTE)
        ->and(AppNotification::query()->where('notifiable_id', $client->id)->where('type', 'ARRIVEE_A_CONFIRMER')->count())->toBe(1)
        ->and(Activity::query()->where('log_name', 'securite')->where('subject_id', $ticket->id)->count())->toBe(1);

    Sanctum::actingAs($client);
    $this->getJson("/api/v1/tickets/{$ticket->id}")->assertJsonPath('ticket.arrivee.bloque', true);
});

it('laisse le client confirmer l\'arrivée lui-même', function (): void {
    [$ticket, $client] = interventionAvecCode();

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/confirmer-arrivee")
        ->assertOk()
        ->assertJsonPath('ticket.etat', TicketState::SUR_PLACE->value);

    $evenement = TicketEvent::query()->where('ticket_id', $ticket->id)->latest('id')->firstOrFail();
    expect($evenement->metadata)->toBe(['code_arrivee' => 'confirme_par_client'])
        ->and($evenement->actor_type)->toBe(ActorType::CLIENT);
});

it('réserve la confirmation au client, et seulement en route', function (): void {
    [$ticket, $client, $technicien] = interventionAvecCode(TicketState::ACCEPTEE);

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/confirmer-arrivee")->assertNotFound();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/tickets/{$ticket->id}/confirmer-arrivee")->assertNotFound();

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/confirmer-arrivee")->assertStatus(422);
});

it('laisse passer un ticket antérieur au code', function (): void {
    [$ticket, , $technicien] = interventionAvecCode();
    $ticket->forceFill(['arrival_code' => null])->save();

    Sanctum::actingAs($technicien);
    arriver($ticket)->assertOk()->assertJsonPath('ticket.etat', TicketState::SUR_PLACE->value);

    $evenement = TicketEvent::query()->where('ticket_id', $ticket->id)->latest('id')->firstOrFail();
    expect($evenement->metadata)->toBe(['code_arrivee' => 'sans_code']);
});

it('tire un code neuf et remet les essais à zéro à chaque acceptation', function (): void {
    [$ticket, , $technicien] = interventionAvecCode(TicketState::ACCEPTEE);
    $ticket->forceFill(['arrival_code_attempts' => 3])->save();

    $transition = app(TransitionTicket::class);
    $ticket = $transition->execute($ticket, TicketState::PUBLIEE, ActorType::ADMIN);
    $ticket = $transition->execute($ticket, TicketState::ACCEPTEE, ActorType::TECHNICIEN, $technicien->id);

    expect($ticket->fresh()->arrival_code_attempts)->toBe(0)
        ->and($ticket->fresh()->arrival_code)->toMatch('/^\d{4}$/');
});
