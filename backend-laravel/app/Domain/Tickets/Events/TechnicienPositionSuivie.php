<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Position du technicien, diffusée aux parties de **son** intervention.
 *
 * Exception assumée à « le WebSocket ne transporte que des signaux » (D4) :
 * relire le ticket toutes les huit secondes pour un point sur une carte
 * coûterait du forfait au client pour rien, et une position n'a pas de règle
 * de visibilité à recopier — elle est la même pour les deux parties.
 *
 * Ne part que pendant le trajet (`SuiviTechnicien::ETATS_SUIVIS`) : une fois le
 * technicien sur place, le client n'a plus de raison de le suivre.
 * `ShouldBroadcastNow`, comme la carte du back-office : une position mise en
 * file arriverait après la suivante.
 */
final class TechnicienPositionSuivie implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly int $ticketId,
        public readonly float $latitude,
        public readonly float $longitude,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('ticket.'.$this->ticketId)];
    }

    public function broadcastAs(): string
    {
        return 'technicien.position';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'le' => now()->toIso8601String(),
        ];
    }
}
