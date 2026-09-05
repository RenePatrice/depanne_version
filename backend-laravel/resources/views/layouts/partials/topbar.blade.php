@php
    $admin = auth('admin')->user();
    $initiales = collect(explode(' ', (string) $admin?->full_name))
        ->take(2)
        ->map(fn (string $mot): string => mb_strtoupper(mb_substr($mot, 0, 1)))
        ->implode('');
@endphp

<header class="dm-topbar">
    <button type="button" class="dm-topbar__toggle" data-bascule-sidebar
            aria-label="Afficher ou replier la navigation">
        <i class="bi bi-list" aria-hidden="true"></i>
    </button>

    <div class="min-w-0">
        <div class="dm-topbar__crumb">@yield('rubrique', 'Back-office')</div>
        <h1 class="dm-topbar__title">@yield('titre', 'Back-office')</h1>
    </div>

    <div class="dm-topbar__spacer"></div>

    <button type="button" class="dm-topbar__toggle" data-bascule-theme
            aria-pressed="false" title="Changer de thème">
        <i class="bi bi-moon-stars" aria-hidden="true"></i>
    </button>

    <div class="dropdown">
        <button class="btn btn-link p-0 border-0 d-flex align-items-center gap-2 text-decoration-none"
                type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="dm-avatar" aria-hidden="true">{{ $initiales }}</span>
            <span class="d-none d-md-block text-start lh-sm">
                <span class="d-block fw-semibold small text-body">{{ $admin?->full_name }}</span>
                <span class="d-block text-body-secondary" style="font-size:.75rem">
                    {{ $admin?->getRoleNames()->first() ?? '—' }}
                </span>
            </span>
            <i class="bi bi-chevron-down text-body-secondary small" aria-hidden="true"></i>
        </button>

        <ul class="dropdown-menu dropdown-menu-end shadow-sm mt-2">
            <li class="px-3 py-2">
                <div class="fw-semibold">{{ $admin?->full_name }}</div>
                <div class="small text-body-secondary">{{ $admin?->email }}</div>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <span class="dropdown-item-text small text-body-secondary">
                    Dernière connexion :
                    {{ $admin?->last_login_at?->timezone(config('app.timezone'))->format('d/m/Y à H:i') ?? 'première visite' }}
                </span>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="POST" action="{{ route('deconnexion') }}">
                    @csrf
                    <button type="submit" class="dropdown-item text-danger">
                        <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Se déconnecter
                    </button>
                </form>
            </li>
        </ul>
    </div>
</header>
