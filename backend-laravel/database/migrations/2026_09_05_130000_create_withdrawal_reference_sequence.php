<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Séquence des références de retrait (RET-2026-00042).
 *
 * Troisième et dernière du même genre. Les techniciens demandent leur retrait
 * en fin de journée, souvent à la même heure : c'est précisément le moment où
 * un compteur calculé en PHP produirait des doublons.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS withdrawal_reference_seq START WITH 1 INCREMENT BY 1');

        DB::statement(
            "SELECT setval('withdrawal_reference_seq', GREATEST((SELECT COUNT(*) FROM withdrawals), 1))"
        );
    }

    public function down(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS withdrawal_reference_seq');
    }
};
