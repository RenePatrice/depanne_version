@extends('layouts.backoffice')

@section('titre', 'Configuration')
@section('rubrique', 'Administration')

@php
    use App\Domain\Settings\Models\AppSetting;

    $peutModifier = auth('admin')->user()?->can('configuration.modifier') ?? false;
@endphp

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <h2 class="dm-page-head__title">Configuration</h2>
            <p class="dm-page-head__lede">
                Tout ce qui est réglable ici commande le calcul de prix, le matching ou la
                répartition financière. Rien de tout cela n'est codé en dur : c'est ce
                formulaire qui fait autorité.
            </p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger d-flex gap-2" role="alert">
            <i class="bi bi-exclamation-triangle mt-1" aria-hidden="true"></i>
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    <form method="POST" action="{{ route('configuration.enregistrer') }}">
        @csrf

        <ul class="nav nav-pills gap-2 mb-3" role="tablist">
            @foreach ($groupes as $index => $groupe)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $index === 0 ? 'active' : '' }}" type="button" role="tab"
                            data-bs-toggle="pill" data-bs-target="#groupe-{{ $groupe['cle'] }}"
                            aria-controls="groupe-{{ $groupe['cle'] }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                        <i class="bi {{ $groupe['icone'] }} me-1" aria-hidden="true"></i>{{ $groupe['libelle'] }}
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="tab-content">
            @foreach ($groupes as $index => $groupe)
                <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}"
                     id="groupe-{{ $groupe['cle'] }}" role="tabpanel">

                    <div class="dm-card p-4">
                        <p class="text-body-secondary mb-4" style="max-width:70ch">{{ $groupe['resume'] }}</p>

                        @foreach ($groupe['parametres'] as $parametre)
                            @php $valeur = AppSetting::get($parametre->key); @endphp

                            <div class="dm-param">
                                <div class="row g-3 align-items-start">
                                    <div class="col-12 col-lg-5">
                                        <label class="form-label mb-0" for="p-{{ $parametre->key }}">
                                            {{ $parametre->label }}
                                        </label>
                                        @if ($parametre->description)
                                            <div class="dm-param__aide">{{ $parametre->description }}</div>
                                        @endif
                                        <div class="dm-param__aide font-monospace" style="opacity:.7">{{ $parametre->key }}</div>
                                    </div>

                                    <div class="col-12 col-lg-7">
                                        @if ($parametre->key === AppSetting::SCORE_WEIGHTS)
                                            {{-- Les quatre pondérations du score de matching (§8.3). --}}
                                            <div class="dm-ponderations">
                                                @foreach (['proximity' => 'Proximité', 'rating' => 'Note', 'acceptance' => 'Acceptation', 'cancellation' => 'Annulation'] as $cle => $libelle)
                                                    <div>
                                                        <label class="form-label" for="poids-{{ $cle }}">{{ $libelle }}</label>
                                                        <input class="form-control" type="number" step="0.01" min="-1" max="1"
                                                               id="poids-{{ $cle }}"
                                                               name="parametres[{{ $parametre->key }}][{{ $cle }}]"
                                                               value="{{ $valeur[$cle] ?? 0 }}"
                                                               @disabled(! $peutModifier)>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="dm-param__aide">
                                                Les trois premières doivent totaliser 1,00. L'annulation est une
                                                pénalité : son poids reste négatif.
                                            </div>

                                        @elseif ($parametre->type === 'boolean')
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" value="1"
                                                       id="p-{{ $parametre->key }}" name="parametres[{{ $parametre->key }}]"
                                                       @checked((bool) $valeur) @disabled(! $peutModifier)>
                                            </div>

                                        @elseif ($parametre->group === 'contenus')
                                            <textarea class="form-control" rows="6" id="p-{{ $parametre->key }}"
                                                      name="parametres[{{ $parametre->key }}]"
                                                      @disabled(! $peutModifier)>{{ $valeur }}</textarea>

                                        @elseif ($parametre->group === 'notifications')
                                            <textarea class="form-control" rows="2" id="p-{{ $parametre->key }}"
                                                      name="parametres[{{ $parametre->key }}]"
                                                      @disabled(! $peutModifier)>{{ $valeur }}</textarea>

                                        @elseif ($parametre->type === 'integer')
                                            <input class="form-control" type="number" step="1"
                                                   id="p-{{ $parametre->key }}" name="parametres[{{ $parametre->key }}]"
                                                   value="{{ $valeur }}" @disabled(! $peutModifier)>

                                        @elseif ($parametre->type === 'decimal')
                                            <input class="form-control" type="number" step="0.01"
                                                   id="p-{{ $parametre->key }}" name="parametres[{{ $parametre->key }}]"
                                                   value="{{ $valeur }}" @disabled(! $peutModifier)>
                                            @if ($parametre->key === AppSetting::COMMISSION_RATE)
                                                <div class="dm-param__aide">
                                                    Soit <strong>{{ number_format((float) $valeur * 100, 1, ',', ' ') }} %</strong> —
                                                    sur 100 000 GNF :
                                                    {{ number_format(round(100000 * (float) $valeur), 0, ',', ' ') }} GNF pour la
                                                    plateforme, {{ number_format(100000 - round(100000 * (float) $valeur), 0, ',', ' ') }} GNF
                                                    pour le technicien.
                                                </div>
                                            @endif

                                        @else
                                            <input class="form-control" type="text"
                                                   id="p-{{ $parametre->key }}" name="parametres[{{ $parametre->key }}]"
                                                   value="{{ $valeur }}" @disabled(! $peutModifier)>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        @if ($peutModifier)
            <div class="d-flex align-items-center gap-3 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Enregistrer la configuration
                </button>
                <span class="text-body-secondary small">
                    Chaque modification est tracée dans le journal d'audit, avec sa valeur avant et après.
                </span>
            </div>
        @else
            <p class="text-body-secondary small mt-3">
                Ton rôle permet de consulter la configuration, pas de la modifier.
            </p>
        @endif
    </form>

@endsection
