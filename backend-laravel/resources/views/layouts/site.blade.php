<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FBF9F4">

    {{-- Thème posé AVANT le premier rendu : sans cela une page sombre clignote
         en clair au chargement. Choix mémorisé, sinon réglage du système. --}}
    <script>
        (function () {
            // `?theme=light|dark` force un thème pour un lien partagé ou un test.
            var theme = (location.search.match(/[?&]theme=(light|dark)\b/) || [])[1] || null;
            try { theme = theme || localStorage.getItem('parema.site.theme'); } catch (e) {}
            if (theme !== 'dark' && theme !== 'light') {
                theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>

    <title>@yield('titre', 'Parema Connect')</title>
    <meta name="description"
          content="@yield('description', 'Parema Connect met en relation clients et techniciens vérifiés à Conakry : plomberie, électricité, maçonnerie et plus. Comparez, choisissez, payez en toute sécurité.')">
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Partage social --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Parema Connect">
    <meta property="og:title" content="@yield('titre', 'Parema Connect — Dépannage & travaux de confiance à Conakry')">
    <meta property="og:description"
          content="@yield('description', 'Techniciens vérifiés, prix transparents, paiement sécurisé. L\'application qui change le dépannage et les travaux à Conakry.')">
    <meta property="og:image" content="{{ asset('images/logo.png') }}">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" type="image/png" href="{{ asset('images/logo-mark.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-mark.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Instrument+Sans:wght@400..700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">

    @vite(['resources/scss/site.scss', 'resources/js/site.js'])
</head>
<body>

{{-- Écran de chargement : le logo s'anime avant l'affichage, à chaque chargement --}}
<div class="pc-loader" id="pc-loader" aria-hidden="true">
    <div class="pc-loader__badge">
        <img src="{{ asset('images/logo-mark.png') }}" alt="Parema Connect">
    </div>
</div>

<div class="pc-progress" aria-hidden="true"></div>

@if (($hero['annonce'] ?? '') !== '')
    <div class="pc-annonce">
        <div class="pc-container">
            <i class="pc-annonce__point" aria-hidden="true"></i>
            <span>{{ $hero['annonce'] }}</span>
        </div>
    </div>
@endif

<header class="pc-nav" data-nav>
    <div class="pc-container pc-nav__inner">
        <a href="{{ route('site.accueil') }}" class="pc-nav__brand" aria-label="Parema Connect, accueil">
            <img class="pc-nav__logo" src="{{ asset('images/logo.png') }}" alt="Parema Connect">
        </a>

        <button class="pc-nav__burger" type="button" aria-label="Ouvrir le menu" data-nav-toggle>
            <span></span><span></span><span></span>
        </button>

        {{-- Sur l'accueil, les liens sont de simples ancres (suivi de section au
             défilement) ; ailleurs, ils ramènent à l'accueil, à la bonne section. --}}
        @php $ancre = request()->routeIs('site.accueil') ? '' : route('site.accueil'); @endphp
        <nav class="pc-nav__links" data-nav-links aria-label="Navigation principale">
            <a href="{{ $ancre }}#fonctionnement">Comment ça marche</a>
            <a href="{{ $ancre }}#metiers" @class(['is-current' => request()->routeIs('site.metier')])>Métiers</a>
            <a href="{{ $ancre }}#securite">Sécurité</a>
            <a href="{{ $ancre }}#equipe">Équipe</a>
            <a href="{{ route('site.contact') }}" @class(['is-current' => request()->routeIs('site.contact')])>Contact</a>
            <button class="pc-theme" type="button" data-theme-toggle aria-label="Passer en mode sombre" aria-pressed="false">
                <i class="bi bi-moon-stars-fill" aria-hidden="true"></i>
                <i class="bi bi-sun-fill" aria-hidden="true"></i>
            </button>
            @include('site.partials.btn-telecharger', ['size' => 'pc-btn--sm', 'libelle' => "Télécharger l'app", 'libelleBientot' => 'Bientôt'])
        </nav>
    </div>
</header>

<main id="contenu">
    @yield('contenu')
</main>

<footer class="pc-footer">
    <div class="pc-container">
        @include('site.partials.newsletter')
    </div>

    <div class="pc-container pc-footer__grid">
        <div class="pc-footer__brand">
            <a href="{{ route('site.accueil') }}" class="pc-footer__logo" aria-label="Parema Connect, accueil">
                <img src="{{ asset('images/logo.png') }}" alt="Parema Connect">
            </a>
            <p>Dépannage, installation et travaux de confiance à Conakry. Des techniciens vérifiés, des prix clairs, un paiement sécurisé.</p>
            <p class="pc-footer__made">Fièrement conçu en Guinée 🇬🇳</p>
        </div>

        <div class="pc-footer__col">
            <h3>Plateforme</h3>
            <a href="{{ $ancre }}#fonctionnement">Comment ça marche</a>
            <a href="{{ $ancre }}#metiers">Nos métiers</a>
            <a href="{{ $ancre }}#securite">Sécurité</a>
            <a href="{{ $ancre }}#equipe">L'équipe</a>
        </div>

        <div class="pc-footer__col">
            <h3>Application</h3>
            <a href="{{ route('site.telecharger') }}">Télécharger (Android)</a>
            @if (($telechargement['playstore_disponible'] ?? false))
                <a href="{{ route('site.playstore') }}">Google Play Store</a>
            @endif
            <a href="{{ route('site.devenir-technicien') }}">Devenir technicien</a>
        </div>

        <div class="pc-footer__col">
            <h3>Contact</h3>
            <a href="{{ route('site.contact') }}">Nous écrire</a>
            @if (($contact['whatsapp'] ?? '') !== '')
                <a href="{{ route('site.whatsapp') }}">WhatsApp</a>
            @endif
            @if (($contact['email'] ?? '') !== '')
                <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>
            @endif
            <a href="{{ route('connexion') }}">Espace équipe</a>
        </div>
    </div>

    <div class="pc-container pc-footer__bottom">
        <span>© {{ date('Y') }} Parema Connect. Tous droits réservés.</span>
        <span class="pc-footer__dot">Conakry · Guinée</span>
    </div>
</footer>

</body>
</html>
