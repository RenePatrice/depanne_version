<?php

declare(strict_types=1);

namespace App\Domain\Chat\Contracts;

use App\Domain\Accounts\Models\User;

/**
 * Mise en relation téléphonique sans divulgation des numéros (§7.3, ADR-0005).
 *
 * Le principe : l'opérateur attribue un numéro relais temporaire, chacun
 * l'appelle, et personne ne voit celui de l'autre. C'est le pendant vocal du
 * masquage du chat — sans lui, il suffirait de demander « appelle-moi » pour
 * contourner tout le filtre écrit.
 *
 * Aucun fournisseur n'est branché pour le pilote ; l'interface existe pour que
 * le jour où il l'est, rien d'autre ne change.
 */
interface MaskedCallProvider
{
    /**
     * Ouvre une mise en relation entre deux comptes.
     *
     * @return array{numero: string, expire_le: string, simule: bool}
     */
    public function ouvrir(User $appelant, User $appele, string $referenceTicket): array;
}
