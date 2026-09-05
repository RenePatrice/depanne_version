<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\UserStatus;
use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Matching\Actions\RecalculateTechnicianStats;
use App\Domain\Matching\Actions\RespondToMatch;
use App\Domain\Matching\Actions\SolicitNextTechnician;
use App\Domain\Matching\Data\Candidat;
use App\Domain\Matching\Data\MatchResponse;
use App\Domain\Matching\Jobs\HandleMatchTimeoutJob;
use App\Domain\Matching\Models\MatchAttempt;
use App\Domain\Matching\Services\MatchScorer;
use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Geo;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Matching séquentiel (§8.3)
|--------------------------------------------------------------------------
|
| Ce que ces tests protègent : qu'un seul technicien soit sollicité à la fois,
| qu'aucun ticket ne soit attribué deux fois, que le prix se fige au bon
| moment, et que la recherche s'arrête proprement quand il n'y a personne.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);
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

function solliciter(): SolicitNextTechnician
{
    return app(SolicitNextTechnician::class);
}

// ------------------------------------------------------------- présélection --

it('ne sollicite qu’un seul technicien à la fois', function (): void {
    Queue::fake();

    technicienA(9.596, -13.641);
    technicienA(9.597, -13.642);
    technicienA(9.598, -13.643);

    $ticket = ticketPublie();

    solliciter()->execute($ticket);

    expect(MatchAttempt::query()->where('ticket_id', $ticket->id)->count())->toBe(1);
});

it('choisit le plus proche à profil équivalent', function (): void {
    Queue::fake();

    $loin = technicienA(9.620, -13.665);
    $proche = technicienA(9.596, -13.641);

    solliciter()->execute(ticketPublie());

    expect(MatchAttempt::query()->value('technician_id'))->toBe($proche->id)
        ->and($loin->id)->not->toBe($proche->id);
});

it('écarte un technicien hors ligne', function (): void {
    Queue::fake();

    technicienA(9.596, -13.641, ['is_online' => false]);

    solliciter()->execute(ticketPublie());

    expect(MatchAttempt::query()->count())->toBe(0);
});

it('écarte un technicien dont le dossier n’est pas validé', function (): void {
    Queue::fake();

    technicienA(9.596, -13.641, ['verification_status' => VerificationStatus::EN_ATTENTE_VALIDATION]);

    solliciter()->execute(ticketPublie());

    expect(MatchAttempt::query()->count())->toBe(0);
});

it('écarte un technicien sans la bonne spécialité', function (): void {
    Queue::fake();

    technicienA(9.596, -13.641, ['specialties' => ['MENUISERIE']]);

    solliciter()->execute(ticketPublie());

    expect(MatchAttempt::query()->count())->toBe(0);
});

it('écarte un technicien déjà sur une intervention', function (): void {
    Queue::fake();

    $occupe = technicienA(9.596, -13.641);

    $enCours = ticketPublie();
    $enCours->forceFill([
        'technician_id' => $occupe->id,
        'state' => TicketState::EN_ROUTE->value,
    ])->save();

    solliciter()->execute(ticketPublie());

    expect(MatchAttempt::query()->count())->toBe(0);
});

it('ignore une position périmée et retombe sur le point de rattachement', function (): void {
    Queue::fake();

    // Dernière position à 100 m mais vieille de deux heures, base à 40 km.
    technicienA(9.596, -13.641, [
        'last_position_at' => now()->subHours(2),
        'base_location' => Geo::point(9.95, -13.20),
    ]);

    solliciter()->execute(ticketPublie());

    expect(MatchAttempt::query()->count())->toBe(0);
});

it('élargit le rayon quand personne n’est proche', function (): void {
    Queue::fake();

    // ~8 km du client : hors du premier cycle (5 km), dans le deuxième (10 km).
    technicienA(9.667, -13.640);

    solliciter()->execute(ticketPublie());

    $tentative = MatchAttempt::query()->firstOrFail();

    expect($tentative->cycle)->toBe(2)->and($tentative->radius_km)->toBe(10);
});

it('conclut à l’absence de technicien après tous les cycles', function (): void {
    Queue::fake();

    $ticket = ticketPublie();

    solliciter()->execute($ticket);

    expect($ticket->fresh()->state->etat())->toBe(TicketState::SANS_REPONSE)
        ->and(MatchAttempt::query()->count())->toBe(0);
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

    // Note réelle à 0 faute d'avis, mais deux interventions seulement.
    $nouveau = new Candidat(1, 'Nouveau', 2.0, 0.0, 1.0, 0.0, 2, 9.59, -13.64);

    $detail = $scoring->noter($nouveau, 5);

    // 4,0 sur 5 = 0,8 : le nouveau venu reste sollicitable.
    expect($detail['detail']['note'])->toBe(0.8);
});

it('conserve le détail du score pour pouvoir l’expliquer', function (): void {
    Queue::fake();

    technicienA(9.596, -13.641);
    solliciter()->execute(ticketPublie());

    $detail = MatchAttempt::query()->value('score_breakdown');

    expect($detail)->toHaveKeys(['proximite', 'proximite_apport', 'note', 'acceptation', 'annulation']);
});

// --------------------------------------------------------------- attribution --

it('attribue le ticket et fige son prix à l’acceptation', function (): void {
    Queue::fake();

    // Technicien à ~7 km : au-delà du seuil de 3 km, le déplacement devient
    // facturable et le total dépasse l'estimation de proximité.
    $technicien = technicienA(9.658, -13.640);
    $ticket = ticketPublie();

    solliciter()->execute($ticket);

    $tentative = MatchAttempt::query()->firstOrFail();
    $ticket = app(RespondToMatch::class)->accepter($tentative, $technicien);

    expect($ticket->state->etat())->toBe(TicketState::ACCEPTEE)
        ->and($ticket->technician_id)->toBe($technicien->id)
        ->and($ticket->accepted_at)->not->toBeNull()
        ->and($ticket->short_trip_uplift_gnf)->toBe(0)
        ->and($ticket->travel_fee_gnf)->toBeGreaterThan(0)
        ->and($ticket->priceIsCoherent())->toBeTrue();
});

it('conserve la majoration de proximité quand le technicien est tout près', function (): void {
    Queue::fake();

    $technicien = technicienA(9.596, -13.641);
    $ticket = ticketPublie();

    solliciter()->execute($ticket);

    $ticket = app(RespondToMatch::class)->accepter(MatchAttempt::query()->firstOrFail(), $technicien);

    expect($ticket->travel_fee_gnf)->toBe(0)
        ->and($ticket->short_trip_uplift_gnf)->toBe(850);
});

it('refuse une seconde attribution du même ticket', function (): void {
    Queue::fake();

    $premier = technicienA(9.596, -13.641);
    $second = technicienA(9.597, -13.642);
    $ticket = ticketPublie();

    // Deux sollicitations ouvertes de force : c'est la situation que le verrou
    // et la mise à jour conditionnelle doivent rendre inoffensive.
    $tentatives = collect([$premier, $second])->map(fn (User $t, int $i): MatchAttempt => MatchAttempt::query()->create([
        'ticket_id' => $ticket->id,
        'technician_id' => $t->id,
        'cycle' => 1,
        'radius_km' => 5,
        'position' => $i + 1,
        'score' => 0.8,
        'distance_km' => 1.0,
        'response' => MatchResponse::EN_ATTENTE->value,
        'notified_at' => now(),
        'expires_at' => now()->addSeconds(45),
    ]));

    app(RespondToMatch::class)->accepter($tentatives[0], $premier);

    expect(fn () => app(RespondToMatch::class)->accepter($tentatives[1], $second))
        ->toThrow(DomainException::class);

    expect($ticket->fresh()->technician_id)->toBe($premier->id);
});

it('clôt les sollicitations restées ouvertes après une attribution', function (): void {
    Queue::fake();

    $gagnant = technicienA(9.596, -13.641);
    $autre = technicienA(9.597, -13.642);
    $ticket = ticketPublie();

    $perdante = MatchAttempt::query()->create([
        'ticket_id' => $ticket->id, 'technician_id' => $autre->id, 'cycle' => 1,
        'radius_km' => 5, 'position' => 1, 'score' => 0.7, 'distance_km' => 1.0,
        'response' => MatchResponse::EN_ATTENTE->value,
        'notified_at' => now(), 'expires_at' => now()->addSeconds(45),
    ]);

    $gagnante = MatchAttempt::query()->create([
        'ticket_id' => $ticket->id, 'technician_id' => $gagnant->id, 'cycle' => 1,
        'radius_km' => 5, 'position' => 2, 'score' => 0.8, 'distance_km' => 1.0,
        'response' => MatchResponse::EN_ATTENTE->value,
        'notified_at' => now(), 'expires_at' => now()->addSeconds(45),
    ]);

    app(RespondToMatch::class)->accepter($gagnante, $gagnant);

    expect($perdante->fresh()->response)->toBe(MatchResponse::ANNULE);
});

it('refuse une réponse adressée à quelqu’un d’autre', function (): void {
    Queue::fake();

    $destinataire = technicienA(9.596, -13.641);
    $intrus = technicienA(9.597, -13.642);
    $ticket = ticketPublie();

    solliciter()->execute($ticket);

    expect(fn () => app(RespondToMatch::class)->accepter(MatchAttempt::query()->firstOrFail(), $intrus))
        ->toThrow(DomainException::class, 'Cette demande ne t\'est pas adressée.');

    expect($destinataire->id)->not->toBe($intrus->id);
});

it('refuse une réponse après expiration de la fenêtre', function (): void {
    Queue::fake();

    $technicien = technicienA(9.596, -13.641);
    $ticket = ticketPublie();

    solliciter()->execute($ticket);

    $tentative = MatchAttempt::query()->firstOrFail();
    $tentative->forceFill(['expires_at' => now()->subSecond()])->save();

    expect(fn () => app(RespondToMatch::class)->accepter($tentative, $technicien))
        ->toThrow(DomainException::class, 'Le délai de réponse est dépassé.');
});

// ------------------------------------------------------------------- relance --

it('passe au suivant dès qu’un technicien refuse', function (): void {
    Queue::fake();

    $premier = technicienA(9.596, -13.641);
    $second = technicienA(9.600, -13.645);
    $ticket = ticketPublie();

    solliciter()->execute($ticket);

    $tentative = MatchAttempt::query()->firstOrFail();
    app(RespondToMatch::class)->refuser($tentative, $tentative->technician, 'Trop loin');

    $tentatives = MatchAttempt::query()->orderBy('id')->get();

    expect($tentatives)->toHaveCount(2)
        ->and($tentatives[0]->response)->toBe(MatchResponse::REFUSE)
        ->and($tentatives[1]->response)->toBe(MatchResponse::EN_ATTENTE)
        ->and($tentatives[1]->technician_id)->not->toBe($tentatives[0]->technician_id)
        ->and([$premier->id, $second->id])->toContain($tentatives[1]->technician_id);
});

it('ne sollicite jamais deux fois le même technicien pour un ticket', function (): void {
    Queue::fake();

    $seul = technicienA(9.596, -13.641);
    $ticket = ticketPublie();

    solliciter()->execute($ticket);

    $tentative = MatchAttempt::query()->firstOrFail();
    app(RespondToMatch::class)->refuser($tentative, $seul);

    // Personne d'autre n'existe : la recherche doit s'arrêter, pas boucler.
    expect(MatchAttempt::query()->count())->toBe(1)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::SANS_REPONSE);
});

it('n’ouvre pas de seconde sollicitation tant que la première est vivante', function (): void {
    Queue::fake();

    technicienA(9.596, -13.641);
    technicienA(9.597, -13.642);
    $ticket = ticketPublie();

    solliciter()->execute($ticket);
    solliciter()->execute($ticket);   // job dupliqué par la file

    expect(MatchAttempt::query()->count())->toBe(1);
});

it('ne sollicite plus personne une fois le ticket annulé', function (): void {
    Queue::fake();

    technicienA(9.596, -13.641);
    $ticket = ticketPublie();
    $ticket->forceFill(['state' => TicketState::ANNULEE_CLIENT->value])->save();

    solliciter()->execute($ticket->fresh());

    expect(MatchAttempt::query()->count())->toBe(0);
});

// ----------------------------------------------------------------- expiration --

it('expire une fenêtre sans réponse et relance la recherche', function (): void {
    Queue::fake();

    technicienA(9.596, -13.641);
    technicienA(9.600, -13.645);
    $ticket = ticketPublie();

    solliciter()->execute($ticket);
    $tentative = MatchAttempt::query()->firstOrFail();

    app(HandleMatchTimeoutJob::class, ['tentativeId' => $tentative->id])->handle(
        app(SolicitNextTechnician::class),
        app(SendNotification::class),
        app(RecalculateTechnicianStats::class),
    );

    expect($tentative->fresh()->response)->toBe(MatchResponse::EXPIRE)
        ->and(MatchAttempt::query()->count())->toBe(2);
});

it('n’écrase pas une réponse déjà donnée quand le job d’expiration rejoue', function (): void {
    Queue::fake();

    $technicien = technicienA(9.596, -13.641);
    $ticket = ticketPublie();

    solliciter()->execute($ticket);
    $tentative = MatchAttempt::query()->firstOrFail();

    app(RespondToMatch::class)->accepter($tentative, $technicien);

    app(HandleMatchTimeoutJob::class, ['tentativeId' => $tentative->id])->handle(
        app(SolicitNextTechnician::class),
        app(SendNotification::class),
        app(RecalculateTechnicianStats::class),
    );

    expect($tentative->fresh()->response)->toBe(MatchResponse::ACCEPTE)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::ACCEPTEE);
});

// ------------------------------------------------------------- configuration --

it('suit le nombre de cycles défini en back-office', function (): void {
    Queue::fake();

    AppSetting::put(AppSetting::MATCH_MAX_CYCLES, 1);

    // Technicien à ~8 km : atteignable au cycle 2, qui n'existe plus.
    technicienA(9.667, -13.640);
    $ticket = ticketPublie();

    solliciter()->execute($ticket);

    expect(MatchAttempt::query()->count())->toBe(0)
        ->and($ticket->fresh()->state->etat())->toBe(TicketState::SANS_REPONSE);

    AppSetting::put(AppSetting::MATCH_MAX_CYCLES, 3);
});

it('suit le délai de réponse défini en back-office', function (): void {
    Queue::fake();

    AppSetting::put(AppSetting::MATCH_RESPONSE_SECONDS, 90);

    technicienA(9.596, -13.641);
    solliciter()->execute(ticketPublie());

    $tentative = MatchAttempt::query()->firstOrFail();
    $fenetre = (int) $tentative->notified_at->diffInSeconds($tentative->expires_at);

    expect($fenetre)->toBeGreaterThanOrEqual(89)->toBeLessThanOrEqual(91);

    AppSetting::put(AppSetting::MATCH_RESPONSE_SECONDS, 45);
});
