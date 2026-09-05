@extends('layouts.backoffice')

@section('titre', 'Retraits')
@section('rubrique', 'Finances')

@php use App\Support\Money; @endphp

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <h2 class="dm-page-head__title">Demandes de retrait</h2>
            <p class="dm-page-head__lede">
                Workflow : <strong>En attente → Approuvé → Payé</strong>, ou rejeté avec motif.
                Le solde du technicien ne bouge qu'au versement effectif — approuver, c'est décider ;
                payer, c'est sortir l'argent.
            </p>
        </div>

        <div class="d-flex gap-3 align-self-center">
            <div class="text-end">
                <div class="dm-stat__value" style="font-size:1.4rem">{{ $enAttente }}</div>
                <div class="dm-stat__label">en attente</div>
            </div>
            <div class="text-end">
                <div class="dm-stat__value" style="font-size:1.4rem">{{ Money::format($aVerser, false) }}</div>
                <div class="dm-stat__label">à verser (GNF)</div>
            </div>
        </div>
    </div>

    <x-table-serveur
        id="retraits"
        :url="route('retraits.donnees')"
        :export-url="route('retraits.export')"
        :order="[[1, 'asc']]"
        :colonnes="[
            ['data' => 'reference', 'libelle' => 'Référence', 'name' => 'withdrawals.reference', 'classe' => 'text-nowrap'],
            ['data' => 'requested_at', 'libelle' => 'Demandé le', 'name' => 'withdrawals.requested_at', 'searchable' => false, 'classe' => 'text-nowrap'],
            ['data' => 'technicien', 'libelle' => 'Technicien', 'orderable' => false],
            ['data' => 'numero', 'libelle' => 'Mobile Money', 'searchable' => false, 'orderable' => false],
            ['data' => 'montant', 'libelle' => 'Montant', 'searchable' => false, 'classe' => 'text-end'],
            ['data' => 'solde_formate', 'libelle' => 'Solde', 'searchable' => false, 'classe' => 'text-end'],
            ['data' => 'statut', 'libelle' => 'Statut', 'name' => 'withdrawals.status', 'searchable' => false],
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
