<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prise de contact depuis le site vitrine.
 *
 *   - `site_messages` : messages déposés par le formulaire de contact, traités
 *     en back-office (`site.gerer`). Un message ne se supprime pas : il se
 *     marque « traité ».
 *   - `site_abonnes` : adresses inscrites à la lettre d'information. L'adresse
 *     est unique ; une réinscription ne crée pas de doublon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 120);
            $table->string('telephone', 30);
            $table->string('email', 160)->nullable();
            $table->string('sujet', 40);
            $table->text('message');
            $table->timestamp('traite_at')->nullable();
            $table->timestamps();

            $table->index(['traite_at', 'created_at']);
        });

        Schema::create('site_abonnes', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 160)->unique();
            // Page d'où vient l'inscription (accueil, contact…) : utile pour
            // savoir où le formulaire convertit.
            $table->string('source', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_abonnes');
        Schema::dropIfExists('site_messages');
    }
};
