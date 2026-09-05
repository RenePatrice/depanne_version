<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use App\Domain\Disputes\Actions\ResolveDispute;
use App\Domain\Disputes\Data\DisputePriority;
use App\Domain\Disputes\Data\DisputeReason;
use App\Domain\Disputes\Data\DisputeStatus;
use App\Domain\Disputes\Models\Dispute;
use App\Domain\Payments\Data\PaymentMethod;
use App\Domain\Reporting\Services\FinanceService;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Wallet\Actions\ProcessWithdrawal;
use App\Domain\Wallet\Data\TransactionType;
use App\Domain\Wallet\Data\WithdrawalStatus;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Withdrawal;
use App\Support\Geo;
use Database\Seeders\Demo\AdminUserSeeder;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Illuminate\Database\Eloquent\Model;

/*
 * Cette phase touche directement à l'argent. Un retrait payé deux fois, un
 * remboursement qui ne reprend rien au technicien, un solde qui passe sous zéro :
 * ce sont des erreurs qui se soldent en francs, pas en tickets de bug.
 */

beforeEach(function (): void {
    $this->seed(AppSettingsSeeder::class);
    $this->seed(AdminUserSeeder::class);
    $this->seed(CatalogSeeder::class);

    $this->admin = adminAvecRole('ADMIN');
    $this->retraits = app(ProcessWithdrawal::class);
    $this->litiges = app(ResolveDispute::class);
    $this->finances = app(FinanceService::class);
});

/** Crédite un technicien et renvoie son compte. */
function technicienAvecSolde(int $montant): User
{
    $technicien = User::factory()->technician()->create();

    Transaction::query()->create([
        'user_id' => $technicien->id,
        'type' => TransactionType::EARNING,
        'amount_gnf' => $montant,
        'balance_after_gnf' => $montant,
        'description' => 'Crédit initial de test',
    ]);

    return $technicien;
}

function demandeDeRetrait(User $technicien, int $montant, WithdrawalStatus $statut = WithdrawalStatus::EN_ATTENTE): Withdrawal
{
    return Withdrawal::query()->create([
        'reference' => 'RET-TEST-'.str_pad((string) (Withdrawal::query()->count() + 1), 4, '0', STR_PAD_LEFT),
        'technician_id' => $technicien->id,
        'amount_gnf' => $montant,
        'mobile_money_number' => '+224620000000',
        'provider' => PaymentMethod::ORANGE_MONEY,
        'status' => $statut,
        'requested_at' => now()->subHour(),
    ]);
}

function ticketRegle(int $total, int $commission): Ticket
{
    $client = User::factory()->create();
    $technicien = technicienAvecSolde($total - $commission);

    return Model::unguarded(fn (): Ticket => Ticket::query()->create([
        'reference' => 'DM-LIT-'.str_pad((string) (Ticket::query()->count() + 1), 4, '0', STR_PAD_LEFT),
        'client_id' => $client->id,
        'technician_id' => $technicien->id,
        'service_id' => Service::query()->value('id'),
        'state' => TicketState::PAYEE,
        'address_snapshot' => ['formatted_address' => 'Kipé, Ratoma, Conakry'],
        'location' => Geo::point(9.598, -13.643),
        'base_price_gnf' => $total - 15_000,
        'travel_fee_gnf' => 15_000,
        'extra_fee_gnf' => 0,
        'total_gnf' => $total,
        'commission_gnf' => $commission,
        'technician_net_gnf' => $total - $commission,
        'commission_rate' => 0.10,
    ]));
}

function litigeSur(Ticket $ticket): Dispute
{
    return Dispute::query()->create([
        'reference' => 'LIT-TEST-'.str_pad((string) (Dispute::query()->count() + 1), 4, '0', STR_PAD_LEFT),
        'ticket_id' => $ticket->id,
        'opened_by' => $ticket->client_id,
        'reason' => DisputeReason::TRAVAIL_NON_CONFORME,
        'description' => 'La fuite est revenue dès le lendemain matin.',
        'status' => DisputeStatus::OUVERT,
        'priority' => DisputePriority::HAUTE,
        'sla_due_at' => now()->addHours(24),
    ]);
}

// -------------------------------------------------------------------- retraits --

it('n\'écrit le mouvement de portefeuille qu\'au versement effectif', function (): void {
    $technicien = technicienAvecSolde(500_000);
    $retrait = demandeDeRetrait($technicien, 200_000);

    $this->retraits->approuver($retrait, $this->admin->id);

    // Approuver, c'est décider : le solde ne doit pas encore bouger.
    expect($retrait->fresh()->status)->toBe(WithdrawalStatus::APPROUVE)
        ->and(Transaction::balanceFor($technicien->id))->toBe(500_000);

    $this->retraits->marquerPaye($retrait->fresh(), $this->admin->id, 'OM-123456');

    expect($retrait->fresh()->status)->toBe(WithdrawalStatus::PAYE)
        ->and(Transaction::balanceFor($technicien->id))->toBe(300_000);
});

it('refuse d\'approuver un retrait que le solde ne couvre pas', function (): void {
    $technicien = technicienAvecSolde(100_000);
    $retrait = demandeDeRetrait($technicien, 250_000);

    expect(fn () => $this->retraits->approuver($retrait, $this->admin->id))
        ->toThrow(DomainException::class);

    expect($retrait->fresh()->status)->toBe(WithdrawalStatus::EN_ATTENTE);
});

it('revérifie le solde au moment du versement', function (): void {
    $technicien = technicienAvecSolde(300_000);
    $retrait = demandeDeRetrait($technicien, 250_000);

    $this->retraits->approuver($retrait, $this->admin->id);

    // Entre l'approbation et le versement, un litige reprend de l'argent.
    Transaction::query()->create([
        'user_id' => $technicien->id,
        'type' => TransactionType::REFUND,
        'amount_gnf' => -200_000,
        'balance_after_gnf' => 100_000,
        'description' => 'Reprise sur litige',
    ]);

    expect(fn () => $this->retraits->marquerPaye($retrait->fresh(), $this->admin->id))
        ->toThrow(DomainException::class);

    expect(Transaction::balanceFor($technicien->id))->toBe(100_000)
        ->and($retrait->fresh()->status)->toBe(WithdrawalStatus::APPROUVE);
});

it('refuse de verser deux fois le même retrait', function (): void {
    $technicien = technicienAvecSolde(500_000);
    $retrait = demandeDeRetrait($technicien, 100_000);

    $this->retraits->approuver($retrait, $this->admin->id);
    $this->retraits->marquerPaye($retrait->fresh(), $this->admin->id);

    expect(fn () => $this->retraits->marquerPaye($retrait->fresh(), $this->admin->id))
        ->toThrow(DomainException::class);

    expect(Transaction::balanceFor($technicien->id))->toBe(400_000);
});

it('rejette une demande avec son motif', function (): void {
    $technicien = technicienAvecSolde(500_000);
    $retrait = demandeDeRetrait($technicien, 100_000);

    $this->retraits->rejeter($retrait, 'Numéro Mobile Money non conforme.', $this->admin->id);

    expect($retrait->fresh()->status)->toBe(WithdrawalStatus::REJETE)
        ->and($retrait->fresh()->note)->toBe('Numéro Mobile Money non conforme.')
        ->and(Transaction::balanceFor($technicien->id))->toBe(500_000);
});

it('exige un motif pour rejeter depuis le back-office', function (): void {
    $technicien = technicienAvecSolde(500_000);
    $retrait = demandeDeRetrait($technicien, 100_000);

    $this->actingAs($this->admin, 'admin')
        ->post(route('retraits.decider', $retrait), ['decision' => 'rejeter', 'note' => 'non'])
        ->assertSessionHasErrors('decision');

    expect($retrait->fresh()->status)->toBe(WithdrawalStatus::EN_ATTENTE);
});

// --------------------------------------------------------------------- litiges --

it('rembourse en écrivant deux mouvements symétriques', function (): void {
    $ticket = ticketRegle(200_000, 20_000);
    $litige = litigeSur($ticket);

    $soldeAvant = Transaction::balanceFor($ticket->technician_id);

    $this->litiges->resoudre($litige, 'REMBOURSEMENT_PARTIEL', 'Travail à reprendre en partie.', 60_000, $this->admin->id);

    // Le client est crédité, le technicien est débité du même montant :
    // le grand livre reste équilibré.
    expect(Transaction::balanceFor($ticket->client_id))->toBe(60_000)
        ->and(Transaction::balanceFor($ticket->technician_id))->toBe($soldeAvant - 60_000)
        ->and($litige->fresh()->status)->toBe(DisputeStatus::RESOLU)
        ->and($litige->fresh()->refund_gnf)->toBe(60_000);
});

it('refuse un remboursement supérieur au montant payé', function (): void {
    $ticket = ticketRegle(200_000, 20_000);
    $litige = litigeSur($ticket);

    expect(fn () => $this->litiges->resoudre($litige, 'REMBOURSEMENT_PARTIEL', 'Motivation suffisante.', 500_000, $this->admin->id))
        ->toThrow(DomainException::class);

    expect(Transaction::balanceFor($ticket->client_id))->toBe(0);
});

it('exige la totalité du montant pour un remboursement total', function (): void {
    $ticket = ticketRegle(200_000, 20_000);
    $litige = litigeSur($ticket);

    expect(fn () => $this->litiges->resoudre($litige, 'REMBOURSEMENT_TOTAL', 'Motivation suffisante.', 100_000, $this->admin->id))
        ->toThrow(DomainException::class);

    $this->litiges->resoudre($litige, 'REMBOURSEMENT_TOTAL', 'Intervention entièrement ratée.', 200_000, $this->admin->id);

    expect($litige->fresh()->refund_gnf)->toBe(200_000);
});

it('refuse de trancher deux fois le même litige', function (): void {
    $litige = litigeSur(ticketRegle(200_000, 20_000));

    $this->litiges->resoudre($litige, 'AUCUNE_ACTION', 'Rien à reprocher au technicien.', 0, $this->admin->id);

    expect(fn () => $this->litiges->resoudre($litige->fresh(), 'AVERTISSEMENT', 'Deuxième décision.', 0, $this->admin->id))
        ->toThrow(DomainException::class);
});

it('garde les notes internes hors de la vue des parties', function (): void {
    $litige = litigeSur(ticketRegle(200_000, 20_000));

    $this->litiges->ecrire($litige, 'Message au client.', 'CLIENT', false, $this->admin->id);
    $this->litiges->ecrire($litige, 'Le technicien a déjà deux litiges.', 'LES_DEUX', true, $this->admin->id);

    $visibles = $litige->messages()->visible()->get();

    expect($litige->messages()->count())->toBe(2)
        ->and($visibles)->toHaveCount(1)
        ->and($visibles->first()->content)->toBe('Message au client.');
});

it('trie la file des litiges par échéance de traitement', function (): void {
    $tardif = litigeSur(ticketRegle(100_000, 10_000));
    $tardif->forceFill(['sla_due_at' => now()->subHours(5)])->save();

    $recent = litigeSur(ticketRegle(100_000, 10_000));
    $recent->forceFill(['sla_due_at' => now()->addHours(20)])->save();

    $reponse = $this->actingAs($this->admin, 'admin')->get(route('litiges'))->assertOk();

    $contenu = $reponse->getContent();

    // Celui qui est hors délai doit apparaître avant l'autre.
    expect(strpos($contenu, $tardif->reference))->toBeLessThan(strpos($contenu, $recent->reference));
});

// -------------------------------------------------------------------- finances --

it('déduit les retraits approuvés du montant dû', function (): void {
    $technicien = technicienAvecSolde(400_000);
    $retrait = demandeDeRetrait($technicien, 150_000);

    expect($this->finances->totalDu())->toBe(400_000);

    $this->retraits->approuver($retrait, $this->admin->id);

    // Approuvé donc engagé : il ne doit plus compter comme disponible.
    expect($this->finances->totalDu())->toBe(250_000);
});

it('affiche l\'écran des finances avec ses chiffres', function (): void {
    ticketRegle(200_000, 20_000);

    $this->actingAs($this->admin, 'admin')
        ->get(route('finances'))
        ->assertOk()
        ->assertSee('Commissions plateforme')
        ->assertSee('Montants dus aux techniciens')
        ->assertSee('data-graphique="colonnes"', escape: false);
});

it('exporte la comptabilité de la période', function (): void {
    ticketRegle(200_000, 20_000);
    ticketRegle(150_000, 15_000);

    $reponse = $this->actingAs($this->admin, 'admin')
        ->get(route('finances.export', ['format' => 'csv', 'periode' => '30j']));

    $reponse->assertOk()->assertDownload();

    $contenu = $reponse->streamedContent();

    expect($contenu)->toContain('Commission (GNF)')
        ->and($contenu)->toContain('20000')
        ->and(substr_count(trim($contenu), "\n"))->toBe(2); // en-tête + 2 lignes
});

it('réserve les écrans financiers à ADMIN et FINANCE', function (): void {
    $support = adminAvecRole('SUPPORT');
    $finance = adminAvecRole('FINANCE');

    $this->actingAs($finance, 'admin')->get(route('finances'))->assertOk();
    $this->actingAs($finance, 'admin')->get(route('retraits'))->assertOk();
    $this->actingAs($finance, 'admin')->get(route('litiges'))->assertForbidden();

    $this->actingAs($support, 'admin')->get(route('litiges'))->assertOk();
    $this->actingAs($support, 'admin')->get(route('finances'))->assertForbidden();
    $this->actingAs($support, 'admin')->get(route('retraits'))->assertForbidden();
});
