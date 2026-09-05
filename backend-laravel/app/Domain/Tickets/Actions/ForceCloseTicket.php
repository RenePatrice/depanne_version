<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Actions;

use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use DomainException;

/**
 * Clôture forcée par le support (§6).
 *
 * Sert quand le client ne valide jamais et que la libération automatique à 24 h
 * n'a pas pu s'exécuter. La répartition financière reste du ressort du domaine
 * Wallet (phase C5) : cette action ne fait que fermer le dossier et en laisser
 * la trace.
 */
final class ForceCloseTicket
{
    public function __construct(private readonly TransitionTicket $transition) {}

    public function execute(Ticket $ticket, string $motif, int $adminId): Ticket
    {
        if (! $ticket->state->peutAllerVers(TicketState::CLOTUREE)) {
            throw new DomainException(
                'Un ticket en '.$ticket->state->label().' ne peut pas être clôturé directement.'
            );
        }

        $ticket = $this->transition->execute(
            $ticket,
            TicketState::CLOTUREE,
            ActorType::ADMIN,
            $adminId,
            ['motif' => $motif, 'origine' => 'cloture-forcee'],
        );

        activity('tickets')
            ->performedOn($ticket)
            ->withProperties(['motif' => $motif])
            ->log('Clôture forcée du ticket '.$ticket->reference);

        return $ticket;
    }
}
