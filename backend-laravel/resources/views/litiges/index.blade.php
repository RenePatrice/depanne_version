@extends('layouts.backoffice')

@section('titre', 'Litiges')
@section('rubrique', 'Opérations')

@php use App\Support\Money; @endphp

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <h2 class="dm-page-head__title">Litiges &amp; réclamations</h2>
            <p class="dm-page-head__lede">
                La file est triée par <strong>échéance de traitement</strong>, pas par ordre
                d'arrivée : ce qui est en retard remonte, puis ce qui va expirer.
            </p>
        </div>

        <div class="d-flex gap-3 align-self-center">
            <div class="text-end">
                <div class="dm-stat__value" style="font-size:1.4rem">{{ $compteurs['ouverts'] }}</div>
                <div class="dm-stat__label">ouverts</div>
            </div>
            <div class="text-end">
                <div class="dm-stat__value" style="font-size:1.4rem">{{ $compteurs['en_cours'] }}</div>
                <div class="dm-stat__label">en cours</div>
            </div>
            <div class="text-end">
                <div class="dm-stat__value text-danger" style="font-size:1.4rem">{{ $compteurs['en_retard'] }}</div>
                <div class="dm-stat__label">hors délai</div>
            </div>
        </div>
    </div>

    <nav class="dm-periodes mb-3" aria-label="Filtre par statut">
        <a href="{{ route('litiges') }}" class="{{ $statutCourant === '' ? 'is-active' : '' }}">À traiter</a>
        @foreach ($statuts as $statut)
            <a href="{{ route('litiges', ['statut' => $statut->value]) }}"
               class="{{ $statutCourant === $statut->value ? 'is-active' : '' }}">{{ $statut->label() }}</a>
        @endforeach
    </nav>

    <div class="dm-card p-0">
        @forelse ($litiges as $litige)
            @php $enRetard = $litige->isOverdue(); @endphp

            <a href="{{ route('litiges.detail', $litige) }}"
               class="d-flex align-items-center gap-3 p-3 border-bottom text-decoration-none text-body
                      {{ $enRetard ? 'bg-danger-subtle' : '' }}">

                <span class="dm-badge dm-badge--{{ $litige->priority->color() }}">{{ $litige->priority->label() }}</span>

                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="font-monospace small">{{ $litige->reference }}</span>
                        <span class="dm-badge dm-badge--{{ $litige->status->color() }}">{{ $litige->status->label() }}</span>
                        <span class="small text-body-secondary">{{ $litige->reason->label() }}</span>
                    </div>
                    <div class="small text-body-secondary text-truncate mt-1">
                        {{ $litige->ticket?->reference }} ·
                        {{ $litige->ticket?->client?->full_name ?? '—' }}
                        @if ($litige->ticket?->technician) · {{ $litige->ticket->technician->full_name }} @endif
                        · ouvert le {{ $litige->created_at?->translatedFormat('j M Y') }}
                    </div>
                </div>

                <div class="text-end">
                    <div class="dm-amount">{{ Money::format($litige->ticket?->total_gnf) }}</div>
                    @if ($litige->sla_due_at)
                        <div class="small {{ $enRetard ? 'text-danger fw-semibold' : 'text-body-secondary' }}">
                            @if ($enRetard)
                                en retard de {{ $litige->sla_due_at->diffForHumans(null, true) }}
                            @else
                                échéance {{ $litige->sla_due_at->translatedFormat('j M H:i') }}
                            @endif
                        </div>
                    @endif
                </div>

                <i class="bi bi-chevron-right text-body-secondary" aria-hidden="true"></i>
            </a>
        @empty
            <div class="dm-empty border-0">
                <span class="dm-empty__icon" aria-hidden="true"><i class="bi bi-emoji-smile"></i></span>
                <h3 class="dm-empty__title">Aucun litige à traiter</h3>
                <p class="dm-empty__text">
                    Rien n'est en attente dans cette vue. Les réclamations ouvertes par les clients
                    dans les 72 heures suivant une intervention apparaîtront ici.
                </p>
            </div>
        @endforelse
    </div>

    @if ($litiges->hasPages())
        <div class="mt-3">{{ $litiges->links() }}</div>
    @endif

@endsection
