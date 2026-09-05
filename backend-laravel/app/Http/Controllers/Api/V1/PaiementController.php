<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\Models\User;
use App\Domain\Payments\Actions\HandlePaymentWebhook;
use App\Domain\Payments\Actions\InitiatePayment;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Wallet\Services\EscrowService;
use App\Http\Controllers\Controller;
use App\Support\Money;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Paiement d'une intervention (§8.4).
 *
 * Trois routes seulement, et une seule est publique — le webhook, gardé par la
 * signature de l'opérateur.
 */
final class PaiementController extends Controller
{
    public function __construct(
        private readonly InitiatePayment $initier,
        private readonly EscrowService $sequestre,
    ) {}

    /**
     * Lancer le paiement.
     *
     * Renvoie la référence de suivi et, selon l'opérateur, une adresse de
     * confirmation. Le ticket ne change pas d'état ici : il ne passera en PAYEE
     * qu'à la confirmation de l'opérateur.
     */
    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $valide = $request->validate([
            'telephone' => ['nullable', 'string', 'max:20'],
        ]);

        /** @var User $client */
        $client = $request->user();

        try {
            $paiement = $this->initier->execute($ticket, $client, $valide['telephone'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Paiement lancé. Confirme depuis ton téléphone.',
            'paiement' => [
                'reference' => $paiement->provider_ref,
                'statut' => $paiement->status->value,
                'montant_gnf' => $paiement->amount_gnf,
                'montant_formate' => Money::format($paiement->amount_gnf),
                'instruction' => $paiement->getAttribute('instruction'),
                'url_paiement' => $paiement->getAttribute('url_paiement'),
            ],
        ], 201);
    }

    /**
     * Suivre un paiement.
     *
     * L'application interroge cette route pendant que le client confirme sur
     * son téléphone : la notification de l'opérateur peut arriver avant ou
     * après son retour dans l'application, et rien ne garantit l'ordre.
     */
    public function show(Request $request, string $reference): JsonResponse
    {
        /** @var Payment|null $paiement */
        $paiement = Payment::query()
            ->where('provider_ref', $reference)
            ->whereHas('ticket', fn ($q) => $q->where('client_id', $request->user()?->getKey()))
            ->with('ticket')
            ->first();

        if ($paiement === null) {
            return response()->json(['message' => 'Paiement introuvable.'], 404);
        }

        return response()->json([
            'reference' => $paiement->provider_ref,
            'statut' => $paiement->status->value,
            'statut_libelle' => $paiement->status->label(),
            'montant_gnf' => $paiement->amount_gnf,
            'motif_echec' => $paiement->failure_reason,
            'ticket' => ['reference' => $paiement->ticket->reference, 'etat' => $paiement->ticket->state->etat()->value],
            'capture_le' => $paiement->captured_at?->toIso8601String(),
            'libere_le' => $paiement->released_at?->toIso8601String(),
        ]);
    }

    /**
     * Valider l'intervention et libérer les fonds.
     *
     * Le geste volontaire du client. Sans lui, la libération se fait seule au
     * bout de vingt-quatre heures — mais un client satisfait ne devrait pas
     * avoir à attendre pour que son technicien soit payé.
     */
    public function valider(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $client */
        $client = $request->user();

        Gate::authorize('payer', $ticket);

        /** @var Payment|null $paiement */
        $paiement = Payment::query()
            ->where('ticket_id', $ticket->getKey())
            ->where('status', PaymentStatus::CAPTUREE->value)
            ->first();

        if ($paiement === null) {
            return response()->json([
                'message' => 'Aucun paiement en attente de validation sur cette intervention.',
            ], 422);
        }

        $libere = $this->sequestre->liberer($paiement, ActorType::CLIENT, (int) $client->getKey());

        return response()->json([
            'message' => $libere
                ? 'Merci. Le technicien a été payé.'
                : 'La libération est suspendue : une réclamation est en cours.',
            'libere' => $libere,
        ]);
    }

    /**
     * Notification de l'opérateur.
     *
     * Route **publique** : c'est l'opérateur qui appelle, pas l'utilisateur. La
     * seule chose qui la protège est la signature — d'où le refus sec avant
     * même de lire le corps.
     *
     * La réponse est un 200 dans tous les cas traités, y compris pour un rejeu
     * ou une référence inconnue. Répondre en erreur ferait rejouer l'opérateur
     * indéfiniment sur un cas qui ne se résoudra jamais.
     */
    public function webhook(
        Request $request,
        PaymentProvider $operateur,
        HandlePaymentWebhook $traitement,
    ): JsonResponse {
        if (! $operateur->verifierSignature($request)) {
            Log::warning('Webhook de paiement : signature invalide.', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Signature invalide.'], 401);
        }

        $resultat = $traitement->execute($operateur->lireNotification($request));

        return response()->json(['recu' => true, 'traite' => $resultat['traite'], 'motif' => $resultat['motif']]);
    }

    /**
     * Retour du client depuis la page de l'opérateur.
     *
     * Elle ne décide de rien : le paiement est confirmé par le webhook, pas
     * par le navigateur du client. Si elle tranchait, il suffirait d'ouvrir
     * cette adresse à la main pour se déclarer payé.
     */
    public function retour(): JsonResponse
    {
        return response()->json([
            'message' => 'Retour enregistré. Le paiement est confirmé par l\'opérateur, '
                .'suis son état depuis l\'application.',
        ]);
    }
}
