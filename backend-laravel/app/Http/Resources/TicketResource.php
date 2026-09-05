<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Money;
use App\Support\Telephone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ticket vu depuis l'application mobile.
 *
 * Deux précautions valent d'être notées.
 *
 * Le numéro du technicien n'est exposé qu'une fois l'intervention engagée, et
 * masqué sinon : avant acceptation, personne n'a de raison de l'avoir, et le
 * §7.3 impose que les échanges restent dans l'application.
 *
 * L'adresse renvoyée est l'**instantané** du ticket, pas l'adresse vivante :
 * le client peut avoir supprimé ou modifié celle-ci entre-temps, et
 * l'historique doit rester lisible tel qu'il s'est passé.
 *
 * @mixin Ticket
 */
final class TicketResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $etat = $this->state->etat();
        $estLeClient = (int) $this->client_id === (int) $request->user()?->getKey();

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'etat' => $etat->value,
            'etat_libelle' => $etat->label(),
            'etat_actif' => $etat->isActive(),
            'etat_final' => $etat->isFinal(),
            'annulable' => $this->state->peutAllerVers(
                $estLeClient
                    ? TicketState::ANNULEE_CLIENT
                    : TicketState::ANNULEE_TECHNICIEN
            ),

            'prestation' => new ServiceResource($this->whenLoaded('service')),
            'description' => $this->problem_description,
            'photos' => $this->photos ?? [],

            'adresse' => $this->address_snapshot,
            'distance_km' => $this->distance_km === null ? null : (float) $this->distance_km,
            'distance_estimee' => $this->distance_is_estimated,

            'prix' => [
                'prestation_gnf' => $this->base_price_gnf,
                'majoration_proximite_gnf' => $this->short_trip_uplift_gnf,
                'deplacement_gnf' => $this->travel_fee_gnf,
                'supplement_gnf' => $this->extra_fee_gnf,
                'total_gnf' => $this->total_gnf,
                'total_formate' => Money::format($this->total_gnf),
                // Tant qu'aucun technicien n'a accepté, le déplacement n'est
                // qu'une estimation : la distance facturée est la sienne.
                'ferme' => $this->accepted_at !== null,
                // La part technicien n'a de sens que pour lui.
                'net_technicien_gnf' => $this->when(! $estLeClient, $this->technician_net_gnf),
            ],

            'frais_annulation_gnf' => $this->cancellation_fee_gnf,
            'motif_annulation' => $this->cancellation_reason,
            'diagnostic' => $this->diagnosis,

            'client' => $this->when(! $estLeClient && $this->relationLoaded('client'), fn (): ?array => $this->partie($this->client, $etat)),
            'technicien' => $this->when($estLeClient && $this->relationLoaded('technician'), fn (): ?array => $this->partie($this->technician, $etat)),

            'jalons' => array_filter([
                'publiee_le' => $this->published_at?->toIso8601String(),
                'acceptee_le' => $this->accepted_at?->toIso8601String(),
                'en_route_le' => $this->en_route_at?->toIso8601String(),
                'arrivee_le' => $this->arrived_at?->toIso8601String(),
                'demarree_le' => $this->started_at?->toIso8601String(),
                'terminee_le' => $this->completed_at?->toIso8601String(),
                'payee_le' => $this->paid_at?->toIso8601String(),
                'cloturee_le' => $this->closed_at?->toIso8601String(),
            ]),

            'creee_le' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * L'autre partie. Le téléphone n'apparaît en clair qu'à partir du moment
     * où les deux doivent pouvoir se joindre — c'est-à-dire une fois le ticket
     * accepté et tant qu'il est actif.
     *
     * @return array<string, mixed>|null
     */
    private function partie(mixed $utilisateur, TicketState $etat): ?array
    {
        if ($utilisateur === null) {
            return null;
        }

        return [
            'id' => $utilisateur->id,
            'nom_complet' => $utilisateur->full_name,
            'avatar_url' => $utilisateur->avatar_url,
            'telephone' => $etat->isActive()
                ? $utilisateur->phone
                : Telephone::masquer($utilisateur->phone),
        ];
    }
}
