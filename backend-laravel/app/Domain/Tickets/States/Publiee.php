<?php

declare(strict_types=1);

namespace App\Domain\Tickets\States;

/** Diffusée : le moteur de matching cherche un technicien. */
final class Publiee extends TicketStatus
{
    public static string $name = 'PUBLIEE';
}
