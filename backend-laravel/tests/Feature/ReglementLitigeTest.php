<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\User;
use App\Domain\Disputes\Actions\OpenDispute;
use App\Domain\Disputes\Actions\ResolveDispute;
use App\Domain\Disputes\Data\DisputePriority;
use App\Domain\Disputes\Data\DisputeReason;
use App\Domain\Disputes\Data\DisputeStatus;
use App\Domain\Disputes\Models\Dispute;
use App\Domain\Payments\Data\PaymentMethod;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Tickets\Actions\ForceCloseTicket;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Wallet\Data\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Services\EscrowService;
use Database\Seeders\Demo\AdminUserSeeder;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Règlement d'un litige (ADR-0041)
|--------------------------------------------------------------------------
|
| Avant ces tests, un litige tranché laissait le ticket en LITIGE_OUVERT et
| l'argent en séquestre pour toujours : le job de libération était passé
| pendant le gel et ne repassait jamais. Un remboursement reprenait en outre au
| technicien une somme qu'il n'avait jamais reçue.
|
| La règle retenue est le prorata : le remboursement sort avant la répartition,
| et la commission ne porte que sur ce qui reste facturé. Elle doit donner le
| même résultat que le litige arrive avant ou après la libération.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);
    $this->seed(AdminUserSeeder::class);

    Queue::fake();

    $this->admin = adminAvecRole('ADMIN');
    $this->litiges = app(ResolveDispute::class);
});

/** Ticket terminé à 100 000 GNF, commission de 10 %, avec son technicien. */
function ticketFacture(TicketState $etat, int $majoration = 0): Ticket
{
    $ticket = ticketPublie();
    $technicien = User::factory()->technician()->create();
    $total = 100_000 + $majoration;

    $ticket->forceFill([
        'technician_id' => $technicien->id,
        'state' => $etat->value,
        'base_price_gnf' => 100_000,
        'short_trip_uplift_gnf' => $majoration,
        'travel_fee_gnf' => 0,
        'extra_fee_gnf' => 0,
        'total_gnf' => $total,
        'commission_gnf' => 10_000,
        'technician_net_gnf' => $total - 10_000,
        'commission_rate' => 0.10,
        'completed_at' => now()->subHour(),
    ])->save();

    return $ticket->fresh();
}

function paiementDe(Ticket $ticket, PaymentStatus $statut): Payment
{
    return Payment::query()->create([
        'ticket_id' => $ticket->id,
        'provider' => 'mock',
        'provider_ref' => 'SIM-'.$ticket->id.'-'.$statut->value,
        'method' => PaymentMethod::ORANGE_MONEY,
        'payer_phone' => '+224620000000',
        'amount_gnf' => $ticket->total_gnf,
        'status' => $statut,
        'captured_at' => now()->subMinutes(30),
    ]);
}

function reclamationSur(Ticket $ticket): Dispute
{
    return Dispute::query()->create([
        'reference' => 'LIT-REG-'.str_pad((string) (Dispute::query()->count() + 1), 4, '0', STR_PAD_LEFT),
        'ticket_id' => $ticket->id,
        'opened_by' => $ticket->client_id,
        'reason' => DisputeReason::TRAVAIL_NON_CONFORME,
        'description' => 'La fuite est revenue dès le lendemain matin.',
        'status' => DisputeStatus::OUVERT,
        'priority' => DisputePriority::NORMALE,
        'sla_due_at' => now()->addDay(),
    ]);
}

/** @return array<string, int> */
function mouvementsDe(int $userId): array
{
    return Transaction::query()
        ->where('user_id', $userId)
        ->orderBy('id')
        ->get()
        ->mapWithKeys(fn (Transaction $t, int $i): array => [$i.':'.$t->type->value => $t->amount_gnf])
        ->all();
}

// ---------------------------------------------------------------- séquestre --

it('libère le séquestre au prorata d’un remboursement partiel', function (): void {
    $ticket = ticketFacture(TicketState::LITIGE_OUVERT);
    $paiement = paiementDe($ticket, PaymentStatus::CAPTUREE);
    $litige = reclamationSur($ticket);

    $this->litiges->resoudre($litige, 'REMBOURSEMENT_PARTIEL', 'Fuite reprise en partie seulement.', 40_000, $this->admin->id);

    // 60 000 restent facturés : 6 000 de commission, 54 000 au technicien.
    expect(Transaction::balanceFor($ticket->technician_id))->toBe(54_000)
        ->and(mouvementsDe($ticket->technician_id))->toBe([
            '0:'.TransactionType::EARNING->value => 60_000,
            '1:'.TransactionType::COMMISSION->value => -6_000,
        ])
        ->and(Transaction::balanceFor($ticket->client_id))->toBe(40_000)
        ->and($paiement->fresh()->status)->toBe(PaymentStatus::LIBEREE)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::CLOTUREE);
});

it('ne laisse aucun solde négatif sur un remboursement total', function (): void {
    $ticket = ticketFacture(TicketState::LITIGE_OUVERT);
    $paiement = paiementDe($ticket, PaymentStatus::CAPTUREE);

    $this->litiges->resoudre(reclamationSur($ticket), 'REMBOURSEMENT_TOTAL', 'Intervention entièrement ratée.', 100_000, $this->admin->id);

    // Ni gain ni commission : le technicien n'a rien facturé, la plateforme
    // ne prélève rien sur rien.
    expect(Transaction::balanceFor($ticket->technician_id))->toBe(0)
        ->and(mouvementsDe($ticket->technician_id))->toBe([])
        ->and(Transaction::balanceFor($ticket->client_id))->toBe(100_000)
        ->and($paiement->fresh()->status)->toBe(PaymentStatus::REMBOURSEE)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::CLOTUREE);
});

it('libère le séquestre en entier quand la réclamation est rejetée', function (): void {
    $ticket = ticketFacture(TicketState::LITIGE_OUVERT);
    paiementDe($ticket, PaymentStatus::CAPTUREE);

    $this->litiges->rejeter(reclamationSur($ticket), 'Rien à reprocher au technicien.', $this->admin->id);

    expect(Transaction::balanceFor($ticket->technician_id))->toBe(90_000)
        ->and(Transaction::balanceFor($ticket->client_id))->toBe(0)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::CLOTUREE);
});

it('libère aussi quand le litige se clôt sans remboursement', function (): void {
    $ticket = ticketFacture(TicketState::LITIGE_OUVERT);
    paiementDe($ticket, PaymentStatus::CAPTUREE);

    $this->litiges->resoudre(reclamationSur($ticket), 'AVERTISSEMENT', 'Retard signalé au technicien.', 0, $this->admin->id);

    expect(Transaction::balanceFor($ticket->technician_id))->toBe(90_000)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::CLOTUREE);
});

it('laisse la majoration de proximité hors de l’assiette après remboursement', function (): void {
    $ticket = ticketFacture(TicketState::LITIGE_OUVERT, majoration: 1_000);
    paiementDe($ticket, PaymentStatus::CAPTUREE);

    $this->litiges->resoudre(reclamationSur($ticket), 'REMBOURSEMENT_PARTIEL', 'Une partie du travail à reprendre.', 51_000, $this->admin->id);

    // 50 000 conservés dont 1 000 de majoration : la commission porte sur
    // 49 000, la majoration revient en entier au technicien.
    expect(Transaction::balanceFor($ticket->technician_id))->toBe(50_000 - 4_900);
});

it('ne relibère pas un séquestre déjà soldé par le litige', function (): void {
    $ticket = ticketFacture(TicketState::LITIGE_OUVERT);
    $paiement = paiementDe($ticket, PaymentStatus::CAPTUREE);

    $this->litiges->resoudre(reclamationSur($ticket), 'REMBOURSEMENT_PARTIEL', 'Fuite reprise en partie seulement.', 40_000, $this->admin->id);

    // Le job de libération différé peut encore passer : il ne doit rien écrire.
    expect(app(EscrowService::class)->liberer($paiement->fresh()))->toBeFalse()
        ->and(Transaction::balanceFor($ticket->technician_id))->toBe(54_000);
});

// ------------------------------------------------------------ déjà libéré --

it('aboutit au même partage quand les fonds étaient déjà libérés', function (): void {
    $ticket = ticketFacture(TicketState::LITIGE_OUVERT);
    $paiement = paiementDe($ticket, PaymentStatus::CAPTUREE);

    // Libération normale avant le litige : 100 000 de gain, 10 000 de commission.
    $ticket->forceFill(['state' => TicketState::PAYEE->value])->save();
    app(EscrowService::class)->liberer($paiement);
    expect(Transaction::balanceFor($ticket->technician_id))->toBe(90_000);

    $this->litiges->resoudre(reclamationSur($ticket->fresh()), 'REMBOURSEMENT_PARTIEL', 'Fuite reprise en partie seulement.', 40_000, $this->admin->id);

    // Même résultat qu'en séquestre : 54 000. Le technicien rend 40 000, la
    // plateforme lui restitue les 4 000 prélevés sur la part remboursée.
    expect(Transaction::balanceFor($ticket->technician_id))->toBe(54_000)
        ->and(Transaction::balanceFor($ticket->client_id))->toBe(40_000)
        ->and(Transaction::query()
            ->where('user_id', $ticket->technician_id)
            ->where('type', TransactionType::COMMISSION)
            ->where('amount_gnf', '>', 0)
            ->value('amount_gnf'))->toBe(4_000)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::CLOTUREE);
});

// --------------------------------------------------------- jamais encaissé --

it('refuse de rembourser ce qui n’a jamais été encaissé', function (): void {
    $ticket = ticketFacture(TicketState::LITIGE_OUVERT);
    $litige = reclamationSur($ticket);

    expect(fn () => $this->litiges->resoudre($litige, 'REMBOURSEMENT_PARTIEL', 'Une partie à reprendre.', 40_000, $this->admin->id))
        ->toThrow(DomainException::class, 'rien à rembourser');

    expect(Transaction::query()->count())->toBe(0)
        ->and($litige->fresh()->status)->toBe(DisputeStatus::OUVERT)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::LITIGE_OUVERT);
});

it('remet en attente de paiement un ticket tranché avant tout encaissement', function (): void {
    $ticket = ticketFacture(TicketState::LITIGE_OUVERT);

    $this->litiges->rejeter(reclamationSur($ticket), 'Travail conforme au devis.', $this->admin->id);

    // Le client doit encore payer : TERMINEE est l'état qui l'y invite.
    expect($ticket->fresh()->state->etat())->toBe(TicketState::TERMINEE);
});

it('n’accepte pas une seconde réclamation sur une intervention déjà arbitrée', function (): void {
    $ticket = ticketFacture(TicketState::LITIGE_OUVERT);
    $this->litiges->rejeter(reclamationSur($ticket), 'Travail conforme au devis.', $this->admin->id);

    $ticket = $ticket->fresh();

    expect(fn () => app(OpenDispute::class)->execute(
        $ticket,
        $ticket->client,
        DisputeReason::TRAVAIL_NON_CONFORME,
        'Je conteste encore une fois la même intervention.',
    ))->toThrow(DomainException::class, 'déjà été tranchée');

    expect($ticket->fresh()->state->etat())->toBe(TicketState::TERMINEE);
});

// -------------------------------------------------------- clôture forcée --

it('libère le séquestre en forçant la clôture d’un ticket payé', function (): void {
    $ticket = ticketFacture(TicketState::PAYEE);
    $paiement = paiementDe($ticket, PaymentStatus::CAPTUREE);

    app(ForceCloseTicket::class)->execute($ticket, 'Le client ne valide pas depuis trois jours.', $this->admin->id);

    expect(Transaction::balanceFor($ticket->technician_id))->toBe(90_000)
        ->and($paiement->fresh()->status)->toBe(PaymentStatus::LIBEREE)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::CLOTUREE);
});

it('refuse de forcer la clôture d’un ticket en litige', function (): void {
    $ticket = ticketFacture(TicketState::LITIGE_OUVERT);
    paiementDe($ticket, PaymentStatus::CAPTUREE);

    expect(fn () => app(ForceCloseTicket::class)->execute($ticket, 'Tentative de clôture en marge.', $this->admin->id))
        ->toThrow(DomainException::class, 'décision du litige');

    expect(Transaction::query()->count())->toBe(0)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::LITIGE_OUVERT);
});
