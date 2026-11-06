<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rôles et permissions du back-office — **sans aucun compte** (#9).
 *
 * Séparé des comptes de démonstration : la production crée ses rôles et son
 * premier administrateur par `php artisan admin:create`, sans jamais écrire
 * d'identifiant en dur. Le seeder de démo (`AdminUserSeeder`) s'appuie dessus
 * pour le développement local.
 *
 * ADMIN voit tout, y compris la gestion des comptes du back-office. SUPPORT
 * traite tickets, litiges et validations. FINANCE voit les commissions et
 * approuve les retraits. Un module réservé à l'ADMIN n'apparaît pas pour les
 * deux autres : `Navigation::forAdmin()` filtre sur la permission.
 */
final class RolesPermissionsSeeder extends Seeder
{
    public const GUARD = 'admin';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::permissions() as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        Role::findOrCreate('ADMIN', self::GUARD)->syncPermissions(self::permissions());
        Role::findOrCreate('SUPPORT', self::GUARD)->syncPermissions(self::supportPermissions());
        Role::findOrCreate('FINANCE', self::GUARD)->syncPermissions(self::financePermissions());
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
            'site.gerer',
        ];
    }

    /** @return array<int, string> */
    public static function supportPermissions(): array
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
    public static function financePermissions(): array
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
