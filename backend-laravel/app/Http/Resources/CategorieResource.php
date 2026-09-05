<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Catalog\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Catégorie du catalogue, avec ses prestations imbriquées : l'application
 * mobile affiche la liste complète en une seule requête, y compris hors
 * réseau après mise en cache.
 *
 * @mixin ServiceCategory
 */
final class CategorieResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code->value,
            'nom' => $this->name,
            'description' => $this->description,
            'icone' => $this->icon,
            'couleur' => $this->color,
            'prestations' => ServiceResource::collection($this->whenLoaded('services')),
        ];
    }
}
