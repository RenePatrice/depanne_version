<?php

declare(strict_types=1);

namespace App\Domain\Disputes\Data;

/** Priorité et délai de traitement associé (SLA affiché dans la file du support). */
enum DisputePriority: string
{
    case BASSE = 'BASSE';
    case NORMALE = 'NORMALE';
    case HAUTE = 'HAUTE';
    case URGENTE = 'URGENTE';

    public function label(): string
    {
        return match ($this) {
            self::BASSE => 'Basse',
            self::NORMALE => 'Normale',
            self::HAUTE => 'Haute',
            self::URGENTE => 'Urgente',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BASSE => 'secondary',
            self::NORMALE => 'info',
            self::HAUTE => 'warning',
            self::URGENTE => 'danger',
        };
    }

    /** Délai de traitement, en heures. */
    public function slaHours(): int
    {
        return match ($this) {
            self::BASSE => 72,
            self::NORMALE => 48,
            self::HAUTE => 24,
            self::URGENTE => 4,
        };
    }
}
