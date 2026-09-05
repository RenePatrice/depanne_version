@extends('layouts.backoffice')

@section('titre', 'Techniciens')
@section('rubrique', 'Opérations')

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <h2 class="dm-page-head__title">Techniciens</h2>
            <p class="dm-page-head__lede">
                Les quatre statistiques qui pèsent dans le score de matching sont visibles ici :
                note, interventions, taux d'acceptation et taux d'annulation.
            </p>
        </div>

        <a href="{{ route('techniciens.validation') }}" class="btn btn-primary align-self-center">
            <i class="bi bi-person-check me-1" aria-hidden="true"></i>
            File de validation
            @if ($enAttente > 0)
                <span class="badge rounded-pill text-bg-light ms-1">{{ $enAttente }}</span>
            @endif
        </a>
    </div>

    <x-table-serveur
        id="techniciens"
        :url="route('techniciens.donnees')"
        :export-url="route('techniciens.export')"
        :order="[[5, 'desc']]"
        :colonnes="[
            ['data' => 'full_name', 'libelle' => 'Nom', 'name' => 'users.full_name'],
            ['data' => 'phone', 'libelle' => 'Téléphone', 'name' => 'users.phone', 'classe' => 'text-nowrap'],
            ['data' => 'specialites', 'libelle' => 'Spécialités', 'searchable' => false, 'orderable' => false],
            ['data' => 'verification', 'libelle' => 'Vérification', 'name' => 'technician_profiles.verification_status', 'searchable' => false],
            ['data' => 'presence', 'libelle' => 'Présence', 'name' => 'technician_profiles.is_online', 'searchable' => false],
            ['data' => 'jobs_completed', 'libelle' => 'Interventions', 'name' => 'technician_profiles.jobs_completed', 'searchable' => false, 'classe' => 'text-end'],
            ['data' => 'note', 'libelle' => 'Note', 'searchable' => false, 'classe' => 'text-end'],
            ['data' => 'acceptation', 'libelle' => 'Acceptation', 'searchable' => false, 'classe' => 'text-end'],
            ['data' => 'solde_formate', 'libelle' => 'Portefeuille', 'searchable' => false, 'classe' => 'text-end'],
            ['data' => 'actions', 'libelle' => '', 'searchable' => false, 'orderable' => false, 'classe' => 'text-end'],
        ]">

        <x-slot:filtres>
            <div>
                <label for="filtre-verification">Vérification</label>
                <select class="form-select" id="filtre-verification" name="verification">
                    <option value="">Toutes</option>
                    @foreach ($verifications as $verification)
                        <option value="{{ $verification['valeur'] }}">{{ $verification['libelle'] }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filtre-specialite">Spécialité</label>
                <select class="form-select" id="filtre-specialite" name="specialite">
                    <option value="">Toutes</option>
                    @foreach ($specialites as $specialite)
                        <option value="{{ $specialite['valeur'] }}">{{ $specialite['libelle'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-check align-self-center mt-3">
                <input class="form-check-input" type="checkbox" id="filtre-en-ligne" name="en_ligne" value="1">
                <label class="form-check-label" for="filtre-en-ligne"
                       style="text-transform:none;letter-spacing:0;font-weight:400">
                    En ligne uniquement
                </label>
            </div>
        </x-slot:filtres>
    </x-table-serveur>

@endsection
