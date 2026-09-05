@extends('layouts.backoffice')

@section('titre', 'Zones de déploiement')
@section('rubrique', 'Administration')

@php
    $peutModifier = auth('admin')->user()?->can('zones.modifier') ?? false;
@endphp

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <h2 class="dm-page-head__title">Zones de déploiement</h2>
            <p class="dm-page-head__lede">
                Chaque zone porte son emprise et sa grille tarifaire de déplacement :
                tarif de base, prix au kilomètre et kilomètres inclus. Ces trois valeurs
                sont figées sur le ticket au moment de la publication.
            </p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div data-react-component="EditeurZones"
         data-props="{{ json_encode([
             'zones' => $zonesJson,
             'urlCreer' => route('zones.creer'),
             'urlModifier' => route('zones.modifier', ['zone' => '__ID__']),
             'urlBasculer' => route('zones.basculer', ['zone' => '__ID__']),
             'jeton' => csrf_token(),
             'peutModifier' => $peutModifier,
         ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}">

        {{-- Rendu de repli : la liste reste lisible avant le montage de l'îlot. --}}
        <div class="dm-card p-4">
            <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Zones existantes</h3>
            @foreach ($zones as $zone)
                <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                    <div class="flex-grow-1">
                        <div class="fw-medium">{{ $zone->name }}</div>
                        <div class="small text-body-secondary">
                            {{ number_format($zone->base_travel_fee_gnf, 0, ',', ' ') }} GNF
                            + {{ number_format($zone->price_per_km_gnf, 0, ',', ' ') }} GNF/km ·
                            {{ $zone->included_km }} km inclus ·
                            {{ $ticketsParZone[$zone->id] ?? 0 }} ticket(s)
                        </div>
                    </div>
                    <span class="dm-badge dm-badge--{{ $zone->is_active ? 'success' : 'secondary' }}">
                        {{ $zone->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

@endsection
