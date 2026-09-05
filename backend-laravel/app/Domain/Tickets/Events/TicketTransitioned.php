<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Events;

use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Money;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Transition d'état d'un ticket, diffusée au back-office (§8.1).
 *
 * L'événement porte **ce qu'il faut afficher**, pas le modèle : le
 * back-office ne doit pas avoir à requêter la base à chaque message reçu, et
 * la charge utile ne transporte aucune donnée superflue.
 */
final class TicketTransitioned implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly ?TicketState $depuis,
        public readonly TicketState $vers,
        public readonly ActorType $acteur,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('back-office')];
    }

    public function broadcastAs(): string
    {
        return 'ticket.transition';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $position = $this->ticket->location;

        return [
            'id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'depuis' => $this->depuis?->value,
            'etat' => $this->vers->value,
            'etatLibelle' => $this->vers->label(),
            'ton' => $this->vers->color(),
            'active' => $this->vers->isActive(),
            'acteur' => $this->acteur->label(),
            'montant' => $this->ticket->total_gnf,
            'montantFormate' => Money::format($this->ticket->total_gnf),
            'latitude' => $position?->getLatitude(),
            'longitude' => $position?->getLongitude(),
            'horodatage' => now()->toIso8601String(),
        ];
    }
}
