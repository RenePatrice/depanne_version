<?php

declare(strict_types=1);

namespace App\Domain\Tickets\States;

/** Une réclamation suspend la libération des fonds. */
final class LitigeOuvert extends TicketStatus
{
    public static string $name = 'LITIGE_OUVERT';
}
