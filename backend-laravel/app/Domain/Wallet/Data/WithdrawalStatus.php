<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Data;

enum WithdrawalStatus: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case APPROUVE = 'APPROUVE';
    case PAYE = 'PAYE';
    case REJETE = 'REJETE';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente',
            self::APPROUVE => 'Approuvé',
            self::PAYE => 'Payé',
            self::REJETE => 'Rejeté',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'warning',
            self::APPROUVE => 'info',
            self::PAYE => 'success',
            self::REJETE => 'danger',
        };
    }
}
