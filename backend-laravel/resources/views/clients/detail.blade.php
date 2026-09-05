@extends('layouts.backoffice')

@section('titre', $client->full_name)
@section('rubrique', 'Clients')

@php
    use App\Domain\Accounts\Data\UserStatus;
    use App\Support\Money;

    $suspendu = $client->status === UserStatus::SUSPENDU;
    $peutSuspendre = auth('admin')->user()?->can('clients.suspendre') ?? false;
@endphp

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <a href="{{ route('clients') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                </a>
                <h2 class="dm-page-head__title mb-0">{{ $client->full_name }}</h2>
                @include('composants.badge-statut-compte', ['statut' => $client->status])
                @if ($client->is_technician)
                    <a href="{{ route('techniciens.detail', $client) }}" class="dm-badge dm-badge--tech text-decoration-none">
                        Aussi technicien
                    </a>
                @endif
            </div>
            <p class="dm-page-head__lede">
                <span class="font-monospace">{{ $client->phone }}</span>
                @if ($client->email) · {{ $client->email }} @endif
                · inscrit le {{ $client->created_at?->translatedFormat('j F Y') }}
            </p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="dm-stat">
                <span class="dm-stat__icon is-primary" aria-hidden="true"><i class="bi bi-ticket-detailed"></i></span>
                <div>
                    <div class="dm-stat__value">{{ $ticketsTotal }}</div>
                    <div class="dm-stat__label">Demandes</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dm-stat">
                <span class="dm-stat__icon is-success" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
                <div>
                    <div class="dm-stat__value" style="font-size:1.25rem">{{ Money::format($depense, false) }}</div>
                    <div class="dm-stat__label">Total dépensé (GNF)</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dm-stat">
                <span class="dm-stat__icon is-warning" aria-hidden="true"><i class="bi bi-award"></i></span>
                <div>
                    <div class="dm-stat__value">{{ $client->clientProfile?->loyalty_points ?? 0 }}</div>
                    <div class="dm-stat__label">Points de fidélité</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dm-stat">
                <span class="dm-stat__icon is-tech" aria-hidden="true"><i class="bi bi-geo-alt"></i></span>
                <div>
                    <div class="dm-stat__value">{{ $client->addresses->count() }}</div>
                    <div class="dm-stat__label">Adresses enregistrées</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="dm-card p-4">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">
                    Dernières interventions
                </h3>

                @forelse ($tickets as $ticket)
                    <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                        <a href="{{ route('tickets.detail', $ticket) }}" class="font-monospace small">{{ $ticket->reference }}</a>
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-truncate">{{ $ticket->service?->name }}</div>
                            <div class="small text-body-secondary">
                                {{ $ticket->created_at?->translatedFormat('j M Y') }}
                                @if ($ticket->technician) · {{ $ticket->technician->full_name }} @endif
                            </div>
                        </div>
                        @include('composants.badge-etat', ['etat' => $ticket->state])
                        <span class="dm-amount">{{ Money::format($ticket->total_gnf) }}</span>
                    </div>
                @empty
                    <p class="text-body-secondary mb-0">Ce client n'a encore publié aucune demande.</p>
                @endforelse
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="dm-card p-4 mb-3">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Adresses</h3>

                @forelse ($client->addresses as $adresse)
                    <div class="py-2 border-bottom">
                        <div class="fw-medium">
                            {{ $adresse->label ?? 'Sans nom' }}
                            @if ($adresse->is_default)
                                <span class="dm-badge dm-badge--primary ms-1">Par défaut</span>
                            @endif
                        </div>
                        <div class="small">{{ $adresse->formatted_address }}</div>
                        @if ($adresse->landmark)
                            <div class="small text-body-secondary">{{ $adresse->landmark }}</div>
                        @endif
                    </div>
                @empty
                    <p class="text-body-secondary mb-0">Aucune adresse enregistrée.</p>
                @endforelse
            </div>

            <div class="dm-card p-4">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Compte</h3>

                @if ($peutSuspendre)
                    <form method="POST" action="{{ route('clients.statut', $client) }}">
                        @csrf
                        <input type="hidden" name="statut" value="{{ $suspendu ? 'ACTIF' : 'SUSPENDU' }}">

                        @unless ($suspendu)
                            <label for="motif-suspension" class="form-label">Motif de la suspension</label>
                            <textarea id="motif-suspension" name="motif" rows="2" maxlength="300"
                                      class="form-control mb-2"
                                      placeholder="Exemple : demandes répétées annulées à l'arrivée du technicien."></textarea>
                        @endunless

                        <button type="submit" class="btn btn-sm {{ $suspendu ? 'btn-success' : 'btn-outline-danger' }} w-100">
                            <i class="bi {{ $suspendu ? 'bi-arrow-counterclockwise' : 'bi-slash-circle' }} me-1" aria-hidden="true"></i>
                            {{ $suspendu ? 'Réactiver le compte' : 'Suspendre le compte' }}
                        </button>
                    </form>

                    <p class="text-body-secondary mt-3 mb-0" style="font-size:.75rem">
                        Un compte ayant une intervention en cours ne peut pas être suspendu :
                        l'autre partie se retrouverait sans interlocuteur.
                    </p>
                @else
                    <p class="text-body-secondary small mb-0">
                        Ton rôle permet de consulter cette fiche, pas de modifier le compte.
                    </p>
                @endif
            </div>
        </div>
    </div>

@endsection
