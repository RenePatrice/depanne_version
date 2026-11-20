<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Providers;

use App\Domain\Assistant\Contracts\ModeleDiagnostic;
use App\Domain\Assistant\Data\Diagnostic;

/** Aucun modèle hébergé : l'application garde son moteur local. */
final class AucunModele implements ModeleDiagnostic
{
    public function diagnostiquer(string $description, array $catalogue): ?Diagnostic
    {
        return null;
    }
}
