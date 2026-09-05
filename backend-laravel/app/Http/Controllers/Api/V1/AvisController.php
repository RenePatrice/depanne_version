<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\Models\User;
use App\Domain\Disputes\Actions\OpenDispute;
use App\Domain\Disputes\Data\DisputeReason;
use App\Domain\Disputes\Models\Dispute;
use App\Domain\Reviews\Actions\SubmitReview;
use App\Domain\Reviews\Models\Review;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Resources\AvisResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Avis et réclamations côté mobile (§7.2, §8.1).
 *
 * Les deux vivent ensemble parce qu'ils partagent le même moment du parcours :
 * la fin de l'intervention. Le client note, ou conteste ; c'est le même écran.
 */
final class AvisController extends Controller
{
    public function __construct(
        private readonly SubmitReview $noter,
        private readonly OpenDispute $reclamer,
    ) {}

    /** Noter une intervention. */
    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $valide = $request->validate([
            'note' => ['required', 'integer', 'between:1,5'],
            'etiquettes' => ['nullable', 'array', 'max:5'],
            'etiquettes.*' => [Rule::in(SubmitReview::ETIQUETTES)],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var User $client */
        $client = $request->user();

        try {
            $avis = $this->noter->execute(
                $ticket,
                $client,
                (int) $valide['note'],
                $valide['etiquettes'] ?? [],
                $valide['commentaire'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Merci. Ton avis aide les prochains clients à choisir.',
            'avis' => new AvisResource($avis),
        ], 201);
    }

    /**
     * Les avis reçus par un technicien.
     *
     * Ouvert à tout compte authentifié : c'est ce qui permet à un client de
     * consulter la réputation de celui qui va venir chez lui.
     */
    public function pourTechnicien(User $technicien): JsonResponse
    {
        $avis = Review::query()
            ->where('technician_id', $technicien->getKey())
            ->with('client')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        // Un client peut demander les avis d'un compte qui n'est pas
        // technicien : la réponse est alors vide plutôt qu'une erreur.
        $profil = $technicien->is_technician ? $technicien->technicianProfile : null;

        $statistiques = $profil === null
            ? ['note_moyenne' => null, 'nombre_avis' => 0, 'interventions' => 0]
            : [
                'note_moyenne' => $profil->rating_avg,
                'nombre_avis' => $profil->reviews_count,
                'interventions' => $profil->jobs_completed,
            ];

        return response()->json($statistiques + [
            'avis' => AvisResource::collection($avis),
        ]);
    }

    /** Ouvrir une réclamation. */
    public function reclamation(Request $request, Ticket $ticket): JsonResponse
    {
        $valide = $request->validate([
            'motif' => ['required', Rule::enum(DisputeReason::class)],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'preuves' => ['nullable', 'array', 'max:5'],
            'preuves.*' => ['string', 'max:500'],
        ]);

        Gate::authorize('reclamer', $ticket);

        /** @var User $auteur */
        $auteur = $request->user();

        try {
            $litige = $this->reclamer->execute(
                $ticket,
                $auteur,
                DisputeReason::from((string) $valide['motif']),
                (string) $valide['description'],
                $valide['preuves'] ?? [],
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Réclamation enregistrée. Le paiement reste bloqué le temps de l\'examen.',
            'reclamation' => $this->presenter($litige),
        ], 201);
    }

    /** Mes réclamations. */
    public function reclamations(Request $request): JsonResponse
    {
        /** @var User $moi */
        $moi = $request->user();

        $litiges = Dispute::query()
            ->where('opened_by', $moi->getKey())
            ->with('ticket')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        return response()->json([
            'reclamations' => $litiges->map(fn (Dispute $d): array => $this->presenter($d))->all(),
        ]);
    }

    /**
     * Ce que le déclarant voit de sa réclamation.
     *
     * Ni la priorité ni l'échéance interne ne sont exposées : ce sont des
     * outils de pilotage du support, et les afficher inviterait à négocier son
     * rang dans la file.
     *
     * @return array<string, mixed>
     */
    private function presenter(Dispute $litige): array
    {
        return [
            'reference' => $litige->reference,
            'ticket' => $litige->ticket->reference,
            'motif' => $litige->reason->value,
            'motif_libelle' => $litige->reason->label(),
            'description' => $litige->description,
            'statut' => $litige->status->value,
            'statut_libelle' => $litige->status->label(),
            'ouverte_le' => $litige->created_at?->toIso8601String(),
            'resolue_le' => $litige->resolved_at?->toIso8601String(),
        ];
    }
}
