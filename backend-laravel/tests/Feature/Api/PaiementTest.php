<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Payments\Data\PaymentMethod;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Providers\MockPaymentProvider;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Wallet\Data\TransactionType;
use App\Domain\Wallet\Data\WithdrawalStatus;
use App\Domain\Wallet\Jobs\ReleaseEscrowJob;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Withdrawal;
use App\Domain\Wallet\Services\EscrowService;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| Paiement, séquestre et portefeuille (§8.4)
|--------------------------------------------------------------------------
|
| Le cahier des charges impose une couverture des webhooks — signature,
| idempotence, rejeu — et de la répartition financière. C'est la partie du
| code où une erreur ne se rattrape pas : l'argent est parti.
|
| Tout tourne sur le pilote simulé : aucun compte marchand n'est ouvert, et
| c'est justement l'intérêt d'avoir mis l'opérateur derrière une interface.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);

    // La libération du séquestre part en file avec un délai de 24 heures. Le
    // pilote `sync` des tests ignore les délais et l'exécuterait aussitôt :
    // le séquestre n'existerait jamais, et c'est précisément ce qu'on veut
    // observer. Les tests qui portent sur la libération jouent le job à la
    // main, comme le ferait le worker.
    Queue::fake();
});

/** Intervention terminée, prête à être payée. */
function interventionTerminee(int $total = 100_000): array
{
    $ticket = ticketPublie();

    $technicien = User::factory()->technician()->create();
    TechnicianProfile::query()->updateOrCreate(['user_id' => $technicien->id], [
        'specialties' => ['PLOMBERIE'],
        'verification_status' => VerificationStatus::VALIDE,
        'is_online' => true,
    ]);

    $commission = (int) round($total * 0.10);

    $ticket->forceFill([
        'technician_id' => $technicien->id,
        'state' => TicketState::TERMINEE->value,
        'total_gnf' => $total,
        'base_price_gnf' => $total,
        'short_trip_uplift_gnf' => 0,
        'travel_fee_gnf' => 0,
        'extra_fee_gnf' => 0,
        'commission_gnf' => $commission,
        'technician_net_gnf' => $total - $commission,
        'commission_rate' => 0.10,
        'completed_at' => now()->subMinutes(5),
    ])->save();

    return [$ticket->fresh(), $ticket->client, $technicien];
}

/** Appelle le webhook avec une signature valide. */
function notifier(array $corps, ?string $signature = null): TestResponse
{
    $json = json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return test()->call(
        'POST',
        '/api/v1/paiements/webhook',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DEPANNE_SIGNATURE' => $signature ?? (new MockPaymentProvider)->signer((string) $json),
        ],
        (string) $json,
    );
}

// ------------------------------------------------------------- encaissement --

it('lance un paiement et le laisse en attente', function (): void {
    [$ticket, $client] = interventionTerminee();

    Sanctum::actingAs($client);

    $reponse = $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")
        ->assertCreated()
        ->assertJsonPath('paiement.statut', 'EN_ATTENTE');

    // Le ticket ne bouge pas tant que l'opérateur n'a pas confirmé.
    expect($ticket->fresh()->state->etat())->toBe(TicketState::TERMINEE)
        ->and($reponse->json('paiement.reference'))->toStartWith('SIM-');
});

it('refuse de payer une intervention pas encore terminée', function (): void {
    [$ticket, $client] = interventionTerminee();
    $ticket->forceFill(['state' => TicketState::EN_COURS->value])->save();

    Sanctum::actingAs($client);

    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->assertStatus(422);
});

it('renvoie le paiement en cours plutôt que d’en ouvrir un second', function (): void {
    [$ticket, $client] = interventionTerminee();

    Sanctum::actingAs($client);

    $un = $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.reference');
    $deux = $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.reference');

    expect($un)->toBe($deux)->and(Payment::query()->count())->toBe(1);
});

it('ne laisse pas un tiers payer l’intervention d’un autre', function (): void {
    [$ticket] = interventionTerminee();

    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->assertStatus(422);
});

// ------------------------------------------------------------------ webhook --

it('rejette une notification sans signature', function (): void {
    $this->postJson('/api/v1/paiements/webhook', ['reference' => 'SIM-X', 'statut' => 'SUCCESS'])
        ->assertStatus(401);
});

it('rejette une notification dont la signature ne correspond pas', function (): void {
    notifier(['reference' => 'SIM-X', 'statut' => 'SUCCESS'], 'signature-bidon')->assertStatus(401);
});

it('ignore une référence inconnue sans la créer', function (): void {
    notifier(['reference' => 'SIM-INEXISTANTE', 'statut' => 'SUCCESS'])
        ->assertOk()
        ->assertJsonPath('traite', false);

    expect(Payment::query()->count())->toBe(0);
});

it('capture le paiement et fait passer le ticket en payé', function (): void {
    [$ticket, $client] = interventionTerminee();

    Sanctum::actingAs($client);
    $reference = $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.reference');

    notifier(['reference' => $reference, 'statut' => 'SUCCESS', 'montant_gnf' => 100_000])
        ->assertOk()
        ->assertJsonPath('traite', true);

    expect(Payment::query()->firstOrFail()->status)->toBe(PaymentStatus::CAPTUREE)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::PAYEE)
        ->and($ticket->fresh()->paid_at)->not->toBeNull();
});

it('ne rejoue pas une notification déjà traitée', function (): void {
    [$ticket, $client] = interventionTerminee();

    Sanctum::actingAs($client);
    $reference = $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.reference');

    $corps = ['reference' => $reference, 'statut' => 'SUCCESS', 'montant_gnf' => 100_000];

    notifier($corps)->assertOk()->assertJsonPath('traite', true);
    notifier($corps)->assertOk()->assertJsonPath('traite', false);

    // Un rejeu ne doit produire ni second mouvement, ni seconde transition.
    expect(Transaction::query()->count())->toBe(0)
        ->and($ticket->fresh()->events()->where('to_state', TicketState::PAYEE->value)->count())->toBe(1);
});

it('enregistre un échec sans toucher au ticket', function (): void {
    [$ticket, $client] = interventionTerminee();

    Sanctum::actingAs($client);
    $reference = $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.reference');

    notifier(['reference' => $reference, 'statut' => 'FAILED', 'motif' => 'Solde insuffisant'])
        ->assertOk()
        ->assertJsonPath('traite', true);

    expect(Payment::query()->firstOrFail()->status)->toBe(PaymentStatus::ECHOUEE)
        ->and(Payment::query()->firstOrFail()->failure_reason)->toBe('Solde insuffisant')
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::TERMINEE);
});

it('conserve la charge utile brute de l’opérateur', function (): void {
    [$ticket, $client] = interventionTerminee();

    Sanctum::actingAs($client);
    $reference = $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.reference');

    notifier(['reference' => $reference, 'statut' => 'SUCCESS', 'operateur_ref' => 'OM-778899'])->assertOk();

    $charge = json_encode(Payment::query()->firstOrFail()->webhook_payload);

    expect($charge)->toContain('OM-778899');
});

it('capture malgré un montant inattendu, mais le trace', function (): void {
    [$ticket, $client] = interventionTerminee();

    Sanctum::actingAs($client);
    $reference = $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.reference');

    notifier(['reference' => $reference, 'statut' => 'SUCCESS', 'montant_gnf' => 95_000])->assertOk();

    // L'argent est parti de chez le client : refuser le laisserait débité
    // sans intervention payée. L'écart est journalisé pour le support.
    expect(Payment::query()->firstOrFail()->status)->toBe(PaymentStatus::CAPTUREE)
        ->and(Activity::query()
            ->where('log_name', 'finances')->count())->toBeGreaterThan(0);
});

// ---------------------------------------------------------------- séquestre --

it('garde les fonds en séquestre après la capture', function (): void {
    [$ticket, $client] = interventionTerminee();

    Sanctum::actingAs($client);
    $reference = $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.reference');
    notifier(['reference' => $reference, 'statut' => 'SUCCESS'])->assertOk();

    // Capturé, mais rien n'est encore acquis au technicien.
    expect(Transaction::query()->count())->toBe(0)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::PAYEE);
});

it('libère les fonds quand le client valide, et répartit à l’entier près', function (): void {
    [$ticket, $client, $technicien] = interventionTerminee(100_000);

    Sanctum::actingAs($client);
    $reference = $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.reference');
    notifier(['reference' => $reference, 'statut' => 'SUCCESS'])->assertOk();

    $this->postJson("/api/v1/tickets/{$ticket->id}/valider")
        ->assertOk()
        ->assertJsonPath('libere', true);

    $mouvements = Transaction::query()->where('user_id', $technicien->id)->orderBy('id')->get();

    expect($mouvements)->toHaveCount(2)
        ->and($mouvements[0]->type)->toBe(TransactionType::EARNING)
        ->and($mouvements[0]->amount_gnf)->toBe(100_000)
        ->and($mouvements[1]->type)->toBe(TransactionType::COMMISSION)
        ->and($mouvements[1]->amount_gnf)->toBe(-10_000)
        ->and(Transaction::balanceFor($technicien->id))->toBe(90_000)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::CLOTUREE);
});

it('ne libère pas deux fois', function (): void {
    [$ticket, $client, $technicien] = interventionTerminee();

    Sanctum::actingAs($client);
    $reference = $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.reference');
    notifier(['reference' => $reference, 'statut' => 'SUCCESS'])->assertOk();

    $this->postJson("/api/v1/tickets/{$ticket->id}/valider")->assertOk();
    $this->postJson("/api/v1/tickets/{$ticket->id}/valider")->assertStatus(422);

    expect(Transaction::query()->where('user_id', $technicien->id)->count())->toBe(2);
});

it('libère automatiquement au bout du délai', function (): void {
    [$ticket, $client, $technicien] = interventionTerminee();

    Sanctum::actingAs($client);
    $reference = $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.reference');
    notifier(['reference' => $reference, 'statut' => 'SUCCESS'])->assertOk();

    // Le job différé, joué à la main comme le ferait le worker.
    $paiement = Payment::query()->firstOrFail();
    app(ReleaseEscrowJob::class, ['paiementId' => $paiement->id])->handle(app(EscrowService::class));

    expect(Transaction::balanceFor($technicien->id))->toBe(90_000)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::CLOTUREE);
});

it('suspend la libération tant qu’un litige est ouvert', function (): void {
    [$ticket, $client, $technicien] = interventionTerminee();

    Sanctum::actingAs($client);
    $reference = $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.reference');
    notifier(['reference' => $reference, 'statut' => 'SUCCESS'])->assertOk();

    $ticket->fresh()->forceFill(['state' => TicketState::LITIGE_OUVERT->value])->save();

    $this->postJson("/api/v1/tickets/{$ticket->id}/valider")
        ->assertOk()
        ->assertJsonPath('libere', false);

    expect(Transaction::balanceFor($technicien->id))->toBe(0);
});

// ------------------------------------------------------------- portefeuille --

it('affiche un solde égal à la somme des mouvements', function (): void {
    [$ticket, $client, $technicien] = interventionTerminee();

    Sanctum::actingAs($client);
    $reference = $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.reference');
    notifier(['reference' => $reference, 'statut' => 'SUCCESS'])->assertOk();
    $this->postJson("/api/v1/tickets/{$ticket->id}/valider")->assertOk();

    Sanctum::actingAs($technicien);

    $this->getJson('/api/v1/portefeuille')
        ->assertOk()
        ->assertJsonPath('solde_gnf', 90_000)
        ->assertJsonPath('retirable_gnf', 90_000)
        ->assertJsonCount(2, 'mouvements');
});

it('interdit le portefeuille à un client', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/portefeuille')->assertForbidden();
});

// ------------------------------------------------------------------ retraits --

it('accepte une demande de retrait sans toucher au solde', function (): void {
    [$ticket, $client, $technicien] = interventionTerminee();

    Sanctum::actingAs($client);
    $reference = $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.reference');
    notifier(['reference' => $reference, 'statut' => 'SUCCESS'])->assertOk();
    $this->postJson("/api/v1/tickets/{$ticket->id}/valider")->assertOk();

    Sanctum::actingAs($technicien);

    $this->postJson('/api/v1/retraits', [
        'montant_gnf' => 60_000,
        'numero' => '620112233',
        'operateur' => PaymentMethod::ORANGE_MONEY->value,
    ])->assertCreated()->assertJsonPath('retrait.statut', WithdrawalStatus::EN_ATTENTE->value);

    // Demander n'est pas être payé : le solde reste entier.
    expect(Transaction::balanceFor($technicien->id))->toBe(90_000);
});

it('retranche du retirable ce qui est déjà engagé', function (): void {
    [$ticket, $client, $technicien] = interventionTerminee();

    Sanctum::actingAs($client);
    $reference = $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.reference');
    notifier(['reference' => $reference, 'statut' => 'SUCCESS'])->assertOk();
    $this->postJson("/api/v1/tickets/{$ticket->id}/valider")->assertOk();

    Sanctum::actingAs($technicien);

    $corps = ['montant_gnf' => 60_000, 'numero' => '620112233', 'operateur' => PaymentMethod::ORANGE_MONEY->value];

    $this->postJson('/api/v1/retraits', $corps)->assertCreated();

    // 90 000 − 60 000 engagés : la seconde demande dépasse.
    $this->postJson('/api/v1/retraits', $corps)->assertStatus(422);

    $this->getJson('/api/v1/portefeuille')
        ->assertJsonPath('solde_gnf', 90_000)
        ->assertJsonPath('engage_gnf', 60_000)
        ->assertJsonPath('retirable_gnf', 30_000);
});

it('refuse un retrait sous le minimum', function (): void {
    [, , $technicien] = interventionTerminee();

    Sanctum::actingAs($technicien);

    $this->postJson('/api/v1/retraits', [
        'montant_gnf' => 1_000,
        'numero' => '620112233',
        'operateur' => PaymentMethod::ORANGE_MONEY->value,
    ])->assertStatus(422)->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'minimum'));
});

it('refuse un retrait supérieur au solde', function (): void {
    [, , $technicien] = interventionTerminee();

    Sanctum::actingAs($technicien);

    $this->postJson('/api/v1/retraits', [
        'montant_gnf' => 500_000,
        'numero' => '620112233',
        'operateur' => PaymentMethod::ORANGE_MONEY->value,
    ])->assertStatus(422);

    expect(Withdrawal::query()->count())->toBe(0);
});

it('suit le minimum de retrait défini en back-office', function (): void {
    AppSetting::put(AppSetting::WITHDRAWAL_MIN_GNF, 1_000);

    [$ticket, $client, $technicien] = interventionTerminee();

    Sanctum::actingAs($client);
    $reference = $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.reference');
    notifier(['reference' => $reference, 'statut' => 'SUCCESS'])->assertOk();
    $this->postJson("/api/v1/tickets/{$ticket->id}/valider")->assertOk();

    Sanctum::actingAs($technicien);

    $this->postJson('/api/v1/retraits', [
        'montant_gnf' => 2_000,
        'numero' => '620112233',
        'operateur' => PaymentMethod::ORANGE_MONEY->value,
    ])->assertCreated();

    AppSetting::put(AppSetting::WITHDRAWAL_MIN_GNF, 50_000);
});

it('normalise le numéro Mobile Money du retrait', function (): void {
    [$ticket, $client, $technicien] = interventionTerminee();

    Sanctum::actingAs($client);
    $reference = $this->postJson("/api/v1/tickets/{$ticket->id}/paiement")->json('paiement.reference');
    notifier(['reference' => $reference, 'statut' => 'SUCCESS'])->assertOk();
    $this->postJson("/api/v1/tickets/{$ticket->id}/valider")->assertOk();

    Sanctum::actingAs($technicien);

    $this->postJson('/api/v1/retraits', [
        'montant_gnf' => 60_000,
        'numero' => '620 11 22 33',
        'operateur' => PaymentMethod::ORANGE_MONEY->value,
    ])->assertCreated()->assertJsonPath('retrait.numero', '+224620112233');
});
