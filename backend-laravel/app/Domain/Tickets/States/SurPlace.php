<?php

declare(strict_types=1);

namespace App\Domain\Tickets\States;

/** Le technicien est à l'adresse indiquée. */
final class SurPlace extends TicketStatus
{
    public static string $name = 'SUR_PLACE';
}
