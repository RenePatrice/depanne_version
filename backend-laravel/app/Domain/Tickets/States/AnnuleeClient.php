<?php

declare(strict_types=1);

namespace App\Domain\Tickets\States;

/** Annulée par le client, avec ou sans frais selon l'avancement. */
final class AnnuleeClient extends TicketStatus
{
    public static string $name = 'ANNULEE_CLIENT';
}
