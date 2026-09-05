<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Chat\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Message tel que l'application le voit.
 *
 * `content` est déjà la version masquée : l'original n'est pas exclu ici mais
 * dans le modèle, par `$hidden`. Le mettre à un seul endroit vaut mieux que de
 * s'en souvenir à chaque sérialisation — c'est la ligne qu'on oublie.
 *
 * `masque` est renvoyé pour que l'application puisse marquer visuellement le
 * message : voir que son propre message a été filtré est plus dissuasif qu'un
 * avertissement lu une fois.
 *
 * @mixin Message
 */
final class MessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contenu' => $this->content,
            'expediteur_id' => $this->sender_id,
            'expediteur' => $this->whenLoaded('sender', fn (): string => $this->sender->full_name),
            'de_moi' => (int) $this->sender_id === (int) $request->user()?->getKey(),
            'masque' => $this->is_flagged,
            'lu' => $this->read_at !== null,
            'envoye_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
