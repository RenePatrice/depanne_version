<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Data;

/** Spécialités du pilote. Le code correspond à `service_categories.code`. */
enum Specialty: string
{
    case PLOMBERIE = 'PLOMBERIE';
    case ELECTRICITE = 'ELECTRICITE';

    public function label(): string
    {
        return match ($this) {
            self::PLOMBERIE => 'Plomberie',
            self::ELECTRICITE => 'Électricité',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PLOMBERIE => 'bi-droplet-half',
            self::ELECTRICITE => 'bi-lightning-charge',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PLOMBERIE => '#1B6FF3',
            self::ELECTRICITE => '#F59E0B',
        };
    }
}
