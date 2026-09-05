<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Data;

/** Nature d'un mouvement de portefeuille (ADR-0004). */
enum TransactionType: string
{
    case EARNING = 'EARNING';         // part technicien d'une intervention
    case COMMISSION = 'COMMISSION';   // part plateforme, en négatif côté technicien
    case WITHDRAWAL = 'WITHDRAWAL';   // retrait vers Mobile Money
    case REFUND = 'REFUND';           // remboursement client
    case ADJUSTMENT = 'ADJUSTMENT';   // correction manuelle du support
    case TIP = 'TIP';                 // pourboire du client

    public function label(): string
    {
        return match ($this) {
            self::EARNING => 'Gain d\'intervention',
            self::COMMISSION => 'Commission plateforme',
            self::WITHDRAWAL => 'Retrait',
            self::REFUND => 'Remboursement',
            self::ADJUSTMENT => 'Ajustement',
            self::TIP => 'Pourboire',
        };
    }

    /** Le mouvement diminue le solde du technicien. */
    public function isDebit(): bool
    {
        return in_array($this, [self::COMMISSION, self::WITHDRAWAL, self::REFUND], true);
    }
}
