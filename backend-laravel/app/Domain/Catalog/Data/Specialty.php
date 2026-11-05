<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Data;

/** Spécialités du pilote. Le code correspond à `service_categories.code`. */
enum Specialty: string
{
    case PLOMBERIE = 'PLOMBERIE';
    case ELECTRICITE = 'ELECTRICITE';
    // Métiers du BTP (#5) : le catalogue s'ouvre aux corps d'état du bâtiment.
    case MACONNERIE = 'MACONNERIE';
    case PEINTURE = 'PEINTURE';
    case MENUISERIE = 'MENUISERIE';
    case CARRELAGE = 'CARRELAGE';

    public function label(): string
    {
        return match ($this) {
            self::PLOMBERIE => 'Plomberie',
            self::ELECTRICITE => 'Électricité',
            self::MACONNERIE => 'Maçonnerie',
            self::PEINTURE => 'Peinture',
            self::MENUISERIE => 'Menuiserie',
            self::CARRELAGE => 'Carrelage',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PLOMBERIE => 'bi-droplet-half',
            self::ELECTRICITE => 'bi-lightning-charge',
            self::MACONNERIE => 'bi-bricks',
            self::PEINTURE => 'bi-palette',
            self::MENUISERIE => 'bi-hammer',
            self::CARRELAGE => 'bi-grid-3x3-gap',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PLOMBERIE => '#1B6FF3',
            self::ELECTRICITE => '#F59E0B',
            self::MACONNERIE => '#8B5E3C',
            self::PEINTURE => '#7C3AED',
            self::MENUISERIE => '#B45309',
            self::CARRELAGE => '#0D9488',
        };
    }

    /** Les métiers du bâtiment, ouverts en #5. */
    public function estBtp(): bool
    {
        return in_array($this, [
            self::MACONNERIE, self::PEINTURE, self::MENUISERIE, self::CARRELAGE,
        ], true);
    }
}
