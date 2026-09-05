@extends('layouts.backoffice')

@section('titre', 'Tableau de bord')
@section('rubrique', 'Pilotage')

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <h2 class="dm-page-head__title">
                Bonjour {{ explode(' ', (string) auth('admin')->user()?->full_name)[0] }}
            </h2>
            <p class="dm-page-head__lede">
                {{ $periode->libelle }}, du {{ $periode->debut->translatedFormat('j F') }}
                au {{ $periode->fin->translatedFormat('j F Y') }}.
            </p>
        </div>

        <nav class="dm-periodes" aria-label="Période d'analyse">
            @foreach ($choixPeriodes as $choix)
                <a href="{{ route('tableau-de-bord', ['periode' => $choix['cle']]) }}"
                   class="{{ $periode->cle === $choix['cle'] ? 'is-active' : '' }}"
                   @if ($periode->cle === $choix['cle']) aria-current="page" @endif>
                    {{ $choix['libelle'] }}
                </a>
            @endforeach
        </nav>
    </div>

    {{-- Cartes KPI --}}
    <div class="row g-3 mb-4">
        @foreach ($indicateurs as $indicateur)
            <div class="col-6 col-lg-4 col-xxl-3">
                @include('tableau-de-bord.partials.kpi', ['indicateur' => $indicateur])
            </div>
        @endforeach
    </div>

    {{-- Volume quotidien et taux d'acceptation --}}
    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-8">
            <div class="dm-chart-card">
                <div class="dm-chart-card__head">
                    <h3 class="dm-chart-card__title">Volume des demandes</h3>
                    <span class="dm-chart-card__note">par jour</span>
                </div>
                <div class="dm-chart"
                     data-graphique="ligne"
                     data-serie="{{ json_encode($volume, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}"></div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="dm-chart-card">
                <div class="dm-chart-card__head">
                    <h3 class="dm-chart-card__title">Taux d'acceptation</h3>
                    <span class="dm-chart-card__note">objectif 70 %</span>
                </div>
                <div class="dm-chart"
                     data-graphique="jauge"
                     data-valeur="{{ $tauxAcceptation }}"></div>
                <p class="text-body-secondary mb-0" style="font-size:.75rem">
                    En dessous de 45 %, le matching séquentiel s'allonge et des demandes
                    finissent sans réponse.
                </p>
            </div>
        </div>
    </div>

    {{-- Répartition et revenus --}}
    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-5">
            <div class="dm-chart-card">
                <div class="dm-chart-card__head">
                    <h3 class="dm-chart-card__title">Répartition par catégorie</h3>
                    <span class="dm-chart-card__note">{{ $periode->libelle }}</span>
                </div>
                <div class="dm-chart"
                     data-graphique="camembert"
                     data-serie="{{ json_encode($categories, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}"></div>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            <div class="dm-chart-card">
                <div class="dm-chart-card__head">
                    <h3 class="dm-chart-card__title">Répartition des encaissements</h3>
                    <span class="dm-chart-card__note">par {{ $revenus['pas'] }}</span>
                </div>
                <div class="dm-chart"
                     data-graphique="colonnes"
                     data-serie="{{ json_encode($revenus, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}"></div>
            </div>
        </div>
    </div>

    {{-- Carte de chaleur et flux d'activité --}}
    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="dm-chart-card">
                <div class="dm-chart-card__head">
                    <h3 class="dm-chart-card__title">Quand les pannes arrivent</h3>
                    <span class="dm-chart-card__note">heure × jour de la semaine</span>
                </div>
                <div class="dm-chart"
                     data-graphique="heatmap"
                     data-serie="{{ json_encode($heatmap, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}"></div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="dm-chart-card">
                <div class="dm-chart-card__head">
                    <h3 class="dm-chart-card__title">Activité en direct</h3>
                    <span class="dm-chart-card__note">
                        <span class="dm-badge dm-badge--success">Live</span>
                    </span>
                </div>

                {{-- L'îlot démarre avec les événements rendus ici : la liste
                     n'est jamais vide au premier affichage. --}}
                <div data-react-component="FluxActivite"
                     data-props="{{ json_encode([
                         'url' => route('flux-activite'),
                         'initial' => $activite,
                     ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}">
                    <ul class="dm-flux">
                        @foreach (array_slice($activite, 0, 6) as $evenement)
                            <li class="dm-flux__item">
                                <span class="dm-flux__puce dm-flux__puce--{{ $evenement['ton'] }}" aria-hidden="true"></span>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="dm-badge dm-badge--{{ $evenement['ton'] }}">{{ $evenement['etatLibelle'] }}</span>
                                        <span class="font-monospace small text-body-secondary">{{ $evenement['reference'] }}</span>
                                    </div>
                                    <div class="small text-body-secondary mt-1 text-truncate">
                                        {{ $evenement['client'] }}@if ($evenement['technicien']) · {{ $evenement['technicien'] }}@endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>

@endsection
