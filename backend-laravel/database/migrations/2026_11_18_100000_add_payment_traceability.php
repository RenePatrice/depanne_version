<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Traçabilité complète des paiements (ADR-0047).
 *
 * Le client choisit son moyen — espèces ou Mobile Money — et un paiement direct
 * n'est réglé qu'avec la confirmation **des deux parties**. Chaque étape est
 * écrite dans `payment_events`, table en ajout seul : on n'y met jamais à jour
 * ni n'y supprime une ligne, c'est ce qui en fait une piste d'audit.
 *
 * Migration additive : les paiements existants restent valides. Les anciens
 * paiements en espèces (`provider = cash`, déclarés par le technicien) sont
 * rattachés au canal DIRECT avec leurs confirmations reconstituées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            // DIRECT : payé de la main à la main ou par transfert au technicien.
            // APP : Mobile Money encaissé par l'application, sous séquestre.
            $table->string('channel', 10)->default('APP');
            $table->string('declared_by', 12)->nullable();
            // Référence de transaction donnée par le client (SMS de l'opérateur).
            $table->string('transaction_ref', 60)->nullable();
            $table->timestamp('client_confirmed_at')->nullable();
            $table->timestamp('technician_confirmed_at')->nullable();
            $table->timestamp('contested_at')->nullable();
            $table->string('contested_by', 12)->nullable();
            $table->string('contest_reason', 500)->nullable();
            $table->timestamp('reminded_at')->nullable();

            $table->index(['channel', 'status']);
        });

        DB::table('payments')->where('provider', 'cash')->update([
            'channel' => 'DIRECT',
            'declared_by' => 'TECHNICIEN',
            'technician_confirmed_at' => DB::raw('created_at'),
        ]);
        DB::table('payments')->where('provider', 'cash')->where('status', 'CONFIRMEE')->update([
            'client_confirmed_at' => DB::raw('captured_at'),
        ]);

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            // CLIENT | TECHNICIEN | ADMIN | SYSTEME
            $table->string('actor_type', 12);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('amount_gnf')->nullable();
            $table->jsonb('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_id', 'id']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['channel', 'status']);
            $table->dropColumn([
                'channel', 'declared_by', 'transaction_ref',
                'client_confirmed_at', 'technician_confirmed_at',
                'contested_at', 'contested_by', 'contest_reason', 'reminded_at',
            ]);
        });
    }
};
