<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Matching\Data\MatchResponse;
use App\Domain\Matching\Models\MatchAttempt;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sollicitation présentée au technicien (§8.3).
 *
 * Elle porte tout ce qu'il faut pour décider en quelques secondes : la
 * prestation, la distance, ce qu'il touchera, et le temps qu'il lui reste.
 *
 * `expire_dans_s` est recalculé à chaque lecture plutôt que renvoyé comme un
 * horodatage : l'horloge du téléphone peut être décalée de plusieurs minutes,
 * et un compte à rebours fondé dessus se tromperait de fenêtre.
 *
 * L'adresse exacte du client n'apparaît **pas** avant l'acceptation — seuls le
 * quartier et la distance. C'est ce qui empêche de collecter des adresses en
 * refusant systématiquement les demandes.
 *
 * @mixin MatchAttempt
 */
final class SollicitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $ticket = $this->ticket;
        $accepte = $this->response === MatchResponse::ACCEPTE;

        return [
            'id' => $this->id,
            'reference' => $ticket->reference,
            'prestation' => $ticket->service?->name,
            'description' => $ticket->problem_description,
            'photos' => $ticket->photos ?? [],

            'distance_km' => (float) $this->distance_km,
            'quartier' => $this->quartier($ticket->address_snapshot),
            // L'adresse complète n'est livrée qu'une fois la demande acceptée.
            'adresse' => $accepte ? $ticket->address_snapshot : null,

            'total_gnf' => $ticket->total_gnf,
            'total_formate' => Money::format($ticket->total_gnf),
            'net_technicien_gnf' => $ticket->technician_net_gnf,
            'net_formate' => Money::format($ticket->technician_net_gnf),

            'reponse' => $this->response->value,
            'reponse_libelle' => $this->response->label(),
            'expire_dans_s' => $this->expiresDansSecondes(),

            'cycle' => $this->cycle,
            'rayon_km' => $this->radius_km,
            'score' => (float) $this->score,
            // Le détail n'est utile qu'a posteriori : dans l'urgence d'une
            // sollicitation vivante, il encombrerait la charge utile.
            'score_detail' => $this->when($request->boolean('historique'), $this->score_breakdown),

            'sollicite_le' => $this->notified_at->toIso8601String(),
            'repondu_le' => $this->responded_at?->toIso8601String(),
        ];
    }

    private function expiresDansSecondes(): int
    {
        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }

    /**
     * Le repère ou le quartier, jamais le numéro de rue.
     *
     * @param  array<string, mixed>|null  $instantane
     */
    private function quartier(?array $instantane): ?string
    {
        if ($instantane === null) {
            return null;
        }

        $repere = $instantane['landmark'] ?? null;

        if (is_string($repere) && $repere !== '') {
            return $repere;
        }

        $adresse = $instantane['formatted_address'] ?? null;

        if (! is_string($adresse) || $adresse === '') {
            return null;
        }

        // « Kipé, Ratoma, Conakry » → « Kipé » : de quoi se repérer, pas de
        // quoi se présenter à la porte.
        return trim(explode(',', $adresse)[0]);
    }
}
