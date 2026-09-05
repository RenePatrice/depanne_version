<?php

declare(strict_types=1);

namespace App\Http\DataTables;

use App\Domain\Wallet\Data\WithdrawalStatus;
use App\Domain\Wallet\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * File des demandes de retrait (§6, §8.4).
 *
 * Le solde du technicien est affiché à côté du montant demandé : c'est ce qui
 * permet de décider en un coup d'œil, sans ouvrir la fiche.
 */
final class RetraitsTable
{
    public const COLONNES_EXPORT = [
        'reference' => 'Référence',
        'demande_le' => 'Demandé le',
        'technicien' => 'Technicien',
        'numero' => 'Numéro Mobile Money',
        'operateur' => 'Opérateur',
        'montant_gnf' => 'Montant (GNF)',
        'solde_gnf' => 'Solde au moment de l\'export (GNF)',
        'statut' => 'Statut',
        'traite_le' => 'Traité le',
        'paye_le' => 'Payé le',
        'note' => 'Note',
    ];

    /** @return Builder<Withdrawal> */
    public function requete(Request $request): Builder
    {
        return Withdrawal::query()
            ->join('users', 'users.id', '=', 'withdrawals.technician_id')
            ->select([
                'withdrawals.id', 'withdrawals.reference', 'withdrawals.technician_id',
                'withdrawals.amount_gnf', 'withdrawals.mobile_money_number', 'withdrawals.provider',
                'withdrawals.status', 'withdrawals.note', 'withdrawals.requested_at',
                'withdrawals.processed_at', 'withdrawals.paid_at',
                'users.full_name',
            ])
            ->selectSub(
                DB::table('transactions')
                    ->selectRaw('COALESCE(SUM(amount_gnf), 0)')
                    ->whereColumn('transactions.user_id', 'withdrawals.technician_id'),
                'solde',
            )
            ->when($request->filled('statut'), fn (Builder $q) => $q->where('withdrawals.status', $request->string('statut')))
            ->when($request->filled('du'), fn (Builder $q) => $q->whereDate('withdrawals.requested_at', '>=', $request->date('du')))
            ->when($request->filled('au'), fn (Builder $q) => $q->whereDate('withdrawals.requested_at', '<=', $request->date('au')));
    }

    public function json(Request $request): JsonResponse
    {
        return DataTables::eloquent($this->requete($request))
            ->addColumn('statut', static fn (Withdrawal $r): string => sprintf(
                '<span class="dm-badge dm-badge--%s">%s</span>',
                $r->status->color(),
                e($r->status->label()),
            ))
            ->addColumn('technicien', static fn (Withdrawal $r): string => e((string) $r->getAttribute('full_name')))
            ->addColumn('montant', static fn (Withdrawal $r): string => '<span class="dm-amount">'.Money::format($r->amount_gnf).'</span>')
            ->addColumn('solde_formate', static function (Withdrawal $r): string {
                $solde = (int) $r->getAttribute('solde');
                // Un solde insuffisant se voit avant même d'ouvrir la demande.
                $classe = $solde < $r->amount_gnf ? 'text-danger' : '';

                return '<span class="dm-amount '.$classe.'">'.Money::format($solde).'</span>';
            })
            ->addColumn('numero', static fn (Withdrawal $r): string => sprintf(
                '<span class="font-monospace small">%s</span><span class="d-block text-body-secondary" style="font-size:.7rem">%s</span>',
                e($r->mobile_money_number),
                e($r->provider->label()),
            ))
            ->addColumn('actions', static fn (Withdrawal $r): string => sprintf(
                '<a href="%s" class="btn btn-sm btn-outline-secondary" title="Ouvrir %s">'
                .'<i class="bi bi-arrow-right-short" aria-hidden="true"></i></a>',
                route('retraits.detail', $r->id),
                e($r->reference),
            ))
            ->editColumn('reference', static fn (Withdrawal $r): string => '<span class="font-monospace">'.e($r->reference).'</span>')
            // `requested_at` est obligatoire en base : pas de nullsafe ici.
            ->editColumn('requested_at', static fn (Withdrawal $r): string => $r->requested_at->translatedFormat('d/m/Y H:i'))
            ->filterColumn('technicien', static function (Builder $query, string $terme): void {
                $query->where('users.full_name', 'ilike', "%{$terme}%");
            })
            ->filterColumn('statut', static fn (): null => null)
            ->filterColumn('montant', static fn (): null => null)
            ->filterColumn('solde_formate', static fn (): null => null)
            ->filterColumn('numero', static fn (): null => null)
            ->filterColumn('actions', static fn (): null => null)
            ->orderColumn('montant', 'withdrawals.amount_gnf $1')
            ->orderColumn('solde_formate', 'solde $1')
            ->rawColumns(['reference', 'statut', 'montant', 'solde_formate', 'numero', 'actions'])
            ->toJson();
    }

    /** @return array<string, string|int|null> */
    public function ligneExport(Withdrawal $retrait): array
    {
        return [
            'reference' => $retrait->reference,
            'demande_le' => $retrait->requested_at->format('Y-m-d H:i'),
            'technicien' => (string) $retrait->getAttribute('full_name'),
            'numero' => $retrait->mobile_money_number,
            'operateur' => $retrait->provider->label(),
            'montant_gnf' => $retrait->amount_gnf,
            'solde_gnf' => (int) $retrait->getAttribute('solde'),
            'statut' => $retrait->status->label(),
            'traite_le' => $retrait->processed_at?->format('Y-m-d H:i'),
            'paye_le' => $retrait->paid_at?->format('Y-m-d H:i'),
            'note' => $retrait->note,
        ];
    }

    /** @return array<int, array{valeur: string, libelle: string}> */
    public static function statutsFiltrables(): array
    {
        return array_map(
            static fn (WithdrawalStatus $s): array => ['valeur' => $s->value, 'libelle' => $s->label()],
            WithdrawalStatus::cases(),
        );
    }
}
