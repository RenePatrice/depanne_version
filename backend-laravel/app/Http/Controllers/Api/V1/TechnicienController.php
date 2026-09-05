<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\Actions\UpdateTechnicianPresence;
use App\Domain\Accounts\Models\User;
use App\Domain\Matching\Actions\RespondToMatch;
use App\Domain\Matching\Data\MatchResponse;
use App\Domain\Matching\Models\MatchAttempt;
use App\Domain\Tickets\Actions\AdvanceIntervention;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Resources\SollicitationResource;
use App\Http\Resources\TicketResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Côté technicien de l'application (§7.3, §8.3).
 *
 * Toutes ces routes exigent une casquette technicien, vérifiée une fois dans
 * `technicien()` : un client qui appellerait ces adresses reçoit un 403 net,
 * pas une erreur de propriété nulle plus loin dans le domaine.
 */
final class TechnicienController extends Controller
{
    public function __construct(
        private readonly UpdateTechnicianPresence $presence,
        private readonly RespondToMatch $reponse,
        private readonly AdvanceIntervention $intervention,
    ) {}

    /**
     * Se mettre en ligne ou hors ligne.
     *
     * Être en ligne, c'est accepter d'être sollicité : le matching ne retient
     * que les techniciens en ligne, validés et sans intervention en cours.
     */
    public function disponibilite(Request $request): JsonResponse
    {
        $valide = $request->validate(['en_ligne' => ['required', 'boolean']]);

        try {
            $profil = $this->presence->definirDisponibilite(
                $this->technicien($request),
                (bool) $valide['en_ligne'],
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $profil->is_online
                ? 'Tu es en ligne. Tu vas recevoir les demandes proches de toi.'
                : 'Tu es hors ligne. Tu ne recevras plus de demandes.',
            'en_ligne' => $profil->is_online,
        ]);
    }

    /**
     * Remonter sa position.
     *
     * Appelée fréquemment pendant une intervention : la réponse est
     * volontairement minimale, et l'écriture ne déclenche aucun événement
     * Eloquent.
     */
    public function position(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'latitude' => ['required', 'numeric', 'between:7.0,13.0'],
            'longitude' => ['required', 'numeric', 'between:-15.5,-7.5'],
        ]);

        $this->presence->definirPosition(
            $this->technicien($request),
            (float) $valide['latitude'],
            (float) $valide['longitude'],
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Mes sollicitations.
     *
     * Sans filtre, seule la sollicitation vivante est renvoyée — c'est ce que
     * l'application affiche au premier plan. `?historique=1` renvoie les
     * dernières, avec leur score : un technicien a le droit de savoir pourquoi
     * il a été classé comme il l'a été.
     */
    public function sollicitations(Request $request): JsonResponse
    {
        $technicien = $this->technicien($request);

        $requete = MatchAttempt::query()
            ->where('technician_id', $technicien->getKey())
            ->with(['ticket.service', 'ticket.client'])
            ->orderByDesc('notified_at');

        if (! $request->boolean('historique')) {
            $requete->where('response', MatchResponse::EN_ATTENTE->value)
                ->where('expires_at', '>', now());
        }

        return response()->json([
            'sollicitations' => SollicitationResource::collection($requete->limit(20)->get()),
        ]);
    }

    /** Accepter une demande. */
    public function accepter(Request $request, MatchAttempt $sollicitation): JsonResponse
    {
        $technicien = $this->technicien($request);

        try {
            $ticket = $this->reponse->accepter($sollicitation, $technicien);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $ticket->load(['service', 'client']);

        return response()->json([
            'message' => 'Demande acceptée. Le client a été prévenu.',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    /** Refuser une demande. */
    public function refuser(Request $request, MatchAttempt $sollicitation): JsonResponse
    {
        $valide = $request->validate(['motif' => ['nullable', 'string', 'max:200']]);

        try {
            $this->reponse->refuser($sollicitation, $this->technicien($request), $valide['motif'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['message' => 'Demande refusée. Elle repart vers un autre technicien.']);
    }

    /**
     * Franchir une étape de l'intervention.
     *
     * Le technicien nomme un geste — « je pars », « je suis arrivé » — et non
     * un état : c'est la machine à états qui décide si le geste est possible.
     */
    public function avancer(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('avancer', $ticket);

        $valide = $request->validate([
            'etape' => ['required', Rule::in(AdvanceIntervention::etapesDisponibles())],
            'diagnostic' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $ticket = $this->intervention->execute(
                $ticket,
                $this->technicien($request),
                (string) $valide['etape'],
                $valide['diagnostic'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $ticket->load(['service', 'client']);

        return response()->json([
            'message' => 'Étape enregistrée. Le client a été prévenu.',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    private function technicien(Request $request): User
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        abort_unless($utilisateur->is_technician, 403, 'Cette action est réservée aux techniciens.');

        return $utilisateur;
    }
}
