<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Data\UserStatus;
use App\Domain\Accounts\Models\User;
use App\Domain\Tickets\Models\Ticket;
use DomainException;

/**
 * Suspension et réactivation d'un compte de l'application (§6).
 *
 * Un compte suspendu ne peut plus se connecter ni publier. On refuse de
 * suspendre quelqu'un qui a une intervention en cours : le client attend un
 * technicien, ou le technicien est en route — couper l'accès à ce moment-là
 * laisserait l'autre partie sans interlocuteur.
 */
final class SetUserStatus
{
    public function execute(User $user, UserStatus $statut, ?string $motif, int $adminId): User
    {
        if ($statut === UserStatus::SUSPENDU) {
            $enCours = Ticket::query()
                ->where(fn ($q) => $q->where('client_id', $user->id)->orWhere('technician_id', $user->id))
                ->active()
                ->count();

            if ($enCours > 0) {
                throw new DomainException(
                    'Ce compte a '.$enCours.' intervention'.($enCours > 1 ? 's' : '')
                    .' en cours : clôture-la avant de suspendre.'
                );
            }
        }

        $user->forceFill(['status' => $statut])->save();

        // Un technicien suspendu doit sortir du matching immédiatement.
        if ($statut === UserStatus::SUSPENDU && $user->is_technician) {
            $user->technicianProfile()->update(['is_online' => false]);
        }

        activity('comptes')
            ->performedOn($user)
            ->withProperties(['statut' => $statut->value, 'motif' => $motif, 'admin_id' => $adminId])
            ->log($statut === UserStatus::SUSPENDU ? 'Suspension du compte' : 'Réactivation du compte');

        return $user;
    }
}
