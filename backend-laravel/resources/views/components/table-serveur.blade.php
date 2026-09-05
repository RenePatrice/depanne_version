@props([
    'id',
    'url',
    'exportUrl',
    'colonnes',
    'order' => [[0, 'desc']],
])

@php
    /**
     * Coquille d'une table en mode serveur (§10).
     * Le slot `filtres` reçoit les champs de filtrage ; ils sont envoyés à
     * chaque requête et recopiés sur les liens d'export, pour que le fichier
     * téléchargé corresponde exactement à ce qui est affiché.
     */
    $colonnesJs = collect($colonnes)->map(fn (array $c): array => [
        'data' => $c['data'],
        'name' => $c['name'] ?? $c['data'],
        'orderable' => $c['orderable'] ?? true,
        'searchable' => $c['searchable'] ?? true,
        'classe' => $c['classe'] ?? null,
    ])->values();
@endphp

<div class="dm-card p-3 p-md-4" data-table-bloc>

    @isset($filtres)
        <form class="dm-filtres mb-3" data-filtres="{{ $id }}">
            {{ $filtres }}
            <button type="reset" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-lg me-1" aria-hidden="true"></i>Réinitialiser
            </button>
        </form>
    @endisset

    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <span class="text-body-secondary small me-auto">
            <i class="bi bi-download me-1" aria-hidden="true"></i>Exporter la sélection courante
        </span>
        @foreach (['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $libelle)
            <a class="btn btn-sm btn-outline-secondary"
               data-export="{{ $format }}"
               data-export-url="{{ $exportUrl }}"
               href="{{ $exportUrl }}?format={{ $format }}">{{ $libelle }}</a>
        @endforeach
    </div>

    <div class="dm-scroll-x">
        <table class="table table-hover align-middle w-100" id="table-{{ $id }}"
               data-table-serveur
               data-config="{{ json_encode([
                   'url' => $url,
                   'filtres' => isset($filtres) ? $id : null,
                   'order' => $order,
                   'colonnes' => $colonnesJs,
               ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}">
            <thead>
                <tr>
                    @foreach ($colonnes as $colonne)
                        <th class="{{ $colonne['classe'] ?? '' }}">{{ $colonne['libelle'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
