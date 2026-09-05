<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Data;

enum UserStatus: string
{
    case ACTIF = 'ACTIF';
    case SUSPENDU = 'SUSPENDU';

    public function label(): string
    {
        return match ($this) {
            self::ACTIF => 'Actif',
            self::SUSPENDU => 'Suspendu',
        };
    }

    public function color(): string
    {
        return $this === self::ACTIF ? 'success' : 'danger';
    }
}
