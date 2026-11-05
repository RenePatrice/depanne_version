<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\UserStatus;
use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Matching\Actions\ReassignTicket;
use App\Domain\Matching\Actions\RespondToMatch;
use App\Domain\Matching\Actions\SolicitNextTechnician;
use App\Domain\Matching\Data\MatchResponse;
use App\Domain\Matching\Models\MatchAttempt;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Notifications\Models\AppNotification;
use App\Domain\Pricing\Services\PricingService;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Geo;
use Database\Seeders\Demo\AdminUserSeeder;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Réassignation par le support (ADR-0040)
|--------------------------------------------------------------------------
|
| Le technicien a accepté puis ne vient pas. Le client garde sa demande, le
| matching reprend sans lui, et le prix qu'il avait fixé depuis sa position
| redevient une estimation.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);
    $this->seed(AdminUserSeeder::class);

    Queue::fake();

    $this->admin = adminAvecRole('ADMIN');
});

function technicienDisponible(float $lat, float $lng): User
{
    $user = User::factory()->technician()->create(['status' => UserStatus::ACTIF]);

    TechnicianProfile::query()->updateOrCreate(['user_id' => $user->id], [
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
        'cancellation_rate' => 0.0,
    ]);

    return $user->fresh();
}

/**
 * Ticket accepté par le premier technicien sollicité, un second restant
 * disponible.
 *
 * @return array{Ticket, User, User} ticket, technicien retenu, technicien restant
 */
function ticketAttribue(): array
{
    $proche = technicienDisponible(9.5955, -13.6405);
    $loin = technicienDisponible(9.610, -13.655);

    $ticket = ticketPublie();
    $tentative = app(SolicitNextTechnician::class)->execute($ticket);

    $retenu = (int) $tentative->technician_id === $proche->id ? $proche : $loin;
    $restant = $retenu->is($proche) ? $loin : $proche;

    app(RespondToMatch::class)->accepter($tentative, $retenu);

    return [$ticket->fresh(), $retenu, $restant];
}

it('remet le ticket en recherche et sollicite un autre technicien', function (): void {
    [$ticket, $retenu, $restant] = ticketAttribue();

    app(ReassignTicket::class)->execute($ticket, 'Technicien injoignable depuis vingt minutes.', false, $this->admin->id);

    $ticket = $ticket->fresh();
    $enAttente = MatchAttempt::query()
        ->where('ticket_id', $ticket->id)
        ->where('response', MatchResponse::EN_ATTENTE->value)
        ->sole();

    expect($ticket->state->etat())->toBe(TicketState::PUBLIEE)
        ->and($ticket->technician_id)->toBeNull()
        ->and($ticket->accepted_at)->toBeNull()
        ->and((int) $enAttente->technician_id)->toBe($restant->id);
});

it('ne resollicite jamais le technicien retiré', function (): void {
    [$ticket, $retenu, $restant] = ticketAttribue();

    // Le seul autre technicien disparaît : la recherche doit s'arrêter plutôt
    // que de revenir vers celui qu'on vient de retirer.
    $restant->technicianProfile->forceFill(['is_online' => false])->save();

    app(ReassignTicket::class)->execute($ticket, 'Technicien injoignable depuis vingt minutes.', false, $this->admin->id);

    expect(MatchAttempt::query()->where('ticket_id', $ticket->id)->where('technician_id', $retenu->id)->count())->toBe(1)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::SANS_REPONSE);
});

it('ramène le prix à l’estimation tant que personne n’a réaccepté', function (): void {
    [$ticket] = ticketAttribue();

    app(ReassignTicket::class)->execute($ticket, 'Panne de moto signalée par le technicien.', false, $this->admin->id);

    $ticket = $ticket->fresh()->load('zone');
    $estimation = app(PricingService::class)->reestimation($ticket);

    expect($ticket->total_gnf)->toBe($estimation->totalGnf)
        // Le prix de la prestation reste celui de l'instantané (ADR-0029).
        ->and($ticket->base_price_gnf)->toBe(85_000);
});

it('impute le désistement au technicien quand le support le décide', function (): void {
    [$ticket, $retenu] = ticketAttribue();

    app(ReassignTicket::class)->execute($ticket, 'Ne s’est jamais mis en route.', true, $this->admin->id);

    $tentative = MatchAttempt::query()->where('ticket_id', $ticket->id)->where('technician_id', $retenu->id)->sole();

    expect($tentative->response)->toBe(MatchResponse::DESISTE)
        ->and((float) $retenu->technicianProfile->fresh()->cancellation_rate)->toBe(1.0)
        // Il avait accepté : son taux d'acceptation n'est pas puni une seconde fois.
        ->and((float) $retenu->technicianProfile->fresh()->acceptance_rate)->toBe(1.0);
});

it('n’impute rien sans décision explicite du support', function (): void {
    [$ticket, $retenu] = ticketAttribue();

    app(ReassignTicket::class)->execute($ticket, 'Accident de la route, rien à lui reprocher.', false, $this->admin->id);

    expect(MatchAttempt::query()->where('ticket_id', $ticket->id)->where('technician_id', $retenu->id)->sole()->response)
        ->toBe(MatchResponse::ACCEPTE)
        ->and((float) $retenu->technicianProfile->fresh()->cancellation_rate)->toBe(0.0);
});

it('prévient le client et le technicien retiré', function (): void {
    [$ticket, $retenu] = ticketAttribue();

    app(ReassignTicket::class)->execute($ticket, 'Technicien injoignable depuis vingt minutes.', false, $this->admin->id);

    expect(AppNotification::query()
        ->where('notifiable_id', $ticket->client_id)
        ->where('type', NotificationType::TECHNICIEN_REMPLACE->value)
        ->exists())->toBeTrue()
        ->and(AppNotification::query()
            ->where('notifiable_id', $retenu->id)
            ->where('type', NotificationType::DEMANDE_RETIREE->value)
            ->exists())->toBeTrue();
});

it('refuse de réassigner une fois le technicien sur place', function (): void {
    [$ticket] = ticketAttribue();
    $ticket->forceFill(['state' => TicketState::SUR_PLACE->value])->save();

    expect(fn () => app(ReassignTicket::class)->execute($ticket->fresh(), 'Trop tard pour remplacer.', false, $this->admin->id))
        ->toThrow(DomainException::class, 'sur place');
});

it('réassigne depuis la fiche ticket du back-office', function (): void {
    [$ticket] = ticketAttribue();

    $this->actingAs($this->admin, 'admin')
        ->post(route('tickets.reassigner', $ticket), [
            'motif' => 'Technicien injoignable depuis vingt minutes.',
            'impute_au_technicien' => '1',
        ])
        ->assertRedirect(route('tickets.detail', $ticket))
        ->assertSessionHasNoErrors();

    expect($ticket->fresh()->state->etat())->toBe(TicketState::PUBLIEE);
});

it('réserve la réassignation aux rôles qui peuvent agir sur un ticket', function (): void {
    [$ticket] = ticketAttribue();

    $this->actingAs(adminAvecRole('FINANCE'), 'admin')
        ->post(route('tickets.reassigner', $ticket), ['motif' => 'Tentative sans permission.'])
        ->assertForbidden();

    expect($ticket->fresh()->state->etat())->toBe(TicketState::ACCEPTEE);
});

it('affiche les actions réassigner et rembourser sur la fiche ticket', function (): void {
    [$ticket] = ticketAttribue();

    $this->actingAs($this->admin, 'admin')
        ->get(route('tickets.detail', $ticket))
        ->assertOk()
        ->assertSee('Confirmer la réassignation')
        ->assertSee('aucune réclamation en cours sur ce ticket')
        ->assertDontSee('phase C3')
        ->assertDontSee('phase C5');
});
