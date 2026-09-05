<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Catalog\Data\Specialty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TechnicianProfile */
final class ProfilTechnicienResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $position = $this->base_location;

        return [
            'specialites' => array_map(
                static fn (string $code): array => [
                    'code' => $code,
                    'libelle' => Specialty::tryFrom($code)?->label() ?? $code,
                ],
                $this->specialties ?? [],
            ),
            'verification' => [
                'statut' => $this->verification_status->value,
                'libelle' => $this->verification_status->label(),
                'peut_travailler' => $this->verification_status->canWork(),
                'motif_rejet' => $this->rejection_reason,
                'validee_le' => $this->verified_at?->toIso8601String(),
            ],
            'zone' => [
                'latitude' => $position?->getLatitude(),
                'longitude' => $position?->getLongitude(),
                'rayon_km' => $this->service_radius_km,
            ],
            'en_ligne' => $this->is_online,
            'statistiques' => [
                'note_moyenne' => round($this->rating_avg, 2),
                'note_appliquee' => $this->effectiveRating(),
                'avis' => $this->reviews_count,
                'interventions' => $this->jobs_completed,
                'taux_acceptation' => round($this->acceptance_rate, 4),
                'taux_annulation' => round($this->cancellation_rate, 4),
                'debutant' => $this->isNewcomer(),
            ],
        ];
    }
}
