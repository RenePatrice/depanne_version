<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Contracts\PushProvider;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Notifications\Events\NotificationPoussee;
use App\Domain\Notifications\Models\AppNotification;
use App\Domain\Settings\Models\AppSetting;
use Illuminate\Support\Str;

/**
 * Émission d'une notification (§7.4).
 *
 * Trois chemins de livraison, dans cet ordre :
 *
 * 1. **La base**, toujours. C'est le seul support fiable : l'application peut
 *    afficher un centre de notifications complet même si tout le reste a
 *    échoué. Sur le réseau de Conakry, un message perdu est un cas courant.
 * 2. **Reverb**, pour une application ouverte. Immédiat, sans tiers.
 * 3. **Le push**, pour une application fermée. Aujourd'hui un pilote `log`, en
 *    attendant Firebase.
 *
 * Aucun de ces chemins ne peut faire échouer l'appelant : une notification qui
 * ne part pas est un désagrément, un matching interrompu est une panne.
 */
final class SendNotification
{
    public function __construct(private readonly PushProvider $push) {}

    /**
     * @param  array<string, string|int>  $variables  substituées dans le gabarit, entre accolades
     * @param  array<string, mixed>  $donnees  charge utile lue par l'application
     */
    public function execute(
        User $destinataire,
        NotificationType $type,
        array $variables = [],
        array $donnees = [],
    ): AppNotification {
        $corps = $this->composer($type, $variables);
        $identifiant = (string) Str::uuid();

        /** @var AppNotification $notification */
        $notification = AppNotification::query()->create([
            'id' => $identifiant,
            'type' => $type->value,
            'notifiable_type' => $destinataire->getMorphClass(),
            'notifiable_id' => $destinataire->getKey(),
            'data' => [
                'titre' => $type->titre(),
                'corps' => $corps,
                'urgente' => $type->estUrgente(),
            ] + $donnees,
        ]);

        NotificationPoussee::dispatch(
            (int) $destinataire->getKey(),
            $identifiant,
            $type->value,
            $type->titre(),
            $corps,
            $donnees,
        );

        $this->push->envoyer($destinataire, $type->titre(), $corps, $donnees + ['type' => $type->value]);

        return $notification;
    }

    /**
     * Le gabarit vient des paramètres quand le type en déclare un : les textes
     * vus par le client se corrigent en back-office, sans déploiement.
     *
     * @param  array<string, string|int>  $variables
     */
    private function composer(NotificationType $type, array $variables): string
    {
        $cle = $type->cleParametre();

        $gabarit = $cle === null
            ? $type->gabaritParDefaut()
            : (string) AppSetting::get($cle, $type->gabaritParDefaut());

        foreach ($variables as $nom => $valeur) {
            $gabarit = str_replace('{'.$nom.'}', (string) $valeur, $gabarit);
        }

        // Une variable oubliée laisserait « {technicien} » en clair dans la
        // notification. Mieux vaut une phrase incomplète qu'une accolade.
        return trim((string) preg_replace('/\s*\{[a-z_]+\}/', '', $gabarit));
    }
}
