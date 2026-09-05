<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des transitions du ticket (§8.1). Chaque changement d'état y laisse
 * une trace horodatée et attribuée : c'est la timeline affichée au support et
 * la preuve en cas de litige. Cette table n'est jamais modifiée après écriture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('from_state', 40)->nullable();
            $table->string('to_state', 40);

            // CLIENT | TECHNICIEN | ADMIN | SYSTEME
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id')->nullable();

            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
    }
};
