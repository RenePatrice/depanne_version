<?php

declare(strict_types=1);

namespace App\Domain\Tickets\States;

/** Un supplément est proposé au client, qui doit trancher. */
final class DiagnosticEnAttente extends TicketStatus
{
    public static string $name = 'DIAGNOSTIC_EN_ATTENTE';
}
