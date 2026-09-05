<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Notification poussée sur le canal privé de son destinataire.
 *
 * C'est le second chemin de livraison, à côté du push. Tant que Firebase n'est
 * pas branché, c'est même le seul qui arrive en temps réel : une application
 * ouverte reçoit la sollicitation par le WebSocket, sans dépendre d'un service
 * tiers indisponible en local.
 *
 * Diffusée **immédiatement** et sans modèle Eloquent : une sollicitation qui
 * attendrait son tour dans la file mangerait la fenêtre de 45 secondes qu'elle
 * annonce.
 */
final class NotificationPoussee implements ShouldBroadcastNow
{
    use Dispatchable;

    /** @param array<string, mixed> $donnees */
    public function __construct(
        public readonly int $destinataireId,
        public readonly string $identifiant,
        public readonly string $type,
        public readonly string $titre,
        public readonly string $corps,
        public readonly array $donnees = [],
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('utilisateur.'.$this->destinataireId)];
    }

    public function broadcastAs(): string
    {
        return 'notification';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->identifiant,
            'type' => $this->type,
            'titre' => $this->titre,
            'corps' => $this->corps,
            'donnees' => $this->donnees,
            'horodatage' => now()->toIso8601String(),
        ];
    }
}
