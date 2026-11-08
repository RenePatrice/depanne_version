@extends('layouts.backoffice')

@section('titre', 'Site vitrine')
@section('rubrique', 'Administration')

@php
    use App\Domain\Settings\Models\AppSetting;
@endphp

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <h2 class="dm-page-head__title">Site vitrine</h2>
            
        </div>

        <nav class="dm-periodes" aria-label="Période d'analyse">
            @foreach ($choixPeriodes as $choix)
                <a href="{{ route('site-admin', ['periode' => $choix['cle']]) }}"
                   class="{{ $periode->cle === $choix['cle'] ? 'is-active' : '' }}"
                   @if ($periode->cle === $choix['cle']) aria-current="page" @endif>
                    {{ $choix['libelle'] }}
                </a>
            @endforeach
        </nav>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger d-flex gap-2" role="alert">
            <i class="bi bi-exclamation-triangle mt-1" aria-hidden="true"></i>
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    {{-- ---------- Fréquentation ---------- --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="dm-card p-3 h-100">
                <div class="text-body-secondary small">Visites</div>
                <div class="h3 mb-0">{{ number_format($resume['visites'], 0, ',', ' ') }}</div>
                @if ($resume['evolution'] !== null)
                    <span class="dm-badge dm-badge--{{ $resume['evolution'] >= 0 ? 'success' : 'danger' }}">
                        {{ $resume['evolution'] >= 0 ? '+' : '' }}{{ $resume['evolution'] }} %
                    </span>
                @endif
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dm-card p-3 h-100">
                <div class="text-body-secondary small">Visiteurs uniques</div>
                <div class="h3 mb-0">{{ number_format($resume['visiteurs'], 0, ',', ' ') }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dm-card p-3 h-100">
                <div class="text-body-secondary small">Clics mesurés</div>
                <div class="h3 mb-0">{{ number_format($resume['total_clics'], 0, ',', ' ') }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dm-card p-3 h-100">
                <div class="text-body-secondary small">Membres affichés</div>
                <div class="h3 mb-0">{{ $equipe->where('actif', true)->count() }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-8">
            <div class="dm-chart-card h-100">
                <div class="dm-chart-card__head">
                    <h3 class="dm-chart-card__title">Visites par jour</h3>
                </div>
                <div class="dm-chart"
                     data-graphique="ligne"
                     data-serie="{{ json_encode($serie, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}"></div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="dm-chart-card h-100">
                <div class="dm-chart-card__head">
                    <h3 class="dm-chart-card__title">Détail des clics</h3>
                </div>
                <ul class="list-unstyled d-flex flex-column gap-2 mt-2 mb-0">
                    @foreach ($resume['clics'] as $clic)
                        <li class="d-flex align-items-center gap-3">
                            <i class="bi {{ $clic['icone'] }} fs-5 text-primary flex-shrink-0" aria-hidden="true"></i>
                            <span class="flex-grow-1 text-body-secondary">{{ $clic['libelle'] }}</span>
                            <strong class="fs-5">{{ number_format($clic['total'], 0, ',', ' ') }}</strong>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    {{-- ---------- Contenu éditorial ---------- --}}
    <div class="dm-card p-4 mb-4">
        <h3 class="h6 mb-3">Contenu & téléchargement</h3>
        <form method="POST" action="{{ route('site-admin.contenu') }}" class="row g-3">
            @csrf
            <div class="col-12">
                <label class="form-label" for="{{ AppSetting::SITE_HERO_TITRE }}">Titre de la page d'accueil</label>
                <input type="text" class="form-control" maxlength="160"
                       id="{{ AppSetting::SITE_HERO_TITRE }}" name="{{ AppSetting::SITE_HERO_TITRE }}"
                       value="{{ old(AppSetting::SITE_HERO_TITRE, $contenu[AppSetting::SITE_HERO_TITRE]) }}" required>
            </div>
            <div class="col-12">
                <label class="form-label" for="{{ AppSetting::SITE_HERO_SOUS_TITRE }}">Sous-titre</label>
                <textarea class="form-control" rows="2" maxlength="400"
                          id="{{ AppSetting::SITE_HERO_SOUS_TITRE }}" name="{{ AppSetting::SITE_HERO_SOUS_TITRE }}" required>{{ old(AppSetting::SITE_HERO_SOUS_TITRE, $contenu[AppSetting::SITE_HERO_SOUS_TITRE]) }}</textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="{{ AppSetting::SITE_ANNONCE }}">Bandeau d'annonce <span class="text-body-secondary">(vide = aucun)</span></label>
                <input type="text" class="form-control" maxlength="200"
                       id="{{ AppSetting::SITE_ANNONCE }}" name="{{ AppSetting::SITE_ANNONCE }}"
                       value="{{ old(AppSetting::SITE_ANNONCE, $contenu[AppSetting::SITE_ANNONCE]) }}">
            </div>

            <div class="col-md-6">
                <label class="form-label" for="{{ AppSetting::SITE_APK_URL }}">Lien APK (téléchargement direct)</label>
                <input type="url" class="form-control" maxlength="2048" placeholder="https://…/parema.apk"
                       id="{{ AppSetting::SITE_APK_URL }}" name="{{ AppSetting::SITE_APK_URL }}"
                       value="{{ old(AppSetting::SITE_APK_URL, $contenu[AppSetting::SITE_APK_URL]) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="{{ AppSetting::SITE_PLAYSTORE_URL }}">Lien Google Play Store</label>
                <input type="url" class="form-control" maxlength="2048" placeholder="https://play.google.com/…"
                       id="{{ AppSetting::SITE_PLAYSTORE_URL }}" name="{{ AppSetting::SITE_PLAYSTORE_URL }}"
                       value="{{ old(AppSetting::SITE_PLAYSTORE_URL, $contenu[AppSetting::SITE_PLAYSTORE_URL]) }}">
            </div>

            <div class="col-md-6">
                <label class="form-label" for="{{ AppSetting::SITE_CONTACT_EMAIL }}">E-mail de contact public</label>
                <input type="email" class="form-control" maxlength="160"
                       id="{{ AppSetting::SITE_CONTACT_EMAIL }}" name="{{ AppSetting::SITE_CONTACT_EMAIL }}"
                       value="{{ old(AppSetting::SITE_CONTACT_EMAIL, $contenu[AppSetting::SITE_CONTACT_EMAIL]) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="{{ AppSetting::SITE_CONTACT_WHATSAPP }}">WhatsApp public <span class="text-body-secondary">(ex. 224620000000)</span></label>
                <input type="text" class="form-control" maxlength="20"
                       id="{{ AppSetting::SITE_CONTACT_WHATSAPP }}" name="{{ AppSetting::SITE_CONTACT_WHATSAPP }}"
                       value="{{ old(AppSetting::SITE_CONTACT_WHATSAPP, $contenu[AppSetting::SITE_CONTACT_WHATSAPP]) }}">
            </div>

            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="{{ AppSetting::SITE_TELECHARGEMENT_ACTIF }}" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" value="1"
                           id="{{ AppSetting::SITE_TELECHARGEMENT_ACTIF }}" name="{{ AppSetting::SITE_TELECHARGEMENT_ACTIF }}"
                           @checked($contenu[AppSetting::SITE_TELECHARGEMENT_ACTIF])>
                    <label class="form-check-label" for="{{ AppSetting::SITE_TELECHARGEMENT_ACTIF }}">
                        Afficher les boutons de téléchargement sur le site
                    </label>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">Enregistrer le contenu</button>
            </div>
        </form>
    </div>

    {{-- ---------- Équipe ---------- --}}
    <div class="dm-card p-4 mb-4">
        <h3 class="h6 mb-1">Équipe présentée sur le site</h3>
        <p class="text-body-secondary small mb-3">
            Déposez une photo (JPG/PNG/WebP, 3 Mo max) pour chaque membre. Sans photo, une
            pastille d'initiales s'affiche. L'ordre règle la position sur le site.
        </p>

        <div class="row g-3">
            @foreach ($equipe as $membre)
                <div class="col-md-6 col-xl-4">
                    <div class="dm-card p-3 h-100 border">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-circle overflow-hidden flex-shrink-0 d-grid"
                                 style="width:56px;height:56px;background:#1B6FF3;place-items:center;color:#fff;font-weight:700">
                                @if ($membre->photoUrl())
                                    <img src="{{ $membre->photoUrl() }}" alt="" style="width:100%;height:100%;object-fit:cover">
                                @else
                                    {{ $membre->initiales() }}
                                @endif
                            </div>
                            <div class="min-w-0">
                                <strong class="d-block text-truncate">{{ $membre->nom }}</strong>
                                <span class="small text-body-secondary">{{ $membre->role }}</span>
                            </div>
                            <span class="dm-badge dm-badge--{{ $membre->actif ? 'success' : 'neutral' }} ms-auto">
                                {{ $membre->actif ? 'Affiché' : 'Masqué' }}
                            </span>
                        </div>

                        <form method="POST" action="{{ route('site-admin.equipe.modifier', $membre) }}"
                              enctype="multipart/form-data" class="row g-2">
                            @csrf
                            <div class="col-12">
                                <label class="form-label small">Nom</label>
                                <input type="text" class="form-control form-control-sm" name="nom" maxlength="120"
                                       value="{{ $membre->nom }}" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Rôle</label>
                                <input type="text" class="form-control form-control-sm" name="role" maxlength="120"
                                       value="{{ $membre->role }}" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Bio</label>
                                <textarea class="form-control form-control-sm" name="bio" rows="2" maxlength="400">{{ $membre->bio }}</textarea>
                            </div>
                            <div class="col-8">
                                <label class="form-label small">Lien (optionnel)</label>
                                <input type="url" class="form-control form-control-sm" name="lien_url" maxlength="2048"
                                       value="{{ $membre->lien_url }}" placeholder="https://linkedin.com/in/…">
                            </div>
                            <div class="col-4">
                                <label class="form-label small">Ordre</label>
                                <input type="number" class="form-control form-control-sm" name="ordre" min="0" max="999"
                                       value="{{ $membre->ordre }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Remplacer la photo</label>
                                <input type="file" class="form-control form-control-sm" name="photo" accept="image/*">
                            </div>
                            <input type="hidden" name="actif" value="{{ $membre->actif ? 1 : 0 }}">
                            <div class="col-12 d-flex gap-2 mt-1">
                                <button type="submit" class="btn btn-sm btn-primary">Enregistrer</button>
                            </div>
                        </form>

                        <div class="d-flex gap-2 mt-2 pt-2 border-top">
                            <form method="POST" action="{{ route('site-admin.equipe.basculer', $membre) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    {{ $membre->actif ? 'Masquer' : 'Afficher' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('site-admin.equipe.supprimer', $membre) }}"
                                  onsubmit="return confirm('Retirer {{ $membre->nom }} de l\'équipe ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <hr class="my-4">

        <h3 class="h6 mb-3">Ajouter un membre</h3>
        <form method="POST" action="{{ route('site-admin.equipe.creer') }}"
              enctype="multipart/form-data" class="row g-3">
            @csrf
            <div class="col-md-4">
                <label class="form-label" for="nouveau_nom">Nom</label>
                <input type="text" class="form-control" id="nouveau_nom" name="nom" maxlength="120"
                       value="{{ old('nom') }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="nouveau_role">Rôle</label>
                <input type="text" class="form-control" id="nouveau_role" name="role" maxlength="120"
                       value="{{ old('role') }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="nouveau_ordre">Ordre</label>
                <input type="number" class="form-control" id="nouveau_ordre" name="ordre" min="0" max="999" value="{{ old('ordre', 0) }}">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Ajouter</button>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="nouvelle_bio">Bio</label>
                <textarea class="form-control" id="nouvelle_bio" name="bio" rows="2" maxlength="400">{{ old('bio') }}</textarea>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="nouveau_lien">Lien (optionnel)</label>
                <input type="url" class="form-control" id="nouveau_lien" name="lien_url" maxlength="2048" value="{{ old('lien_url') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="nouvelle_photo">Photo</label>
                <input type="file" class="form-control" id="nouvelle_photo" name="photo" accept="image/*">
            </div>
        </form>
    </div>

@endsection
