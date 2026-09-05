<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Domicile, Bureau, Chez maman…
            $table->string('label', 60)->nullable();
            $table->string('formatted_address', 300);

            // Le repère textuel compense l'absence d'adressage fiable à Conakry :
            // « près de la mosquée de Kipé », « en face de la pharmacie Bonheur ».
            $table->string('landmark', 200)->nullable();

            $table->magellanPoint('location', 4326, 'GEOGRAPHY');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_default']);
        });

        DB::statement('CREATE INDEX addresses_location_gist ON addresses USING GIST (location)');

        // Dépendance circulaire résolue ici : client_profiles a été créée avant
        // addresses, la contrainte ne pouvait pas être posée à ce moment-là.
        Schema::table('client_profiles', function (Blueprint $table): void {
            $table->foreign('default_address_id')->references('id')->on('addresses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_profiles', function (Blueprint $table): void {
            $table->dropForeign(['default_address_id']);
        });

        Schema::dropIfExists('addresses');
    }
};
