<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

/**
 * Ce que l'opérateur renvoie quand on lui demande un encaissement.
 *
 * `reference` est la clé de tout le reste : c'est elle qui reviendra dans la
 * notification, elle qui porte la contrainte d'unicité en base, et donc elle
 * qui rend le webhook idempotent (§8.4).
 */
final readonly class IntentionPaiement
{
    /** @param array<string, mixed> $brut réponse de l'opérateur, conservée telle quelle */
    public function __construct(
        public string $reference,
        public ?string $urlPaiement = null,
        public ?string $instruction = null,
        public array $brut = [],
    ) {}
}
