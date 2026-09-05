<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Accounts\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Adresse du carnet client.
 *
 * La couverture géographique est **passée** à la ressource plutôt que calculée
 * ici : chaque test d'appartenance est une requête PostGIS, et une ressource ne
 * doit pas déclencher de requête pendant le rendu. Elle est portée par une
 * propriété typée plutôt que posée sur le modèle : un attribut inventé sur un
 * Eloquent finit tôt ou tard par partir en base.
 *
 * @mixin Address
 */
final class AdresseResource extends JsonResource
{
    private ?bool $couverte = null;

    /** Renseigne la couverture, déjà calculée par l'appelant. */
    public function couverte(bool $couverte): self
    {
        $this->couverte = $couverte;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'libelle' => $this->label,
            'adresse' => $this->formatted_address,
            'repere' => $this->landmark,
            'latitude' => $this->location?->getLatitude(),
            'longitude' => $this->location?->getLongitude(),
            'par_defaut' => $this->is_default,
            'couverte' => $this->when($this->couverte !== null, fn (): bool => (bool) $this->couverte),
        ];
    }
}
