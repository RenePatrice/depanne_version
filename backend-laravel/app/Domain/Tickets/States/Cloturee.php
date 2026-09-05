<?php

declare(strict_types=1);

namespace App\Domain\Tickets\States;

/** Fonds libérés au technicien. État final nominal. */
final class Cloturee extends TicketStatus
{
    public static string $name = 'CLOTUREE';
}
