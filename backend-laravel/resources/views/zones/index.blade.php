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
            <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Quartiers par commune</h3>
            @foreach ($zones->groupBy('commune') as $commune => $quartiers)
                <div class="mb-3">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="bi bi-geo-alt text-primary" aria-hidden="true"></i>
                        <span class="fw-semibold">{{ $commune }}</span>
                        <span class="text-body-secondary small">{{ $quartiers->count() }} quartier(s)</span>
                    </div>
                    @foreach ($quartiers as $zone)
                        <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-medium text-truncate">{{ $zone->name }}</div>
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
            @endforeach
        </div>
    </div>

@endsection
