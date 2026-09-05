<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/*
 * Le schéma porte des garanties dont dépend tout le reste : sans index GiST le
 * matching s'effondre en charge, et un montant en flottant fausserait la
 * répartition financière. Ces tests verrouillent ces choix.
 */

it('déclare toutes les colonnes géographiques en GEOGRAPHY 4326', function (): void {
    $colonnes = DB::table('geography_columns')
        ->selectRaw("f_table_name || '.' || f_geography_column as nom, type, srid")
        ->get()
        ->keyBy('nom');

    $attendues = [
        'addresses.location' => 'Point',
        'tickets.location' => 'Point',
        'zones.boundary' => 'Polygon',
        'technician_profiles.base_location' => 'Point',
        'technician_profiles.last_known_location' => 'Point',
        'technician_profiles.service_area' => 'Polygon',
    ];

    foreach ($attendues as $nom => $type) {
        expect($colonnes)->toHaveKey($nom)
            ->and($colonnes[$nom]->type)->toBe($type)
            ->and((int) $colonnes[$nom]->srid)->toBe(4326);
    }
});

it('indexe en GiST chacune de ces colonnes', function (): void {
    $index = DB::table('pg_indexes')
        ->where('indexdef', 'like', '%USING gist%')
        ->pluck('indexname')
        ->all();

    expect($index)->toContain(
        'addresses_location_gist',
        'tickets_location_gist',
        'zones_boundary_gist',
        'technician_profiles_base_location_gist',
        'technician_profiles_last_known_location_gist',
        'technician_profiles_service_area_gist',
    );
});

it('porte les deux index composites imposés par le cahier des charges', function (): void {
    $index = DB::table('pg_indexes')->pluck('indexname')->all();

    expect($index)->toContain(
        'tickets_state_created_at_index',
        'technician_profiles_is_online_verification_status_index',
    );
});

it('stocke tous les montants en entiers, jamais en flottant', function (): void {
    $colonnes = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('column_name', 'like', '%_gnf')
        ->get(['table_name', 'column_name', 'data_type']);

    expect($colonnes)->not->toBeEmpty();

    foreach ($colonnes as $colonne) {
        expect($colonne->data_type)
            ->toBe('bigint', "{$colonne->table_name}.{$colonne->column_name} devrait être un bigint");
    }
});

it('impose l\'unicité du téléphone et de la référence de paiement', function (): void {
    $index = DB::table('pg_indexes')->pluck('indexname')->all();

    // Le téléphone est l'identifiant de connexion, la référence fournisseur est
    // la clé d'idempotence du webhook de paiement (§8.4).
    expect($index)->toContain('users_phone_unique', 'payments_provider_ref_unique');
});
