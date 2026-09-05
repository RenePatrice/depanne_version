<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Litiges et réclamations (§6). Ouvrables dans les 72 h suivant la fin de
 * l'intervention, avec priorité et échéance de traitement (SLA).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();

            // TRAVAIL_NON_CONFORME | SURFACTURATION | RETARD | COMPORTEMENT |
            // DEGAT_MATERIEL | NON_PAIEMENT | AUTRE
            $table->string('reason', 40);
            $table->text('description');
            $table->jsonb('evidence')->nullable();     // photos, captures

            // OUVERT | EN_COURS | RESOLU | REJETE
            $table->string('status', 20)->default('OUVERT');
            // BASSE | NORMALE | HAUTE | URGENTE
            $table->string('priority', 20)->default('NORMALE');
            $table->timestamp('sla_due_at')->nullable();

            // REMBOURSEMENT_TOTAL | REMBOURSEMENT_PARTIEL | SANCTION |
            // AUCUNE_ACTION | AVERTISSEMENT
            $table->string('resolution', 40)->nullable();
            $table->text('resolution_note')->nullable();
            $table->unsignedBigInteger('refund_gnf')->default(0);

            $table->foreignId('resolved_by')->nullable();  // admin_users.id
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
