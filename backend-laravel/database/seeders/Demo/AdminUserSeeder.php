<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Domain\Accounts\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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
    private const GUARD = 'admin';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::permissions() as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        $admin = Role::findOrCreate('ADMIN', self::GUARD);
        $support = Role::findOrCreate('SUPPORT', self::GUARD);
        $finance = Role::findOrCreate('FINANCE', self::GUARD);

        $admin->syncPermissions(self::permissions());
        $support->syncPermissions(self::supportPermissions());
        $finance->syncPermissions(self::financePermissions());

        $this->createAdmin('admin@depanne-moi.gn', 'Aïssatou Barry', $admin->name);
        $this->createAdmin('support@depanne-moi.gn', 'Mamadou Camara', $support->name);
        $this->createAdmin('finance@depanne-moi.gn', 'Oumou Sylla', $finance->name);
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

    /** @return array<int, string> */
    public static function permissions(): array
    {
        return [
            'tableau-de-bord.voir',
            'carte-live.voir',
            'tickets.voir', 'tickets.agir',
            'clients.voir', 'clients.suspendre',
            'techniciens.voir', 'techniciens.valider', 'techniciens.sanctionner',
            'catalogue.voir', 'catalogue.modifier',
            'zones.voir', 'zones.modifier',
            'finances.voir', 'finances.exporter', 'retraits.approuver',
            'litiges.voir', 'litiges.resoudre',
            'configuration.voir', 'configuration.modifier',
            'journal-audit.voir',
            'administrateurs.gerer',
        ];
    }

    /** @return array<int, string> */
    private static function supportPermissions(): array
    {
        return [
            'tableau-de-bord.voir', 'carte-live.voir',
            'tickets.voir', 'tickets.agir',
            'clients.voir', 'clients.suspendre',
            'techniciens.voir', 'techniciens.valider', 'techniciens.sanctionner',
            'catalogue.voir', 'zones.voir',
            'litiges.voir', 'litiges.resoudre',
        ];
    }

    /** @return array<int, string> */
    private static function financePermissions(): array
    {
        return [
            'tableau-de-bord.voir',
            'tickets.voir',
            'techniciens.voir',
            'finances.voir', 'finances.exporter', 'retraits.approuver',
            'journal-audit.voir',
        ];
    }
}
