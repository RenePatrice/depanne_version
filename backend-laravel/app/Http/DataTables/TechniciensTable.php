<?php

declare(strict_types=1);

namespace App\Http\DataTables;

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Data\Specialty;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * Table des techniciens. Les quatre statistiques qui pèsent dans le score de
 * matching (note, interventions, acceptation, annulation) sont visibles ici :
 * c'est là que le support comprend pourquoi un technicien est peu sollicité.
 */
final class TechniciensTable
{
    public const COLONNES_EXPORT = [
        'nom' => 'Nom',
        'telephone' => 'Téléphone',
        'specialites' => 'Spécialités',
        'verification' => 'Vérification',
        'en_ligne' => 'En ligne',
        'interventions' => 'Interventions',
        'note' => 'Note moyenne',
        'taux_acceptation' => "Taux d'acceptation (%)",
        'taux_annulation' => "Taux d'annulation (%)",
        'solde_gnf' => 'Solde portefeuille (GNF)',
        'inscrit_le' => 'Inscrit le',
    ];

    /** @return Builder<User> */
    public function requete(Request $request): Builder
    {
        return User::query()
            ->where('users.is_technician', true)
            ->join('technician_profiles', 'technician_profiles.user_id', '=', 'users.id')
            ->select([
                'users.id', 'users.full_name', 'users.phone', 'users.created_at',
                'technician_profiles.specialties', 'technician_profiles.verification_status',
                'technician_profiles.is_online', 'technician_profiles.rating_avg',
                'technician_profiles.reviews_count', 'technician_profiles.jobs_completed',
                'technician_profiles.acceptance_rate', 'technician_profiles.cancellation_rate',
            ])
            ->selectSub(
                DB::table('transactions')
                    ->selectRaw('COALESCE(SUM(amount_gnf), 0)')
                    ->whereColumn('transactions.user_id', 'users.id'),
                'solde',
            )
            ->when($request->filled('verification'), fn (Builder $q) => $q->where('technician_profiles.verification_status', $request->string('verification')))
            ->when($request->filled('specialite'), fn (Builder $q) => $q->whereJsonContains('technician_profiles.specialties', $request->string('specialite')->toString()))
            ->when($request->boolean('en_ligne'), fn (Builder $q) => $q->where('technician_profiles.is_online', true));
    }

    public function json(Request $request): JsonResponse
    {
        return DataTables::eloquent($this->requete($request))
            ->addColumn('verification', static fn (User $u): string => view('composants.badge-verification', [
                'statut' => VerificationStatus::from((string) $u->getAttribute('verification_status')),
            ])->render())
            ->addColumn('specialites', static function (User $u): string {
                /** @var array<int, string> $codes */
                $codes = (array) json_decode((string) $u->getAttribute('specialties'), true);

                return collect($codes)
                    ->map(static fn (string $code): string => sprintf(
                        '<span class="dm-badge dm-badge--secondary">%s</span>',
                        e(Specialty::tryFrom($code)?->label() ?? $code),
                    ))
                    ->implode(' ');
            })
            ->addColumn('presence', static fn (User $u): string => $u->getAttribute('is_online')
                ? '<span class="dm-badge dm-badge--success">En ligne</span>'
                : '<span class="dm-badge dm-badge--secondary">Hors ligne</span>')
            ->addColumn('note', static function (User $u): string {
                $note = (float) $u->getAttribute('rating_avg');
                $avis = (int) $u->getAttribute('reviews_count');

                if ($avis === 0) {
                    return '<span class="text-body-secondary small">aucun avis</span>';
                }

                return sprintf(
                    '<span class="dm-amount">%s</span> <span class="text-body-secondary small">(%d)</span>',
                    number_format($note, 2, ',', ' '),
                    $avis,
                );
            })
            ->addColumn('acceptation', static fn (User $u): string => number_format((float) $u->getAttribute('acceptance_rate') * 100, 0, ',', ' ').' %')
            ->addColumn('solde_formate', static fn (User $u): string => '<span class="dm-amount">'.Money::format((int) $u->getAttribute('solde')).'</span>')
            ->addColumn('actions', static fn (User $u): string => sprintf(
                '<a href="%s" class="btn btn-sm btn-outline-secondary" title="Ouvrir la fiche de %s">'
                .'<i class="bi bi-arrow-right-short" aria-hidden="true"></i></a>',
                route('techniciens.detail', $u->id),
                e($u->full_name),
            ))
            ->editColumn('phone', static fn (User $u): string => '<span class="font-monospace">'.e($u->phone).'</span>')
            // Colonnes composees : elles n'existent pas en base, la recherche
            // globale ne doit jamais tenter de les interroger.
            ->filterColumn('specialites', static fn (): null => null)
            ->filterColumn('presence', static fn (): null => null)
            ->filterColumn('note', static fn (): null => null)
            ->filterColumn('acceptation', static fn (): null => null)
            ->filterColumn('solde_formate', static fn (): null => null)
            ->filterColumn('actions', static fn (): null => null)
            ->orderColumn('note', 'technician_profiles.rating_avg $1')
            ->orderColumn('solde_formate', 'solde $1')
            ->orderColumn('acceptation', 'technician_profiles.acceptance_rate $1')
            ->rawColumns(['verification', 'specialites', 'presence', 'note', 'solde_formate', 'phone', 'actions'])
            ->toJson();
    }

    /** @return array<string, string|int|float|null> */
    public function ligneExport(User $technicien): array
    {
        /** @var array<int, string> $codes */
        $codes = (array) json_decode((string) $technicien->getAttribute('specialties'), true);

        return [
            'nom' => $technicien->full_name,
            'telephone' => $technicien->phone,
            'specialites' => collect($codes)
                ->map(static fn (string $c): string => Specialty::tryFrom($c)?->label() ?? $c)
                ->implode(', '),
            'verification' => VerificationStatus::from((string) $technicien->getAttribute('verification_status'))->label(),
            'en_ligne' => $technicien->getAttribute('is_online') ? 'Oui' : 'Non',
            'interventions' => (int) $technicien->getAttribute('jobs_completed'),
            'note' => round((float) $technicien->getAttribute('rating_avg'), 2),
            'taux_acceptation' => round((float) $technicien->getAttribute('acceptance_rate') * 100, 1),
            'taux_annulation' => round((float) $technicien->getAttribute('cancellation_rate') * 100, 1),
            'solde_gnf' => (int) $technicien->getAttribute('solde'),
            'inscrit_le' => $technicien->created_at?->format('Y-m-d'),
        ];
    }
}
