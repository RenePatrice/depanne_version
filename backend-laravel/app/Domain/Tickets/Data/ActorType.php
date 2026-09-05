<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Data;

/** Auteur d'une transition d'état, journalisé dans `ticket_events`. */
enum ActorType: string
{
    case CLIENT = 'CLIENT';
    case TECHNICIEN = 'TECHNICIEN';
    case ADMIN = 'ADMIN';
    case SYSTEME = 'SYSTEME';

    public function label(): string
    {
        return match ($this) {
            self::CLIENT => 'Client',
            self::TECHNICIEN => 'Technicien',
            self::ADMIN => 'Administrateur',
            self::SYSTEME => 'Système',
        };
    }
}
