<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\Models\User;
use App\Domain\Tickets\Actions\ProposeDiagnostic;
use App\Domain\Tickets\Actions\RespondToDiagnostic;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Supplément après diagnostic (§8.1).
 *
 * Deux routes, une par partie : le technicien propose, le client tranche. Le
 * total ne bouge qu'au second geste — c'est ce qui distingue une révision de
 * prix d'une surprise sur la facture.
 */
final class DiagnosticController extends Controller
{
    public function __construct(
        private readonly ProposeDiagnostic $proposer,
        private readonly RespondToDiagnostic $repondre,
    ) {}

    /**
     * Proposer un supplément.
     *
     * Le technicien décrit ce qu'il a constaté et chiffre le complément. Le
     * diagnostic écrit est obligatoire : un supplément sans explication est un
     * litige à venir, et le support n'aurait rien à examiner.
     */
    public function proposer(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('avancer', $ticket);

        $valide = $request->validate([
            'diagnostic' => ['required', 'string', 'min:20', 'max:2000'],
            'supplement_gnf' => ['required', 'integer', 'min:1'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['string', 'max:500'],
        ]);

        /** @var User $technicien */
        $technicien = $request->user();

        try {
            $ticket = $this->proposer->execute(
                $ticket,
                $technicien,
                (string) $valide['diagnostic'],
                (int) $valide['supplement_gnf'],
                $valide['photos'] ?? [],
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $ticket->load(['service', 'client']);

        return response()->json([
            'message' => 'Proposition envoyée. Le client doit la valider avant que tu continues.',
            'ticket' => new TicketResource($ticket),
        ], 201);
    }

    /**
     * Ce qui est proposé, et ce que ça changerait.
     *
     * Le client doit voir le nouveau total **avant** de décider. Le lui montrer
     * après serait exactement la facture surprise que tout le reste du système
     * cherche à éviter.
     */
    public function apercu(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        $devis = $this->repondre->apercu($ticket);

        if ($devis === null) {
            return response()->json([
                'message' => 'Aucun supplément n\'est en attente sur cette intervention.',
            ], 404);
        }

        return response()->json([
            'diagnostic' => $ticket->diagnosis,
            'photos' => $ticket->diagnosis_photos ?? [],
            'supplement_gnf' => $ticket->proposed_extra_fee_gnf,
            'total_actuel_gnf' => $ticket->total_gnf,
            'devis' => $devis->pourClient(),
        ]);
    }

    /** Accepter ou refuser le supplément. */
    public function repondre(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('payer', $ticket);

        $valide = $request->validate(['accepte' => ['required', 'boolean']]);

        /** @var User $client */
        $client = $request->user();

        try {
            $ticket = $this->repondre->execute($ticket, $client, (bool) $valide['accepte']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $ticket->load(['service', 'technician']);

        return response()->json([
            'message' => $valide['accepte']
                ? 'Supplément accepté. Le technicien peut continuer.'
                : 'Supplément refusé. Le technicien fera ce qui était prévu au départ.',
            'ticket' => new TicketResource($ticket),
        ]);
    }
}
