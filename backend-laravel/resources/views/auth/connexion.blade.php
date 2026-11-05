<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Connexion — Parema Connect</title>

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
</head>
<body>

<div class="dm-auth">
    <div class="dm-auth__card">

        <div class="dm-auth__brand">
            <img src="{{ asset('images/logo-mark.png') }}" alt="Parema Connect">
            <span class="fw-semibold" style="font-family:Poppins,sans-serif">Parema Connect</span>
        </div>

        <h1 class="h5 text-center mb-4">Connexion</h1>

        @if (session('statut'))
            <div class="alert alert-success py-2 small" role="status">{{ session('statut') }}</div>
        @endif

        @error('email')
            <div class="alert alert-danger d-flex align-items-start gap-2 py-2 small" role="alert">
                <i class="bi bi-exclamation-circle mt-1" aria-hidden="true"></i>
                <span>{{ $message }}</span>
            </div>
        @enderror

        <form method="POST" action="{{ route('connexion') }}" novalidate>
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">Adresse e-mail</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="{{ old('email') }}" required autofocus
                       autocomplete="username" inputmode="email"
                       placeholder="prenom@depanne-moi.gn">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Mot de passe</label>
                <input type="password" id="password" name="password" class="form-control"
                       required autocomplete="current-password">
                @error('password')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                <label class="form-check-label small" for="remember">Rester connecté sur cet appareil</label>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i> Se connecter
            </button>
        </form>
    </div>
</div>

</body>
</html>
