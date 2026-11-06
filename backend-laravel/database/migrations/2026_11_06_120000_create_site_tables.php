<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plateforme vitrine (site public).
 *
 * Deux tables :
 *   - `site_membres`   : l'équipe présentée sur le site, éditable en back-office.
 *   - `site_evenements`: la fréquentation (visites, clics de téléchargement…),
 *     agrégée au tableau de bord. Une ligne par événement, `created_at` seul :
 *     un événement ne se modifie pas, il s'ajoute.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_membres', function (Blueprint $table): void {
            $table->id();
            $table->string('nom');
            $table->string('role');
            $table->text('bio')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('lien_url')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index(['actif', 'ordre']);
        });

        Schema::create('site_evenements', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->index();
            $table->string('chemin')->nullable();
            $table->string('referent')->nullable();
            $table->json('meta')->nullable();
            // Empreinte de l'IP (haché) : comptage approximatif des visiteurs
            // uniques sans jamais stocker d'adresse en clair.
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_evenements');
        Schema::dropIfExists('site_membres');
    }
};
