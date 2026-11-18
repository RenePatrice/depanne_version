<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

/** Cycle de vie du paiement et du séquestre logique (§8.4). */
enum PaymentStatus: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case ATTENTE_CONFIRMATION = 'ATTENTE_CONFIRMATION'; // direct : une partie a déclaré, l'autre doit confirmer
    case CONFIRMEE = 'CONFIRMEE';                        // direct : confirmé par les deux parties
    case CONTESTEE = 'CONTESTEE';                        // direct : contesté, à arbitrer par le support
    case ANNULEE = 'ANNULEE';                            // direct : déclaration retirée ou annulée par le support
    case CAPTUREE = 'CAPTUREE';     // fonds en séquestre
    case LIBEREE = 'LIBEREE';       // répartition 90/10 effectuée
    case ECHOUEE = 'ECHOUEE';
    case REMBOURSEE = 'REMBOURSEE';
    case EXPIREE = 'EXPIREE';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente de confirmation',
            self::ATTENTE_CONFIRMATION => 'En attente de confirmation',
            self::CONFIRMEE => 'Confirmé par les deux parties',
            self::CONTESTEE => 'Contesté — à arbitrer',
            self::ANNULEE => 'Annulé',
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
            self::EN_ATTENTE, self::ATTENTE_CONFIRMATION => 'warning',
            self::CAPTUREE => 'info',
            self::LIBEREE, self::CONFIRMEE => 'success',
            self::ECHOUEE, self::EXPIREE, self::CONTESTEE => 'danger',
            self::REMBOURSEE, self::ANNULEE => 'secondary',
        };
    }
}
