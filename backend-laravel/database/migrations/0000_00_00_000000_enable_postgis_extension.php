<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostGIS conditionne tout le matching : zones de couverture, présélection par
 * rayon, calcul de distance. L'extension doit exister avant la première colonne
 * géographique.
 *
 * Sur Supabase, cette instruction doit passer par la connexion directe
 * (port 5432) : le pooler en mode transaction ne sait pas l'exécuter.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
    }

    public function down(): void
    {
        // L'extension n'est pas supprimée : d'autres schémas de la même base
        // peuvent en dépendre.
    }
};
