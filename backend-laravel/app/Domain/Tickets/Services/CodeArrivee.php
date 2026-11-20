<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Services;

use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;

/**
 * Code d'arrivée (lot 2).
 *
 * Quatre chiffres, tirés à l'acceptation et montrés au seul client. Le
 * technicien les lui demande devant la porte : c'est la preuve qu'il est bien
 * là — pas à deux rues en train de cocher « arrivé » — et, pour le client, que
 * la personne qui sonne est bien celle que l'application a envoyée.
 *
 * Cinq essais. Quatre chiffres et cinq essais, c'est une chance sur deux mille
 * de tomber juste au hasard ; au-delà, la porte se ferme et seul le client
 * peut confirmer l'arrivée depuis son application.
 */
final class CodeArrivee
{
    public const LONGUEUR = 4;

    public const ESSAIS_MAX = 5;

    /** États où le code sert : le technicien est attendu, pas encore arrivé. */
    private const ETATS = [TicketState::ACCEPTEE, TicketState::EN_ROUTE];

    public static function generer(): string
    {
        return str_pad((string) random_int(0, 10 ** self::LONGUEUR - 1), self::LONGUEUR, '0', STR_PAD_LEFT);
    }

    /** Le code doit-il être saisi pour franchir « je suis arrivé » ? */
    public static function requis(Ticket $ticket): bool
    {
        return $ticket->arrival_code !== null
            && in_array($ticket->state->etat(), self::ETATS, true);
    }

    public static function bloque(Ticket $ticket): bool
    {
        return $ticket->arrival_code_attempts >= self::ESSAIS_MAX;
    }

    public static function essaisRestants(Ticket $ticket): int
    {
        return max(0, self::ESSAIS_MAX - $ticket->arrival_code_attempts);
    }

    /** Comparaison à temps constant, chiffres seuls (espaces et tirets tolérés). */
    public static function correspond(Ticket $ticket, string $saisi): bool
    {
        $saisi = preg_replace('/\D/', '', $saisi) ?? '';

        return $ticket->arrival_code !== null && hash_equals($ticket->arrival_code, $saisi);
    }
}
