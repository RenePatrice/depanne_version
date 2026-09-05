@extends('layouts.app')

@section('titre', 'État de l\'environnement — Dépanne-Moi')

@section('contenu')
<div class="container py-5" style="max-width: 960px;">

    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="d-flex align-items-center justify-content-center rounded-4 text-white"
             style="width:52px;height:52px;background:linear-gradient(135deg,#1B6FF3,#0B4CB8);">
            <i class="bi bi-tools fs-4"></i>
        </div>
        <div class="flex-grow-1">
            <h1 class="h3 mb-0">Dépanne-Moi</h1>
            <p class="text-body-secondary mb-0">Environnement de développement local — phase A0</p>
        </div>
        <div data-react-component="HorlogeConakry"></div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em;">Socle technique</h2>
                    <ul class="list-unstyled mb-0">
                        @foreach ($composants as $c)
                            <li class="d-flex justify-content-between align-items-center py-2 border-bottom border-light-subtle">
                                <span class="fw-medium">{{ $c['nom'] }}</span>
                                <span class="text-end">
                                    <span class="font-monospace small">{{ $c['valeur'] }}</span>
                                    <span class="badge rounded-pill text-bg-light ms-2 fw-normal">{{ $c['attendu'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em;">Réglages applicatifs</h2>
                    <ul class="list-unstyled mb-0">
                        @foreach ($reglages as $r)
                            <li class="d-flex justify-content-between align-items-center py-2 border-bottom border-light-subtle">
                                <span class="fw-medium">{{ $r['nom'] }}</span>
                                <span class="font-monospace small">{{ $r['valeur'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-body-secondary mb-1" style="letter-spacing:.08em;">Fournisseurs externes</h2>
                    <p class="text-body-secondary small mb-3">
                        Chaque fournisseur est derrière une interface. Tant qu'aucune clé n'est fournie,
                        l'implémentation simulée permet de développer et de tester le parcours complet.
                    </p>
                    <div class="row g-3">
                        @foreach ($fournisseurs as $f)
                            <div class="col-6 col-md-3">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="small text-body-secondary">{{ $f['nom'] }}</div>
                                    <div class="fw-semibold font-monospace">{{ $f['valeur'] }}</div>
                                    <span class="badge rounded-pill mt-2 {{ $f['reel'] ? 'text-bg-success' : 'text-bg-warning' }}">
                                        {{ $f['reel'] ? 'branché' : 'simulé' }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-4">
        <a href="{{ route('health') }}" class="btn btn-primary">
            <i class="bi bi-activity me-1"></i> Sonde de santé
        </a>
        <span class="btn btn-outline-secondary disabled">Back-office — phase B1</span>
        <span class="btn btn-outline-secondary disabled">API mobile — phase C1</span>
    </div>

</div>
@endsection
