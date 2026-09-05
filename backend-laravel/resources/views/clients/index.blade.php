@extends('layouts.backoffice')

@section('titre', 'Clients')
@section('rubrique', 'Opérations')

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <h2 class="dm-page-head__title">Clients</h2>
            <p class="dm-page-head__lede">
                Comptes clients, historique et dépenses. La recherche porte sur le nom
                comme sur le numéro de téléphone.
            </p>
        </div>
    </div>

    <x-table-serveur
        id="clients"
        :url="route('clients.donnees')"
        :export-url="route('clients.export')"
        :order="[[5, 'desc']]"
        :colonnes="[
            ['data' => 'full_name', 'libelle' => 'Nom', 'name' => 'users.full_name'],
            ['data' => 'phone', 'libelle' => 'Téléphone', 'name' => 'users.phone', 'classe' => 'text-nowrap'],
            ['data' => 'statut', 'libelle' => 'Statut', 'name' => 'users.status'],
            ['data' => 'casquettes', 'libelle' => 'Profil', 'searchable' => false, 'orderable' => false],
            ['data' => 'interventions', 'libelle' => 'Interventions', 'searchable' => false, 'classe' => 'text-end'],
            ['data' => 'depense_formatee', 'libelle' => 'Total dépensé', 'searchable' => false, 'classe' => 'text-end'],
            ['data' => 'created_at', 'libelle' => 'Inscrit le', 'name' => 'users.created_at', 'searchable' => false, 'classe' => 'text-nowrap'],
            ['data' => 'actions', 'libelle' => '', 'searchable' => false, 'orderable' => false, 'classe' => 'text-end'],
        ]">

        <x-slot:filtres>
            <div>
                <label for="filtre-statut">Statut</label>
                <select class="form-select" id="filtre-statut" name="statut">
                    <option value="">Tous</option>
                    @foreach ($statuts as $statut)
                        <option value="{{ $statut['valeur'] }}">{{ $statut['libelle'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-check align-self-center mt-3">
                <input class="form-check-input" type="checkbox" id="filtre-double" name="double_casquette" value="1">
                <label class="form-check-label text-none" for="filtre-double"
                       style="text-transform:none;letter-spacing:0;font-weight:400">
                    Double casquette uniquement
                </label>
            </div>
        </x-slot:filtres>
    </x-table-serveur>

@endsection
