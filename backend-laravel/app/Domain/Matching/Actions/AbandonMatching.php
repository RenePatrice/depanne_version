<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Tickets\Actions\TransitionTicket;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Support\Facades\Log;

/**
 * Fin de recherche sans technicien (§8.3, étape 6).
 *
 * Le ticket passe en SANS_REPONSE, le client est prévenu et le support alerté.
 * Cet état n'est pas un échec technique mais une information commerciale : une
 * série de SANS_REPONSE sur un créneau dit que le parc de techniciens est trop
 * mince à ce moment-là, et c'est exactement ce que le pilote doit mesurer.
 */
final class AbandonMatching
{
    public function __construct(
        private readonly TransitionTicket $transition,
        private readonly SendNotification $notifier,
    ) {}

    public function execute(Ticket $ticket): ?Ticket
    {
        if (! $ticket->state->peutAllerVers(TicketState::SANS_REPONSE)) {
            return null;
        }

        $ticket = $this->transition->execute(
            $ticket,
            TicketState::SANS_REPONSE,
            ActorType::SYSTEME,
            null,
            ['motif' => 'Aucun technicien disponible après tous les cycles.'],
        );

        $this->notifier->execute(
            $ticket->client,
            NotificationType::AUCUN_TECHNICIEN,
            ['reference' => $ticket->reference],
            ['ticket_id' => $ticket->getKey(), 'reference' => $ticket->reference],
        );

        // Trace destinée au support : le back-office la reprend dans le flux
        // d'activité, et elle alimente l'indicateur de couverture du pilote.
        activity('matching')
            ->performedOn($ticket)
            ->withProperties([
                'reference' => $ticket->reference,
                'zone' => $ticket->zone?->name,
                'prestation' => $ticket->service?->name,
            ])
            ->log('Aucun technicien trouvé pour '.$ticket->reference);

        Log::warning('Matching : aucun technicien trouvé.', [
            'ticket' => $ticket->reference,
            'zone' => $ticket->zone?->code,
        ]);

        return $ticket;
    }
}
