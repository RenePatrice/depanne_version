<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Accounts\Models\Address;
use App\Domain\Accounts\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Carnet d'adresses : chacun le sien.
 *
 * Refus en 404, pour la même raison que sur les tickets — une adresse est une
 * donnée personnelle, et confirmer son existence en dit déjà trop.
 */
final class AddressPolicy
{
    public function view(User $utilisateur, Address $adresse): Response
    {
        return (int) $adresse->user_id === (int) $utilisateur->getKey()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $utilisateur, Address $adresse): Response
    {
        return $this->view($utilisateur, $adresse);
    }

    public function delete(User $utilisateur, Address $adresse): Response
    {
        return $this->view($utilisateur, $adresse);
    }
}
