@extends('layouts.backoffice')

@section('titre', 'Catalogue & tarifs')
@section('rubrique', 'Administration')

@php
    use App\Support\Money;

    $peutModifier = auth('admin')->user()?->can('catalogue.modifier') ?? false;
@endphp

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <h2 class="dm-page-head__title">Catalogue &amp; tarifs</h2>
            <p class="dm-page-head__lede">
                Les prix modifiés ici ne s'appliquent qu'aux <strong>demandes à venir</strong> :
                chaque ticket porte son propre instantané de prix, si bien qu'un changement de
                grille ne réécrit jamais une intervention déjà publiée.
            </p>
        </div>

        @if ($peutModifier)
            <button class="btn btn-primary align-self-center" type="button"
                    data-bs-toggle="collapse" data-bs-target="#form-prestation">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nouvelle prestation
            </button>
        @endif
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($peutModifier)
        <div class="collapse mb-4 {{ $errors->any() ? 'show' : '' }}" id="form-prestation">
            <div class="dm-card p-4">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">
                    Nouvelle prestation
                </h3>

                <form method="POST" action="{{ route('catalogue.prestation.creer') }}">
                    @csrf
                    @include('catalogue.partials.champs-prestation', ['categories' => $categories, 'prestation' => null])
                    <button type="submit" class="btn btn-primary mt-3">Créer la prestation</button>
                </form>
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            @foreach ($categories as $categorie)
                <div class="dm-card p-4 mb-3">
                    <div class="dm-categorie-titre">
                        <span class="dm-categorie-pastille" aria-hidden="true"
                              style="background: {{ $categorie->color }}1F; color: {{ $categorie->color }}">
                            <i class="bi {{ $categorie->icon }}"></i>
                        </span>
                        <div class="flex-grow-1">
                            <h3 class="h6 mb-0">{{ $categorie->name }}</h3>
                            <div class="small text-body-secondary">
                                {{ $categorie->services->count() }} prestation(s) ·
                                code <span class="font-monospace">{{ $categorie->code->value }}</span>
                            </div>
                        </div>
                        <span class="dm-badge dm-badge--{{ $categorie->is_active ? 'success' : 'secondary' }}">
                            {{ $categorie->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        @if ($peutModifier)
                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#categorie-{{ $categorie->id }}"
                                    title="Modifier la catégorie">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </button>
                        @endif
                    </div>

                    @if ($peutModifier)
                        <div class="collapse mb-3" id="categorie-{{ $categorie->id }}">
                            <form method="POST" action="{{ route('catalogue.categorie.modifier', $categorie) }}"
                                  class="border rounded-3 p-3">
                                @csrf
                                <div class="row g-2">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="cat-nom-{{ $categorie->id }}">Nom</label>
                                        <input class="form-control" id="cat-nom-{{ $categorie->id }}" name="name"
                                               value="{{ $categorie->name }}" required maxlength="80">
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label" for="cat-couleur-{{ $categorie->id }}">Couleur</label>
                                        <input class="form-control form-control-color w-100" type="color"
                                               id="cat-couleur-{{ $categorie->id }}" name="color" value="{{ $categorie->color }}">
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label" for="cat-ordre-{{ $categorie->id }}">Ordre</label>
                                        <input class="form-control" type="number" min="0" max="999"
                                               id="cat-ordre-{{ $categorie->id }}" name="sort_order" value="{{ $categorie->sort_order }}">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="cat-desc-{{ $categorie->id }}">Description</label>
                                        <input class="form-control" id="cat-desc-{{ $categorie->id }}" name="description"
                                               value="{{ $categorie->description }}" maxlength="300">
                                    </div>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" value="1" name="is_active"
                                           id="cat-actif-{{ $categorie->id }}" @checked($categorie->is_active)>
                                    <label class="form-check-label small" for="cat-actif-{{ $categorie->id }}">Catégorie active</label>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary mt-2">Enregistrer</button>
                            </form>
                        </div>
                    @endif

                    @foreach ($categorie->services as $prestation)
                        <div class="dm-prestation {{ $prestation->is_active ? '' : 'is-inactive' }}">
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-medium">{{ $prestation->name }}</div>
                                <div class="small text-body-secondary">
                                    {{ $prestation->estimated_duration_min }} min ·
                                    {{ $usages[$prestation->id] ?? 0 }} demande(s)
                                    @unless ($prestation->is_active) · <span class="text-danger">désactivée</span> @endunless
                                </div>
                            </div>

                            <span class="dm-amount">{{ Money::format($prestation->base_price_gnf) }}</span>

                            @if ($peutModifier)
                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#prestation-{{ $prestation->id }}"
                                        title="Modifier {{ $prestation->name }}">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                </button>

                                <form method="POST" action="{{ route('catalogue.prestation.basculer', $prestation) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary"
                                            title="{{ $prestation->is_active ? 'Désactiver' : 'Réactiver' }}">
                                        <i class="bi {{ $prestation->is_active ? 'bi-slash-circle' : 'bi-arrow-counterclockwise' }}"
                                           aria-hidden="true"></i>
                                    </button>
                                </form>
                            @endif
                        </div>

                        @if ($peutModifier)
                            <div class="collapse" id="prestation-{{ $prestation->id }}">
                                <form method="POST" action="{{ route('catalogue.prestation.modifier', $prestation) }}"
                                      class="border rounded-3 p-3 my-2">
                                    @csrf
                                    @include('catalogue.partials.champs-prestation', [
                                        'categories' => $categories,
                                        'prestation' => $prestation,
                                    ])
                                    <button type="submit" class="btn btn-sm btn-primary mt-3">Enregistrer</button>
                                </form>
                            </div>
                        @endif
                    @endforeach

                    @if ($categorie->services->isEmpty())
                        <p class="text-body-secondary small mb-0">Aucune prestation dans cette catégorie.</p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="col-12 col-xl-4">
            <div class="dm-card p-4">
                <h3 class="h6 text-uppercase text-body-secondary mb-1" style="letter-spacing:.08em">
                    Historique des modifications
                </h3>
                <p class="text-body-secondary mb-3" style="font-size:.75rem">
                    Repris du journal d'audit, limité au catalogue.
                </p>

                @forelse ($historique as $entree)
                    <div class="py-2 border-bottom">
                        <div class="small">{{ $entree->description }}</div>
                        <div class="text-body-secondary" style="font-size:.72rem">
                            {{ $entree->causer?->full_name ?? 'Système' }} ·
                            {{ $entree->created_at?->translatedFormat('j M Y à H:i') }}
                        </div>
                    </div>
                @empty
                    <p class="text-body-secondary small mb-0">
                        Aucune modification enregistrée pour l'instant. La première création ou
                        modification de prestation apparaîtra ici.
                    </p>
                @endforelse
            </div>
        </div>
    </div>

@endsection
