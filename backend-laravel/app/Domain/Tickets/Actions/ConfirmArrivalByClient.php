<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use DomainException;

/**
 * Le client confirme lui-même l'arrivée du technicien (lot 2).
 *
 * C'est la sortie de secours du code d'arrivée : le technicien a manqué ses
 * cinq essais, ou le client n'arrive pas à lire son code. Il est devant sa
 * porte, il le voit — sa parole vaut le code. Le journal du ticket retient que
 * l'arrivée a été confirmée par le client, pas vérifiée par le code.
 *
 * Seulement depuis EN_ROUTE : la machine à états ne connaît pas de passage
 * direct ACCEPTEE → SUR_PLACE, et un technicien qui n'a pas dit « je pars »
 * n'est vraisemblablement pas devant la porte.
 */
final class ConfirmArrivalByClient
{
    public function __construct(private readonly TransitionTicket $transition) {}

    /** @throws DomainException */
    public function execute(Ticket $ticket, User $client): Ticket
    {
        if (! $ticket->estLeClient($client)) {
            throw new DomainException('Seul le client peut confirmer l\'arrivée du technicien.');
        }

        if ($ticket->state->etat() !== TicketState::EN_ROUTE) {
            throw new DomainException('Le technicien n\'est pas en route : il n\'y a pas d\'arrivée à confirmer.');
        }

        return $this->transition->execute(
            $ticket,
            TicketState::SUR_PLACE,
            ActorType::CLIENT,
            (int) $client->getKey(),
            ['code_arrivee' => 'confirme_par_client'],
        );
    }
}
