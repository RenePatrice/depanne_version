<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Socle de la place de marché (orientation du 5 novembre 2026, #2).
 *
 * - `services.type` distingue le **dépannage** de l'**installation** : le
 *   premier autorise jusqu'à trois photos de la panne, le second aucune (la
 *   règle est portée par l'énumération `ServiceType`).
 * - `technician_service_rates` porte le **tarif propre** d'un technicien pour
 *   une prestation. En l'absence de ligne, le prix catalogue fait foi : le
 *   catalogue devient un **défaut**, pas un prix imposé. Le déplacement reste
 *   calculé par la grille de la zone, comme avant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            // DEPANNAGE | INSTALLATION
            $table->string('type', 20)->default('DEPANNAGE')->after('description');
        });

        Schema::create('technician_service_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            // Tarif de la prestation fixé par le technicien, hors déplacement.
            $table->unsignedBigInteger('price_gnf');

            $table->timestamps();

            // Un seul tarif par technicien et par prestation.
            $table->unique(['user_id', 'service_id']);
            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_service_rates');

        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
