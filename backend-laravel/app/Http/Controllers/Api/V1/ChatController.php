<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\Models\User;
use App\Domain\Chat\Actions\SendMessage;
use App\Domain\Chat\Contracts\MaskedCallProvider;
use App\Domain\Chat\Models\Message;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Chat d'une intervention (§7.3).
 *
 * Les deux parties utilisent les mêmes routes : ce qui compte est d'être
 * partie au ticket, pas d'être client ou technicien.
 */
final class ChatController extends Controller
{
    public function __construct(private readonly SendMessage $envoi) {}

    /**
     * Lire la conversation.
     *
     * Les messages sont renvoyés du plus ancien au plus récent — l'ordre de
     * lecture — et marqués comme lus au passage : demander un second appel
     * pour un accusé de lecture doublerait le trafic sur un réseau qui n'en a
     * pas les moyens.
     */
    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $moi */
        $moi = $request->user();

        $this->refuserSiJeNySuisPas($ticket, $moi);

        $messages = Message::query()
            ->where('ticket_id', $ticket->getKey())
            ->with('sender')
            ->orderBy('created_at')
            ->limit(200)
            ->get();

        $this->envoi->marquerLus($ticket, $moi);

        return response()->json([
            'ouvert' => $this->chatOuvert($ticket),
            'messages' => MessageResource::collection($messages),
        ]);
    }

    /** Envoyer un message. */
    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $valide = $request->validate([
            'contenu' => ['required', 'string', 'max:'.SendMessage::LONGUEUR_MAX],
        ]);

        /** @var User $moi */
        $moi = $request->user();

        try {
            $resultat = $this->envoi->execute($ticket, $moi, (string) $valide['contenu']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            // L'avertissement est renvoyé avec le message, pas à la place :
            // le message part quand même, masqué. Le bloquer pousserait à
            // recommencer autrement plutôt qu'à renoncer.
            'avertissement' => $resultat['masquage']->avertissement(),
            'message' => new MessageResource($resultat['message']),
        ], 201);
    }

    /**
     * Demander une mise en relation téléphonique.
     *
     * Le numéro de l'autre partie n'est jamais renvoyé : c'est un numéro relais
     * temporaire. Tant qu'aucun fournisseur n'est branché, la réponse le dit
     * franchement plutôt que de proposer un appel qui ne partirait pas.
     */
    public function appel(Request $request, Ticket $ticket, MaskedCallProvider $telephonie): JsonResponse
    {
        /** @var User $moi */
        $moi = $request->user();

        $this->refuserSiJeNySuisPas($ticket, $moi);

        if (! $this->chatOuvert($ticket)) {
            return response()->json([
                'message' => 'La mise en relation n\'est possible que pendant une intervention.',
            ], 422);
        }

        $autre = (int) $moi->getKey() === (int) $ticket->client_id
            ? $ticket->technician
            : $ticket->client;

        if ($autre === null) {
            return response()->json(['message' => 'Aucun technicien n\'est encore assigné.'], 422);
        }

        $relais = $telephonie->ouvrir($moi, $autre, $ticket->reference);

        return response()->json([
            'message' => $relais['simule']
                ? 'La mise en relation par numéro masqué arrive bientôt. En attendant, utilise le chat.'
                : 'Compose ce numéro : il te met en relation sans dévoiler ton numéro.',
            'relais' => $relais,
        ]);
    }

    private function chatOuvert(Ticket $ticket): bool
    {
        $etat = $ticket->state->etat();

        return $etat->isActive()
            || in_array($etat->value, ['TERMINEE', 'PAYEE', 'LITIGE_OUVERT'], true);
    }

    private function refuserSiJeNySuisPas(Ticket $ticket, User $moi): void
    {
        $id = (int) $moi->getKey();

        if ((int) $ticket->client_id !== $id && (int) $ticket->technician_id !== $id) {
            abort(404);
        }
    }
}
