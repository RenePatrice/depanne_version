<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use App\Domain\Matching\Actions\RespondToMatch;
use App\Domain\Matching\Data\MatchResponse;
use App\Domain\Matching\Models\MatchAttempt;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Wallet\Data\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Services\CommissionLedger;
use App\Support\Geo;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Paiement direct et commission due (#1, décision du 5 novembre 2026)
|--------------------------------------------------------------------------
|
| Le client paie le technicien directement. En espèces, le technicien encaisse
| tout et la commission lui reste à devoir ; au-delà d'un plafond, il ne peut
| plus accepter de demandes avant de régler. Les gains Mobile Money compensent
| la dette. La confirmation du client est ce qui engage.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);
    Queue::fake();
});

/** Intervention terminée à 100 000 GNF, commission 10 000, prête à payer. */
function interventionEspeces(): array
{
    $client = User::factory()->create();
    $technicien = User::factory()->technician()->create();
    TechnicianProfile::query()->updateOrCreate(['user_id' => $technicien->id], [
        'specialties' => ['PLOMBERIE'],
        'verification_status' => VerificationStatus::VALIDE,
        'is_online' => true,
    ]);

    $ticket = Illuminate\Database\Eloquent\Model::unguarded(fn (): Ticket => Ticket::query()->create([
        'reference' => 'DM-ESP-'.str_pad((string) (Ticket::query()->count() + 1), 4, '0', STR_PAD_LEFT),
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

it('déclare puis confirme un paiement espèces, et la commission devient une dette', function (): void {
    [$ticket, $client, $technicien] = interventionEspeces();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-especes")
        ->assertCreated()
        ->assertJsonPath('statut', PaymentStatus::ATTENTE_CONFIRMATION->value);

    // Tant que le client n'a pas confirmé, rien n'est inscrit et rien n'est clos.
    expect(Transaction::balanceFor($technicien->id))->toBe(0)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::TERMINEE);

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-especes/confirmer")->assertOk();

    expect($ticket->fresh()->state->etat())->toBe(TicketState::CLOTUREE)
        // Le technicien a encaissé 100 000 ; il doit 10 000 de commission.
        ->and(Transaction::balanceFor($technicien->id))->toBe(-10_000)
        ->and(app(CommissionLedger::class)->due($technicien->fresh()))->toBe(10_000)
        ->and(Payment::query()->where('ticket_id', $ticket->id)->value('status'))
        ->toBe(PaymentStatus::CONFIRMEE);
});

it('réserve la confirmation au client', function (): void {
    [$ticket, , $technicien] = interventionEspeces();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-especes")->assertCreated();

    // Le technicien ne peut pas confirmer à la place du client.
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-especes/confirmer")->assertStatus(422);
});

it('refuse de déclarer un paiement avant la fin de l’intervention', function (): void {
    [$ticket, , $technicien] = interventionEspeces();
    $ticket->forceFill(['state' => TicketState::EN_COURS->value])->save();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement-especes")->assertStatus(422);
});

it('bloque l’acceptation d’une demande quand la commission due atteint le plafond', function (): void {
    [, , $technicien] = interventionEspeces();

    // Dette au plafond (100 000).
    Transaction::query()->create([
        'user_id' => $technicien->id, 'type' => TransactionType::COMMISSION,
        'amount_gnf' => -100_000, 'balance_after_gnf' => -100_000,
        'description' => 'Commission due (test)',
    ]);

    // Une sollicitation en attente pour ce technicien.
    $ticket = ticketPublie();
    $tentative = MatchAttempt::query()->create([
        'ticket_id' => $ticket->id, 'technician_id' => $technicien->id, 'cycle' => 1,
        'radius_km' => 5, 'position' => 1, 'score' => 0.8, 'distance_km' => 1.0,
        'response' => MatchResponse::EN_ATTENTE->value,
        'notified_at' => now(), 'expires_at' => now()->addSeconds(45),
    ]);

    expect(fn () => app(RespondToMatch::class)->accepter($tentative, $technicien))
        ->toThrow(DomainException::class, 'commission à régler');
});

it('débloque le technicien quand il règle sa commission', function (): void {
    [, , $technicien] = interventionEspeces();

    Transaction::query()->create([
        'user_id' => $technicien->id, 'type' => TransactionType::COMMISSION,
        'amount_gnf' => -100_000, 'balance_after_gnf' => -100_000,
        'description' => 'Commission due (test)',
    ]);

    Sanctum::actingAs($technicien);
    $this->postJson('/api/v1/portefeuille/regler-commission', ['montant_gnf' => 100_000])
        ->assertOk()
        ->assertJsonPath('commission_due_gnf', 0);

    expect(app(CommissionLedger::class)->estBloque($technicien->fresh()))->toBeFalse();
});

it('les gains Mobile Money compensent une dette d’espèces', function (): void {
    [, , $technicien] = interventionEspeces();

    // Mobile Money : gain 100 000, commission retenue 10 000 → net 90 000 tenu.
    Transaction::query()->create(['user_id' => $technicien->id, 'type' => TransactionType::EARNING, 'amount_gnf' => 100_000, 'balance_after_gnf' => 100_000, 'description' => 'MoMo']);
    Transaction::query()->create(['user_id' => $technicien->id, 'type' => TransactionType::COMMISSION, 'amount_gnf' => -10_000, 'balance_after_gnf' => 90_000, 'description' => 'MoMo commission']);
    // Espèces : commission due 10 000.
    Transaction::query()->create(['user_id' => $technicien->id, 'type' => TransactionType::COMMISSION, 'amount_gnf' => -10_000, 'balance_after_gnf' => 80_000, 'description' => 'Espèces commission']);

    // Solde 80 000 positif : aucune dette nette, le technicien n'est pas bloqué.
    expect(app(CommissionLedger::class)->due($technicien))->toBe(0)
        ->and(app(CommissionLedger::class)->estBloque($technicien))->toBeFalse();
});

it('expose la commission due et le blocage dans le portefeuille', function (): void {
    [, , $technicien] = interventionEspeces();

    Transaction::query()->create([
        'user_id' => $technicien->id, 'type' => TransactionType::COMMISSION,
        'amount_gnf' => -120_000, 'balance_after_gnf' => -120_000, 'description' => 'Commission due (test)',
    ]);

    Sanctum::actingAs($technicien);
    $this->getJson('/api/v1/portefeuille')
        ->assertOk()
        ->assertJsonPath('commission_due_gnf', 120_000)
        ->assertJsonPath('bloque', true);
});
