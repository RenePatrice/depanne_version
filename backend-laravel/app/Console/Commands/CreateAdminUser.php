<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Accounts\Actions\ManageAdminUsers;
use Database\Seeders\RolesPermissionsSeeder;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Crée le premier administrateur du back-office, sans rien coder en dur (#9).
 *
 * En production, aucun compte n'est livré avec l'application. Cette commande
 * garantit d'abord que les rôles et permissions existent, puis demande e-mail,
 * nom, rôle et mot de passe — ce dernier saisi masqué, jamais passé en
 * argument où il traînerait dans l'historique du shell.
 *
 *   php artisan admin:create
 *   php artisan admin:create --email=… --nom="…" --role=ADMIN
 */
final class CreateAdminUser extends Command
{
    protected $signature = 'admin:create
                            {--email= : adresse e-mail du compte}
                            {--nom= : nom complet}
                            {--role=ADMIN : ADMIN, SUPPORT ou FINANCE}';

    protected $description = 'Crée un compte du back-office (rôles inclus) sans identifiant en dur';

    public function handle(ManageAdminUsers $comptes): int
    {
        $this->call('db:seed', ['--class' => RolesPermissionsSeeder::class, '--force' => true]);

        $email = (string) ($this->option('email') ?? text(
            label: 'Adresse e-mail',
            required: true,
        ));

        if (Validator::make(['email' => $email], ['email' => 'email'])->fails()) {
            $this->error('Adresse e-mail invalide.');

            return self::FAILURE;
        }

        $nom = (string) ($this->option('nom') ?? text(label: 'Nom complet', required: true));

        $role = (string) $this->option('role');
        if (! in_array($role, ManageAdminUsers::ROLES, true)) {
            $role = select(label: 'Rôle', options: ManageAdminUsers::ROLES, default: 'ADMIN');
        }

        $motDePasse = password(
            label: 'Mot de passe (au moins 12 caractères)',
            required: true,
            validate: fn (string $v): ?string => mb_strlen($v) < 12
                ? 'Le mot de passe doit faire au moins 12 caractères.'
                : null,
        );

        try {
            $admin = $comptes->creer($email, $nom, $role, $motDePasse);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Compte {$admin->email} créé avec le rôle {$role}.");

        return self::SUCCESS;
    }
}
