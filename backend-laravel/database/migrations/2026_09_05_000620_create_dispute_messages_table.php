<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messagerie interne d'un litige (§6).
 *
 * Elle ne réutilise pas `messages` : ce fil-là est entre le client et le
 * technicien, alors que celui-ci est tenu par le support et peut s'adresser à
 * l'un, à l'autre, ou aux deux. Les mélanger exposerait au client ce que le
 * support écrit au technicien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispute_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dispute_id')->constrained()->cascadeOnDelete();

            // SUPPORT | CLIENT | TECHNICIEN
            $table->string('author_type', 20);
            $table->unsignedBigInteger('author_id')->nullable();

            // CLIENT | TECHNICIEN | LES_DEUX — qui voit ce message.
            $table->string('audience', 20)->default('LES_DEUX');

            // Note interne : jamais visible par les parties.
            $table->boolean('is_internal')->default(false);

            $table->text('content');
            $table->timestamp('created_at')->nullable();

            $table->index(['dispute_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_messages');
    }
};
