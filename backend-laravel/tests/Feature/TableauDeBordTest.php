<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use App\Domain\Matching\Data\MatchResponse;
use App\Domain\Reporting\Data\Periode;
use App\Domain\Reporting\Services\DashboardService;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Zones\Models\Zone;
use App\Support\Geo;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\AdminUserSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/*
 * Le tableau de bord est ce que la direction regardera pour décider. Un chiffre
 * faux y coûte plus cher qu'un bug d'affichage : ces tests fixent l'arithmétique
 * sur un jeu de données construit à la main, valeur par valeur.
 */

beforeEach(function (): void {
    $this->seed(AdminUserSeeder::class);
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);

    $this->service = app(DashboardService::class);
});

/** Crée un ticket aux valeurs entièrement maîtrisées. */
function ticketDeTest(TicketState $etat, CarbonImmutable $creeLe, int $total, ?int $commission = null): Ticket
{
    $client = User::factory()->create();
    $technicien = User::factory()->technician()->create();

    // Les horodatages ne sont pas assignables en masse : c'est voulu en
    // production, mais une fixture doit pouvoir dater ses tickets. Les seeders
    // de Laravel s'appuient sur le meme mecanisme.
    return Model::unguarded(fn (): Ticket => Ticket::query()->create([
        'reference' => 'DM-TEST-'.str_pad((string) Ticket::query()->count(), 6, '0', STR_PAD_LEFT),
        'client_id' => $client->id,
        'technician_id' => $technicien->id,
        'service_id' => Service::query()->value('id'),
        'zone_id' => Zone::query()->value('id'),
        'state' => $etat,
        'address_snapshot' => ['formatted_address' => 'Kipé, Ratoma, Conakry'],
        'location' => Geo::point(9.598, -13.643),
        'base_price_gnf' => $total - 15_000,
        'travel_fee_gnf' => 15_000,
        'extra_fee_gnf' => 0,
        'total_gnf' => $total,
        'commission_gnf' => $commission,
        'technician_net_gnf' => $commission === null ? null : $total - $commission,
        'commission_rate' => $commission === null ? null : 0.10,
        'published_at' => $creeLe,
        'accepted_at' => $creeLe->addSeconds(60),
        'created_at' => $creeLe,
        'updated_at' => $creeLe,
    ]));
}

it('additionne le chiffre d\'affaires et les commissions de la période', function (): void {
    $hier = CarbonImmutable::now()->subDay()->setTime(10, 0);

    ticketDeTest(TicketState::CLOTUREE, $hier, 100_000, 10_000);
    ticketDeTest(TicketState::PAYEE, $hier, 200_000, 20_000);
    // Un ticket annulé ne porte pas de chiffre d'affaires.
    ticketDeTest(TicketState::ANNULEE_CLIENT, $hier, 500_000);
    // Un ticket hors période ne doit pas être compté.
    ticketDeTest(TicketState::CLOTUREE, CarbonImmutable::now()->subDays(60), 999_000, 99_900);

    $indicateurs = collect($this->service->indicateurs(Periode::derniersJours(7)))->keyBy('cle');

    expect($indicateurs['chiffre-affaires']['valeur'])->toBe(300_000)
        ->and($indicateurs['commissions']['valeur'])->toBe(30_000)
        ->and($indicateurs['demandes']['valeur'])->toBe(3);
});

it('calcule le taux d\'acceptation sur les seules sollicitations arbitrées', function (): void {
    $ticket = ticketDeTest(TicketState::CLOTUREE, CarbonImmutable::now()->subHours(3), 100_000, 10_000);
    $technicien = User::factory()->technician()->create();

    $sollicitation = static function (MatchResponse $reponse) use ($ticket, $technicien): void {
        static $cycle = 0;
        $cycle++;

        DB::table('match_attempts')->insert([
            'ticket_id' => $ticket->id,
            'technician_id' => $technicien->id,
            'cycle' => $cycle,
            'radius_km' => 5,
            'position' => $cycle,
            'score' => 0.8,
            'distance_km' => 2.5,
            'response' => $reponse->value,
            'notified_at' => now()->subHours(2),
            'expires_at' => now()->subHours(2)->addSeconds(45),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    };

    $sollicitation(MatchResponse::ACCEPTE);
    $sollicitation(MatchResponse::REFUSE);
    $sollicitation(MatchResponse::EXPIRE);
    // Une sollicitation annulée par la plateforme ne pénalise personne et
    // n'entre pas dans le calcul.
    $sollicitation(MatchResponse::ANNULE);

    expect($this->service->tauxAcceptation(Periode::derniersJours(7)))->toBe(33.3);
});

it('renvoie une courbe sans trou, un point par jour', function (): void {
    ticketDeTest(TicketState::CLOTUREE, CarbonImmutable::now()->subDays(3)->setTime(9, 0), 100_000, 10_000);

    $volume = $this->service->volumeParJour(Periode::derniersJours(7));

    expect($volume['categories'])->toHaveCount(7)
        ->and($volume['series'])->toHaveCount(3)
        ->and($volume['series'][0]['data'])->toHaveCount(7)
        // Six jours à zéro et un jour à un : la somme vaut le nombre de tickets.
        ->and(array_sum($volume['series'][0]['data']))->toBe(1);
});

it('empile la part technicien et la commission sans jamais les mélanger', function (): void {
    $hier = CarbonImmutable::now()->subDay()->setTime(11, 0);

    ticketDeTest(TicketState::CLOTUREE, $hier, 100_000, 10_000);
    ticketDeTest(TicketState::CLOTUREE, $hier, 200_000, 20_000);

    $revenus = $this->service->revenus(Periode::derniersJours(7));

    expect($revenus['pas'])->toBe('jour')
        ->and(array_sum($revenus['series'][0]['data']))->toBe(270_000)   // part technicien
        ->and(array_sum($revenus['series'][1]['data']))->toBe(30_000);   // commission
});

it('bascule la maille des revenus à la semaine au-delà d\'un mois', function (): void {
    ticketDeTest(TicketState::CLOTUREE, CarbonImmutable::now()->subDays(10), 100_000, 10_000);

    expect($this->service->revenus(Periode::derniersJours(90))['pas'])->toBe('semaine');
});

it('range la carte de chaleur du lundi au dimanche', function (): void {
    // Un mercredi à 14 h : ISODOW vaut 3, donc l'index attendu est 2.
    //
    // Celui de la semaine **précédente**, jamais celui de la semaine en cours :
    // un lundi ou un mardi, le mercredi courant est dans le futur et tombe hors
    // de la fenêtre de trente jours. Le test passait alors cinq jours sur sept.
    $mercredi = CarbonImmutable::now()->startOfWeek()->subWeek()->addDays(2)->setTime(14, 0);

    ticketDeTest(TicketState::CLOTUREE, $mercredi, 100_000, 10_000);

    $heatmap = $this->service->heatmapHeureJour(Periode::derniersJours(30));

    expect($heatmap['jours'][0])->toBe('Lundi')
        ->and($heatmap['jours'][6])->toBe('Dimanche')
        ->and($heatmap['data'])->toContain([14, 2, 1])
        ->and($heatmap['max'])->toBe(1);
});

it('compare la période à la précédente de même durée', function (): void {
    $periode = Periode::derniersJours(7);
    $precedente = $periode->precedente();

    expect($precedente->jours())->toBe(7)
        ->and($precedente->fin->lessThan($periode->debut))->toBeTrue();
});

it('affiche le tableau de bord avec ses cinq graphiques', function (): void {
    ticketDeTest(TicketState::CLOTUREE, CarbonImmutable::now()->subDay(), 100_000, 10_000);

    $reponse = $this->actingAs(adminAvecRole(), 'admin')
        ->get(route('tableau-de-bord'))
        ->assertOk();

    foreach (['ligne', 'camembert', 'colonnes', 'heatmap', 'jauge'] as $type) {
        $reponse->assertSee('data-graphique="'.$type.'"', escape: false);
    }

    $reponse->assertSee('Volume des demandes')
        ->assertSee('Quand les pannes arrivent')
        ->assertSee('data-react-component="FluxActivite"', escape: false);
});

it('change de fenêtre quand on choisit une autre période', function (): void {
    $this->actingAs(adminAvecRole(), 'admin')
        ->get(route('tableau-de-bord', ['periode' => '7j']))
        ->assertOk()
        ->assertSee('7 derniers jours');

    $this->actingAs(adminAvecRole(), 'admin')
        ->get(route('tableau-de-bord', ['periode' => 'annee']))
        ->assertOk()
        ->assertSee('Année '.now()->year);
});

it('sert le flux d\'activité en JSON aux seuls administrateurs', function (): void {
    ticketDeTest(TicketState::CLOTUREE, CarbonImmutable::now()->subHour(), 100_000, 10_000);
    DB::table('ticket_events')->insert([
        'ticket_id' => Ticket::query()->value('id'),
        'to_state' => TicketState::CLOTUREE->value,
        'actor_type' => 'CLIENT',
        'created_at' => now(),
    ]);

    $this->getJson(route('flux-activite'))->assertUnauthorized();

    $this->actingAs(adminAvecRole(), 'admin')
        ->getJson(route('flux-activite'))
        ->assertOk()
        ->assertJsonStructure([
            'evenements' => [['id', 'reference', 'etat', 'etatLibelle', 'ton', 'client', 'horodatage']],
            'horodatage',
        ]);
});

it('reste lisible quand la plateforme n\'a encore rien enregistré', function (): void {
    // Aucun ticket : les graphiques doivent se rendre vides, pas exploser.
    $reponse = $this->actingAs(adminAvecRole(), 'admin')
        ->get(route('tableau-de-bord'))
        ->assertOk();

    $indicateurs = collect($this->service->indicateurs(Periode::derniersJours(30)))->keyBy('cle');

    expect($indicateurs['demandes']['valeur'])->toBe(0)
        ->and($indicateurs['chiffre-affaires']['valeur'])->toBe(0)
        ->and($this->service->tauxAcceptation(Periode::derniersJours(30)))->toBe(0.0)
        ->and($this->service->repartitionParCategorie(Periode::derniersJours(30)))->toBe([]);

    $reponse->assertSee('data-graphique="camembert"', escape: false);
});
