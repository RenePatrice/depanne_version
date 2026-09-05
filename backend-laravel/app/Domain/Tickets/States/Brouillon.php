<?php

declare(strict_types=1);

namespace App\Domain\Tickets\States;

/** Demande en cours de saisie, jamais vue par un technicien. */
final class Brouillon extends TicketStatus
{
    public static string $name = 'BROUILLON';
}
