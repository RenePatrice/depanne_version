<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une ligne par technicien sollicité (§8.3). La diffusion étant séquentielle,
 * cette table raconte l'ordre exact des sollicitations, le score qui a produit
 * cet ordre, et ce que chacun a répondu — indispensable pour comprendre après
 * coup pourquoi un ticket est resté sans réponse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedTinyInteger('cycle')->default(1);       // 1 à 3
            $table->unsignedSmallInteger('radius_km');              // 5, 10 puis 15
            $table->unsignedSmallInteger('position');               // rang dans le cycle

            $table->decimal('score', 6, 4);
            $table->jsonb('score_breakdown')->nullable();           // détail des 4 composantes
            $table->decimal('distance_km', 6, 2);

            // EN_ATTENTE | ACCEPTE | REFUSE | EXPIRE | ANNULE
            $table->string('response', 20)->default('EN_ATTENTE');
            $table->string('refusal_reason', 200)->nullable();

            $table->timestamp('notified_at');
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['ticket_id', 'technician_id', 'cycle']);
            $table->index(['technician_id', 'response']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_attempts');
    }
};
