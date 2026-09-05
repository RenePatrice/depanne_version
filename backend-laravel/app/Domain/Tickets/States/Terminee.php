<?php

declare(strict_types=1);

namespace App\Domain\Tickets\States;

/** Travail achevé, en attente de règlement. */
final class Terminee extends TicketStatus
{
    public static string $name = 'TERMINEE';
}
