<?php

declare(strict_types=1);

namespace App\Domain\Tickets\States;

/** Un technicien s'est engagé ; le prix est désormais ferme. */
final class Acceptee extends TicketStatus
{
    public static string $name = 'ACCEPTEE';
}
