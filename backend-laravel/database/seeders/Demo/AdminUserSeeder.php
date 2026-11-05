<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Domain\Accounts\Models\AdminUser;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Rôles, permissions et comptes du back-office (§4).
 *
 * ADMIN voit tout. SUPPORT traite les tickets, les litiges et la validation des
 * techniciens, mais ne touche ni aux tarifs ni aux versements. FINANCE voit les
 * commissions et approuve les retraits, sans accès aux données personnelles
 * au-delà de ce que la comptabilité exige.
 */
final class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Les rôles et permissions vivent dans un seeder non-démo, partagé avec
        // `php artisan admin:create` : la production les crée sans aucun compte.
        $this->call(RolesPermissionsSeeder::class);

        $this->createAdmin('admin@depanne-moi.gn', 'Aïssatou Barry', 'ADMIN');
        $this->createAdmin('support@depanne-moi.gn', 'Mamadou Camara', 'SUPPORT');
        $this->createAdmin('finance@depanne-moi.gn', 'Oumou Sylla', 'FINANCE');
    }

    private function createAdmin(string $email, string $fullName, string $role): void
    {
        $user = AdminUser::query()->updateOrCreate(
            ['email' => $email],
            [
                // Mot de passe de démonstration : à changer avant toute mise en ligne.
                'password' => Hash::make('DepanneMoi2026'),
                'full_name' => $fullName,
                'is_active' => true,
            ],
        );

        $user->syncRoles([$role]);
    }
}
