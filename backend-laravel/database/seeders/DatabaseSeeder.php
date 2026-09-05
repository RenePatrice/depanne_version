<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Demo\AdminUserSeeder;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\TicketSeeder;
use Database\Seeders\Demo\UserSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * L'ordre compte : les paramètres pilotent le calcul de prix des tickets, et
 * les tickets ont besoin du catalogue, des zones et des comptes.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Les seeders parcourent volontairement des relations : la protection
        // contre le chargement paresseux gênerait sans rien apporter ici.
        Model::preventLazyLoading(false);

        $this->call([
            AppSettingsSeeder::class,
            AdminUserSeeder::class,
            CatalogSeeder::class,
            ZoneSeeder::class,
            UserSeeder::class,
            TicketSeeder::class,
        ]);

        Model::preventLazyLoading(! app()->isProduction());

        $this->command?->newLine();
        $this->command?->info('Jeu de démonstration prêt.');
        $this->command?->line('  Back-office : admin@depanne-moi.gn / DepanneMoi2026');
        $this->command?->line('  Application : n\'importe quel téléphone de la table users / motdepasse');
    }
}
