<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Data;

/** Réponse brute d'un modèle : identifiants proposés et courte explication. */
final readonly class Diagnostic
{
    /**
     * @param  array<int, int>  $prestations
     */
    public function __construct(
        public array $prestations,
        public string $explication,
        public ?string $conseil = null,
    ) {}
}
