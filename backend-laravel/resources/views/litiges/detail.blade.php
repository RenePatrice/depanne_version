@extends('layouts.backoffice')

@section('titre', 'Litige '.$litige->reference)
@section('rubrique', 'Litiges')

@php
    use App\Domain\Disputes\Data\DisputeStatus;
    use App\Support\Money;

    $peutResoudre = auth('admin')->user()?->can('litiges.resoudre') ?? false;
    $ouvert = $litige->status->isOpen();
    $ticket = $litige->ticket;
@endphp

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <a href="{{ route('litiges') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                </a>
                <h2 class="dm-page-head__title mb-0 font-monospace">{{ $litige->reference }}</h2>
                <span class="dm-badge dm-badge--{{ $litige->status->color() }}">{{ $litige->status->label() }}</span>
                <span class="dm-badge dm-badge--{{ $litige->priority->color() }}">{{ $litige->priority->label() }}</span>
            </div>
            <p class="dm-page-head__lede">
                {{ $litige->reason->label() }} · ouvert par
                {{ $litige->openedBy?->full_name ?? 'un utilisateur supprimé' }}
                le {{ $litige->created_at?->translatedFormat('j F Y à H:i') }}
            </p>
        </div>

        @if ($litige->sla_due_at)
            <div class="text-end align-self-center">
                <div class="dm-stat__label">Échéance de traitement</div>
                <div class="{{ $litige->isOverdue() ? 'text-danger fw-semibold' : '' }}">
                    {{ $litige->sla_due_at->translatedFormat('j F à H:i') }}
                    @if ($litige->isOverdue()) — dépassée @endif
                </div>
            </div>
        @endif
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-xl-7">

            <div class="dm-card p-4 mb-3">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Réclamation</h3>
                <p class="mb-3">{{ $litige->description }}</p>

                @if ($litige->evidence)
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($litige->evidence as $piece)
                            <span class="dm-badge dm-badge--secondary">
                                <i class="bi bi-paperclip me-1" aria-hidden="true"></i>{{ basename($piece) }}
                            </span>
                        @endforeach
                    </div>
                    <div class="dm-param__aide mt-2">
                        Les pièces jointes seront consultables dès le branchement du bucket privé Supabase.
                    </div>
                @endif
            </div>

            <div class="dm-card p-4 mb-3">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">
                    Messagerie interne
                </h3>

                @forelse ($litige->messages as $message)
                    <div class="dm-chat__bulle mb-2 {{ $message->is_internal ? 'dm-chat__bulle--signale' : '' }}">
                        <div class="dm-chat__meta">
                            {{ $message->author_type }} ·
                            {{ $message->created_at?->translatedFormat('j M H:i') }}
                            @if ($message->is_internal)
                                · <span class="text-danger">note interne</span>
                            @else
                                · vers {{ str_replace('_', ' ', mb_strtolower($message->audience)) }}
                            @endif
                        </div>
                        {{ $message->content }}
                    </div>
                @empty
                    <p class="text-body-secondary small">Aucun message pour l'instant.</p>
                @endforelse

                @if ($peutResoudre && $ouvert)
                    <form method="POST" action="{{ route('litiges.ecrire', $litige) }}" class="mt-3 pt-3 border-top">
                        @csrf
                        <label class="form-label" for="message">Écrire</label>
                        <textarea class="form-control mb-2" id="message" name="content" rows="3"
                                  required minlength="2" maxlength="2000"></textarea>

                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <div>
                                <label class="form-label" for="audience">Destinataire</label>
                                <select class="form-select form-select-sm" id="audience" name="audience">
                                    <option value="LES_DEUX">Les deux parties</option>
                                    <option value="CLIENT">Le client</option>
                                    <option value="TECHNICIEN">Le technicien</option>
                                </select>
                            </div>

                            <div class="form-check align-self-end mb-2">
                                <input class="form-check-input" type="checkbox" value="1"
                                       id="interne" name="is_internal">
                                <label class="form-check-label small" for="interne">
                                    Note interne — invisible des deux parties
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm ms-auto align-self-end mb-2">
                                Envoyer
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            @if ($ticket && $ticket->messages->isNotEmpty())
                <div class="dm-card p-4">
                    <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">
                        Conversation client ↔ technicien
                    </h3>
                    <div class="dm-chat">
                        @foreach ($ticket->messages->sortBy('created_at') as $message)
                            <div class="dm-chat__bulle {{ $message->sender_id === $ticket->technician_id ? 'dm-chat__bulle--technicien' : '' }} {{ $message->is_flagged ? 'dm-chat__bulle--signale' : '' }}">
                                <div class="dm-chat__meta">
                                    {{ $message->sender?->full_name ?? 'Compte supprimé' }} ·
                                    {{ $message->created_at?->translatedFormat('j M H:i') }}
                                </div>
                                {{ $message->content }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="col-12 col-xl-5">

            <div class="dm-card p-4 mb-3">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">
                    Intervention concernée
                </h3>

                @if ($ticket)
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <a href="{{ route('tickets.detail', $ticket) }}" class="font-monospace">{{ $ticket->reference }}</a>
                        @include('composants.badge-etat', ['etat' => $ticket->state])
                    </div>
                    <div class="small text-body-secondary mb-3">{{ $ticket->service?->name }}</div>

                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span>Client</span><span>{{ $ticket->client?->full_name ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span>Technicien</span><span>{{ $ticket->technician?->full_name ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span>Montant payé</span>
                        <span class="dm-amount">{{ Money::format($ticket->total_gnf) }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span>Part technicien</span>
                        <span class="dm-amount">{{ Money::format($ticket->technician_net_gnf) }}</span>
                    </div>
                @else
                    <p class="text-body-secondary mb-0">Le ticket rattaché n'existe plus.</p>
                @endif
            </div>

            <div class="dm-card p-4">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Décision</h3>

                @if (! $ouvert)
                    <div class="mb-2">
                        <span class="dm-badge dm-badge--{{ $litige->status->color() }}">{{ $litige->status->label() }}</span>
                        @if ($litige->resolution)
                            <span class="dm-badge dm-badge--secondary ms-1">{{ $resolutions[$litige->resolution] ?? $litige->resolution }}</span>
                        @endif
                    </div>
                    @if ($litige->refund_gnf > 0)
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span>Remboursement accordé</span>
                            <span class="dm-amount text-danger">{{ Money::format($litige->refund_gnf) }}</span>
                        </div>
                    @endif
                    <p class="small mt-2 mb-0">{{ $litige->resolution_note }}</p>
                    <p class="text-body-secondary mt-2 mb-0" style="font-size:.72rem">
                        Résolu le {{ $litige->resolved_at?->translatedFormat('j F Y à H:i') }}
                    </p>

                @elseif (! $peutResoudre)
                    <p class="text-body-secondary small mb-0">
                        Ton rôle permet de consulter ce litige, pas de le trancher.
                    </p>

                @else
                    @if ($litige->status === DisputeStatus::OUVERT)
                        <form method="POST" action="{{ route('litiges.prendre', $litige) }}" class="mb-3">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="bi bi-person-check me-1" aria-hidden="true"></i>Prendre en charge
                            </button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('litiges.resoudre', $litige) }}">
                        @csrf

                        <label class="form-label" for="resolution">Décision</label>
                        <select class="form-select mb-2" id="resolution" name="resolution" required>
                            @foreach ($resolutions as $valeur => $libelle)
                                <option value="{{ $valeur }}">{{ $libelle }}</option>
                            @endforeach
                            <option value="REJETER">Rejeter la réclamation</option>
                        </select>

                        <label class="form-label" for="refund">Montant du remboursement (GNF)</label>
                        <input class="form-control mb-1" type="number" id="refund" name="refund_gnf"
                               min="0" max="{{ $ticket?->total_gnf ?? 0 }}" step="1000" value="0">
                        <div class="dm-param__aide mb-2">
                            Maximum {{ Money::format($ticket?->total_gnf) }} — le montant réellement payé.
                        </div>

                        <label class="form-label" for="note">Motivation</label>
                        <textarea class="form-control mb-2" id="note" name="resolution_note" rows="3"
                                  required minlength="10" maxlength="2000"
                                  placeholder="Ce que tu as constaté et pourquoi tu tranches ainsi."></textarea>

                        <button type="submit" class="btn btn-primary w-100">Enregistrer la décision</button>
                    </form>

                    <p class="text-body-secondary mt-3 mb-0" style="font-size:.72rem">
                        Un remboursement est écrit au grand livre — le client est crédité, la part
                        correspondante est reprise au technicien. Le versement effectif vers Mobile
                        Money interviendra en phase C5.
                    </p>
                @endif
            </div>
        </div>
    </div>

@endsection
