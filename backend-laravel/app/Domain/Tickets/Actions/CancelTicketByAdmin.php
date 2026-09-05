<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Actions;

use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use DomainException;

/**
 * Annulation décidée par le support (§6, actions support).
 *
 * L'annulation est imputée au client ou au technicien selon ce que le support
 * constate : c'est cette imputation qui alimente le taux d'annulation du
 * technicien, donc son score de matching. Elle n'est jamais implicite.
 */
final class CancelTicketByAdmin
{
    public function __construct(private readonly TransitionTicket $transition) {}

    public function execute(Ticket $ticket, string $motif, bool $imputeAuTechnicien, int $adminId): Ticket
    {
        if (! $ticket->state->estAnnulable()) {
            throw new DomainException(
                'Ce ticket est en '.$ticket->state->label().' : il ne peut plus être annulé.'
            );
        }

        $cible = $imputeAuTechnicien
            ? TicketState::ANNULEE_TECHNICIEN
            : TicketState::ANNULEE_CLIENT;

        if (! $ticket->state->peutAllerVers($cible)) {
            throw new DomainException(sprintf(
                'Un ticket en %s ne peut pas être annulé au tort du %s.',
                $ticket->state->label(),
                $imputeAuTechnicien ? 'technicien' : 'client',
            ));
        }

        $ticket->forceFill(['cancellation_reason' => $motif])->save();

        $ticket = $this->transition->execute(
            $ticket,
            $cible,
            ActorType::ADMIN,
            $adminId,
            ['motif' => $motif, 'origine' => 'back-office'],
        );

        activity('tickets')
            ->performedOn($ticket)
            ->withProperties(['motif' => $motif, 'etat' => $cible->value])
            ->log('Annulation du ticket '.$ticket->reference);

        return $ticket;
    }
}
