<?php

declare(strict_types=1);

namespace App\Domain\Tickets\States;

/** Annulée par le technicien ; compte dans son taux d'annulation. */
final class AnnuleeTechnicien extends TicketStatus
{
    public static string $name = 'ANNULEE_TECHNICIEN';
}
