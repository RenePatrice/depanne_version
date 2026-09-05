<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\AdminUser;
use App\Domain\Accounts\Models\User;
use App\Domain\Tickets\Models\Ticket;
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
 * Canal personnel d'un utilisateur de l'application mobile : notifications,
 * pastille de message non lu, statuts de ses tickets.
 */
Broadcast::channel('utilisateur.{id}', function (mixed $utilisateur, int $id): bool {
    return $utilisateur !== null && (int) $utilisateur->getKey() === $id;
});

/*
 * Conversation d'une intervention. Seules les deux parties y ont accès — un
 * chat est aussi personnel qu'une position GPS, et le canal se ferme aussi
 * soigneusement. Le support lit l'historique par le back-office, pas par ici.
 */
Broadcast::channel('ticket.{id}', function (mixed $utilisateur, int $id): bool {
    if (! $utilisateur instanceof User) {
        return false;
    }

    return Ticket::query()
        ->whereKey($id)
        ->where(fn ($q) => $q
            ->where('client_id', $utilisateur->getKey())
            ->orWhere('technician_id', $utilisateur->getKey()))
        ->exists();
});
