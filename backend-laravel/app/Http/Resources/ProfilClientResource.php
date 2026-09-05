<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Accounts\Models\ClientProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClientProfile */
final class ProfilClientResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'points_fidelite' => $this->loyalty_points,
            'interventions' => $this->tickets_count,
            'adresse_par_defaut_id' => $this->default_address_id,
        ];
    }
}
