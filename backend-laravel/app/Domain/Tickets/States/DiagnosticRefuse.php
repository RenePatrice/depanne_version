<?php

declare(strict_types=1);

namespace App\Domain\Tickets\States;

/** Le client refuse le supplément ; l'intervention se limite au devis initial. */
final class DiagnosticRefuse extends TicketStatus
{
    public static string $name = 'DIAGNOSTIC_REFUSE';
}
