@extends('layouts.backoffice')

@section('titre', $technicien->full_name)
@section('rubrique', 'Techniciens')

@php
    use App\Domain\Accounts\Data\UserStatus;
    use App\Domain\Accounts\Data\VerificationStatus;
    use App\Domain\Catalog\Data\Specialty;
    use App\Support\Money;

    $suspendu = $technicien->status === UserStatus::SUSPENDU;
    $peutSanctionner = auth('admin')->user()?->can('techniciens.sanctionner') ?? false;
    $peutValider = auth('admin')->user()?->can('techniciens.valider') ?? false;
    $enAttente = $profil?->verification_status === VerificationStatus::EN_ATTENTE_VALIDATION;
@endphp

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <a href="{{ route('techniciens') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                </a>
                <h2 class="dm-page-head__title mb-0">{{ $technicien->full_name }}</h2>
                @if ($profil)
                    @include('composants.badge-verification', ['statut' => $profil->verification_status])
                @endif
                @include('composants.badge-statut-compte', ['statut' => $technicien->status])
                @if ($profil?->is_online)
                    <span class="dm-badge dm-badge--success">En ligne</span>
                @endif
            </div>
            <p class="dm-page-head__lede">
                <span class="font-monospace">{{ $technicien->phone }}</span>
                · inscrit le {{ $technicien->created_at?->translatedFormat('j F Y') }}
                @if ($profil?->verified_at)
                    · validé le {{ $profil->verified_at->translatedFormat('j F Y') }}
                @endif
            </p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($profil?->verification_status === VerificationStatus::REJETE && $profil->rejection_reason)
        <div class="alert alert-danger d-flex gap-2" role="alert">
            <i class="bi bi-x-octagon mt-1" aria-hidden="true"></i>
            <div>
                <strong>Dossier rejeté.</strong>
                <div class="small">{{ $profil->rejection_reason }}</div>
            </div>
        </div>
    @endif

    {{-- Les quatre statistiques du scoring (§8.3), dans l'ordre où elles y pèsent. --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="dm-stat">
                <span class="dm-stat__icon is-warning" aria-hidden="true"><i class="bi bi-star"></i></span>
                <div>
                    <div class="dm-stat__value">
                        {{ $profil && $profil->reviews_count > 0 ? number_format($profil->rating_avg, 2, ',', ' ') : '—' }}
                    </div>
                    <div class="dm-stat__label">
                        Note moyenne
                        @if ($profil?->isNewcomer())
                            <span class="d-block" style="font-size:.7rem">note neutre de 4,0 appliquée au matching</span>
                        @else
                            <span class="d-block" style="font-size:.7rem">{{ $profil?->reviews_count ?? 0 }} avis · poids 30 %</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dm-stat">
                <span class="dm-stat__icon is-primary" aria-hidden="true"><i class="bi bi-wrench-adjustable"></i></span>
                <div>
                    <div class="dm-stat__value">{{ $profil?->jobs_completed ?? 0 }}</div>
                    <div class="dm-stat__label">Interventions terminées</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dm-stat">
                <span class="dm-stat__icon is-success" aria-hidden="true"><i class="bi bi-hand-thumbs-up"></i></span>
                <div>
                    <div class="dm-stat__value">{{ number_format(($profil?->acceptance_rate ?? 0) * 100, 0) }} %</div>
                    <div class="dm-stat__label">Taux d'acceptation<span class="d-block" style="font-size:.7rem">poids 20 %</span></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dm-stat">
                <span class="dm-stat__icon is-danger" aria-hidden="true"><i class="bi bi-x-circle"></i></span>
                <div>
                    <div class="dm-stat__value">{{ number_format(($profil?->cancellation_rate ?? 0) * 100, 0) }} %</div>
                    <div class="dm-stat__label">Taux d'annulation<span class="d-block" style="font-size:.7rem">pénalité 10 %</span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-7">

            <div class="dm-card p-4 mb-3">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Dossier</h3>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach ($profil?->specialties ?? [] as $code)
                        <span class="dm-badge dm-badge--secondary">
                            {{ Specialty::tryFrom($code)?->label() ?? $code }}
                        </span>
                    @endforeach
                    <span class="dm-badge dm-badge--primary">Rayon {{ $profil?->service_radius_km ?? '—' }} km</span>
                </div>

                <div class="dm-pieces">
                    @foreach (['recto' => "Pièce d'identité — recto", 'verso' => "Pièce d'identité — verso", 'selfie' => 'Selfie'] as $cle => $titre)
                        <figure class="dm-piece">
                            <figcaption class="dm-piece__titre">{{ $titre }}</figcaption>
                            @if ($documents[$cle])
                                <a href="{{ $documents[$cle] }}" class="dm-piece__cadre" target="_blank" rel="noopener">
                                    <img src="{{ $documents[$cle] }}" alt="{{ $titre }}" loading="lazy">
                                </a>
                            @else
                                <div class="dm-piece__cadre dm-piece__cadre--vide">
                                    <i class="bi bi-file-earmark-lock" aria-hidden="true"></i>
                                    <span>Pièce non consultable</span>
                                    <small>Le bucket privé Supabase n'est pas encore branché.</small>
                                </div>
                            @endif
                        </figure>
                    @endforeach
                </div>
            </div>

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
                                @if ($ticket->client) · {{ $ticket->client->full_name }} @endif
                            </div>
                        </div>
                        @include('composants.badge-etat', ['etat' => $ticket->state])
                        <span class="dm-amount">{{ Money::format($ticket->total_gnf) }}</span>
                    </div>
                @empty
                    <p class="text-body-secondary mb-0">Aucune intervention à ce jour.</p>
                @endforelse
            </div>
        </div>

        <div class="col-12 col-xl-5">

            <div class="dm-card p-4 mb-3">
                <h3 class="h6 text-uppercase text-body-secondary mb-2" style="letter-spacing:.08em">Portefeuille</h3>
                <div class="dm-amount fs-4 mb-1">{{ Money::format($solde) }}</div>
                <p class="text-body-secondary mb-3" style="font-size:.75rem">
                    Somme des mouvements, jamais un compteur incrémenté.
                </p>

                @forelse ($mouvements as $mouvement)
                    <div class="d-flex justify-content-between align-items-baseline small py-1 border-bottom">
                        <div class="min-w-0">
                            <div class="text-truncate">{{ $mouvement->type->label() }}</div>
                            <div class="text-body-secondary" style="font-size:.7rem">
                                {{ $mouvement->created_at?->translatedFormat('j M H:i') }}
                            </div>
                        </div>
                        <span class="dm-amount {{ $mouvement->amount_gnf < 0 ? 'text-danger' : 'text-success' }}">
                            {{ Money::format($mouvement->amount_gnf) }}
                        </span>
                    </div>
                @empty
                    <p class="text-body-secondary small mb-0">Aucun mouvement.</p>
                @endforelse
            </div>

            <div class="dm-card p-4 mb-3">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Avis reçus</h3>

                @forelse ($avis as $avisRecu)
                    <div class="py-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="dm-amount">{{ $avisRecu->rating }}/5</span>
                            <span class="small text-body-secondary">
                                {{ $avisRecu->client?->full_name }} ·
                                {{ $avisRecu->created_at?->translatedFormat('j M Y') }}
                            </span>
                        </div>
                        @if ($avisRecu->comment)
                            <div class="small mt-1">« {{ $avisRecu->comment }} »</div>
                        @endif
                    </div>
                @empty
                    <p class="text-body-secondary small mb-0">Aucun avis pour l'instant.</p>
                @endforelse
            </div>

            <div class="dm-card p-4">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Décisions</h3>

                @if ($enAttente && $peutValider)
                    <form method="POST" action="{{ route('techniciens.approuver', $technicien) }}" class="mb-2">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm w-100">
                            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Approuver le dossier
                        </button>
                    </form>

                    <form method="POST" action="{{ route('techniciens.rejeter', $technicien) }}">
                        @csrf
                        <label for="motif-rejet" class="form-label">Motif du rejet</label>
                        <textarea id="motif-rejet" name="motif" rows="2" minlength="10" maxlength="500" required
                                  class="form-control mb-2"></textarea>
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">Rejeter le dossier</button>
                    </form>
                @elseif ($enAttente)
                    <p class="text-body-secondary small">
                        Ce dossier attend une décision, mais ton rôle ne permet pas de la prendre.
                    </p>
                @endif

                @if ($peutSanctionner)
                    <form method="POST" action="{{ route('techniciens.statut', $technicien) }}" class="mt-3 pt-3 border-top">
                        @csrf
                        <input type="hidden" name="statut" value="{{ $suspendu ? 'ACTIF' : 'SUSPENDU' }}">

                        @unless ($suspendu)
                            <label for="motif-sanction" class="form-label">Motif de la suspension</label>
                            <textarea id="motif-sanction" name="motif" rows="2" maxlength="300"
                                      class="form-control mb-2"
                                      placeholder="Exemple : trois annulations tardives en une semaine."></textarea>
                        @endunless

                        <button type="submit" class="btn btn-sm {{ $suspendu ? 'btn-success' : 'btn-outline-danger' }} w-100">
                            {{ $suspendu ? 'Réactiver le compte' : 'Suspendre le compte' }}
                        </button>
                    </form>

                    <p class="text-body-secondary mt-3 mb-0" style="font-size:.75rem">
                        Une suspension met immédiatement le technicien hors ligne : il sort du matching
                        sans attendre sa prochaine connexion.
                    </p>
                @endif
            </div>
        </div>
    </div>

@endsection
