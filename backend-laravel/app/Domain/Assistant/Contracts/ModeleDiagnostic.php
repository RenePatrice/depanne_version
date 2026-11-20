<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Contracts;

use App\Domain\Assistant\Data\Diagnostic;

/**
 * Modèle de langue qui rapproche une panne décrite par le client des
 * prestations du catalogue (lot 4).
 *
 * Comme `MapProvider`, un pilote **ne lève jamais** : tout incident rend
 * `null`, et l'application retombe sur son moteur local par mots-clés.
 */
interface ModeleDiagnostic
{
    /**
     * @param  array<int, array{id: int, nom: string, categorie: string, type: string}>  $catalogue
     */
    public function diagnostiquer(string $description, array $catalogue): ?Diagnostic;
}
