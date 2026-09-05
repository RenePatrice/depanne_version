<?php

declare(strict_types=1);

namespace App\Domain\Chat\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Message poussé au destinataire et au back-office.
 *
 * Il ne transporte que le **texte masqué**. L'original ne quitte jamais la
 * base : le diffuser sur un canal, même privé, reviendrait à annuler le
 * masquage pour qui écoute.
 *
 * Diffusé immédiatement : un message de chat mis en file arriverait après le
 * suivant, et une conversation dans le désordre est pire que pas de temps réel
 * du tout.
 */
final class MessageEnvoye implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly int $ticketId,
        public readonly int $messageId,
        public readonly int $expediteurId,
        public readonly string $expediteurNom,
        public readonly string $contenu,
        public readonly ?int $destinataireId,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        $canaux = [new PrivateChannel('ticket.'.$this->ticketId)];

        if ($this->destinataireId !== null) {
            // Le canal personnel sert quand l'application n'a pas la
            // conversation ouverte : c'est lui qui fait apparaître la pastille.
            $canaux[] = new PrivateChannel('utilisateur.'.$this->destinataireId);
        }

        return $canaux;
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->messageId,
            'ticket_id' => $this->ticketId,
            'expediteur_id' => $this->expediteurId,
            'expediteur' => $this->expediteurNom,
            'contenu' => $this->contenu,
            'horodatage' => now()->toIso8601String(),
        ];
    }
}
