<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Payments\Data\PaymentMethod;
use App\Domain\Payments\Models\Payment;
use App\Domain\Tickets\Models\Ticket;
use DomainException;

/**
 * Le technicien déclare avoir reçu des **espèces** (#1).
 *
 * Conservé pour la route historique `paiement-especes`, utilisée par
 * l'application déjà installée. Le travail est fait par `DeclareDirectPayment`
 * (ADR-0047) : la déclaration vaut confirmation côté technicien, et le client
 * doit confirmer à son tour pour que le paiement soit réglé.
 */
final class DeclareCashPayment
{
    public function __construct(private readonly DeclareDirectPayment $declarer) {}

    /** @throws DomainException */
    public function execute(Ticket $ticket, User $technicien): Payment
    {
        if (! $ticket->estLeTechnicien($technicien)) {
            throw new DomainException('Cette intervention ne t\'est pas attribuée.');
        }

        return $this->declarer->execute($ticket, $technicien, PaymentMethod::ESPECES);
    }
}
