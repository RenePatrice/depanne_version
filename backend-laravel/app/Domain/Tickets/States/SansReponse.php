<?php

declare(strict_types=1);

namespace App\Domain\Tickets\States;

/** Aucun technicien n'a répondu après tous les cycles ; le support est alerté. */
final class SansReponse extends TicketStatus
{
    public static string $name = 'SANS_REPONSE';
}
