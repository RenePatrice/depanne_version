<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paiement Mobile Money et séquestre logique (§8.4).
 *
 * `provider_ref` est UNIQUE : c'est la clé d'idempotence du webhook. Un même
 * événement rejoué par l'agrégateur ne peut pas capturer deux fois les fonds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();

            $table->string('provider', 30);              // mock | orange_money | mtn_momo
            $table->string('provider_ref', 120)->unique();
            $table->string('method', 30);                // ORANGE_MONEY | MTN_MOMO
            $table->string('payer_phone', 20);

            $table->unsignedBigInteger('amount_gnf');
            $table->string('currency', 3)->default('GNF');

            // EN_ATTENTE | CAPTUREE | LIBEREE | ECHOUEE | REMBOURSEE | EXPIREE
            $table->string('status', 20)->default('EN_ATTENTE');
            $table->string('failure_reason', 300)->nullable();

            $table->jsonb('webhook_payload')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('released_at')->nullable();

            // Libération automatique 24 h après la fin de l'intervention si le
            // client ne valide pas lui-même.
            $table->timestamp('auto_release_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'auto_release_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
