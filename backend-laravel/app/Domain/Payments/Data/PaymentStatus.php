<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

/** Cycle de vie du paiement et du séquestre logique (§8.4). */
enum PaymentStatus: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case CAPTUREE = 'CAPTUREE';     // fonds en séquestre
    case LIBEREE = 'LIBEREE';       // répartition 90/10 effectuée
    case ECHOUEE = 'ECHOUEE';
    case REMBOURSEE = 'REMBOURSEE';
    case EXPIREE = 'EXPIREE';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente de confirmation',
            self::CAPTUREE => 'Capturée, en séquestre',
            self::LIBEREE => 'Libérée',
            self::ECHOUEE => 'Échouée',
            self::REMBOURSEE => 'Remboursée',
            self::EXPIREE => 'Expirée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'warning',
            self::CAPTUREE => 'info',
            self::LIBEREE => 'success',
            self::ECHOUEE, self::EXPIREE => 'danger',
            self::REMBOURSEE => 'secondary',
        };
    }
}
