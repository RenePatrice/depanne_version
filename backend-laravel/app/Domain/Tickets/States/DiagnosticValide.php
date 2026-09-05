<?php

declare(strict_types=1);

namespace App\Domain\Tickets\States;

/** Le client a accepté le supplément : le total a été revu. */
final class DiagnosticValide extends TicketStatus
{
    public static string $name = 'DIAGNOSTIC_VALIDE';
}
