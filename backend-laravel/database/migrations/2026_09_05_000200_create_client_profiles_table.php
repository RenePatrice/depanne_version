<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();

            // Programme de fidélité : la valeur de rester dans l'application est
            // l'un des remparts contre le contournement de la plateforme (§11).
            $table->unsignedInteger('loyalty_points')->default(0);

            // Contrainte de clé étrangère ajoutée par la migration des adresses
            // (dépendance circulaire entre les deux tables).
            $table->unsignedBigInteger('default_address_id')->nullable();

            $table->unsignedInteger('tickets_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_profiles');
    }
};
