<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use App\Domain\Payments\Actions\DeclareCashPayment;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Tickets\Actions\AdvanceIntervention;
use App\Domain\Tickets\Actions\TransitionTicket;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Geo;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Garantie après intervention (#4, décision du 5 novembre 2026)
|--------------------------------------------------------------------------
|
| Une panne qui revient dans la fenêtre de garantie ouvre droit au retour du
| même technicien, sans nouveau paiement. Le retour est un ticket à part,
| rattaché à l'intervention d'origine et marqué `is_warranty`.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);
    Queue::fake();
});

/** Intervention clôturée, encore sous garantie, prête pour un retour. */
function interventionCloturee(?string $warrantyUntil = '+5 days'): array
{
    $client = User::factory()->create();
    $technicien = User::factory()->technician()->create();
    TechnicianProfile::query()->updateOrCreate(['user_id' => $technicien->id], [
        'specialties' => ['PLOMBERIE'],
        'verification_status' => VerificationStatus::VALIDE,
        'is_online' => true,
    ]);

    $ticket = Model::unguarded(fn (): Ticket => Ticket::query()->create([
        'reference' => 'DM-GAR-'.str_pad((string) (Ticket::query()->count() + 1), 4, '0', STR_PAD_LEFT),
        'client_id' => $client->id,
        'technician_id' => $technicien->id,
        'service_id' => Service::query()->value('id'),
        'state' => TicketState::CLOTUREE->value,
        'address_snapshot' => ['formatted_address' => 'Kipé, Ratoma'],
        'location' => Geo::point(9.595, -13.640),
        'base_price_gnf' => 100_000,
        'travel_fee_gnf' => 0,
        'extra_fee_gnf' => 0,
        'short_trip_uplift_gnf' => 0,
        'total_gnf' => 100_000,
        'commission_gnf' => 10_000,
        'technician_net_gnf' => 90_000,
        'commission_rate' => 0.10,
        'completed_at' => now()->subDay(),
        'closed_at' => now()->subHours(2),
        'warranty_until' => $warrantyUntil === null ? null : now()->modify($warrantyUntil),
    ]));

    return [$ticket->fresh(), $client, $technicien];
}

it('ouvre un retour sous garantie : même technicien, gratuit, rattaché à l’origine', function (): void {
    [$origine, $client, $technicien] = interventionCloturee();

    Sanctum::actingAs($client);
    $reponse = $this->postJson("/api/v1/tickets/{$origine->id}/garantie", [
        'description' => 'La fuite est revenue au même endroit.',
    ])->assertCreated();

    $retourId = $reponse->json('ticket.id');
    $retour = Ticket::query()->findOrFail($retourId);

    expect($retour->is_warranty)->toBeTrue()
        ->and((int) $retour->parent_ticket_id)->toBe((int) $origine->id)
        ->and((int) $retour->technician_id)->toBe((int) $technicien->id)
        ->and($retour->total_gnf)->toBe(0)
        ->and($retour->commission_gnf)->toBe(0)
        ->and($retour->state->etat())->toBe(TicketState::ACCEPTEE);
});

it('refuse un retour hors de la fenêtre de garantie', function (): void {
    [$origine, $client] = interventionCloturee('-1 day');

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$origine->id}/garantie", [
        'description' => 'La panne est revenue mais trop tard.',
    ])->assertStatus(422);
});

it('réserve la garantie au client', function (): void {
    [$origine, , $technicien] = interventionCloturee();

    // Le technicien devine la référence : il reçoit un 404, jamais un 403.
    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$origine->id}/garantie", [
        'description' => 'Je tente d’ouvrir une garantie moi-même.',
    ])->assertNotFound();
});

it('n’ouvre qu’un seul retour sous garantie à la fois', function (): void {
    [$origine, $client] = interventionCloturee();

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$origine->id}/garantie", [
        'description' => 'Première ouverture de garantie.',
    ])->assertCreated();

    $this->postJson("/api/v1/tickets/{$origine->id}/garantie", [
        'description' => 'Deuxième tentative alors que la première est vivante.',
    ])->assertStatus(422);
});

it('aucun paiement n’est possible sur un retour sous garantie', function (): void {
    [$origine, $client, $technicien] = interventionCloturee();

    Sanctum::actingAs($client);
    $retourId = $this->postJson("/api/v1/tickets/{$origine->id}/garantie", [
        'description' => 'La fuite est revenue.',
    ])->json('ticket.id');

    $retour = Ticket::query()->findOrFail($retourId);

    // Le technicien repasse jusqu'à TERMINEE.
    $avancer = app(AdvanceIntervention::class);
    foreach (['en-route', 'sur-place', 'demarrer', 'terminer'] as $etape) {
        $retour = $avancer->execute($retour->fresh(), $technicien, $etape);
    }

    // Déclarer un paiement espèces est refusé : c'est gratuit.
    expect(fn () => app(DeclareCashPayment::class)->execute($retour->fresh(), $technicien))
        ->toThrow(DomainException::class, 'gratuit');

    // Le client confirme la réparation : le retour se clôt sans argent.
    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$retour->id}/garantie/confirmer")->assertOk();

    expect($retour->fresh()->state->etat())->toBe(TicketState::CLOTUREE);
});

it('fige la garantie à la clôture d’une intervention payante', function (): void {
    AppSetting::put(AppSetting::WARRANTY_DAYS, 7);

    $client = User::factory()->create();
    $ticket = Model::unguarded(fn (): Ticket => Ticket::query()->create([
        'reference' => 'DM-FIG-0001',
        'client_id' => $client->id,
        'service_id' => Service::query()->value('id'),
        'state' => TicketState::TERMINEE->value,
        'address_snapshot' => ['formatted_address' => 'Kipé'],
        'location' => Geo::point(9.595, -13.640),
        'base_price_gnf' => 100_000, 'travel_fee_gnf' => 0, 'extra_fee_gnf' => 0,
        'short_trip_uplift_gnf' => 0, 'total_gnf' => 100_000,
        'commission_gnf' => 10_000, 'technician_net_gnf' => 90_000, 'commission_rate' => 0.10,
        'completed_at' => now(),
    ]));

    $transition = app(TransitionTicket::class);
    $ticket = $transition->execute($ticket, TicketState::PAYEE);
    $ticket = $transition->execute($ticket, TicketState::CLOTUREE);

    expect($ticket->warranty_until)->not->toBeNull()
        ->and($ticket->fresh()->garantieActive())->toBeTrue();
});
