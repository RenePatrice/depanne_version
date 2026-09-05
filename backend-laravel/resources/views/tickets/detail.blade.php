@extends('layouts.backoffice')

@section('titre', 'Ticket '.$ticket->reference)
@section('rubrique', 'Tickets')

@php
    use App\Support\Money;
    $peutAgir = auth('admin')->user()?->can('tickets.agir') ?? false;
@endphp

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <a href="{{ route('tickets') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                </a>
                <h2 class="dm-page-head__title mb-0 font-monospace">{{ $ticket->reference }}</h2>
                @include('composants.badge-etat', ['etat' => $ticket->state])
            </div>
            <p class="dm-page-head__lede">
                {{ $ticket->service?->name }} · {{ $ticket->zone?->name ?? 'zone inconnue' }} ·
                créé le {{ $ticket->created_at?->translatedFormat('j F Y à H:i') }}
            </p>
        </div>

        <div class="dm-amount fs-4 align-self-center">{{ Money::format($ticket->total_gnf) }}</div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="row g-3">

        {{-- Colonne principale --}}
        <div class="col-12 col-xl-7">

            <div class="dm-card p-4 mb-3">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">
                    Déroulé de l'intervention
                </h3>

                @if ($ticket->events->isEmpty())
                    <p class="text-body-secondary mb-0">Aucune transition enregistrée.</p>
                @else
                    <ul class="dm-timeline">
                        @foreach ($ticket->events as $evenement)
                            <li class="dm-timeline__item">
                                <span class="dm-timeline__point dm-timeline__point--{{ $evenement->to_state->color() }}"
                                      aria-hidden="true"></span>
                                <div class="d-flex flex-wrap align-items-baseline gap-2">
                                    <strong>{{ $evenement->to_state->label() }}</strong>
                                    <span class="text-body-secondary small">
                                        par {{ $evenement->actor_type->label() }}
                                    </span>
                                    <span class="dm-timeline__heure ms-auto">
                                        {{ $evenement->created_at?->translatedFormat('j M à H:i') }}
                                    </span>
                                </div>
                                @if ($evenement->metadata['motif'] ?? null)
                                    <div class="small text-body-secondary mt-1">
                                        « {{ $evenement->metadata['motif'] }} »
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="dm-card p-4 mb-3">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <h3 class="h6 text-uppercase text-body-secondary mb-0" style="letter-spacing:.08em">
                        Conversation
                    </h3>
                    @php $signales = $ticket->messages->where('is_flagged', true)->count(); @endphp
                    @if ($signales > 0)
                        <span class="dm-badge dm-badge--danger">{{ $signales }} message{{ $signales > 1 ? 's' : '' }} signalé{{ $signales > 1 ? 's' : '' }}</span>
                    @endif
                </div>

                @if ($ticket->messages->isEmpty())
                    <p class="text-body-secondary mb-0">Aucun message échangé sur ce ticket.</p>
                @else
                    <div class="dm-chat">
                        @foreach ($ticket->messages->sortBy('created_at') as $message)
                            @php $estTechnicien = $message->sender_id === $ticket->technician_id; @endphp
                            <div class="dm-chat__bulle {{ $estTechnicien ? 'dm-chat__bulle--technicien' : '' }} {{ $message->is_flagged ? 'dm-chat__bulle--signale' : '' }}">
                                <div class="dm-chat__meta">
                                    {{ $message->sender?->full_name ?? 'Compte supprimé' }} ·
                                    {{ $message->created_at?->translatedFormat('j M H:i') }}
                                    @if ($message->is_flagged)
                                        · <span class="text-danger">signalé ({{ $message->flag_reason }})</span>
                                    @endif
                                </div>
                                {{ $message->content }}
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="dm-card p-4">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">
                    Sollicitations de matching
                </h3>

                @if ($ticket->matchAttempts->isEmpty())
                    <p class="text-body-secondary mb-0">Aucune sollicitation enregistrée.</p>
                @else
                    <div class="dm-scroll-x">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th><th>Technicien</th><th>Cycle</th>
                                    <th class="text-end">Score</th><th class="text-end">Distance</th><th>Réponse</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ticket->matchAttempts->sortBy('position') as $tentative)
                                    <tr>
                                        <td>{{ $tentative->position }}</td>
                                        <td>{{ $tentative->technician?->full_name ?? '—' }}</td>
                                        <td>{{ $tentative->cycle }} · {{ $tentative->radius_km }} km</td>
                                        <td class="text-end dm-amount">{{ number_format($tentative->score, 3, ',', ' ') }}</td>
                                        <td class="text-end">{{ number_format($tentative->distance_km, 1, ',', ' ') }} km</td>
                                        <td>
                                            <span class="dm-badge dm-badge--{{ $tentative->response->value === 'ACCEPTE' ? 'success' : ($tentative->response->value === 'REFUSE' ? 'danger' : 'secondary') }}">
                                                {{ $tentative->response->label() }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Colonne latérale --}}
        <div class="col-12 col-xl-5">

            <div class="dm-card p-4 mb-3">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">
                    Décomposition du prix
                </h3>

                <ul class="dm-prix">
                    <li><span>Prestation — {{ $ticket->service?->name }}</span>
                        <span class="dm-amount">{{ Money::format($ticket->base_price_gnf) }}</span></li>
                    <li>
                        <span>
                            Déplacement
                            <small class="text-body-secondary d-block">
                                {{ number_format((float) $ticket->distance_km, 1, ',', ' ') }} km
                                @if ($ticket->distance_is_estimated) · distance estimée @endif
                            </small>
                        </span>
                        <span class="dm-amount">{{ Money::format($ticket->travel_fee_gnf) }}</span>
                    </li>
                    <li><span>Supplément de diagnostic</span>
                        <span class="dm-amount">{{ Money::format($ticket->extra_fee_gnf) }}</span></li>
                    <li><span>Total facturé au client</span>
                        <span class="dm-amount">{{ Money::format($ticket->total_gnf) }}</span></li>
                </ul>

                @if ($ticket->commission_gnf !== null)
                    <div class="mt-3 pt-3 border-top">
                        <div class="d-flex justify-content-between">
                            <span class="text-body-secondary">
                                Commission plateforme
                                ({{ number_format((float) $ticket->commission_rate * 100, 0) }} %)
                            </span>
                            <span class="dm-amount text-success">{{ Money::format($ticket->commission_gnf) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <span class="text-body-secondary">Net technicien</span>
                            <span class="dm-amount">{{ Money::format($ticket->technician_net_gnf) }}</span>
                        </div>
                    </div>
                @endif

                @unless ($ticket->priceIsCoherent())
                    <div class="alert alert-danger mt-3 mb-0 py-2 small">
                        Incohérence : le total ne correspond pas à la somme de ses composantes.
                    </div>
                @endunless
            </div>

            <div class="dm-card p-4 mb-3">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Parties</h3>

                <div class="mb-3">
                    <div class="text-body-secondary small">Client</div>
                    @if ($ticket->client)
                        <a href="{{ route('clients.detail', $ticket->client) }}" class="fw-semibold">{{ $ticket->client->full_name }}</a>
                        <div class="font-monospace small text-body-secondary">{{ $ticket->client->phone }}</div>
                    @else
                        <span class="text-body-secondary">Compte supprimé</span>
                    @endif
                </div>

                <div class="mb-3">
                    <div class="text-body-secondary small">Technicien</div>
                    @if ($ticket->technician)
                        <a href="{{ route('techniciens.detail', $ticket->technician) }}" class="fw-semibold">{{ $ticket->technician->full_name }}</a>
                        <div class="font-monospace small text-body-secondary">{{ $ticket->technician->phone }}</div>
                    @else
                        <span class="text-body-secondary">Aucun technicien affecté</span>
                    @endif
                </div>

                <div>
                    <div class="text-body-secondary small">Adresse d'intervention</div>
                    <div>{{ $ticket->address_snapshot['formatted_address'] ?? '—' }}</div>
                    @if ($ticket->address_snapshot['landmark'] ?? null)
                        <div class="small text-body-secondary">{{ $ticket->address_snapshot['landmark'] }}</div>
                    @endif
                </div>
            </div>

            <div class="dm-card p-4 mb-3">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Paiement</h3>

                @forelse ($ticket->payments as $paiement)
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <span class="dm-badge dm-badge--{{ $paiement->status->color() }}">{{ $paiement->status->label() }}</span>
                            <div class="small text-body-secondary mt-1">
                                {{ $paiement->method->label() }} ·
                                <span class="font-monospace">{{ $paiement->provider_ref }}</span>
                            </div>
                        </div>
                        <span class="dm-amount">{{ Money::format($paiement->amount_gnf) }}</span>
                    </div>
                @empty
                    <p class="text-body-secondary mb-0">Aucun paiement enregistré.</p>
                @endforelse

                @if ($ticket->transactions->isNotEmpty())
                    <hr>
                    <div class="small text-body-secondary mb-2">Mouvements de portefeuille</div>
                    @foreach ($ticket->transactions as $mouvement)
                        <div class="d-flex justify-content-between small py-1">
                            <span>{{ $mouvement->type->label() }}</span>
                            <span class="dm-amount {{ $mouvement->amount_gnf < 0 ? 'text-danger' : '' }}">
                                {{ Money::format($mouvement->amount_gnf) }}
                            </span>
                        </div>
                    @endforeach
                @endif
            </div>

            @if ($ticket->review)
                <div class="dm-card p-4 mb-3">
                    <h3 class="h6 text-uppercase text-body-secondary mb-2" style="letter-spacing:.08em">Évaluation</h3>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="dm-amount fs-5">{{ $ticket->review->rating }}/5</span>
                        @foreach ($ticket->review->tags ?? [] as $tag)
                            <span class="dm-badge dm-badge--secondary">{{ $tag }}</span>
                        @endforeach
                    </div>
                    @if ($ticket->review->comment)
                        <p class="mb-0 small">« {{ $ticket->review->comment }} »</p>
                    @endif
                </div>
            @endif

            @include('tickets.partials.actions-support', ['ticket' => $ticket, 'peutAgir' => $peutAgir])
        </div>
    </div>

@endsection
