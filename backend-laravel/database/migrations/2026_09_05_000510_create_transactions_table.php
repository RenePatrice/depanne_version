<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comptabilité en mouvements (ADR-0004). Le solde d'un technicien n'est jamais
 * un champ que l'on incrémente : c'est la somme des lignes de cette table.
 *
 * `amount_gnf` est signé — un retrait ou une commission est une sortie.
 * `balance_after_gnf` est le solde recalculé au moment de l'écriture, dans la
 * même transaction SQL : il sert de point de contrôle, pas de source de vérité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();

            // EARNING | COMMISSION | WITHDRAWAL | REFUND | ADJUSTMENT | TIP
            $table->string('type', 20);

            $table->bigInteger('amount_gnf');
            $table->bigInteger('balance_after_gnf');
            $table->string('description', 300);
            $table->jsonb('metadata')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
