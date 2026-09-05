<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Accounts\Models\User;
use App\Support\Telephone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation d'un compte pour l'application mobile.
 *
 * @mixin User
 */
final class UtilisateurResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom_complet' => $this->full_name,
            'prenom' => $this->firstName(),
            'telephone' => $this->phone,
            'telephone_affichage' => Telephone::formatter($this->phone),
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'statut' => $this->status->value,
            'casquettes' => [
                'client' => $this->is_client,
                'technicien' => $this->is_technician,
            ],
            'profil_client' => new ProfilClientResource($this->whenLoaded('clientProfile')),
            'profil_technicien' => new ProfilTechnicienResource($this->whenLoaded('technicianProfile')),
            'inscrit_le' => $this->created_at?->toIso8601String(),
            'derniere_connexion' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
