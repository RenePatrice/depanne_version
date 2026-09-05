<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat in-app, ouvert entre l'acceptation et la clôture uniquement (§7.3).
 * Le contenu original est conservé à part du contenu affiché : le masquage des
 * coordonnées ne doit pas détruire la preuve pour le support.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();

            $table->text('content');            // contenu affiché, déjà masqué
            $table->text('original_content')->nullable(); // avant masquage, si modifié

            $table->boolean('is_flagged')->default(false);
            $table->string('flag_reason', 60)->nullable(); // TELEPHONE | EMAIL | LIEN

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
            $table->index(['is_flagged', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
