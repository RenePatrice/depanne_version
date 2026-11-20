<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Code d'arrivée (lot 2) : généré à l'acceptation, montré au seul client,
| exigé du technicien à l'étape « je suis arrivé ». Stocké chiffré par le cast
| `encrypted` — d'où un `text`, le chiffré est bien plus long que le code.
| Les tickets antérieurs n'ont pas de code et passent sans.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->text('arrival_code')->nullable();
            $table->unsignedSmallInteger('arrival_code_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn(['arrival_code', 'arrival_code_attempts']);
        });
    }
};
