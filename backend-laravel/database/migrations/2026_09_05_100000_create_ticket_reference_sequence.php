<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Séquence dédiée aux références de ticket (DM-2026-000123).
 *
 * Un compteur calculé en PHP — `SELECT MAX(...) + 1` ou un compte de lignes —
 * ne tient pas la concurrence : deux publications simultanées liraient la même
 * valeur et la seconde échouerait sur la contrainte d'unicité, au pire moment
 * pour le client. Une séquence PostgreSQL délivre des numéros distincts même
 * sous charge, et sans verrou.
 *
 * Elle est volontairement hors transaction applicative : un `nextval` consommé
 * par une publication annulée laisse un trou dans la numérotation, ce qui est
 * sans conséquence, alors qu'un doublon de référence en aurait.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS ticket_reference_seq START WITH 1 INCREMENT BY 1');

        // Le jeu de démonstration crée des tickets avec ses propres numéros :
        // la séquence démarre au-dessus pour ne pas les percuter.
        DB::statement(
            "SELECT setval('ticket_reference_seq', GREATEST((SELECT COUNT(*) FROM tickets), 1))"
        );
    }

    public function down(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS ticket_reference_seq');
    }
};
