<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\AdminUser;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Gestion des comptes du back-office (§6, #9).
 *
 * Réservée à la permission `administrateurs.gerer`, que seul le rôle ADMIN
 * porte : un compte SUPPORT ou FINANCE ne voit même pas ce module. C'est le
 * cloisonnement demandé — un module sous contrôle de l'admin n'est pas visible
 * par un autre utilisateur.
 *
 * Deux garde-fous empêchent de se tirer une balle dans le pied :
 *
 * - on ne désactive pas, ni ne rétrograde, **le dernier administrateur actif** :
 *   sans lui, plus personne ne pourrait rouvrir ce module ;
 * - on ne se désactive, ni ne se retire le rôle, **soi-même** : la déconnexion
 *   immédiate serait aussi un verrouillage.
 *
 * Les mots de passe ne sont jamais écrits en clair : le cast `hashed` du modèle
 * les passe en argon2id (salt compris, ADR-0003) au moment de l'affectation.
 */
final class ManageAdminUsers
{
    /** Les seuls rôles attribuables depuis l'interface. */
    public const ROLES = ['ADMIN', 'SUPPORT', 'FINANCE'];

    private const GUARD = 'admin';

    /** @throws DomainException */
    public function creer(string $email, string $fullName, string $role, string $motDePasse): AdminUser
    {
        $this->verifierRole($role);

        if (AdminUser::query()->where('email', $email)->exists()) {
            throw new DomainException('Un compte utilise déjà cette adresse e-mail.');
        }

        return DB::transaction(function () use ($email, $fullName, $role, $motDePasse): AdminUser {
            /** @var AdminUser $admin */
            $admin = AdminUser::query()->create([
                'email' => $email,
                'full_name' => $fullName,
                'password' => $motDePasse,
                'is_active' => true,
            ]);

            $admin->syncRoles([$role]);

            activity('administrateurs')
                ->withProperties(['email' => $email, 'role' => $role])
                ->log('Création du compte '.$email);

            return $admin;
        });
    }

    /** @throws DomainException */
    public function modifier(AdminUser $admin, string $fullName, string $role, AdminUser $acteur): AdminUser
    {
        $this->verifierRole($role);

        // Se retirer à soi-même le rôle ADMIN, c'est se fermer la porte.
        if ($acteur->is($admin) && $role !== 'ADMIN' && $admin->hasRole('ADMIN')) {
            throw new DomainException('Tu ne peux pas retirer ton propre rôle administrateur.');
        }

        if ($admin->hasRole('ADMIN') && $role !== 'ADMIN') {
            $this->refuserSiDernierAdmin($admin, 'rétrograder');
        }

        return DB::transaction(function () use ($admin, $fullName, $role): AdminUser {
            $admin->forceFill(['full_name' => $fullName])->save();
            $admin->syncRoles([$role]);

            activity('administrateurs')
                ->performedOn($admin)
                ->withProperties(['role' => $role])
                ->log('Modification du compte '.$admin->email);

            return $admin;
        });
    }

    /** @throws DomainException */
    public function definirStatut(AdminUser $admin, bool $actif, AdminUser $acteur): AdminUser
    {
        if (! $actif && $acteur->is($admin)) {
            throw new DomainException('Tu ne peux pas désactiver ton propre compte.');
        }

        if (! $actif && $admin->hasRole('ADMIN')) {
            $this->refuserSiDernierAdmin($admin, 'désactiver');
        }

        $admin->forceFill(['is_active' => $actif])->save();

        activity('administrateurs')
            ->performedOn($admin)
            ->log(($actif ? 'Réactivation' : 'Désactivation').' du compte '.$admin->email);

        return $admin;
    }

    public function reinitialiserMotDePasse(AdminUser $admin, string $motDePasse): AdminUser
    {
        // Le jeton « se souvenir » est vidé en même temps : une réinitialisation
        // par un tiers doit couper les sessions persistantes du compte.
        $admin->forceFill(['password' => $motDePasse, 'remember_token' => null])->save();

        activity('administrateurs')
            ->performedOn($admin)
            ->log('Réinitialisation du mot de passe de '.$admin->email);

        return $admin;
    }

    /** @throws DomainException */
    private function verifierRole(string $role): void
    {
        if (! in_array($role, self::ROLES, true)) {
            throw new DomainException('Rôle inconnu : '.$role.'.');
        }
    }

    /** @throws DomainException */
    private function refuserSiDernierAdmin(AdminUser $admin, string $verbe): void
    {
        $autresAdmins = AdminUser::query()
            ->where('is_active', true)
            ->whereKeyNot($admin->getKey())
            ->whereHas('roles', fn ($q) => $q->where('name', 'ADMIN')->where('guard_name', self::GUARD))
            ->count();

        if ($autresAdmins === 0) {
            throw new DomainException(
                'Impossible de '.$verbe.' le dernier administrateur actif : '
                .'il ne resterait plus personne pour gérer le back-office.'
            );
        }
    }
}
