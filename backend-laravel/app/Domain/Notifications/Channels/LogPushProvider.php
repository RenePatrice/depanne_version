<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Channels;

use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Contracts\PushProvider;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pilote de substitution à Firebase (décision client du 5 septembre 2026).
 *
 * Firebase n'est pas disponible tant que le projet FCM n'existe pas. Plutôt que
 * de laisser un trou dans le parcours, ce pilote écrit la notification dans
 * `laravel.log`, sous une forme lisible pendant une démonstration. La
 * persistance en base et la diffusion Reverb sont assurées ailleurs, par
 * `SendNotification` : elles ne dépendent d'aucun tiers et fonctionnent
 * réellement.
 *
 * Le jour où `PUSH_PROVIDER=fcm`, seule la liaison du conteneur change.
 */
final class LogPushProvider implements PushProvider
{
    /** @param array<string, mixed> $donnees */
    public function envoyer(User $destinataire, string $titre, string $corps, array $donnees = []): bool
    {
        try {
            Log::channel('single')->info('[PUSH] '.$titre, [
                'destinataire' => $destinataire->getKey(),
                'telephone' => $destinataire->phone,
                'corps' => $corps,
                'donnees' => $donnees,
            ]);

            return true;
        } catch (Throwable $e) {
            // Le contrat interdit de lever : une notification perdue ne doit
            // jamais faire échouer l'action métier qui l'a déclenchée.
            report($e);

            return false;
        }
    }
}
