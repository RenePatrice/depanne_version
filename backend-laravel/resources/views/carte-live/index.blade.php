@extends('layouts.backoffice')

@section('titre', 'Carte live')
@section('rubrique', 'Pilotage')

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <h2 class="dm-page-head__title">Carte live</h2>
            <p class="dm-page-head__lede">
                Techniciens en ligne et interventions en cours, en temps réel.
                Les positions sont poussées par le serveur WebSocket ; si celui-ci
                n'est pas joignable, la carte bascule sur un rafraîchissement périodique
                et le signale.
            </p>
        </div>
    </div>

    <div data-react-component="CarteLive"
         data-props="{{ json_encode([
             'urlDonnees' => route('carte-live.donnees'),
             'urlCompteurs' => route('carte-live.compteurs'),
             'categories' => $categories,
             'etats' => $etatsActifs,
         ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}">

        <div class="dm-empty">
            <span class="dm-empty__icon" aria-hidden="true"><i class="bi bi-geo-alt"></i></span>
            <p class="dm-empty__text">Chargement de la carte…</p>
        </div>
    </div>

@endsection
