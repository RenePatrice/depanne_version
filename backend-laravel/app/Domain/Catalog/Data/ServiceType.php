<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Data;

/**
 * Nature d'une prestation (#2).
 *
 * La distinction n'est pas cosmétique : elle décide si le client peut joindre
 * des photos. Pour un **dépannage**, trois photos de la panne aident le
 * technicien à estimer avant de se déplacer ; pour une **installation**, il n'y
 * a encore rien à photographier.
 */
enum ServiceType: string
{
    case DEPANNAGE = 'DEPANNAGE';
    case INSTALLATION = 'INSTALLATION';

    public function label(): string
    {
        return match ($this) {
            self::DEPANNAGE => 'Dépannage',
            self::INSTALLATION => 'Installation',
        };
    }

    /** Nombre de photos de la panne que le client peut joindre. */
    public function photosAutorisees(): int
    {
        return match ($this) {
            self::DEPANNAGE => 3,
            self::INSTALLATION => 0,
        };
    }

    public function acceptePhotos(): bool
    {
        return $this->photosAutorisees() > 0;
    }
}
