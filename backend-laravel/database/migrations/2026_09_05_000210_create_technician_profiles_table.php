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
        Schema::create('technician_profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();

            // ["PLOMBERIE", "ELECTRICITE"] — un technicien peut cumuler.
            $table->jsonb('specialties');

            // EN_ATTENTE_VALIDATION | VALIDE | REJETE | SUSPENDU
            $table->string('verification_status', 30)->default('EN_ATTENTE_VALIDATION');
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable();

            // Chemins dans le bucket privé identity-docs — jamais d'URL publique,
            // l'accès passe par une URL signée à durée limitée (§10).
            $table->string('id_doc_front_url', 500)->nullable();
            $table->string('id_doc_back_url', 500)->nullable();
            $table->string('selfie_url', 500)->nullable();

            // Zone d'intervention déclarée par le technicien, et point de départ
            // servant au calcul de distance.
            $table->magellanPolygon('service_area', 4326, 'GEOGRAPHY')->nullable();
            $table->magellanPoint('base_location', 4326, 'GEOGRAPHY')->nullable();
            $table->unsignedSmallInteger('service_radius_km')->default(5);

            // Position en direct, poussée uniquement pendant une intervention.
            $table->magellanPoint('last_known_location', 4326, 'GEOGRAPHY')->nullable();
            $table->timestamp('last_position_at')->nullable();

            $table->boolean('is_online')->default(false);

            // Statistiques de matching. Un nouveau technicien démarre à 4,00 sur
            // ses cinq premières interventions (§8.3) : cette valeur neutre est
            // portée par le service de scoring, pas par la base.
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->unsignedInteger('jobs_completed')->default(0);
            $table->decimal('acceptance_rate', 5, 4)->default(0);
            $table->decimal('cancellation_rate', 5, 4)->default(0);

            $table->timestamps();

            // Index composite imposé au §9 : c'est le filtre de tête du matching.
            $table->index(['is_online', 'verification_status']);
        });

        // Index GiST obligatoires sur toutes les colonnes géographiques (§9).
        DB::statement('CREATE INDEX technician_profiles_service_area_gist ON technician_profiles USING GIST (service_area)');
        DB::statement('CREATE INDEX technician_profiles_base_location_gist ON technician_profiles USING GIST (base_location)');
        DB::statement('CREATE INDEX technician_profiles_last_known_location_gist ON technician_profiles USING GIST (last_known_location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_profiles');
    }
};
