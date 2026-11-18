<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Tickets\Models\Ticket;
use DomainException;

/**
 * Le client confirme avoir payé en espèces (#1).
 *
 * Conservé pour la route historique `paiement-especes/confirmer`, réservée au
 * client. Le travail est fait par `ConfirmDirectPayment` (ADR-0047) : avec la
 * déclaration du technicien, c'est la seconde confirmation, celle qui règle le
 * paiement — commission inscrite en dette, intervention clôturée.
 */
final class ConfirmCashReceipt
{
    public function __construct(private readonly ConfirmDirectPayment $confirmer) {}

    /** @throws DomainException */
    public function execute(Ticket $ticket, User $client): Payment
    {
        if (! $ticket->estLeClient($client)) {
            throw new DomainException('Cette demande ne t\'appartient pas.');
        }

        return $this->confirmer->execute($ticket, $client);
    }
}
