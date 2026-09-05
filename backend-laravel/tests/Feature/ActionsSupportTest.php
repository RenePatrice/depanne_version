<?php

declare(strict_types=1);

use App\Domain\Accounts\Actions\ReviewTechnicianApplication;
use App\Domain\Accounts\Actions\SetUserStatus;
use App\Domain\Accounts\Data\UserStatus;
use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use App\Domain\Tickets\Actions\CancelTicketByAdmin;
use App\Domain\Tickets\Actions\ForceCloseTicket;
use App\Domain\Tickets\Actions\TransitionTicket;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Geo;
use Database\Seeders\Demo\CatalogSeeder;
use Illuminate\Database\Eloquent\Model;

/*
 * Les actions support touchent à l'argent et à la réputation : une transition
 * illégale fausserait la comptabilité, une suspension au mauvais moment
 * laisserait un client seul face à un technicien en route.
 */

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
});

function ticket(TicketState $etat, ?User $technicien = null): Ticket
{
    $client = User::factory()->create();
    $technicien ??= User::factory()->technician()->create();

    return Model::unguarded(fn (): Ticket => Ticket::query()->create([
        'reference' => 'DM-TEST-'.str_pad((string) (Ticket::query()->count() + 1), 6, '0', STR_PAD_LEFT),
        'client_id' => $client->id,
        'technician_id' => $technicien->id,
        'service_id' => Service::query()->value('id'),
        'state' => $etat->value,
        'address_snapshot' => ['formatted_address' => 'Kipé, Ratoma, Conakry'],
        'location' => Geo::point(9.598, -13.643),
        'base_price_gnf' => 85_000,
        'travel_fee_gnf' => 15_000,
        'extra_fee_gnf' => 0,
        'total_gnf' => 100_000,
    ]));
}

it('refuse une transition que la machine à états interdit', function (): void {
    $ticket = ticket(TicketState::CLOTUREE);

    expect(fn () => app(TransitionTicket::class)->execute($ticket, TicketState::EN_COURS))
        ->toThrow(DomainException::class);

    expect($ticket->fresh()->state->etat())->toBe(TicketState::CLOTUREE);
});

it('journalise chaque transition légale et horodate son jalon', function (): void {
    $ticket = ticket(TicketState::ACCEPTEE);

    expect($ticket->en_route_at)->toBeNull();

    app(TransitionTicket::class)->execute($ticket, TicketState::EN_ROUTE);

    $ticket->refresh();

    expect($ticket->state->etat())->toBe(TicketState::EN_ROUTE)
        ->and($ticket->en_route_at)->not->toBeNull()
        ->and($ticket->events()->count())->toBe(1)
        ->and($ticket->events()->first()->from_state)->toBe(TicketState::ACCEPTEE);
});

it('ne réécrit pas un jalon déjà horodaté', function (): void {
    $ticket = ticket(TicketState::PUBLIEE);

    app(TransitionTicket::class)->execute($ticket, TicketState::ACCEPTEE);
    $premier = $ticket->fresh()->accepted_at;

    // Un aller-retour ne doit pas effacer la date d'origine.
    app(TransitionTicket::class)->execute($ticket->fresh(), TicketState::EN_ROUTE);

    expect($ticket->fresh()->accepted_at?->timestamp)->toBe($premier?->timestamp);
});

it('annule un ticket et impute l\'annulation à la bonne partie', function (): void {
    $ticket = ticket(TicketState::EN_ROUTE);

    app(CancelTicketByAdmin::class)->execute($ticket, 'Le technicien ne répond plus.', true, 1);

    $ticket->refresh();

    expect($ticket->state->etat())->toBe(TicketState::ANNULEE_TECHNICIEN)
        ->and($ticket->cancellation_reason)->toBe('Le technicien ne répond plus.');
});

it('refuse d\'annuler un ticket déjà terminé', function (): void {
    $ticket = ticket(TicketState::TERMINEE);

    expect(fn () => app(CancelTicketByAdmin::class)->execute($ticket, 'Trop tard.', false, 1))
        ->toThrow(DomainException::class);
});

it('force la clôture d\'un ticket payé', function (): void {
    $ticket = ticket(TicketState::PAYEE);

    app(ForceCloseTicket::class)->execute($ticket, 'Le client ne valide pas depuis trois jours.', 1);

    expect($ticket->fresh()->state->etat())->toBe(TicketState::CLOTUREE)
        ->and($ticket->fresh()->closed_at)->not->toBeNull();
});

it('refuse de suspendre un compte qui a une intervention en cours', function (): void {
    $technicien = User::factory()->technician()->create();
    ticket(TicketState::EN_COURS, $technicien);

    expect(fn () => app(SetUserStatus::class)->execute($technicien, UserStatus::SUSPENDU, null, 1))
        ->toThrow(DomainException::class);

    expect($technicien->fresh()->status)->toBe(UserStatus::ACTIF);
});

it('met un technicien suspendu hors ligne immédiatement', function (): void {
    $technicien = User::factory()->technician()->create();

    TechnicianProfile::query()->create([
        'user_id' => $technicien->id,
        'specialties' => ['PLOMBERIE'],
        'verification_status' => VerificationStatus::VALIDE,
        'is_online' => true,
    ]);

    app(SetUserStatus::class)->execute($technicien, UserStatus::SUSPENDU, 'Trois annulations tardives.', 1);

    expect($technicien->fresh()->status)->toBe(UserStatus::SUSPENDU)
        ->and(TechnicianProfile::query()->find($technicien->id)?->is_online)->toBeFalse();
});

it('approuve un dossier et le fait entrer dans le matching', function (): void {
    $profil = profilEnAttente();

    app(ReviewTechnicianApplication::class)->approuver($profil, 7);

    $profil->refresh();

    expect($profil->verification_status)->toBe(VerificationStatus::VALIDE)
        ->and($profil->verified_at)->not->toBeNull()
        ->and($profil->verified_by)->toBe(7)
        ->and($profil->verification_status->canWork())->toBeTrue();
});

it('rejette un dossier avec son motif et le sort du matching', function (): void {
    $profil = profilEnAttente();
    $profil->forceFill(['is_online' => true])->save();

    app(ReviewTechnicianApplication::class)->rejeter($profil, "Pièce d'identité illisible sur le recto.", 7);

    $profil->refresh();

    expect($profil->verification_status)->toBe(VerificationStatus::REJETE)
        ->and($profil->rejection_reason)->toBe("Pièce d'identité illisible sur le recto.")
        ->and($profil->is_online)->toBeFalse();
});

it('refuse de trancher deux fois le même dossier', function (): void {
    $profil = profilEnAttente();

    app(ReviewTechnicianApplication::class)->approuver($profil, 7);

    expect(fn () => app(ReviewTechnicianApplication::class)->approuver($profil->fresh(), 7))
        ->toThrow(DomainException::class);
});

function profilEnAttente(): TechnicianProfile
{
    $technicien = User::factory()->technician()->create();

    return TechnicianProfile::query()->create([
        'user_id' => $technicien->id,
        'specialties' => ['PLOMBERIE'],
        'verification_status' => VerificationStatus::EN_ATTENTE_VALIDATION,
    ]);
}
