@extends('layouts.backoffice')

@section('titre', 'Journal d\'audit')
@section('rubrique', 'Administration')

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <h2 class="dm-page-head__title">Journal d'audit</h2>
            <p class="dm-page-head__lede">
                Toutes les actions administrateur, horodatées et attribuées.
                Ce journal est en <strong>lecture seule</strong> : aucune route ne permet de
                le modifier ni de l'effacer — une trace que l'on peut réécrire ne vaut rien
                le jour où elle sert.
            </p>
        </div>
        <span class="dm-badge dm-badge--secondary align-self-center">
            {{ number_format($total, 0, ',', ' ') }} entrées
        </span>
    </div>

    <x-table-serveur
        id="journal"
        :url="route('journal-audit.donnees')"
        :export-url="route('journal-audit.export')"
        :order="[[0, 'desc']]"
        :colonnes="[
            ['data' => 'created_at', 'libelle' => 'Date', 'name' => 'created_at', 'searchable' => false, 'classe' => 'text-nowrap'],
            ['data' => 'journal', 'libelle' => 'Journal', 'name' => 'log_name', 'searchable' => false],
            ['data' => 'description', 'libelle' => 'Action', 'name' => 'description'],
            ['data' => 'auteur', 'libelle' => 'Auteur', 'orderable' => false],
            ['data' => 'cible', 'libelle' => 'Objet', 'searchable' => false, 'orderable' => false],
            ['data' => 'changements', 'libelle' => 'Modifications', 'searchable' => false, 'orderable' => false],
        ]">

        <x-slot:filtres>
            <div>
                <label for="filtre-journal">Journal</label>
                <select class="form-select" id="filtre-journal" name="journal">
                    <option value="">Tous</option>
                    @foreach ($journaux as $cle => $libelle)
                        <option value="{{ $cle }}">{{ $libelle }}</option>
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
