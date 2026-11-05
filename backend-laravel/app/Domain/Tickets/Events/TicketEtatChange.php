<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Events;

use App\Domain\Tickets\Data\TicketState;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Changement d'état d'un ticket, diffusé aux deux parties (D4).
 *
 * Le pendant mobile de `TicketTransitioned`, avec une différence de nature :
 * c'est un **signal**, pas une donnée. Il dit « relis ce ticket », et
 * l'application relit `/tickets/{id}`, qui reste la seule source de vérité.
 * Porter ici le total ou la part technicien obligerait à recopier les règles de
 * ce que chaque partie a le droit de voir — et la recopie finirait par fuir.
 *
 * Aucun modèle n'est transporté, pour la même raison que pour les positions :
 * un signal sérialisé avec son modèle serait relu en base au moment de partir,
 * dans un état qui n'est peut-être déjà plus celui annoncé.
 */
final class TicketEtatChange implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public readonly int $ticketId,
        public readonly TicketState $etat,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('ticket.'.$this->ticketId)];
    }

    public function broadcastAs(): string
    {
        return 'ticket.etat';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->ticketId,
            'etat' => $this->etat->value,
            'horodatage' => now()->toIso8601String(),
        ];
    }
}
