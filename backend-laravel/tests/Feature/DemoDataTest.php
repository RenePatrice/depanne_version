<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Catalog\Models\Service;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Zones\Models\Zone;
use Illuminate\Support\Facades\DB;

/*
 * Les données de démonstration ne sont pas décoratives : le tableau de bord,
 * les DataTables et les Highcharts du bloc B seront jugés dessus. Un jeu de
 * données incohérent produirait un back-office qui ment.
 *
 * Le seeding complet est lancé une seule fois : il crée 150 tickets et tout
 * leur cortège, ce qui prend quelques secondes.
 */

beforeEach(function (): void {
    $this->seed();
});

it('produit la population attendue par le cahier des charges', function (): void {
    expect(TechnicianProfile::query()->count())->toBe(40)
        ->and(Service::query()->count())->toBe(20)
        ->and(Zone::query()->count())->toBe(3)
        ->and(Ticket::query()->count())->toBe(150)
        ->and(DB::table('client_profiles')->count())->toBeGreaterThanOrEqual(20);
});

it('laisse des dossiers dans la file de validation des techniciens', function (): void {
    // Sinon l'écran de validation du back-office serait vide en démonstration.
    expect(TechnicianProfile::query()
        ->where('verification_status', VerificationStatus::EN_ATTENTE_VALIDATION)
        ->count())->toBeGreaterThan(0);
});

it('respecte la règle de prix sur chacun des 150 tickets', function (): void {
    $incoherents = Ticket::query()
        ->whereRaw('total_gnf <> base_price_gnf + travel_fee_gnf + extra_fee_gnf')
        ->count();

    expect($incoherents)->toBe(0);
});

it('répartit exactement 90 / 10 sur les tickets clôturés', function (): void {
    $repartis = Ticket::query()->whereNotNull('commission_gnf')->get();

    expect($repartis)->not->toBeEmpty();

    foreach ($repartis as $ticket) {
        // La commission est arrondie, le net technicien est le reste : la somme
        // doit retomber au franc près sur le total payé par le client.
        expect($ticket->commission_gnf + $ticket->technician_net_gnf)->toBe($ticket->total_gnf)
            ->and($ticket->commission_gnf)->toBe((int) round($ticket->total_gnf * $ticket->commission_rate));
    }
});

it('tient un grand livre dont chaque solde suit la somme des mouvements', function (): void {
    $incoherents = DB::select(<<<'SQL'
        WITH courant AS (
            SELECT balance_after_gnf,
                   SUM(amount_gnf) OVER (PARTITION BY user_id ORDER BY id) AS cumul
            FROM transactions
        )
        SELECT COUNT(*) AS n FROM courant WHERE balance_after_gnf <> cumul
    SQL)[0]->n;

    expect((int) $incoherents)->toBe(0);
});

it('affiche une note de technicien qui correspond à ses avis', function (): void {
    $ecarts = DB::select(<<<'SQL'
        SELECT COUNT(*) AS n
        FROM technician_profiles tp
        JOIN (SELECT technician_id, AVG(rating) AS moyenne FROM reviews GROUP BY technician_id) r
          ON r.technician_id = tp.user_id
        WHERE ABS(tp.rating_avg - r.moyenne) >= 0.01
    SQL)[0]->n;

    expect((int) $ecarts)->toBe(0);
});

it('signale les messages qui tentent de contourner la plateforme', function (): void {
    // Risque business n° 1 du §11 : le back-office doit avoir de quoi le montrer.
    expect(DB::table('messages')->where('is_flagged', true)->count())->toBeGreaterThan(0);
});

it('charge les paramètres pilotables avec leurs valeurs par défaut', function (): void {
    expect(AppSetting::get(AppSetting::COMMISSION_RATE))->toBe(0.10)
        ->and(AppSetting::get(AppSetting::MATCH_RESPONSE_SECONDS))->toBe(45)
        ->and(AppSetting::get(AppSetting::MATCH_INITIAL_RADIUS_KM))->toBe(5)
        ->and(AppSetting::get(AppSetting::MATCH_MAX_RADIUS_KM))->toBe(15)
        ->and(AppSetting::get(AppSetting::SCORE_WEIGHTS))->toMatchArray([
            'proximity' => 0.40,
            'rating' => 0.30,
            'acceptance' => 0.20,
            'cancellation' => -0.10,
        ]);
});

it('calcule le solde d\'un technicien à partir de ses seuls mouvements', function (): void {
    $userId = (int) DB::table('transactions')->value('user_id');

    $attendu = (int) DB::table('transactions')->where('user_id', $userId)->sum('amount_gnf');

    expect(Transaction::balanceFor($userId))->toBe($attendu);
});

it('place tous les tickets dans une zone de Ratoma', function (): void {
    expect(Ticket::query()->whereNull('zone_id')->count())->toBe(0);
});
