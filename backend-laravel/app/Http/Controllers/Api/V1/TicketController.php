<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\Models\Address;
use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use App\Domain\Pricing\Services\PricingService;
use App\Domain\Tickets\Actions\CancelTicketByClient;
use App\Domain\Tickets\Actions\CreateTicket;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Zones\Services\ZoneService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TicketRequest;
use App\Http\Resources\TicketResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Demandes d'intervention côté mobile (§7.2, §8.1).
 *
 * Les mêmes routes servent le client et le technicien : ce qu'on voit dépend
 * du rôle qu'on tient sur le ticket, pas de l'adresse appelée. Un utilisateur
 * à double casquette n'a donc pas deux jeux d'écrans à synchroniser.
 */
final class TicketController extends Controller
{
    public function __construct(
        private readonly CreateTicket $creer,
        private readonly CancelTicketByClient $annuler,
        private readonly PricingService $tarification,
        private readonly ZoneService $zones,
    ) {}

    /**
     * Estimer le prix avant de publier.
     *
     * Le devis ne crée aucune ligne. Il ne peut pas non plus être ferme : les
     * frais de déplacement dépendent de la distance entre le technicien et le
     * client, et aucun technicien n'est encore assigné (ADR-0026). Le montant
     * part donc du point de référence de la zone et se déclare provisoire ;
     * c'est l'acceptation qui le verrouille.
     */
    public function devis(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'address_id' => ['required', 'integer', 'exists:addresses,id'],
        ]);

        /** @var Address|null $adresse */
        $adresse = Address::query()
            ->where('user_id', $request->user()?->getKey())
            ->find($valide['address_id']);

        if ($adresse === null || $adresse->location === null) {
            return response()->json(['message' => 'Adresse introuvable.'], 404);
        }

        $zone = $this->zones->pour($adresse->location);

        if ($zone === null) {
            return response()->json([
                'message' => 'Cette adresse est hors de notre zone de couverture. '
                    .'Nous intervenons pour le moment à Ratoma uniquement.',
                'hors_zone' => true,
            ], 422);
        }

        /** @var Service $service */
        $service = Service::query()->where('is_active', true)->findOrFail($valide['service_id']);

        $devis = $this->tarification->estimation($service, $zone, $adresse->location);

        return response()->json([
            'devis' => $devis->pourClient(),
            'zone' => ['id' => $zone->id, 'nom' => $zone->name],
            'avertissement' => 'Montant estimé. Le déplacement définitif sera calculé '
                .'selon la distance du technicien qui accepte, et confirmé à ce moment-là.',
        ]);
    }

    /**
     * Mes demandes.
     *
     * `?filtre=en_cours` ne renvoie que les interventions actives, ce que
     * l'écran d'accueil consulte à chaque ouverture ; sans filtre, la liste
     * est paginée du plus récent au plus ancien.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        $tickets = Ticket::query()
            ->where(fn ($q) => $q
                ->where('client_id', $utilisateur->getKey())
                ->orWhere('technician_id', $utilisateur->getKey()))
            ->when($request->string('filtre')->toString() === 'en_cours', fn ($q) => $q->active())
            ->with(['service', 'client', 'technician'])
            ->orderByDesc('created_at')
            ->paginate(min(50, max(5, (int) $request->integer('par_page', 20))));

        return TicketResource::collection($tickets)->response();
    }

    /** Le détail d'une demande. */
    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        $ticket->load(['service', 'client', 'technician']);

        return response()->json(['ticket' => new TicketResource($ticket)]);
    }

    /** Publier une demande. */
    public function store(TicketRequest $request): JsonResponse
    {
        /** @var User $client */
        $client = $request->user();

        try {
            /** @var array{service_id: int, address_id: int, problem_description?: string|null, photos?: array<int, string>|null} $donnees */
            $donnees = $request->validated();
            $ticket = $this->creer->execute($client, $donnees);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $ticket->load(['service', 'technician']);

        return response()->json([
            'message' => 'Demande publiée. On cherche un technicien près de chez toi.',
            'ticket' => new TicketResource($ticket),
        ], 201);
    }

    /**
     * Annuler sa demande.
     *
     * Les frais éventuels sont renvoyés dans la réponse : le client doit
     * savoir ce qui lui est facturé au moment même où il annule, pas le
     * découvrir sur son relevé.
     */
    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        $valide = $request->validate(['motif' => ['nullable', 'string', 'max:300']]);

        try {
            /** @var User $moi */
            $moi = $request->user();

            $ticket = $this->annuler->execute($ticket, $moi, $valide['motif'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $ticket->load(['service']);

        return response()->json([
            'message' => $ticket->cancellation_fee_gnf > 0
                ? 'Demande annulée. Des frais d\'annulation tardive s\'appliquent.'
                : 'Demande annulée.',
            'frais_gnf' => $ticket->cancellation_fee_gnf,
            'ticket' => new TicketResource($ticket),
        ]);
    }
}
