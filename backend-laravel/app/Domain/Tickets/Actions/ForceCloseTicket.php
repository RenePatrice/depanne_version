<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Actions;

use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Wallet\Services\EscrowService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Clôture forcée par le support (§6).
 *
 * Sert quand le client ne valide jamais et que la libération automatique à 24 h
 * n'a pas pu s'exécuter. Clôturer, c'est donc aussi **libérer** : un ticket
 * fermé dont l'argent resterait en séquestre laisserait le technicien impayé
 * sans que plus rien ne le rattrape, le job de libération étant déjà passé.
 *
 * Un ticket en litige ne se clôture pas ici : c'est la décision du litige qui
 * règle le séquestre et motive l'issue. Le fermer en marge laisserait la
 * réclamation ouverte et l'argent gelé.
 */
final class ForceCloseTicket
{
    public function __construct(
        private readonly TransitionTicket $transition,
        private readonly EscrowService $sequestre,
    ) {}

    public function execute(Ticket $ticket, string $motif, int $adminId): Ticket
    {
        if ($ticket->state->etat() === TicketState::LITIGE_OUVERT) {
            throw new DomainException(
                'Un litige est ouvert sur ce ticket : la clôture passe par la décision du litige.'
            );
        }

        if (! $ticket->state->peutAllerVers(TicketState::CLOTUREE)) {
            throw new DomainException(
                'Un ticket en '.$ticket->state->label().' ne peut pas être clôturé directement.'
            );
        }

        $ticket = DB::transaction(function () use ($ticket, $motif, $adminId): Ticket {
            /** @var Payment|null $sequestre */
            $sequestre = Payment::query()
                ->where('ticket_id', $ticket->getKey())
                ->where('status', PaymentStatus::CAPTUREE->value)
                ->first();

            // La libération fait elle-même passer le ticket en CLOTUREE.
            if ($sequestre !== null) {
                $this->sequestre->liberer($sequestre, ActorType::ADMIN, $adminId);
                $ticket->refresh();
            }

            if ($ticket->state->peutAllerVers(TicketState::CLOTUREE)) {
                $ticket = $this->transition->execute(
                    $ticket,
                    TicketState::CLOTUREE,
                    ActorType::ADMIN,
                    $adminId,
                    ['motif' => $motif, 'origine' => 'cloture-forcee'],
                );
            }

            return $ticket;
        });

        activity('tickets')
            ->performedOn($ticket)
            ->withProperties(['motif' => $motif])
            ->log('Clôture forcée du ticket '.$ticket->reference);

        return $ticket;
    }
}
