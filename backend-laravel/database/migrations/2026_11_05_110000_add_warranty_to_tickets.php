<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Garantie après intervention (#4, décision du 5 novembre 2026).
 *
 * Une panne qui revient dans les jours suivant la clôture ouvre droit à un
 * retour du même technicien, sans nouveau paiement. Le retour est lui-même un
 * ticket — il apparaît sur la carte du support, a sa conversation et sa machine
 * à états — mais rattaché à l'intervention d'origine par `parent_ticket_id` et
 * marqué `is_warranty`.
 *
 * `warranty_until` est figé à la clôture de l'intervention payante : changer le
 * délai en back-office ne doit pas raccourcir rétroactivement une garantie déjà
 * promise au client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->unsignedBigInteger('parent_ticket_id')
                ->nullable()
                ->after('id');

            $table->foreign('parent_ticket_id')
                ->references('id')->on('tickets')
                ->nullOnDelete();

            $table->boolean('is_warranty')
                ->default(false)
                ->after('parent_ticket_id');

            // Figé à l'entrée en CLOTUREE d'une intervention payante (voir
            // TransitionTicket). Nul tant que le ticket n'est pas clôturé.
            $table->timestamp('warranty_until')
                ->nullable()
                ->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropForeign(['parent_ticket_id']);
            $table->dropColumn(['parent_ticket_id', 'is_warranty', 'warranty_until']);
        });
    }
};
