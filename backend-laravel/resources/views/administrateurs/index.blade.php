@extends('layouts.backoffice')

@section('titre', 'Administrateurs')
@section('rubrique', 'Administration')

@php
    $moiId = auth('admin')->id();
@endphp

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <h2 class="dm-page-head__title">Comptes du back-office</h2>
            
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger d-flex gap-2" role="alert">
            <i class="bi bi-exclamation-triangle mt-1" aria-hidden="true"></i>
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    <div class="dm-card p-4 mb-4">
        <h3 class="h6 mb-3">Créer un compte</h3>
        <form method="POST" action="{{ route('administrateurs.creer') }}" class="row g-3">
            @csrf
            <div class="col-md-4">
                <label class="form-label" for="email">Adresse e-mail</label>
                <input type="email" id="email" name="email" required maxlength="190"
                       class="form-control" value="{{ old('email') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="full_name">Nom complet</label>
                <input type="text" id="full_name" name="full_name" required maxlength="120"
                       class="form-control" value="{{ old('full_name') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="role">Rôle</label>
                <select id="role" name="role" class="form-select">
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(old('role') === $role)>{{ $role }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required autocomplete="new-password"
                       class="form-control" minlength="12">
                <div class="form-text">Au moins 12 caractères.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="password_confirmation">Confirmer</label>
                <input type="password" id="password_confirmation" name="password_confirmation"
                       required autocomplete="new-password" class="form-control" minlength="12">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">Créer le compte</button>
            </div>
        </form>
    </div>

    <div class="dm-card p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>E-mail</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Dernière connexion</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($administrateurs as $admin)
                        @php $estMoi = (int) $admin->id === (int) $moiId; @endphp
                        <tr>
                            <td>
                                {{ $admin->full_name }}
                                @if ($estMoi)
                                    <span class="badge text-bg-light ms-1">toi</span>
                                @endif
                            </td>
                            <td class="text-body-secondary">{{ $admin->email }}</td>
                            <td><span class="badge text-bg-secondary">{{ $admin->getRoleNames()->first() ?? '—' }}</span></td>
                            <td>
                                @if ($admin->is_active)
                                    <span class="badge text-bg-success">Actif</span>
                                @else
                                    <span class="badge text-bg-danger">Désactivé</span>
                                @endif
                            </td>
                            <td class="text-body-secondary small">
                                {{ $admin->last_login_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary"
                                            data-bs-toggle="collapse" data-bs-target="#edit-{{ $admin->id }}">
                                        Modifier
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary"
                                            data-bs-toggle="collapse" data-bs-target="#pwd-{{ $admin->id }}">
                                        Mot de passe
                                    </button>
                                    @unless ($estMoi)
                                        <form method="POST" action="{{ route('administrateurs.statut', $admin) }}">
                                            @csrf
                                            <input type="hidden" name="actif" value="{{ $admin->is_active ? 0 : 1 }}">
                                            <button type="submit"
                                                    class="btn btn-outline-{{ $admin->is_active ? 'danger' : 'success' }}">
                                                {{ $admin->is_active ? 'Désactiver' : 'Réactiver' }}
                                            </button>
                                        </form>
                                    @endunless
                                </div>

                                <div class="collapse text-start mt-2" id="edit-{{ $admin->id }}">
                                    <form method="POST" action="{{ route('administrateurs.modifier', $admin) }}"
                                          class="border rounded-3 p-3 d-flex flex-wrap gap-2 justify-content-end">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="full_name" required maxlength="120"
                                               class="form-control form-control-sm" style="max-width:220px"
                                               value="{{ $admin->full_name }}">
                                        <select name="role" class="form-select form-select-sm" style="max-width:160px">
                                            @foreach ($roles as $role)
                                                <option value="{{ $role }}" @selected($admin->getRoleNames()->first() === $role)>{{ $role }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                                    </form>
                                </div>

                                <div class="collapse text-start mt-2" id="pwd-{{ $admin->id }}">
                                    <form method="POST" action="{{ route('administrateurs.mot-de-passe', $admin) }}"
                                          class="border rounded-3 p-3 d-flex flex-wrap gap-2 justify-content-end">
                                        @csrf
                                        <input type="password" name="password" required minlength="12"
                                               autocomplete="new-password" placeholder="Nouveau mot de passe"
                                               class="form-control form-control-sm" style="max-width:220px">
                                        <input type="password" name="password_confirmation" required minlength="12"
                                               autocomplete="new-password" placeholder="Confirmer"
                                               class="form-control form-control-sm" style="max-width:220px">
                                        <button type="submit" class="btn btn-secondary btn-sm">Réinitialiser</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

@endsection
