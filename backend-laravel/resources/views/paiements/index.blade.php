@extends('layouts.backoffice')

@section('titre', 'Paiements')
@section('rubrique', 'Finances')

@php
    use App\Domain\Payments\Data\PaymentChannel;
    use App\Domain\Payments\Data\PaymentMethod;
    use App\Support\Money;
    use App\Support\OpaqueId;
@endphp

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <h2 class="dm-page-head__title">Paiements</h2>
            <p class="dm-page-head__lede">
                Chaque paiement, en espèces ou par Mobile Money, avec les confirmations du client et du technicien.
            </p>
        </div>

        <div class="d-flex gap-4 align-self-center">
            <div class="text-end">
                <div class="dm-stat__value {{ $aArbitrer ? 'text-danger' : '' }}" style="font-size:1.4rem">{{ $aArbitrer }}</div>
                <div class="dm-stat__label">contestés</div>
            </div>
            <div class="text-end">
                <div class="dm-stat__value {{ $enSouffrance ? 'text-warning' : '' }}" style="font-size:1.4rem">{{ $enSouffrance }}</div>
                <div class="dm-stat__label">sans réponse depuis 24 h</div>
            </div>
            <div class="text-end">
                <div class="dm-stat__value" style="font-size:1.4rem">{{ $enAttente }}</div>
                <div class="dm-stat__label">en attente</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-7">
            <div class="dm-card p-4 h-100">
                <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">
                    Paiements réglés · 30 derniers jours
                </h3>

                @forelse ($repartition as $ligne)
                    @php
                        $moyen = PaymentMethod::tryFrom((string) $ligne->getRawOriginal('method'));
                        $canal = PaymentChannel::tryFrom((string) $ligne->getRawOriginal('channel'));
                    @endphp
                    <div class="d-flex justify-content-between align-items-baseline py-2 border-bottom small">
                        <div>
                            <strong>{{ $moyen?->label() ?? $ligne->getRawOriginal('method') }}</strong>
                            <span class="text-body-secondary">· {{ $canal?->label() }}</span>
                        </div>
                        <div class="text-end">
                            <span class="dm-amount">{{ Money::format((int) $ligne->getAttribute('montant')) }}</span>
                            <span class="text-body-secondary ms-2">{{ $ligne->getAttribute('nombre') }} paiement(s)</span>
                        </div>
                    </div>
                @empty
                    <p class="text-body-secondary small mb-0">Aucun paiement réglé sur la période.</p>
                @endforelse
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="dm-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-baseline mb-3">
                    <h3 class="h6 text-uppercase text-body-secondary mb-0" style="letter-spacing:.08em">Commissions dues</h3>
                    <span class="dm-amount text-warning">{{ Money::format($totalDettes) }}</span>
                </div>
                <p class="small text-body-secondary">
                    Commission des paiements directs encore due par les techniciens.
                    Au-delà de {{ Money::format($plafond) }}, un technicien ne reçoit plus de demandes.
                </p>

                @forelse ($dettes as $dette)
                    @php $due = (int) -$dette->solde; @endphp
                    <div class="d-flex justify-content-between align-items-baseline py-2 border-bottom small">
                        <a href="{{ route('techniciens.detail', OpaqueId::encode((int) $dette->user_id)) }}">{{ $dette->full_name }}</a>
                        <span class="dm-amount {{ $due >= $plafond ? 'text-danger fw-semibold' : '' }}">
                            {{ Money::format($due) }}
                            @if ($due >= $plafond)
                                <span class="dm-badge dm-badge--danger ms-1">bloqué</span>
                            @endif
                        </span>
                    </div>
                @empty
                    <p class="text-body-secondary small mb-0">Aucune commission en attente de règlement.</p>
                @endforelse
            </div>
        </div>
    </div>

    <x-table-serveur
        id="paiements"
        :url="route('paiements.donnees')"
        :export-url="route('paiements.export')"
        :order="[[1, 'desc']]"
        :colonnes="[
            ['data' => 'provider_ref', 'libelle' => 'Référence', 'name' => 'payments.provider_ref', 'classe' => 'text-nowrap'],
            ['data' => 'created_at', 'libelle' => 'Date', 'name' => 'payments.created_at', 'searchable' => false, 'classe' => 'text-nowrap'],
            ['data' => 'ticket', 'libelle' => 'Intervention', 'orderable' => false],
            ['data' => 'client', 'libelle' => 'Client', 'orderable' => false],
            ['data' => 'technicien', 'libelle' => 'Technicien', 'orderable' => false],
            ['data' => 'moyen', 'libelle' => 'Moyen', 'searchable' => false, 'orderable' => false],
            ['data' => 'montant', 'libelle' => 'Montant', 'searchable' => false, 'classe' => 'text-end'],
            ['data' => 'confirmations', 'libelle' => 'Confirmations', 'searchable' => false, 'orderable' => false],
            ['data' => 'statut', 'libelle' => 'Statut', 'name' => 'payments.status', 'searchable' => false],
            ['data' => 'actions', 'libelle' => '', 'searchable' => false, 'orderable' => false, 'classe' => 'text-end'],
        ]">

        <x-slot:filtres>
            <div>
                <label for="filtre-a-traiter">À traiter</label>
                <select class="form-select" id="filtre-a-traiter" name="a_traiter">
                    <option value="">Tous</option>
                    <option value="1">Contestés ou sans réponse</option>
                </select>
            </div>
            <div>
                <label for="filtre-statut">Statut</label>
                <select class="form-select" id="filtre-statut" name="statut">
                    <option value="">Tous</option>
                    @foreach ($filtres['statuts'] as $statut)
                        <option value="{{ $statut['valeur'] }}">{{ $statut['libelle'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filtre-moyen">Moyen</label>
                <select class="form-select" id="filtre-moyen" name="moyen">
                    <option value="">Tous</option>
                    @foreach ($filtres['moyens'] as $moyen)
                        <option value="{{ $moyen['valeur'] }}">{{ $moyen['libelle'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filtre-canal">Canal</label>
                <select class="form-select" id="filtre-canal" name="canal">
                    <option value="">Tous</option>
                    @foreach ($filtres['canaux'] as $canal)
                        <option value="{{ $canal['valeur'] }}">{{ $canal['libelle'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filtre-du">Du</label>
                <input type="date" class="form-control" id="filtre-du" name="du">
            </div>
            <div>
                <label for="filtre-au">Au</label>
                <input type="date" class="form-control" id="filtre-au" name="au">
            </div>
        </x-slot:filtres>
    </x-table-serveur>

@endsection
