<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titre', 'Back-office') — Dépanne-Moi</title>

    {{-- Le thème est posé avant le premier rendu : sans cela, une page sombre
         apparaîtrait d'abord en clair le temps que le bundle se charge. --}}
    <script>
        (function () {
            try {
                document.documentElement.dataset.bsTheme =
                    localStorage.getItem('depanne.theme')
                    ?? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            } catch (e) {
                document.documentElement.dataset.bsTheme = 'light';
            }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="dm-shell">

<div class="dm-layout" data-layout>

    @include('layouts.partials.sidebar')

    <div class="dm-backdrop" data-fermer-sidebar aria-hidden="true"></div>

    <div class="dm-main">
        @include('layouts.partials.topbar')

        <main class="dm-content">
            @if (session('statut'))
                <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="status">
                    <i class="bi bi-check-circle"></i>
                    <span>{{ session('statut') }}</span>
                </div>
            @endif

            @yield('contenu')
        </main>
    </div>
</div>

@stack('scripts')
</body>
</html>
