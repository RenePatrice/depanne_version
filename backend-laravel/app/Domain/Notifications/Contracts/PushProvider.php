<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Contracts;

use App\Domain\Accounts\Models\User;

/**
 * Envoi d'une notification push (§3.2, ADR-0005).
 *
 * Comme `MapProvider`, ce contrat impose de **ne jamais lever d'exception** :
 * une notification qui échoue ne doit pas faire échouer l'action métier qui
 * l'a déclenchée. Un technicien mal notifié laissera passer sa fenêtre de
 * 45 secondes ; un matching interrompu par une erreur de Firebase laisserait
 * le ticket bloqué.
 *
 * L'implémentation dit seulement si l'envoi est parti, pour que l'appelant
 * puisse le tracer sans avoir à décider quoi faire d'un échec.
 */
interface PushProvider
{
    /** @param array<string, mixed> $donnees */
    public function envoyer(User $destinataire, string $titre, string $corps, array $donnees = []): bool;
}
