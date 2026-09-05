<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\AdminUser;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Canaux de diffusion temps réel
|--------------------------------------------------------------------------
| `back-office` est le canal privé de supervision : positions des techniciens,
| transitions de tickets, flux d'activité. Seuls les comptes du back-office
| ayant le droit d'ouvrir la carte live peuvent s'y abonner — une position GPS
| est une donnée personnelle, pas un fond d'écran.
*/

Broadcast::channel('back-office', function (mixed $utilisateur): bool {
    return $utilisateur instanceof AdminUser
        && $utilisateur->is_active
        && $utilisateur->can('carte-live.voir');
});

/*
 * Canal personnel d'un utilisateur de l'application mobile (statuts de son
 * ticket, messages). Branché en phase C3, quand l'API mobile existe.
 */
Broadcast::channel('utilisateur.{id}', function (mixed $utilisateur, int $id): bool {
    return $utilisateur !== null && (int) $utilisateur->getKey() === $id;
});
