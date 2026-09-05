@extends('layouts.backoffice')

@section('titre', 'Retrait '.$retrait->reference)
@section('rubrique', 'Retraits')

@php
    use App\Domain\Wallet\Data\WithdrawalStatus;
    use App\Support\Money;

    $couvert = $solde >= $retrait->amount_gnf;
@endphp

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <a href="{{ route('retraits') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                </a>
                <h2 class="dm-page-head__title mb-0 font-monospace">{{ $retrait->reference }}</h2>
                <span class="dm-badge dm-badge--{{ $retrait->status->color() }}">{{ $retrait->status->label() }}</span>
            </div>
            <p class="dm-page-head__lede">
                Demandé le {{ $retrait->requested_at?->translatedFormat('j F Y à H:i') }}
                par <a href="{{ route('techniciens.detail', $retrait->technician_id) }}">{{ $retrait->technician?->full_name }}</a>
            </p>
        </div>

        <div class="dm-amount fs-3 align-self-center">{{ Money::format($retrait->amount_gnf) }}</div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @unless ($couvert)
        <div class="alert alert-warning d-flex gap-2" role="alert">
            <i class="bi bi-exclamation-triangle mt-1" aria-hidden="true"></i>
            <div>
                <strong>Solde insuffisant.</strong>
                Le technicien dispose de {{ Money::format($solde) }} alors que la demande porte sur
                {{ Money::format($retrait->amount_gnf) }}. Un remboursement de litige a pu passer entre-temps.
            </div>
        </div>
    @endunless

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="dm-card p-4 mb-3">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Versement</h3>

                <div class="row g-3">
                    <div class="col-6">
                        <div class="dm-stat__label">Numéro Mobile Money</div>
                        <div class="font-monospace">{{ $retrait->mobile_money_number }}</div>
                    </div>
                    <div class="col-6">
                        <div class="dm-stat__label">Opérateur</div>
                        <div>{{ $retrait->provider->label() }}</div>
                    </div>
                    <div class="col-6">
                        <div class="dm-stat__label">Solde du technicien</div>
                        <div class="dm-amount {{ $couvert ? '' : 'text-danger' }}">{{ Money::format($solde) }}</div>
                    </div>
                    <div class="col-6">
                        <div class="dm-stat__label">Statut du compte</div>
                        <div>{{ $retrait->technician?->status->label() }}</div>
                    </div>
                </div>

                @if ($retrait->note)
                    <div class="alert alert-secondary mt-3 mb-0 py-2 small">{{ $retrait->note }}</div>
                @endif
            </div>

            <div class="dm-card p-4">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">
                    Derniers mouvements du portefeuille
                </h3>

                @forelse ($mouvements as $mouvement)
                    <div class="d-flex justify-content-between align-items-baseline py-2 border-bottom small">
                        <div class="min-w-0">
                            <div class="text-truncate">{{ $mouvement->description }}</div>
                            <div class="text-body-secondary" style="font-size:.7rem">
                                {{ $mouvement->type->label() }} ·
                                {{ $mouvement->created_at?->translatedFormat('j M Y à H:i') }}
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
        </div>

        <div class="col-12 col-xl-5">
            <div class="dm-card p-4 mb-3">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Décision</h3>

                @if ($retrait->status === WithdrawalStatus::EN_ATTENTE)
                    <form method="POST" action="{{ route('retraits.decider', $retrait) }}" class="mb-3">
                        @csrf
                        <input type="hidden" name="decision" value="approuver">
                        <label class="form-label" for="note-approbation">Note (facultative)</label>
                        <input class="form-control mb-2" id="note-approbation" name="note" maxlength="300">
                        <button type="submit" class="btn btn-success w-100" @disabled(! $couvert)>
                            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Approuver la demande
                        </button>
                    </form>
                @elseif ($retrait->status === WithdrawalStatus::APPROUVE)
                    <form method="POST" action="{{ route('retraits.decider', $retrait) }}" class="mb-3">
                        @csrf
                        <input type="hidden" name="decision" value="payer">
                        <label class="form-label" for="reference-operateur">Référence de la transaction opérateur</label>
                        <input class="form-control mb-2" id="reference-operateur" name="reference_operateur"
                               maxlength="120" placeholder="Reçu Orange Money">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-send me-1" aria-hidden="true"></i>Marquer comme versé
                        </button>
                        <div class="dm-param__aide mt-2">
                            C'est ce bouton qui écrit le mouvement de sortie au grand livre.
                        </div>
                    </form>
                @else
                    <p class="text-body-secondary small">
                        Cette demande est {{ mb_strtolower($retrait->status->label()) }}
                        @if ($retrait->processed_at)
                            depuis le {{ $retrait->processed_at->translatedFormat('j F Y à H:i') }}
                        @endif
                        : plus aucune action n'est possible.
                    </p>
                @endif

                @if (in_array($retrait->status, [WithdrawalStatus::EN_ATTENTE, WithdrawalStatus::APPROUVE], true))
                    <form method="POST" action="{{ route('retraits.decider', $retrait) }}" class="pt-3 border-top">
                        @csrf
                        <input type="hidden" name="decision" value="rejeter">
                        <label class="form-label" for="motif-rejet">Motif du rejet</label>
                        <textarea class="form-control mb-2" id="motif-rejet" name="note" rows="2"
                                  minlength="5" maxlength="300" required
                                  placeholder="Exemple : le numéro Mobile Money ne correspond pas au titulaire du compte."></textarea>
                        <button type="submit" class="btn btn-outline-danger w-100">Rejeter la demande</button>
                    </form>
                @endif
            </div>

            <div class="dm-card p-4">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">
                    Autres demandes de ce technicien
                </h3>

                @forelse ($autresDemandes as $autre)
                    <div class="d-flex align-items-center gap-2 py-2 border-bottom small">
                        <a href="{{ route('retraits.detail', $autre) }}" class="font-monospace">{{ $autre->reference }}</a>
                        <span class="flex-grow-1 text-body-secondary">
                            {{ $autre->requested_at?->translatedFormat('j M Y') }}
                        </span>
                        <span class="dm-badge dm-badge--{{ $autre->status->color() }}">{{ $autre->status->label() }}</span>
                        <span class="dm-amount">{{ Money::format($autre->amount_gnf) }}</span>
                    </div>
                @empty
                    <p class="text-body-secondary small mb-0">Première demande de ce technicien.</p>
                @endforelse
            </div>
        </div>
    </div>

@endsection
