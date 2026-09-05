<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Séquence des références de réclamation (LIT-2026-0042).
 *
 * Même raisonnement que pour les tickets : deux réclamations ouvertes en même
 * temps sur un incident qui touche plusieurs clients — une panne de réseau,
 * une erreur de tarif — liraient le même compteur et la seconde échouerait sur
 * la contrainte d'unicité, au moment précis où le support a le plus besoin que
 * tout rentre.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS dispute_reference_seq START WITH 1 INCREMENT BY 1');

        DB::statement(
            "SELECT setval('dispute_reference_seq', GREATEST((SELECT COUNT(*) FROM disputes), 1))"
        );
    }

    public function down(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS dispute_reference_seq');
    }
};
