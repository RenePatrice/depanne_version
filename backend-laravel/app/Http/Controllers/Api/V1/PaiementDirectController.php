<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\Models\User;
use App\Domain\Payments\Actions\CancelDirectPayment;
use App\Domain\Payments\Actions\ConfirmDirectPayment;
use App\Domain\Payments\Actions\ContestDirectPayment;
use App\Domain\Payments\Actions\DeclareDirectPayment;
use App\Domain\Payments\Data\PaymentChannel;
use App\Domain\Payments\Data\PaymentMethod;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use App\Domain\Payments\Services\DirectPaymentSettlement;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Support\Money;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Paiement direct d'une intervention (ADR-0047).
 *
 * Le client choisit son moyen — espèces, Orange Money ou MTN MoMo — et le
 * paiement n'est réglé qu'avec la confirmation des deux parties. Aucune route
 * n'accepte de montant : c'est le total ferme du ticket qui est payé.
 */
final class PaiementDirectController extends Controller
{
    /** État du paiement de l'intervention, et ce que l'utilisateur peut faire. */
    public function etat(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('regler', $ticket);

        return response()->json($this->presenter($ticket, $this->utilisateur($request)));
    }

    /** Déclarer un paiement : le client choisit le moyen, le technicien déclare des espèces reçues. */
    public function declarer(Request $request, Ticket $ticket, DeclareDirectPayment $action): JsonResponse
    {
        Gate::authorize('regler', $ticket);

        $donnees = $request->validate([
            'moyen' => ['required', Rule::enum(PaymentMethod::class)],
            'reference_transaction' => ['nullable', 'string', 'max:60'],
        ], attributes: ['moyen' => 'moyen de paiement', 'reference_transaction' => 'référence de transaction']);

        try {
            $action->execute(
                $ticket,
                $this->utilisateur($request),
                PaymentMethod::from($donnees['moyen']),
                $donnees['reference_transaction'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Paiement déclaré. Il attend la confirmation de l\'autre partie.',
        ] + $this->presenter($ticket->fresh() ?? $ticket, $this->utilisateur($request)), 201);
    }

    public function confirmer(Request $request, Ticket $ticket, ConfirmDirectPayment $action): JsonResponse
    {
        Gate::authorize('regler', $ticket);

        try {
            $paiement = $action->execute($ticket, $this->utilisateur($request));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $paiement->status === PaymentStatus::CONFIRMEE
                ? 'Paiement confirmé par les deux parties. L\'intervention est clôturée.'
                : 'Confirmation enregistrée. Elle attend celle de l\'autre partie.',
        ] + $this->presenter($ticket->fresh() ?? $ticket, $this->utilisateur($request)));
    }

    public function contester(Request $request, Ticket $ticket, ContestDirectPayment $action): JsonResponse
    {
        Gate::authorize('regler', $ticket);

        $donnees = $request->validate([
            'motif' => ['required', 'string', 'min:'.ContestDirectPayment::MOTIF_MIN, 'max:500'],
        ], attributes: ['motif' => 'motif']);

        try {
            $action->execute($ticket, $this->utilisateur($request), $donnees['motif']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Contestation envoyée. Le support Parema vous contacte tous les deux sous 24 h.',
        ] + $this->presenter($ticket->fresh() ?? $ticket, $this->utilisateur($request)));
    }

    public function annuler(Request $request, Ticket $ticket, CancelDirectPayment $action): JsonResponse
    {
        Gate::authorize('regler', $ticket);

        try {
            $action->execute($ticket, $this->utilisateur($request));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Déclaration annulée. Tu peux en faire une nouvelle.',
        ] + $this->presenter($ticket->fresh() ?? $ticket, $this->utilisateur($request)));
    }

    private function utilisateur(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    /** @return array<string, mixed> */
    private function presenter(Ticket $ticket, User $utilisateur): array
    {
        $role = DirectPaymentSettlement::roleDe($ticket, $utilisateur);

        /** @var Payment|null $courant */
        $courant = Payment::query()
            ->where('ticket_id', $ticket->getKey())
            ->whereNotIn('status', [PaymentStatus::ANNULEE->value, PaymentStatus::ECHOUEE->value, PaymentStatus::EXPIREE->value])
            ->with('events')
            ->latest('id')
            ->first();

        $terminee = $ticket->state->etat() === TicketState::TERMINEE && ! $ticket->is_warranty;
        $enAttente = $courant?->status === PaymentStatus::ATTENTE_CONFIRMATION && $courant->estDirect();
        $maConfirmation = $role === ActorType::CLIENT ? $courant?->client_confirmed_at : $courant?->technician_confirmed_at;

        return [
            'total_gnf' => (int) $ticket->total_gnf,
            'total_formate' => Money::format((int) $ticket->total_gnf),
            'mon_role' => $role?->value,
            'moyens' => array_map(static fn (PaymentMethod $m): array => [
                'code' => $m->value,
                'libelle' => $m->label(),
            ], $role === ActorType::TECHNICIEN ? [PaymentMethod::ESPECES] : PaymentMethod::cases()),
            'actions' => [
                'declarer' => $terminee && $courant === null,
                'confirmer' => $enAttente && $maConfirmation === null,
                'contester' => $enAttente && $maConfirmation === null,
                'annuler' => $enAttente && $courant->declared_by === $role,
            ],
            'paiement' => $courant === null ? null : [
                'reference' => $courant->provider_ref,
                'canal' => $courant->channel->value,
                'canal_libelle' => $courant->channel->label(),
                'moyen' => $courant->method->value,
                'moyen_libelle' => $courant->method->label(),
                'montant_gnf' => $courant->amount_gnf,
                'statut' => $courant->status->value,
                'statut_libelle' => $courant->status->label(),
                'declare_par' => $courant->declared_by?->value,
                'reference_transaction' => $courant->transaction_ref,
                'confirme_client_le' => $courant->client_confirmed_at?->toIso8601String(),
                'confirme_technicien_le' => $courant->channel === PaymentChannel::DIRECT
                    ? $courant->technician_confirmed_at?->toIso8601String()
                    : null,
                'en_attente_de' => $courant->attenteDe()?->value,
                'contestation' => $courant->contested_at === null ? null : [
                    'par' => $courant->contested_by?->value,
                    'motif' => $courant->contest_reason,
                    'le' => $courant->contested_at->toIso8601String(),
                ],
                'historique' => $courant->events
                    ->filter(static fn (PaymentEvent $e): bool => $role !== ActorType::CLIENT || $e->type->visiblePourLeClient())
                    ->values()
                    ->map(static fn (PaymentEvent $e): array => [
                        'type' => $e->type->value,
                        'libelle' => $e->type->label(),
                        'acteur' => $e->actor_type->value,
                        'le' => $e->created_at->toIso8601String(),
                    ])
                    ->all(),
            ],
        ];
    }
}
