<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

/**
 * Par où l'argent passe (ADR-0047).
 *
 *  - DIRECT : le client paie le technicien lui-même, en espèces ou par
 *    transfert Mobile Money sur son numéro. La plateforme ne voit pas l'argent :
 *    elle trace la double confirmation et inscrit la commission en dette.
 *  - APP : Mobile Money encaissé par l'application via l'opérateur, sous
 *    séquestre. Le webhook de l'opérateur fait foi (ADR-0032).
 */
enum PaymentChannel: string
{
    case DIRECT = 'DIRECT';
    case APP = 'APP';

    public function label(): string
    {
        return match ($this) {
            self::DIRECT => 'Direct au technicien',
            self::APP => 'Via l\'application',
        };
    }
}
