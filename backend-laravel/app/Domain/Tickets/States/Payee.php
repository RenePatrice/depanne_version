<?php

declare(strict_types=1);

namespace App\Domain\Tickets\States;

/** Réglé. Les fonds sont en séquestre jusqu'à la clôture. */
final class Payee extends TicketStatus
{
    public static string $name = 'PAYEE';
}
