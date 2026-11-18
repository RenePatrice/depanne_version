<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use App\Domain\Notifications\Models\AppNotification;
use App\Domain\Payments\Data\PaymentEventType;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Jobs\RemindPaymentConfirmationJob;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Wallet\Models\Transaction;
use App\Support\Geo;
use Database\Seeders\Demo\AdminUserSeeder;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| Paiement tracé (ADR-0047)
|--------------------------------------------------------------------------
|
| Le client choisit son moyen — espèces ou Mobile Money. Un paiement direct
| n'est réglé qu'avec la confirmation des deux parties, et chaque étape est
| écrite au journal, que le back-office consulte et peut arbitrer.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);
    Queue::fake();
});

/** Intervention terminée à 100 000 GNF, commission 10 000. */
function interventionAPayer(): array
{
    $client = User::factory()->create();
    $technicien = User::factory()->technician()->create();
    TechnicianProfile::query()->updateOrCreate(['user_id' => $technicien->id], [
        'specialties' => ['PLOMBERIE'],
        'verification_status' => VerificationStatus::VALIDE,
        'is_online' => true,
    ]);

    $ticket = Model::unguarded(fn (): Ticket => Ticket::query()->create([
        'reference' => 'DM-TRC-'.str_pad((string) (Ticket::query()->count() + 1), 4, '0', STR_PAD_LEFT),
        'client_id' => $client->id,
        'technician_id' => $technicien->id,
        'service_id' => Service::query()->value('id'),
        'state' => TicketState::TERMINEE->value,
        'address_snapshot' => ['formatted_address' => 'Kipé, Ratoma'],
        'location' => Geo::point(9.595, -13.640),
        'base_price_gnf' => 100_000,
        'travel_fee_gnf' => 0,
        'extra_fee_gnf' => 0,
        'total_gnf' => 100_000,
        'commission_gnf' => 10_000,
        'technician_net_gnf' => 90_000,
        'commission_rate' => 0.10,
        'completed_at' => now()->subMinutes(5),
    ]));

    return [$ticket->fresh(), $client, $technicien];
}

/** @return array<int, string> */
function journalDe(Ticket $ticket): array
{
    return PaymentEvent::query()->where('ticket_id', $ticket->id)->orderBy('id')
        ->get()->map(fn (PaymentEvent $e): string => $e->type->value)->all();
}

it('laisse le client choisir Orange Money, puis règle au second accord', function (): void {
    [$ticket, $client, $technicien] = interventionAPayer();

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct", [
        'moyen' => 'ORANGE_MONEY',
        'reference_transaction' => 'CI240918.1452.A12345',
    ])->assertCreated()
        ->assertJsonPath('paiement.statut', PaymentStatus::ATTENTE_CONFIRMATION->value)
        ->assertJsonPath('paiement.en_attente_de', 'TECHNICIEN')
        ->assertJsonPath('paiement.reference_transaction', 'CI240918.1452.A12345');

    // Une seule confirmation ne règle rien.
    expect(Transaction::balanceFor($technicien->id))->toBe(0)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::TERMINEE);

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct/confirmer")
        ->assertOk()
        ->assertJsonPath('paiement.statut', PaymentStatus::CONFIRMEE->value);

    expect($ticket->fresh()->state->etat())->toBe(TicketState::CLOTUREE)
        ->and(Transaction::balanceFor($technicien->id))->toBe(-10_000)
        ->and(journalDe($ticket))->toBe(['DECLARE', 'CONFIRME', 'COMMISSION']);
});

it('demande aussi la confirmation du client quand le technicien déclare des espèces', function (): void {
    [$ticket, $client, $technicien] = interventionAPayer();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct", ['moyen' => 'ESPECES'])
        ->assertCreated()
        ->assertJsonPath('paiement.en_attente_de', 'CLIENT');

    Sanctum::actingAs($client);
    $this->getJson("/api/v1/tickets/{$ticket->id}")
        ->assertJsonPath('ticket.especes_a_confirmer', true)
        ->assertJsonPath('ticket.paiement_en_attente_de', 'CLIENT');

    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct/confirmer")->assertOk();

    expect($ticket->fresh()->state->etat())->toBe(TicketState::CLOTUREE);
});

it('réserve le choix du Mobile Money au client', function (): void {
    [$ticket, , $technicien] = interventionAPayer();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct", ['moyen' => 'MTN_MOMO'])
        ->assertStatus(422);

    expect(Payment::query()->count())->toBe(0);
});

it('ne laisse personne confirmer deux fois, ni pour l’autre', function (): void {
    [$ticket, $client] = interventionAPayer();

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct", ['moyen' => 'ESPECES'])->assertCreated();
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct/confirmer")->assertStatus(422);

    expect(Payment::query()->value('status'))->toBe(PaymentStatus::ATTENTE_CONFIRMATION);
});

it('fige le paiement contesté sans rien inscrire, et le trace', function (): void {
    [$ticket, $client, $technicien] = interventionAPayer();

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct", ['moyen' => 'ESPECES'])->assertCreated();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct/contester", ['motif' => 'court'])
        ->assertStatus(422);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct/contester", [
        'motif' => 'Je n’ai reçu que 50 000 GNF en espèces.',
    ])->assertOk()->assertJsonPath('paiement.statut', PaymentStatus::CONTESTEE->value);

    expect($ticket->fresh()->state->etat())->toBe(TicketState::TERMINEE)
        ->and(Transaction::balanceFor($technicien->id))->toBe(0)
        ->and(journalDe($ticket))->toBe(['DECLARE', 'CONTESTE'])
        ->and(Activity::query()->where('log_name', 'finances')->where('description', 'Paiement direct contesté')->exists())->toBeTrue();
});

it('laisse le déclarant annuler puis redéclarer, et seulement lui', function (): void {
    [$ticket, $client, $technicien] = interventionAPayer();

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct", ['moyen' => 'MTN_MOMO'])->assertCreated();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct/annuler")->assertStatus(422);

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct/annuler")->assertOk();
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct", ['moyen' => 'ESPECES'])->assertCreated();

    expect(Payment::query()->where('ticket_id', $ticket->id)->count())->toBe(2)
        ->and(journalDe($ticket))->toBe(['DECLARE', 'ANNULE', 'DECLARE']);
});

it('répond 404 à qui n’est pas partie à l’intervention', function (): void {
    [$ticket] = interventionAPayer();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/tickets/{$ticket->id}/paiement")->assertNotFound();
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct", ['moyen' => 'ESPECES'])->assertNotFound();
});

it('ne montre jamais la commission au client', function (): void {
    [$ticket, $client, $technicien] = interventionAPayer();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct", ['moyen' => 'ESPECES'])->assertCreated();
    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct/confirmer")->assertOk();

    $typesClient = collect($this->getJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.historique'))->pluck('type');
    Sanctum::actingAs($technicien);
    $typesTech = collect($this->getJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.historique'))->pluck('type');

    expect($typesClient->contains('COMMISSION'))->toBeFalse()
        ->and($typesTech->contains('COMMISSION'))->toBeTrue();
});

it('relance une seule fois la partie qui n’a pas confirmé', function (): void {
    [$ticket, $client, $technicien] = interventionAPayer();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct", ['moyen' => 'ESPECES'])->assertCreated();
    Queue::assertPushed(RemindPaymentConfirmationJob::class);

    $paiement = Payment::query()->firstOrFail();
    $relance = new RemindPaymentConfirmationJob((int) $paiement->id);
    app()->call([$relance, 'handle']);
    app()->call([$relance, 'handle']);

    expect(AppNotification::query()->where('notifiable_id', $client->id)->where('type', 'PAIEMENT_RELANCE')->count())->toBe(1)
        ->and(journalDe($ticket))->toBe(['DECLARE', 'RELANCE'])
        ->and($paiement->fresh()->reminded_at)->not->toBeNull();
});

it('journalise le lancement d’un paiement Mobile Money via l’application', function (): void {
    [$ticket, $client] = interventionAPayer();

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->assertCreated();

    expect(journalDe($ticket))->toBe([PaymentEventType::INITIE->value]);
});

it('montre les paiements au back-office et laisse le support arbitrer une contestation', function (): void {
    $this->seed(AdminUserSeeder::class);
    [$ticket, $client, $technicien] = interventionAPayer();

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct", ['moyen' => 'ESPECES'])->assertCreated();
    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-direct/contester", [
        'motif' => 'Le client n’a rien payé à la fin de l’intervention.',
    ])->assertOk();

    $paiement = Payment::query()->firstOrFail();
    $support = adminAvecRole('SUPPORT');

    $this->actingAs($support, 'admin')->get(route('paiements'))->assertOk()->assertSee('Commissions dues');
    $this->actingAs($support, 'admin')->get(route('paiements.donnees'))->assertOk()->assertSee($paiement->provider_ref);
    $this->actingAs($support, 'admin')->get(route('paiements.detail', $paiement))
        ->assertOk()
        ->assertSee('Contesté par le technicien')
        ->assertSee('Journal du paiement');

    // Motif trop court : refusé.
    $this->actingAs($support, 'admin')
        ->post(route('paiements.arbitrer', $paiement), ['decision' => 'confirmer', 'motif' => 'ok'])
        ->assertSessionHasErrors('motif');

    $this->actingAs($support, 'admin')
        ->post(route('paiements.arbitrer', $paiement), [
            'decision' => 'confirmer',
            'motif' => 'Client et technicien joints : 100 000 GNF payés en espèces.',
        ])->assertRedirect(route('paiements.detail', $paiement));

    expect($paiement->fresh()->status)->toBe(PaymentStatus::CONFIRMEE)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::CLOTUREE)
        ->and(Transaction::balanceFor($technicien->id))->toBe(-10_000)
        ->and(journalDe($ticket))->toBe(['DECLARE', 'CONTESTE', 'ARBITRE', 'COMMISSION']);
});
