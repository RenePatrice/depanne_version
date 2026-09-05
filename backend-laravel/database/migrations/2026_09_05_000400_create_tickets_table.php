<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le ticket est le cœur du produit. Deux principes structurent cette table :
 *
 *  - le prix est figé au moment de la publication (snapshot) : une modification
 *    de la grille tarifaire ne doit jamais changer un ticket déjà publié (§8.2) ;
 *  - l'adresse est copiée en JSON, pas seulement référencée : si le client
 *    supprime son adresse, l'historique de l'intervention reste lisible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 20)->unique();   // DM-2026-000123

            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();

            // Nom de l'état spatie/laravel-model-states (§8.1).
            $table->string('state', 40);

            $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->jsonb('address_snapshot');
            $table->magellanPoint('location', 4326, 'GEOGRAPHY');

            $table->text('problem_description')->nullable();
            $table->jsonb('photos')->nullable();          // 3 au maximum côté client

            // Décomposition du prix, en entiers de GNF (ADR-0002).
            $table->decimal('distance_km', 6, 2)->nullable();
            $table->boolean('distance_is_estimated')->default(false); // repli Haversine
            $table->unsignedBigInteger('base_price_gnf');
            $table->unsignedBigInteger('travel_fee_gnf');
            $table->unsignedBigInteger('extra_fee_gnf')->default(0);
            $table->unsignedBigInteger('total_gnf');

            // Répartition, calculée à la libération du séquestre (§8.4).
            $table->unsignedBigInteger('commission_gnf')->nullable();
            $table->unsignedBigInteger('technician_net_gnf')->nullable();
            $table->decimal('commission_rate', 5, 4)->nullable(); // taux figé au paiement

            // Supplément de diagnostic soumis au client.
            $table->text('diagnosis')->nullable();
            $table->jsonb('diagnosis_photos')->nullable();

            $table->string('cancellation_reason', 300)->nullable();
            $table->unsignedBigInteger('cancellation_fee_gnf')->default(0);

            // Jalons du cycle de vie — ils alimentent les KPI du tableau de bord.
            $table->timestamp('published_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            // Index composite imposé au §9 : toutes les DataTables et tous les
            // graphiques filtrent par statut sur une période.
            $table->index(['state', 'created_at']);
            $table->index(['client_id', 'created_at']);
            $table->index(['technician_id', 'created_at']);
        });

        DB::statement('CREATE INDEX tickets_location_gist ON tickets USING GIST (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
