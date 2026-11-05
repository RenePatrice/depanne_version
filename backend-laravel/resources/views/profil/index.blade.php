@extends('layouts.backoffice')

@section('titre', 'Mon profil')
@section('rubrique', 'Compte')

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <h2 class="dm-page-head__title">Mon profil</h2>
            <p class="dm-page-head__lede">
                Change ton mot de passe ici. L'actuel est demandé : une session laissée
                ouverte sur un poste partagé ne doit pas suffire à le modifier.
            </p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="dm-card p-4">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Identité</h3>
                <dl class="row mb-0">
                    <dt class="col-5 text-body-secondary fw-normal">Nom</dt>
                    <dd class="col-7">{{ $admin->full_name }}</dd>
                    <dt class="col-5 text-body-secondary fw-normal">E-mail</dt>
                    <dd class="col-7">{{ $admin->email }}</dd>
                    <dt class="col-5 text-body-secondary fw-normal">Rôle</dt>
                    <dd class="col-7"><span class="badge text-bg-secondary">{{ $admin->getRoleNames()->first() ?? '—' }}</span></dd>
                    <dt class="col-5 text-body-secondary fw-normal">Dernière connexion</dt>
                    <dd class="col-7">{{ $admin->last_login_at?->timezone(config('app.timezone'))->format('d/m/Y à H:i') ?? '—' }}</dd>
                </dl>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="dm-card p-4">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Mot de passe</h3>

                @if ($errors->any())
                    <div class="alert alert-danger d-flex gap-2" role="alert">
                        <i class="bi bi-exclamation-triangle mt-1" aria-hidden="true"></i>
                        <div>{{ $errors->first() }}</div>
                    </div>
                @endif

                <form method="POST" action="{{ route('profil.mot-de-passe') }}" class="d-flex flex-column gap-3">
                    @csrf
                    <div>
                        <label class="form-label" for="actuel">Mot de passe actuel</label>
                        <input type="password" id="actuel" name="actuel" required
                               autocomplete="current-password" class="form-control">
                    </div>
                    <div>
                        <label class="form-label" for="nouveau">Nouveau mot de passe</label>
                        <input type="password" id="nouveau" name="nouveau" required minlength="12"
                               autocomplete="new-password" class="form-control">
                        <div class="form-text">Au moins 12 caractères, différent de l'ancien.</div>
                    </div>
                    <div>
                        <label class="form-label" for="nouveau_confirmation">Confirmer</label>
                        <input type="password" id="nouveau_confirmation" name="nouveau_confirmation" required
                               minlength="12" autocomplete="new-password" class="form-control">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Mettre à jour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection
