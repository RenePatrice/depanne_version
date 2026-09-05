@php
    $navigation = \App\Support\Navigation::forAdmin(auth('admin')->user());
@endphp

<aside class="dm-sidebar" aria-label="Navigation principale">

    <div class="dm-sidebar__brand">
        <span class="dm-sidebar__logo" aria-hidden="true"><i class="bi bi-tools"></i></span>
        <span>
            <span class="dm-sidebar__name d-block">Dépanne-Moi</span>
            <span class="dm-sidebar__baseline">Back-office · Conakry</span>
        </span>
    </div>

    <nav class="dm-sidebar__nav">
        @foreach ($navigation as $groupe)
            <div>
                <div class="dm-sidebar__group-title">{{ $groupe['groupe'] }}</div>
                <div class="dm-sidebar__group">
                    @foreach ($groupe['items'] as $item)
                        @php $actif = request()->routeIs($item['cle']); @endphp
                        <a href="{{ route($item['cle']) }}"
                           class="dm-sidebar__link {{ $actif ? 'is-active' : '' }}"
                           @if ($actif) aria-current="page" @endif
                           title="{{ $item['libelle'] }}">
                            <i class="bi {{ $item['icone'] }}" aria-hidden="true"></i>
                            <span class="dm-sidebar__label">{{ $item['libelle'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    <div class="dm-sidebar__footer">
        Pilote Ratoma · v0.1
    </div>
</aside>
