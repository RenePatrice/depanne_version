@extends('layouts.backoffice')

@section('titre', 'Tickets')
@section('rubrique', 'Opérations')

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <h2 class="dm-page-head__title">Tickets</h2>
            <p class="dm-page-head__lede">
                Toutes les demandes d'intervention. Le tri, la recherche et la pagination sont
                calculés en base : la table ne charge jamais plus que la page affichée.
            </p>
        </div>
    </div>

    <x-table-serveur
        id="tickets"
        :url="route('tickets.donnees')"
        :export-url="route('tickets.export')"
        :order="[[1, 'desc']]"
        :colonnes="[
            ['data' => 'reference', 'libelle' => 'Référence', 'classe' => 'text-nowrap'],
            ['data' => 'created_at', 'libelle' => 'Date', 'name' => 'tickets.created_at', 'classe' => 'text-nowrap'],
            ['data' => 'statut', 'libelle' => 'Statut', 'name' => 'tickets.state'],
            ['data' => 'client', 'libelle' => 'Client'],
            ['data' => 'technicien', 'libelle' => 'Technicien'],
            ['data' => 'prestation', 'libelle' => 'Prestation', 'searchable' => false, 'orderable' => false],
            ['data' => 'zone', 'libelle' => 'Zone', 'searchable' => false, 'orderable' => false],
            ['data' => 'total', 'libelle' => 'Total', 'searchable' => false, 'classe' => 'text-end'],
            ['data' => 'actions', 'libelle' => '', 'searchable' => false, 'orderable' => false, 'classe' => 'text-end no-export'],
        ]">

        <x-slot:filtres>
            <div>
                <label for="filtre-etat">Statut</label>
                <select class="form-select" id="filtre-etat" name="etat">
                    <option value="">Tous</option>
                    @foreach ($etats as $etat)
                        <option value="{{ $etat['valeur'] }}">{{ $etat['libelle'] }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filtre-categorie">Catégorie</label>
                <select class="form-select" id="filtre-categorie" name="categorie">
                    <option value="">Toutes</option>
                    @foreach ($categories as $categorie)
                        <option value="{{ $categorie->id }}">{{ $categorie->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filtre-zone">Zone</label>
                <select class="form-select" id="filtre-zone" name="zone">
                    <option value="">Toutes</option>
                    @foreach ($zones as $zone)
                        <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filtre-du">Du</label>
                <input type="date" class="form-control" id="filtre-du" name="du">
            </div>

            <div>
                <label for="filtre-au">Au</label>
                <input type="date" class="form-control" id="filtre-au" name="au">
            </div>
        </x-slot:filtres>
    </x-table-serveur>

@endsection
