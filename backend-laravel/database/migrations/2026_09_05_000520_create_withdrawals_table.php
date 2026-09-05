<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes de retrait des techniciens (§8.4). Workflow validé en back-office :
 * EN_ATTENTE → APPROUVE → PAYE, ou REJETE avec motif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('technician_id')->constrained('users')->restrictOnDelete();

            $table->unsignedBigInteger('amount_gnf');
            $table->string('mobile_money_number', 20);
            $table->string('provider', 30);              // ORANGE_MONEY | MTN_MOMO

            // EN_ATTENTE | APPROUVE | PAYE | REJETE
            $table->string('status', 20)->default('EN_ATTENTE');
            $table->string('note', 300)->nullable();

            $table->foreignId('processed_by')->nullable();  // admin_users.id
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'requested_at']);
            $table->index(['technician_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
