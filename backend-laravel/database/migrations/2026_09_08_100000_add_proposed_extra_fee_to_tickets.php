<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supplément **proposé** par le technicien après diagnostic (§8.1).
 *
 * Distinct de `extra_fee_gnf`, qui est le supplément **appliqué**. Tant que le
 * client n'a pas répondu, le total ne doit pas bouger : confondre les deux
 * ferait apparaître un montant que personne n'a validé, et le client verrait sa
 * facture augmenter avant même d'avoir dit oui.
 *
 * La valeur pourrait se retrouver dans les métadonnées de `ticket_events`, mais
 * y puiser une donnée dont dépend un prix reviendrait à traiter un journal
 * comme une table de travail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->unsignedBigInteger('proposed_extra_fee_gnf')
                ->nullable()
                ->after('extra_fee_gnf');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn('proposed_extra_fee_gnf');
        });
    }
};
