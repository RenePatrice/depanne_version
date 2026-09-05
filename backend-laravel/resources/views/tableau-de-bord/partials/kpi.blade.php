@php
    /**
     * Une carte KPI. La valeur finale est écrite dans le DOM ; l'animation
     * JavaScript ne fait que la faire monter depuis zéro, de sorte qu'un
     * lecteur d'écran ou une impression affichent le bon chiffre.
     */
    $valeur = $indicateur['valeur'];

    [$affichage, $unite] = match ($indicateur['format']) {
        'gnf' => [number_format((float) $valeur, 0, ',', ' '), 'GNF'],
        'pourcentage' => [number_format((float) $valeur, 1, ',', ' '), '%'],
        'note' => [number_format((float) $valeur, 2, ',', ' '), '/ 5'],
        'duree' => $valeur < 60
            ? [number_format((float) $valeur, 0, ',', ' '), 's']
            : [number_format($valeur / 60, 0, ',', ' '), 'min'],
        default => [number_format((float) $valeur, 0, ',', ' '), null],
    };

    $evolution = $indicateur['evolution'];
@endphp

<div class="dm-stat">
    <span class="dm-stat__icon is-{{ $indicateur['ton'] }}" aria-hidden="true">
        <i class="bi {{ $indicateur['icone'] }}"></i>
    </span>

    <div class="min-w-0 flex-grow-1">
        <div class="d-flex align-items-baseline gap-1 flex-wrap">
            <span class="dm-stat__value"
                  data-compteur="{{ $valeur }}"
                  data-format="{{ $indicateur['format'] }}">{{ $affichage }}</span>
            @if ($unite)
                <span class="dm-amount__currency">{{ $unite }}</span>
            @endif
        </div>

        <div class="dm-stat__label">{{ $indicateur['libelle'] }}</div>

        @if ($evolution !== null)
            @php
                $classe = $evolution > 0.5 ? 'hausse' : ($evolution < -0.5 ? 'baisse' : 'stable');
                $fleche = $evolution > 0.5 ? 'bi-arrow-up-right' : ($evolution < -0.5 ? 'bi-arrow-down-right' : 'bi-dash');
            @endphp
            <div class="dm-trend dm-trend--{{ $classe }} mt-1">
                <i class="bi {{ $fleche }}" aria-hidden="true"></i>
                {{ number_format(abs($evolution), 1, ',', ' ') }} %
                <span class="fw-normal text-body-secondary">vs période précédente</span>
            </div>
        @elseif ($indicateur['precision'])
            <div class="dm-stat__label mt-1" style="font-size:.75rem">{{ $indicateur['precision'] }}</div>
        @endif
    </div>
</div>
