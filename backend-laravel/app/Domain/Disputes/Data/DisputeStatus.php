<?php

declare(strict_types=1);

namespace App\Domain\Disputes\Data;

enum DisputeStatus: string
{
    case OUVERT = 'OUVERT';
    case EN_COURS = 'EN_COURS';
    case RESOLU = 'RESOLU';
    case REJETE = 'REJETE';

    public function label(): string
    {
        return match ($this) {
            self::OUVERT => 'Ouvert',
            self::EN_COURS => 'En cours de traitement',
            self::RESOLU => 'Résolu',
            self::REJETE => 'Rejeté',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OUVERT => 'danger',
            self::EN_COURS => 'warning',
            self::RESOLU => 'success',
            self::REJETE => 'secondary',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::OUVERT, self::EN_COURS], true);
    }
}
