<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\AdminUser;
use DomainException;
use Illuminate\Support\Facades\Hash;

/**
 * Changement de son propre mot de passe depuis le dashboard (#9).
 *
 * Le mot de passe actuel est **exigé** : sans lui, une session laissée ouverte
 * sur un poste partagé permettrait à n'importe qui de verrouiller le compte en
 * changeant le mot de passe. Le nouveau est haché en argon2id par le cast du
 * modèle.
 */
final class ChangeAdminPassword
{
    /** @throws DomainException */
    public function execute(AdminUser $admin, string $actuel, string $nouveau): void
    {
        if (! Hash::check($actuel, (string) $admin->password)) {
            throw new DomainException('Le mot de passe actuel est incorrect.');
        }

        if (Hash::check($nouveau, (string) $admin->password)) {
            throw new DomainException('Le nouveau mot de passe doit être différent de l\'ancien.');
        }

        $admin->forceFill(['password' => $nouveau])->save();

        activity('administrateurs')
            ->performedOn($admin)
            ->log('Changement de mot de passe de '.$admin->email);
    }
}
