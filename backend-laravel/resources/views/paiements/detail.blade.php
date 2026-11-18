@extends('layouts.backoffice')

@section('titre', 'Paiement '.$paiement->provider_ref)
@section('rubrique', 'Paiements')

@php
    use App\Domain\Payments\Data\PaymentChannel;
    use App\Domain\Payments\Data\PaymentStatus;
    use App\Support\Money;
    use App\Support\OpaqueId;

    $ticket = $paiement->ticket;
    $direct = $paiement->channel === PaymentChannel::DIRECT;
@endphp

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <a href="{{ route('paiements') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                </a>
                <h2 class="dm-page-head__title mb-0 font-monospace">{{ $paiement->provider_ref }}</h2>
                <span class="dm-badge dm-badge--{{ $paiement->status->color() }}">{{ $paiement->status->label() }}</span>
            </div>
            <p class="dm-page-head__lede">
                {{ $paiement->method->label() }} · {{ $paiement->channel->label() }} ·
                créé le {{ $paiement->created_at?->translatedFormat('j F Y à H:i') }}
                @if ($ticket)
                    · intervention <a class="font-monospace" href="{{ route('tickets.detail', OpaqueId::encode($ticket->id)) }}">{{ $ticket->reference }}</a>
                @endif
            </p>
        </div>

        <div class="dm-amount fs-3 align-self-center">{{ Money::format($paiement->amount_gnf) }}</div>
    </div>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($paiement->status === PaymentStatus::CONTESTEE)
        <div class="alert alert-danger d-flex gap-2" role="alert">
            <i class="bi bi-exclamation-octagon mt-1" aria-hidden="true"></i>
            <div>
                <strong>Contesté par le {{ mb_strtolower($paiement->contested_by?->label() ?? '') }}</strong>
                le {{ $paiement->contested_at?->translatedFormat('j F Y à H:i') }}.
                <div class="mt-1">« {{ $paiement->contest_reason }} »</div>
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="dm-card p-4 mb-3">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Les deux parties</h3>

                <div class="row g-3">
                    @foreach ([['Client', $ticket?->client, $paiement->client_confirmed_at, 'clients.detail'], ['Technicien', $ticket?->technician, $paiement->technician_confirmed_at, 'techniciens.detail']] as [$role, $personne, $confirme, $routeFiche])
                        <div class="col-12 col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="dm-stat__label">{{ $role }}</div>
                                @if ($personne)
                                    <a href="{{ route($routeFiche, OpaqueId::encode($personne->id)) }}" class="fw-semibold">{{ $personne->full_name }}</a>
                                    <div class="font-monospace small text-body-secondary">{{ $personne->phone }}</div>
                                @else
                                    <span class="text-body-secondary">—</span>
                                @endif

                                <div class="mt-2 small">
                                    @if (! $direct && $role === 'Technicien')
                                        <span class="text-body-secondary"><i class="bi bi-dash-circle" aria-hidden="true"></i> Sans objet (encaissé par l'application)</span>
                                    @elseif ($confirme)
                                        <span class="text-success"><i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                                            {{ $direct ? 'A confirmé' : 'A validé sur son téléphone' }} le {{ $confirme->translatedFormat('j M Y à H:i') }}</span>
                                    @else
                                        <span class="text-warning"><i class="bi bi-hourglass-split" aria-hidden="true"></i> N'a pas encore confirmé</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-6 col-md-4">
                        <div class="dm-stat__label">Déclaré par</div>
                        <div>{{ $paiement->declared_by?->label() ?? '—' }}</div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="dm-stat__label">Référence opérateur</div>
                        <div class="font-monospace">{{ $paiement->transaction_ref ?? '—' }}</div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="dm-stat__label">Commission</div>
                        <div class="dm-amount">{{ Money::format((int) $ticket?->commission_gnf) }}</div>
                    </div>
                </div>
            </div>

            <div class="dm-card p-4">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Journal du paiement</h3>

                @forelse ($paiement->events as $evenement)
                    <div class="d-flex gap-3 py-2 border-bottom small">
                        <i class="bi {{ $evenement->type->icone() }} fs-5 text-body-secondary" aria-hidden="true"></i>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between gap-2">
                                <strong>{{ $evenement->type->label() }}</strong>
                                <span class="text-body-secondary text-nowrap">{{ $evenement->created_at?->translatedFormat('j M Y · H:i:s') }}</span>
                            </div>
                            <div class="text-body-secondary">
                                {{ $evenement->actor_type->label() }}
                                @if ($evenement->actor_type->value === 'ADMIN' && $evenement->actor_id)
                                    · {{ $admins[$evenement->actor_id] ?? 'compte #'.$evenement->actor_id }}
                                @endif
                                @if ($evenement->amount_gnf !== null && in_array($evenement->type->value, ['COMMISSION', 'LIBERE', 'REMBOURSE'], true))
                                    · {{ Money::format($evenement->amount_gnf) }}
                                @endif
                            </div>
                            @foreach (array_filter([
                                'Moyen' => $evenement->details['moyen'] ?? null,
                                'Référence opérateur' => $evenement->details['reference_transaction'] ?? null,
                                'Motif' => $evenement->details['motif'] ?? null,
                                'Décision' => $evenement->details['decision'] ?? null,
                                'Relancé' => isset($evenement->details['destinataire']) ? mb_strtolower($evenement->details['destinataire']) : null,
                            ]) as $libelle => $valeur)
                                <div><span class="text-body-secondary">{{ $libelle }} :</span> {{ $valeur }}</div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-body-secondary small mb-0">
                        Aucune étape journalisée : paiement antérieur à la mise en place du journal.
                    </p>
                @endforelse
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="dm-card p-4 mb-3">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Décision du support</h3>

                @if ($arbitrable)
                    @can('paiements.arbitrer')
                        <p class="small text-body-secondary">
                            Joignez les deux parties avant de décider. La décision est motivée, signée de votre nom
                            et transmise au client comme au technicien.
                        </p>
                        <form method="POST" action="{{ route('paiements.arbitrer', $paiement) }}">
                            @csrf
                            <div class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="decision" id="d-confirmer" value="confirmer" @checked(old('decision') === 'confirmer')>
                                    <label class="form-check-label" for="d-confirmer">
                                        <strong>Confirmer</strong> — le paiement a bien eu lieu
                                        <span class="d-block small text-body-secondary">Commission inscrite au technicien, intervention clôturée.</span>
                                    </label>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="radio" name="decision" id="d-annuler" value="annuler" @checked(old('decision') === 'annuler')>
                                    <label class="form-check-label" for="d-annuler">
                                        <strong>Annuler</strong> — le paiement n'a pas eu lieu
                                        <span class="d-block small text-body-secondary">Une nouvelle déclaration devient possible.</span>
                                    </label>
                                </div>
                            </div>
                            <label class="form-label" for="motif">Motif</label>
                            <textarea class="form-control mb-2" id="motif" name="motif" rows="3" minlength="10" maxlength="500" required>{{ old('motif') }}</textarea>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-shield-check me-1" aria-hidden="true"></i>Enregistrer la décision
                            </button>
                        </form>
                    @else
                        <p class="small text-body-secondary mb-0">Ce paiement attend une décision ; votre rôle ne permet pas de la prendre.</p>
                    @endcan
                @else
                    <p class="small text-body-secondary mb-0">
                        @if ($direct)
                            Aucune décision attendue : le paiement n'est ni en attente ni contesté.
                        @else
                            Paiement encaissé par l'application : l'opérateur fait foi, il n'y a rien à arbitrer ici.
                            Un désaccord sur l'intervention se traite comme un litige.
                        @endif
                    </p>
                @endif
            </div>

            @if ($ticket)
                <div class="dm-card p-4 mb-3">
                    <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Intervention</h3>
                    <div class="small">
                        <div class="d-flex justify-content-between py-1"><span class="text-body-secondary">Prestation</span><span>{{ $ticket->service?->name }}</span></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-body-secondary">État</span><span>{{ $ticket->state->etat()->label() }}</span></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-body-secondary">Total facturé</span><span class="dm-amount">{{ Money::format((int) $ticket->total_gnf) }}</span></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-body-secondary">Terminée le</span><span>{{ $ticket->completed_at?->translatedFormat('j M Y à H:i') ?? '—' }}</span></div>
                    </div>
                </div>
            @endif

            @if ($autres->isNotEmpty())
                <div class="dm-card p-4">
                    <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Autres paiements de cette intervention</h3>
                    @foreach ($autres as $autre)
                        <a href="{{ route('paiements.detail', $autre) }}" class="d-flex justify-content-between align-items-baseline py-2 border-bottom small text-decoration-none">
                            <span class="font-monospace">{{ $autre->provider_ref }}</span>
                            <span class="dm-badge dm-badge--{{ $autre->status->color() }}">{{ $autre->status->label() }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

@endsection
