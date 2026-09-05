<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zones de déploiement et grille tarifaire du déplacement (§6, §8.2).
 * Les trois paramètres de facturation sont portés par la zone, jamais codés en
 * dur : le back-office doit pouvoir les changer sans livraison.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('commune', 80)->default('Ratoma');
            $table->magellanPolygon('boundary', 4326, 'GEOGRAPHY');

            $table->unsignedBigInteger('base_travel_fee_gnf');
            $table->unsignedBigInteger('price_per_km_gnf');
            $table->unsignedSmallInteger('included_km')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('CREATE INDEX zones_boundary_gist ON zones USING GIST (boundary)');
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
