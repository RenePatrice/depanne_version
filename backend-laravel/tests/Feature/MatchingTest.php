<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\UserStatus;
use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use App\Domain\Catalog\Models\TechnicianServiceRate;
use App\Domain\Matching\Actions\ListAvailableTechnicians;
use App\Domain\Matching\Actions\RecalculateTechnicianStats;
use App\Domain\Matching\Actions\RespondToMatch;
use App\Domain\Matching\Actions\SelectTechnician;
use App\Domain\Matching\Data\Candidat;
use App\Domain\Matching\Data\MatchResponse;
use App\Domain\Matching\Jobs\HandleMatchTimeoutJob;
use App\Domain\Matching\Models\MatchAttempt;
use App\Domain\Matching\Services\MatchScorer;
use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Notifications\Models\AppNotification;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Geo;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Place de marché (#2, décision du 5 novembre 2026)
|--------------------------------------------------------------------------
|
| Le système ne pousse plus un technicien à la fois : le client voit les
| techniciens disponibles — chacun avec son prix, sa note, sa distance — et en
| choisit un, qui dispose de 45 secondes pour confirmer. Ce que ces tests
| protègent : la présélection (en ligne, validé, spécialité, rayon, libre), le
| prix calculé au tarif du technicien, l'unicité de la sélection vivante, et le
| fait qu'un refus ou une expiration rouvre la demande sans re-solliciter.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);
    Queue::fake();
});

/** Technicien en ligne, validé, positionné à la distance voulue du client. */
function technicienA(float $lat, float $lng, array $attributs = []): User
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

    return $user->fresh();
}

/** @return array<int, array<string, mixed>> */
function disponibles(Ticket $ticket): array
{
    return app(ListAvailableTechnicians::class)->execute($ticket);
}

function choisir(Ticket $ticket, int $technicienId): MatchAttempt
{
    return app(SelectTechnician::class)->execute($ticket, $ticket->client, $technicienId);
}

// ------------------------------------------------------------- présélection --

it('liste les techniciens disponibles avec leur prix', function (): void {
    technicienA(9.596, -13.641);
    technicienA(9.597, -13.642);

    $liste = disponibles(ticketPublie());

    expect($liste)->toHaveCount(2)
        ->and($liste[0])->toHaveKeys(['technicien_id', 'nom', 'note', 'distance_km', 'eta_min', 'prix_total_gnf', 'prix_formate']);
});

it('classe le plus proche d’abord à profil équivalent', function (): void {
    $loin = technicienA(9.620, -13.665);
    $proche = technicienA(9.596, -13.641);

    $liste = disponibles(ticketPublie());

    expect($liste[0]['technicien_id'])->toBe($proche->id)
        ->and($loin->id)->not->toBe($proche->id);
});

it('calcule le prix au tarif du technicien, pas au catalogue', function (): void {
    $technicien = technicienA(9.596, -13.641);
    $ticket = ticketPublie();
    $service = Service::query()->find($ticket->service_id);

    // Tarif propre, dans les bornes, supérieur au catalogue.
    $tarif = (int) round($service->base_price_gnf * 1.2);
    TechnicianServiceRate::query()->create([
        'user_id' => $technicien->id, 'service_id' => $service->id, 'price_gnf' => $tarif,
    ]);

    $liste = disponibles($ticket);

    // Le total inclut le tarif de la prestation (+ majoration de proximité).
    expect($liste[0]['prix_total_gnf'])->toBeGreaterThanOrEqual($tarif);
});

it('écarte un technicien hors ligne', function (): void {
    technicienA(9.596, -13.641, ['is_online' => false]);

    expect(disponibles(ticketPublie()))->toBe([]);
});

it('écarte un technicien dont le dossier n’est pas validé', function (): void {
    technicienA(9.596, -13.641, ['verification_status' => VerificationStatus::EN_ATTENTE_VALIDATION]);

    expect(disponibles(ticketPublie()))->toBe([]);
});

it('écarte un technicien sans la bonne spécialité', function (): void {
    technicienA(9.596, -13.641, ['specialties' => ['MENUISERIE']]);

    expect(disponibles(ticketPublie()))->toBe([]);
});

it('écarte un technicien déjà sur une intervention', function (): void {
    $occupe = technicienA(9.596, -13.641);

    $enCours = ticketPublie();
    $enCours->forceFill(['technician_id' => $occupe->id, 'state' => TicketState::EN_ROUTE->value])->save();

    expect(disponibles(ticketPublie()))->toBe([]);
});

it('ignore une position périmée et retombe sur le point de rattachement', function (): void {
    technicienA(9.596, -13.641, [
        'last_position_at' => now()->subHours(2),
        'base_location' => Geo::point(9.95, -13.20),
    ]);

    expect(disponibles(ticketPublie()))->toBe([]);
});

it('inclut un technicien plus loin, dans le rayon de couverture', function (): void {
    technicienA(9.667, -13.640); // ~8 km : hors des 3 km inclus, dans le rayon de 15 km

    expect(disponibles(ticketPublie()))->toHaveCount(1);
});

it('ne liste personne quand aucun technicien n’est disponible', function (): void {
    expect(disponibles(ticketPublie()))->toBe([]);
});

// -------------------------------------------------------------------- score --

it('classe d’abord sur la proximité, à note et taux égaux', function (): void {
    $scoring = app(MatchScorer::class);

    $proche = new Candidat(1, 'Proche', 1.0, 4.5, 0.9, 0.05, 20, 9.59, -13.64);
    $loin = new Candidat(2, 'Loin', 4.0, 4.5, 0.9, 0.05, 20, 9.62, -13.66);

    expect($scoring->noter($proche, 5)['score'])
        ->toBeGreaterThan($scoring->noter($loin, 5)['score']);
});

it('pénalise un fort taux d’annulation', function (): void {
    $scoring = app(MatchScorer::class);

    $fiable = new Candidat(1, 'Fiable', 2.0, 4.5, 0.9, 0.00, 20, 9.59, -13.64);
    $volage = new Candidat(2, 'Volage', 2.0, 4.5, 0.9, 0.80, 20, 9.59, -13.64);

    expect($scoring->noter($fiable, 5)['score'])
        ->toBeGreaterThan($scoring->noter($volage, 5)['score']);
});

it('donne une note neutre au nouveau venu plutôt que zéro', function (): void {
    $scoring = app(MatchScorer::class);

    $nouveau = new Candidat(1, 'Nouveau', 2.0, 0.0, 1.0, 0.0, 2, 9.59, -13.64);

    expect($scoring->noter($nouveau, 5)['detail']['note'])->toBe(0.8);
});

// --------------------------------------------------------------- sélection --

it('le client choisit un technicien et lui ouvre une fenêtre de réponse', function (): void {
    $technicien = technicienA(9.596, -13.641);
    $ticket = ticketPublie();

    $tentative = choisir($ticket, $technicien->id);

    expect($tentative->response)->toBe(MatchResponse::EN_ATTENTE)
        ->and((int) $tentative->technician_id)->toBe($technicien->id)
        ->and($tentative->expires_at->isFuture())->toBeTrue();
});

it('n’ouvre pas de seconde sélection tant que la première est vivante', function (): void {
    $a = technicienA(9.596, -13.641);
    $b = technicienA(9.597, -13.642);
    $ticket = ticketPublie();

    choisir($ticket, $a->id);

    expect(fn () => choisir($ticket, $b->id))->toThrow(DomainException::class);
    expect(MatchAttempt::query()->where('ticket_id', $ticket->id)->count())->toBe(1);
});

it('ne re-sollicite jamais un technicien déjà sollicité', function (): void {
    $technicien = technicienA(9.596, -13.641);
    $ticket = ticketPublie();

    $tentative = choisir($ticket, $technicien->id);
    app(RespondToMatch::class)->refuser($tentative, $technicien);

    // Il n'apparaît plus dans la liste, et on ne peut pas le re-choisir.
    expect(disponibles($ticket))->toBe([])
        ->and(fn () => choisir($ticket, $technicien->id))->toThrow(DomainException::class);
});

it('refuse un technicien devenu indisponible entre la liste et le choix', function (): void {
    $technicien = technicienA(9.596, -13.641);
    $ticket = ticketPublie();

    $technicien->technicianProfile->forceFill(['is_online' => false])->save();

    expect(fn () => choisir($ticket, $technicien->id))
        ->toThrow(DomainException::class, 'plus disponible');
});

// --------------------------------------------------------------- attribution --

it('attribue le ticket et fige son prix au tarif du technicien', function (): void {
    $technicien = technicienA(9.658, -13.640); // ~7 km : déplacement facturable
    $ticket = ticketPublie();
    $service = Service::query()->find($ticket->service_id);

    $tarif = (int) round($service->base_price_gnf * 1.1);
    TechnicianServiceRate::query()->create([
        'user_id' => $technicien->id, 'service_id' => $service->id, 'price_gnf' => $tarif,
    ]);

    $tentative = choisir($ticket, $technicien->id);
    $ticket = app(RespondToMatch::class)->accepter($tentative, $technicien);

    expect($ticket->state->etat())->toBe(TicketState::ACCEPTEE)
        ->and($ticket->technician_id)->toBe($technicien->id)
        ->and($ticket->accepted_at)->not->toBeNull()
        ->and($ticket->base_price_gnf)->toBe($tarif)
        ->and($ticket->travel_fee_gnf)->toBeGreaterThan(0)
        ->and($ticket->priceIsCoherent())->toBeTrue();
});

it('conserve la majoration de proximité quand le technicien est tout près', function (): void {
    $technicien = technicienA(9.596, -13.641);
    $ticket = ticketPublie();

    $ticket = app(RespondToMatch::class)->accepter(choisir($ticket, $technicien->id), $technicien);

    expect($ticket->travel_fee_gnf)->toBe(0)
        ->and($ticket->short_trip_uplift_gnf)->toBeGreaterThan(0);
});

it('refuse une seconde attribution du même ticket', function (): void {
    $premier = technicienA(9.596, -13.641);
    $second = technicienA(9.597, -13.642);
    $ticket = ticketPublie();

    $tentatives = collect([$premier, $second])->map(fn (User $t, int $i): MatchAttempt => MatchAttempt::query()->create([
        'ticket_id' => $ticket->id, 'technician_id' => $t->id, 'cycle' => 1,
        'radius_km' => 5, 'position' => $i + 1, 'score' => 0.8, 'distance_km' => 1.0,
        'response' => MatchResponse::EN_ATTENTE->value,
        'notified_at' => now(), 'expires_at' => now()->addSeconds(45),
    ]));

    app(RespondToMatch::class)->accepter($tentatives[0], $premier);

    expect(fn () => app(RespondToMatch::class)->accepter($tentatives[1], $second))
        ->toThrow(DomainException::class);

    expect($ticket->fresh()->technician_id)->toBe($premier->id);
});

it('clôt les sollicitations restées ouvertes après une attribution', function (): void {
    $gagnant = technicienA(9.596, -13.641);
    $autre = technicienA(9.597, -13.642);
    $ticket = ticketPublie();

    $perdante = MatchAttempt::query()->create([
        'ticket_id' => $ticket->id, 'technician_id' => $autre->id, 'cycle' => 1,
        'radius_km' => 5, 'position' => 1, 'score' => 0.7, 'distance_km' => 1.0,
        'response' => MatchResponse::EN_ATTENTE->value, 'notified_at' => now(), 'expires_at' => now()->addSeconds(45),
    ]);
    $gagnante = MatchAttempt::query()->create([
        'ticket_id' => $ticket->id, 'technician_id' => $gagnant->id, 'cycle' => 1,
        'radius_km' => 5, 'position' => 2, 'score' => 0.8, 'distance_km' => 1.0,
        'response' => MatchResponse::EN_ATTENTE->value, 'notified_at' => now(), 'expires_at' => now()->addSeconds(45),
    ]);

    app(RespondToMatch::class)->accepter($gagnante, $gagnant);

    expect($perdante->fresh()->response)->toBe(MatchResponse::ANNULE);
});

it('refuse une réponse adressée à quelqu’un d’autre', function (): void {
    $destinataire = technicienA(9.596, -13.641);
    $intrus = technicienA(9.597, -13.642);
    $ticket = ticketPublie();

    $tentative = choisir($ticket, $destinataire->id);

    expect(fn () => app(RespondToMatch::class)->accepter($tentative, $intrus))
        ->toThrow(DomainException::class, 'Cette demande ne t\'est pas adressée.');
});

it('refuse une réponse après expiration de la fenêtre', function (): void {
    $technicien = technicienA(9.596, -13.641);
    $ticket = ticketPublie();

    $tentative = choisir($ticket, $technicien->id);
    $tentative->forceFill(['expires_at' => now()->subSecond()])->save();

    expect(fn () => app(RespondToMatch::class)->accepter($tentative, $technicien))
        ->toThrow(DomainException::class, 'Le délai de réponse est dépassé.');
});

// --------------------------------------------------------- refus / expiration --

it('rouvre la demande à la sélection quand le technicien refuse', function (): void {
    $technicien = technicienA(9.596, -13.641);
    $ticket = ticketPublie();

    $tentative = choisir($ticket, $technicien->id);
    app(RespondToMatch::class)->refuser($tentative, $technicien, 'Trop loin');

    expect($tentative->fresh()->response)->toBe(MatchResponse::REFUSE)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::PUBLIEE)
        ->and(AppNotification::query()
            ->where('notifiable_id', $ticket->client_id)
            ->where('type', NotificationType::TECHNICIEN_INDISPONIBLE->value)
            ->exists())->toBeTrue();
});

it('rouvre la demande quand la fenêtre expire sans réponse', function (): void {
    $technicien = technicienA(9.596, -13.641);
    $ticket = ticketPublie();

    $tentative = choisir($ticket, $technicien->id);

    (new HandleMatchTimeoutJob($tentative->id))
        ->handle(app(SendNotification::class), app(RecalculateTechnicianStats::class));

    expect($tentative->fresh()->response)->toBe(MatchResponse::EXPIRE)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::PUBLIEE);
});
