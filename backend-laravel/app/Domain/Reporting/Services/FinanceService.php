<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\Data\Periode;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Wallet\Data\WithdrawalStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Lectures financières du back-office (§6).
 *
 * Le « montant dû » à un technicien est la somme de ses mouvements de
 * portefeuille, moins ce qui lui a déjà été versé et ce qui est engagé par une
 * demande de retrait approuvée mais pas encore payée. Réserver cet engagé évite
 * d'approuver deux retraits que le solde ne couvre qu'une fois.
 */
final class FinanceService
{
    /**
     * Chiffres d'en-tête de l'écran des finances.
     *
     * @return array<string, int>
     */
    public function synthese(Periode $periode): array
    {
        $tickets = DB::table('tickets')
            ->whereBetween('created_at', [$periode->debut, $periode->fin])
            ->selectRaw('COALESCE(SUM(commission_gnf), 0) AS commissions')
            ->selectRaw('COALESCE(SUM(technician_net_gnf), 0) AS net')
            ->selectRaw('COALESCE(SUM(total_gnf) FILTER (WHERE state IN (?, ?, ?)), 0) AS ca', [
                TicketState::TERMINEE->value, TicketState::PAYEE->value, TicketState::CLOTUREE->value,
            ])
            ->first();

        $sequestre = (int) DB::table('payments')
            ->where('status', 'CAPTUREE')
            ->sum('amount_gnf');

        return [
            'chiffre_affaires' => (int) ($tickets->ca ?? 0),
            'commissions' => (int) ($tickets->commissions ?? 0),
            'net_technicien' => (int) ($tickets->net ?? 0),
            'sequestre' => $sequestre,
            'du_aux_techniciens' => $this->totalDu(),
            'retraits_en_attente' => (int) DB::table('withdrawals')
                ->where('status', WithdrawalStatus::EN_ATTENTE->value)
                ->sum('amount_gnf'),
            'retraits_approuves' => (int) DB::table('withdrawals')
                ->where('status', WithdrawalStatus::APPROUVE->value)
                ->sum('amount_gnf'),
        ];
    }

    /**
     * Commissions et part technicien par palier — jour sous un mois, semaine
     * au-delà, comme le tableau de bord.
     *
     * @return array{categories: array<int, string>, series: array<int, array{name: string, data: array<int, int>, color: string}>, pas: string}
     */
    public function commissionsParPeriode(Periode $periode): array
    {
        $parSemaine = $periode->jours() > 31;
        $troncature = $parSemaine ? 'week' : 'day';

        $lignes = DB::table('tickets')
            ->whereNotNull('commission_gnf')
            ->whereBetween('created_at', [$periode->debut, $periode->fin])
            ->selectRaw("DATE_TRUNC('{$troncature}', created_at) AS palier")
            ->selectRaw('SUM(commission_gnf) AS commission')
            ->selectRaw('SUM(technician_net_gnf) AS net')
            ->groupBy('palier')
            ->orderBy('palier')
            ->get();

        $categories = [];
        $commissions = [];
        $nets = [];

        foreach ($lignes as $ligne) {
            $palier = CarbonImmutable::parse((string) $ligne->palier);

            $categories[] = $parSemaine ? 'sem. '.$palier->translatedFormat('d M') : $palier->translatedFormat('d M');
            $commissions[] = (int) $ligne->commission;
            $nets[] = (int) $ligne->net;
        }

        return [
            'categories' => $categories,
            'pas' => $parSemaine ? 'semaine' : 'jour',
            'series' => [
                ['name' => 'Commission plateforme', 'data' => $commissions, 'color' => '#16A34A'],
                ['name' => 'Part technicien', 'data' => $nets, 'color' => '#1B6FF3'],
            ],
        ];
    }

    /**
     * Techniciens à qui la plateforme doit de l'argent, du plus créditeur au
     * moins créditeur.
     *
     * @return array<int, array<string, mixed>>
     */
    public function montantsDus(int $limite = 25): array
    {
        return DB::table('transactions')
            ->join('users', 'users.id', '=', 'transactions.user_id')
            ->where('users.is_technician', true)
            ->groupBy('users.id', 'users.full_name', 'users.phone')
            ->havingRaw('SUM(transactions.amount_gnf) > 0')
            ->orderByRaw('SUM(transactions.amount_gnf) DESC')
            ->limit($limite)
            ->get([
                'users.id',
                'users.full_name',
                'users.phone',
                DB::raw('SUM(transactions.amount_gnf) AS solde'),
                DB::raw('COUNT(*) AS mouvements'),
            ])
            ->map(static fn (object $ligne): array => [
                'id' => (int) $ligne->id,
                'nom' => (string) $ligne->full_name,
                'telephone' => (string) $ligne->phone,
                'solde' => (int) $ligne->solde,
                'mouvements' => (int) $ligne->mouvements,
            ])
            ->all();
    }

    /** Total dû à l'ensemble des techniciens, engagements de retrait déduits. */
    public function totalDu(): int
    {
        $credits = (int) DB::table('transactions')
            ->join('users', 'users.id', '=', 'transactions.user_id')
            ->where('users.is_technician', true)
            ->sum('transactions.amount_gnf');

        $engages = (int) DB::table('withdrawals')
            ->where('status', WithdrawalStatus::APPROUVE->value)
            ->sum('amount_gnf');

        return max(0, $credits - $engages);
    }

    /**
     * Export comptable : une ligne par ticket réglé, avec sa décomposition.
     *
     * @return array<string, string>
     */
    public static function colonnesComptables(): array
    {
        return [
            'reference' => 'Référence',
            'date_cloture' => 'Date de clôture',
            'client' => 'Client',
            'technicien' => 'Technicien',
            'prestation' => 'Prestation',
            'zone' => 'Zone',
            'total_gnf' => 'Total encaissé (GNF)',
            'commission_gnf' => 'Commission (GNF)',
            'net_technicien_gnf' => 'Net technicien (GNF)',
            'taux_commission' => 'Taux appliqué',
            'moyen_paiement' => 'Moyen de paiement',
            'reference_paiement' => 'Référence fournisseur',
        ];
    }
}
