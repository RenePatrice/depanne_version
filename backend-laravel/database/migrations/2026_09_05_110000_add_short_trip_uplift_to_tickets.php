<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Majoration appliquée aux interventions de proximité (§8.2 révisé).
 *
 * Sous le seuil de kilomètres inclus, le déplacement n'est pas facturé : la
 * prestation est majorée de 1 %, et cette majoration revient **en totalité au
 * technicien**. Elle doit donc être distinguée du prix de la prestation, sur
 * lequel la commission s'applique normalement.
 *
 * On pourrait croire la valeur redérivable — 1 % de `base_price_gnf` quand la
 * distance est sous le seuil — mais le taux et le seuil sont pilotables depuis
 * le back-office. Les rejouer dans six mois, avec les réglages de six mois plus
 * tard, donnerait un autre chiffre que celui facturé. Le montant est donc figé
 * sur le ticket, comme le reste du prix (ADR-0013).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->unsignedBigInteger('short_trip_uplift_gnf')
                ->default(0)
                ->after('travel_fee_gnf');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn('short_trip_uplift_gnf');
        });
    }
};
