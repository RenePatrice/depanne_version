<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Catalog\Models\ServiceCategory;
use App\Domain\Tickets\Data\TicketState;
use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Carte live (§6).
 *
 * L'écran reçoit d'abord un instantané complet, puis se met à jour par les
 * messages Reverb. Sans cet instantané, la carte resterait vide jusqu'au
 * premier événement — soit potentiellement plusieurs minutes.
 */
final class CarteLiveController extends Controller
{
    public function index(): View
    {
        return view('carte-live.index', [
            'categories' => ServiceCategory::query()->orderBy('sort_order')->get(['id', 'name', 'color']),
            'etatsActifs' => array_map(
                static fn (TicketState $e): array => ['valeur' => $e->value, 'libelle' => $e->label()],
                array_values(array_filter(TicketState::cases(), static fn (TicketState $e): bool => $e->isActive())),
            ),
        ]);
    }

    /** Instantané : techniciens en ligne, interventions en cours, compteurs. */
    public function donnees(): JsonResponse
    {
        return response()->json([
            'techniciens' => $this->techniciens(),
            'interventions' => $this->interventions(),
            'compteurs' => $this->compteurs(),
            'horodatage' => now()->toIso8601String(),
        ]);
    }

    /** Compteurs seuls : rafraîchis à chaque message reçu, sans recharger la carte. */
    public function compteursLive(): JsonResponse
    {
        return response()->json([
            'compteurs' => $this->compteurs(),
            'horodatage' => now()->toIso8601String(),
        ]);
    }

    /**
     * Techniciens en ligne et validés, avec leur dernière position connue.
     *
     * @return array<int, array<string, mixed>>
     */
    private function techniciens(): array
    {
        $etatsActifs = array_map(
            static fn (TicketState $e): string => $e->value,
            array_filter(TicketState::cases(), static fn (TicketState $e): bool => $e->isActive()),
        );

        return DB::table('technician_profiles')
            ->join('users', 'users.id', '=', 'technician_profiles.user_id')
            ->where('technician_profiles.is_online', true)
            ->where('technician_profiles.verification_status', VerificationStatus::VALIDE->value)
            ->whereNotNull('technician_profiles.last_known_location')
            ->select([
                'users.id', 'users.full_name', 'users.phone',
                'technician_profiles.specialties', 'technician_profiles.rating_avg',
                'technician_profiles.jobs_completed', 'technician_profiles.last_position_at',
                DB::raw('ST_Y(technician_profiles.last_known_location::geometry) AS latitude'),
                DB::raw('ST_X(technician_profiles.last_known_location::geometry) AS longitude'),
            ])
            ->selectSub(
                DB::table('tickets')
                    ->selectRaw('reference')
                    ->whereColumn('tickets.technician_id', 'users.id')
                    ->whereIn('tickets.state', $etatsActifs)
                    ->limit(1),
                'ticket_en_cours',
            )
            ->get()
            ->map(static fn (object $ligne): array => [
                'id' => (int) $ligne->id,
                'nom' => (string) $ligne->full_name,
                'telephone' => (string) $ligne->phone,
                'note' => round((float) $ligne->rating_avg, 2),
                'interventions' => (int) $ligne->jobs_completed,
                'latitude' => (float) $ligne->latitude,
                'longitude' => (float) $ligne->longitude,
                // Vert quand le technicien est disponible, orange quand il est
                // déjà sur une intervention : la couleur suffit à lire la carte.
                'enIntervention' => $ligne->ticket_en_cours !== null,
                'ticket' => $ligne->ticket_en_cours,
                'vuIlYa' => $ligne->last_position_at,
            ])
            ->all();
    }

    /**
     * Interventions en cours, localisées à l'adresse du client.
     *
     * @return array<int, array<string, mixed>>
     */
    private function interventions(): array
    {
        $etatsActifs = array_map(
            static fn (TicketState $e): string => $e->value,
            array_filter(TicketState::cases(), static fn (TicketState $e): bool => $e->isActive()),
        );

        return DB::table('tickets')
            ->join('services', 'services.id', '=', 'tickets.service_id')
            ->join('service_categories', 'service_categories.id', '=', 'services.category_id')
            ->leftJoin('users AS clients', 'clients.id', '=', 'tickets.client_id')
            ->leftJoin('users AS techniciens', 'techniciens.id', '=', 'tickets.technician_id')
            ->whereIn('tickets.state', $etatsActifs)
            ->select([
                'tickets.id', 'tickets.reference', 'tickets.state', 'tickets.total_gnf',
                'tickets.accepted_at',
                'services.name AS prestation',
                'service_categories.id AS categorie_id',
                'service_categories.name AS categorie',
                'service_categories.color AS couleur',
                'clients.full_name AS client',
                'techniciens.full_name AS technicien',
                DB::raw('ST_Y(tickets.location::geometry) AS latitude'),
                DB::raw('ST_X(tickets.location::geometry) AS longitude'),
            ])
            ->get()
            ->map(static function (object $ligne): array {
                $etat = TicketState::tryFrom((string) $ligne->state);

                return [
                    'id' => (int) $ligne->id,
                    'reference' => (string) $ligne->reference,
                    'etat' => (string) $ligne->state,
                    'etatLibelle' => $etat?->label() ?? (string) $ligne->state,
                    'ton' => $etat?->color() ?? 'secondary',
                    'prestation' => (string) $ligne->prestation,
                    'categorieId' => (int) $ligne->categorie_id,
                    'categorie' => (string) $ligne->categorie,
                    'couleur' => (string) $ligne->couleur,
                    'client' => (string) ($ligne->client ?? '—'),
                    'technicien' => $ligne->technicien !== null ? (string) $ligne->technicien : null,
                    'montant' => (int) $ligne->total_gnf,
                    'montantFormate' => Money::format((int) $ligne->total_gnf),
                    'accepteA' => $ligne->accepted_at,
                    'latitude' => (float) $ligne->latitude,
                    'longitude' => (float) $ligne->longitude,
                ];
            })
            ->all();
    }

    /** @return array<string, int> */
    private function compteurs(): array
    {
        $etatsActifs = array_map(
            static fn (TicketState $e): string => $e->value,
            array_filter(TicketState::cases(), static fn (TicketState $e): bool => $e->isActive()),
        );

        return [
            'techniciens_en_ligne' => DB::table('technician_profiles')
                ->where('is_online', true)
                ->where('verification_status', VerificationStatus::VALIDE->value)
                ->count(),
            'interventions_en_cours' => DB::table('tickets')->whereIn('state', $etatsActifs)->count(),
            'demandes_en_attente' => DB::table('tickets')
                ->where('state', TicketState::PUBLIEE->value)
                ->count(),
            'sans_reponse_24h' => DB::table('tickets')
                ->where('state', TicketState::SANS_REPONSE->value)
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];
    }
}
