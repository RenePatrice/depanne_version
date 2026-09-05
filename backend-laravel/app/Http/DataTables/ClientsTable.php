<?php

declare(strict_types=1);

namespace App\Http\DataTables;

use App\Domain\Accounts\Data\UserStatus;
use App\Domain\Accounts\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * Table des clients. Le nombre d'interventions et le total dépensé sont
 * calculés en sous-requête plutôt qu'en `withCount` sur une relation : c'est
 * une seule requête, triable et exportable comme n'importe quelle colonne.
 */
final class ClientsTable
{
    public const COLONNES_EXPORT = [
        'nom' => 'Nom',
        'telephone' => 'Téléphone',
        'email' => 'E-mail',
        'statut' => 'Statut',
        'interventions' => 'Interventions',
        'depense_gnf' => 'Total dépensé (GNF)',
        'fidelite' => 'Points de fidélité',
        'inscrit_le' => 'Inscrit le',
        'derniere_connexion' => 'Dernière connexion',
    ];

    /** @return Builder<User> */
    public function requete(Request $request): Builder
    {
        return User::query()
            ->where('users.is_client', true)
            ->leftJoin('client_profiles', 'client_profiles.user_id', '=', 'users.id')
            ->select([
                'users.id', 'users.full_name', 'users.phone', 'users.email',
                'users.status', 'users.created_at', 'users.last_login_at',
                'client_profiles.loyalty_points',
            ])
            ->selectSub(
                DB::table('tickets')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('tickets.client_id', 'users.id'),
                'interventions',
            )
            ->selectSub(
                DB::table('tickets')
                    ->selectRaw('COALESCE(SUM(total_gnf), 0)')
                    ->whereColumn('tickets.client_id', 'users.id')
                    ->whereIn('state', ['TERMINEE', 'PAYEE', 'CLOTUREE']),
                'depense',
            )
            ->when($request->filled('statut'), fn (Builder $q) => $q->where('users.status', $request->string('statut')))
            ->when($request->boolean('double_casquette'), fn (Builder $q) => $q->where('users.is_technician', true));
    }

    public function json(Request $request): JsonResponse
    {
        return DataTables::eloquent($this->requete($request))
            ->addColumn('statut', static fn (User $u): string => view('composants.badge-statut-compte', ['statut' => $u->status])->render())
            ->addColumn('casquettes', static fn (User $u): string => $u->is_technician
                ? '<span class="dm-badge dm-badge--tech">Client et technicien</span>'
                : '<span class="text-body-secondary small">Client</span>')
            ->addColumn('depense_formatee', static fn (User $u): string => '<span class="dm-amount">'.Money::format((int) $u->getAttribute('depense')).'</span>')
            ->addColumn('actions', static fn (User $u): string => sprintf(
                '<a href="%s" class="btn btn-sm btn-outline-secondary" title="Ouvrir la fiche de %s">'
                .'<i class="bi bi-arrow-right-short" aria-hidden="true"></i></a>',
                route('clients.detail', $u->id),
                e($u->full_name),
            ))
            ->editColumn('phone', static fn (User $u): string => '<span class="font-monospace">'.e($u->phone).'</span>')
            ->editColumn('created_at', static fn (User $u): string => $u->created_at?->translatedFormat('d/m/Y') ?? '—')
            // Ces colonnes sont calculees ou composees : sans neutralisation, une
            // requete qui les declarerait cherchables ferait generer a yajra un
            // `LOWER(users.interventions)` inexistant, et l'ecran renverrait 500.
            ->filterColumn('interventions', static fn (): null => null)
            ->filterColumn('depense_formatee', static fn (): null => null)
            ->filterColumn('casquettes', static fn (): null => null)
            ->filterColumn('actions', static fn (): null => null)
            ->orderColumn('depense_formatee', 'depense $1')
            ->rawColumns(['statut', 'casquettes', 'depense_formatee', 'phone', 'actions'])
            ->toJson();
    }

    /** @return array<string, string|int|null> */
    public function ligneExport(User $client): array
    {
        return [
            'nom' => $client->full_name,
            'telephone' => $client->phone,
            'email' => $client->email,
            'statut' => $client->status->label(),
            'interventions' => (int) $client->getAttribute('interventions'),
            'depense_gnf' => (int) $client->getAttribute('depense'),
            'fidelite' => (int) $client->getAttribute('loyalty_points'),
            'inscrit_le' => $client->created_at?->format('Y-m-d'),
            'derniere_connexion' => $client->last_login_at?->format('Y-m-d H:i'),
        ];
    }

    /** @return array<int, array{valeur: string, libelle: string}> */
    public static function statutsFiltrables(): array
    {
        return array_map(
            static fn (UserStatus $statut): array => ['valeur' => $statut->value, 'libelle' => $statut->label()],
            UserStatus::cases(),
        );
    }
}
