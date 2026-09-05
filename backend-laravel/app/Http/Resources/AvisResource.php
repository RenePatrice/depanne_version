<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Reviews\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Avis affiché sur la fiche d'un technicien.
 *
 * Seul le prénom du client apparaît. Le nom complet ne servirait à rien au
 * lecteur et exposerait des clients dans une ville où tout le monde se
 * connaît — un avis négatif ne doit pas être un risque social.
 *
 * @mixin Review
 */
final class AvisResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'note' => $this->rating,
            'etiquettes' => $this->tags ?? [],
            'commentaire' => $this->comment,
            'client' => $this->whenLoaded('client', fn (): string => $this->client->firstName()),
            'publie_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
