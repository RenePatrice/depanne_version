<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use DomainException;

/**
 * Clôture d'un retour sous garantie (#4).
 *
 * C'est le client qui confirme que la panne est réglée, exactement comme il
 * valide une intervention payante — mais sans argent à libérer, le retour étant
 * gratuit. Le ticket passe directement de TERMINEE à CLOTUREE, le seul usage de
 * cette transition.
 */
final class CloseWarranty
{
    public function __construct(
        private readonly TransitionTicket $transition,
        private readonly SendNotification $notifier,
    ) {}

    /** @throws DomainException */
    public function execute(Ticket $garantie, User $client): Ticket
    {
        if (! $garantie->is_warranty) {
            throw new DomainException('Cette intervention n\'est pas un retour sous garantie.');
        }

        if (! $garantie->estLeClient($client)) {
            throw new DomainException('Cette intervention ne te concerne pas.');
        }

        if ($garantie->state->etat() !== TicketState::TERMINEE) {
            throw new DomainException(
                'La garantie ne peut être clôturée qu\'une fois le technicien repassé.'
            );
        }

        $garantie = $this->transition->execute(
            $garantie,
            TicketState::CLOTUREE,
            ActorType::CLIENT,
            (int) $client->getKey(),
            ['garantie' => true],
        );

        if ($garantie->technician_id !== null) {
            /** @var User $technicien */
            $technicien = $garantie->technician()->firstOrFail();

            $this->notifier->execute(
                $technicien,
                NotificationType::GARANTIE_RESOLUE,
                ['reference' => $garantie->reference],
                ['ticket_id' => $garantie->getKey()],
            );
        }

        return $garantie;
    }
}
