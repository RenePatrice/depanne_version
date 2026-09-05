<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Position d'un technicien, poussée au back-office.
 *
 * Diffusée **immédiatement** plutôt que par la file : une position mise en
 * queue arriverait après la suivante et ferait sauter le marqueur en arrière.
 *
 * L'événement ne transporte volontairement aucun modèle Eloquent : il est émis
 * jusqu'à toutes les huit secondes par technicien en intervention (§10), et
 * sérialiser un modèle à ce rythme coûterait plus que le message lui-même.
 */
final class TechnicianPositionUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly int $technicienId,
        public readonly string $nom,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly bool $enIntervention,
        public readonly ?string $ticketReference = null,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('back-office')];
    }

    public function broadcastAs(): string
    {
        return 'technicien.position';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->technicienId,
            'nom' => $this->nom,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'enIntervention' => $this->enIntervention,
            'ticket' => $this->ticketReference,
            'horodatage' => now()->toIso8601String(),
        ];
    }
}
