<?php

declare(strict_types=1);

namespace App\Domain\Tickets\States;

/** Le technicien a démarré son trajet ; l'annulation devient payante. */
final class EnRoute extends TicketStatus
{
    public static string $name = 'EN_ROUTE';
}
