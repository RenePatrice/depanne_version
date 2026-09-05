@extends('layouts.backoffice')

@section('titre', 'Commissions')
@section('rubrique', 'Finances')

@php
    use App\Support\Money;

    $peutExporter = auth('admin')->user()?->can('finances.exporter') ?? false;
@endphp

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <h2 class="dm-page-head__title">Commissions &amp; encaissements</h2>
            <p class="dm-page-head__lede">
                {{ $periode->libelle }}, du {{ $periode->debut->translatedFormat('j F') }}
                au {{ $periode->fin->translatedFormat('j F Y') }}.
            </p>
        </div>

        <nav class="dm-periodes align-self-center" aria-label="Période d'analyse">
            @foreach ($choixPeriodes as $choix)
                <a href="{{ route('finances', ['periode' => $choix['cle']]) }}"
                   class="{{ $periode->cle === $choix['cle'] ? 'is-active' : '' }}"
                   @if ($periode->cle === $choix['cle']) aria-current="page" @endif>
                    {{ $choix['libelle'] }}
                </a>
            @endforeach
        </nav>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            ['libelle' => "Chiffre d'affaires encaissé", 'valeur' => $synthese['chiffre_affaires'], 'icone' => 'bi-cash-coin', 'ton' => 'primary'],
            ['libelle' => 'Commissions plateforme', 'valeur' => $synthese['commissions'], 'icone' => 'bi-percent', 'ton' => 'success'],
            ['libelle' => 'Part reversée aux techniciens', 'valeur' => $synthese['net_technicien'], 'icone' => 'bi-people', 'ton' => 'primary'],
            ['libelle' => 'Fonds en séquestre', 'valeur' => $synthese['sequestre'], 'icone' => 'bi-safe', 'ton' => 'warning'],
        ] as $carte)
            <div class="col-6 col-xl-3">
                <div class="dm-stat">
                    <span class="dm-stat__icon is-{{ $carte['ton'] }}" aria-hidden="true">
                        <i class="bi {{ $carte['icone'] }}"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="dm-stat__value" style="font-size:1.35rem">
                            {{ Money::format($carte['valeur'], false) }}
                            <span class="dm-amount__currency">GNF</span>
                        </div>
                        <div class="dm-stat__label">{{ $carte['libelle'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-8">
            <div class="dm-chart-card">
                <div class="dm-chart-card__head">
                    <h3 class="dm-chart-card__title">Commissions et part technicien</h3>
                    <span class="dm-chart-card__note">par {{ $commissions['pas'] }}</span>
                </div>
                <div class="dm-chart"
                     data-graphique="colonnes"
                     data-serie="{{ json_encode($commissions, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}"></div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="dm-card p-4 h-100">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">
                    Engagements
                </h3>

                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Dû aux techniciens</span>
                    <span class="dm-amount">{{ Money::format($synthese['du_aux_techniciens']) }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Retraits en attente</span>
                    <span class="dm-amount">{{ Money::format($synthese['retraits_en_attente']) }}</span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span>Retraits approuvés, à verser</span>
                    <span class="dm-amount text-warning">{{ Money::format($synthese['retraits_approuves']) }}</span>
                </div>

                <p class="text-body-secondary mt-3 mb-3" style="font-size:.75rem">
                    Le montant dû déduit déjà les retraits approuvés : ils sont engagés,
                    même s'ils n'ont pas encore quitté la plateforme.
                </p>

                <a href="{{ route('retraits') }}" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="bi bi-cash-stack me-1" aria-hidden="true"></i>Ouvrir la file des retraits
                </a>
            </div>
        </div>
    </div>

    <div class="dm-card p-4">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <h3 class="h6 text-uppercase text-body-secondary mb-0 me-auto" style="letter-spacing:.08em">
                Montants dus aux techniciens
            </h3>

            @if ($peutExporter)
                <span class="text-body-secondary small">Export comptable de la période :</span>
                @foreach (['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $libelle)
                    <a class="btn btn-sm btn-outline-secondary"
                       href="{{ route('finances.export', ['format' => $format, 'periode' => $periode->cle]) }}">{{ $libelle }}</a>
                @endforeach
            @endif
        </div>

        @if ($montantsDus === [])
            <p class="text-body-secondary mb-0">
                Aucun technicien n'a de solde créditeur pour l'instant.
            </p>
        @else
            <div class="dm-scroll-x">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Technicien</th>
                            <th>Téléphone</th>
                            <th class="text-end">Mouvements</th>
                            <th class="text-end">Solde dû</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($montantsDus as $du)
                            <tr>
                                <td>{{ $du['nom'] }}</td>
                                <td class="font-monospace small">{{ $du['telephone'] }}</td>
                                <td class="text-end">{{ $du['mouvements'] }}</td>
                                <td class="text-end"><span class="dm-amount">{{ Money::format($du['solde']) }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('techniciens.detail', $du['id']) }}"
                                       class="btn btn-sm btn-outline-secondary" title="Ouvrir la fiche">
                                        <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p class="text-body-secondary mt-3 mb-0" style="font-size:.75rem">
            Chaque solde est la somme des mouvements du technicien, jamais un compteur
            incrémenté : il se recalcule à chaque affichage.
        </p>
    </div>

@endsection
