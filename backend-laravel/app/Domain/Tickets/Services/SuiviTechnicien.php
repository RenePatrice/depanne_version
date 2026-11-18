<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Services;

use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;

/**
 * Ce que le client voit du trajet de son technicien.
 *
 * Deux limites, toutes deux de minimisation : on ne montre la position que
 * **pendant le trajet** (acceptée → en route), et seulement si elle est
 * **fraîche**. Une position vieille d'une heure dessinée sur une carte
 * mentirait sur l'endroit où se trouve quelqu'un.
 */
final class SuiviTechnicien
{
    /** @var array<int, TicketState> */
    public const ETATS_SUIVIS = [TicketState::ACCEPTEE, TicketState::EN_ROUTE];

    public const FRAICHEUR_MINUTES = 15;

    public static function suiviActif(Ticket $ticket): bool
    {
        return in_array($ticket->state->etat(), self::ETATS_SUIVIS, true);
    }

    /** @return array{latitude: float, longitude: float, le: string}|null */
    public function position(Ticket $ticket): ?array
    {
        if (! self::suiviActif($ticket) || $ticket->technician_id === null) {
            return null;
        }

        $ticket->loadMissing('technician.technicianProfile');
        $profil = $ticket->technician?->technicianProfile;
        $point = $profil?->last_known_location;
        $le = $profil?->last_position_at;

        if ($point === null || $le === null || $le->lt(now()->subMinutes(self::FRAICHEUR_MINUTES))) {
            return null;
        }

        return [
            'latitude' => $point->getLatitude(),
            'longitude' => $point->getLongitude(),
            'le' => $le->toIso8601String(),
        ];
    }
}
