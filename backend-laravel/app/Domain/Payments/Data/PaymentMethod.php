<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

/**
 * Moyens de paiement. Orange Money est le seul branché pour le pilote ;
 * les suivants s'ajoutent ici et derrière l'interface `PaymentProvider`,
 * sans toucher au reste du domaine (ADR-0005).
 */
enum PaymentMethod: string
{
    case ORANGE_MONEY = 'ORANGE_MONEY';
    case MTN_MOMO = 'MTN_MOMO';
    case ESPECES = 'ESPECES';

    public function label(): string
    {
        return match ($this) {
            self::ORANGE_MONEY => 'Orange Money',
            self::MTN_MOMO => 'MTN MoMo',
            self::ESPECES => 'Especes',
        };
    }

    /** Un moyen non disponible reste affichable en historique mais non sélectionnable. */
    public function isAvailable(): bool
    {
        return $this === self::ORANGE_MONEY || $this === self::ESPECES;
    }

    /** @return array<int, self> */
    public static function available(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $m): bool => $m->isAvailable()));
    }
}
