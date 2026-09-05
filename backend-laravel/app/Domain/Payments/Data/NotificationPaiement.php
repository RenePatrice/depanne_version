<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

/**
 * Notification d'opérateur, traduite dans le vocabulaire du domaine.
 *
 * Le `brut` est conservé et stocké : quand un paiement sera contesté dans six
 * mois, la seule preuve utilisable sera ce que l'opérateur a réellement
 * envoyé, pas notre interprétation.
 */
final readonly class NotificationPaiement
{
    /** @param array<string, mixed> $brut */
    public function __construct(
        public string $reference,
        public bool $reussi,
        public ?int $montantGnf = null,
        public ?string $motifEchec = null,
        public array $brut = [],
    ) {}
}
