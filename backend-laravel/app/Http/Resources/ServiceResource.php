<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Catalog\Models\Service;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Prestation du catalogue.
 *
 * `prix_formate` est calculé côté serveur : l'application mobile ne doit pas
 * avoir à réimplémenter le séparateur de milliers guinéen, ni pouvoir se
 * tromper dessus.
 *
 * @mixin Service
 */
final class ServiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'nom' => $this->name,
            'description' => $this->description,
            'inclus' => $this->included ?? [],
            'exclus' => $this->excluded ?? [],
            'prix_gnf' => $this->base_price_gnf,
            'prix_formate' => Money::format($this->base_price_gnf),
            'duree_estimee_min' => $this->estimated_duration_min,
            // Type et photos autorisées (#2) : l'application n'affiche le bouton
            // photo que pour un dépannage, où elles aident à estimer la panne.
            'type' => $this->type->value,
            'type_libelle' => $this->type->label(),
            'photos_autorisees' => $this->type->photosAutorisees(),
        ];
    }
}
