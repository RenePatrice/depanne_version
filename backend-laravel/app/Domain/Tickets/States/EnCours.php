<?php

declare(strict_types=1);

namespace App\Domain\Tickets\States;

/** Le travail a commencé. */
final class EnCours extends TicketStatus
{
    public static string $name = 'EN_COURS';
}
