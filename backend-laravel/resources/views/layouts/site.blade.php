<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B4CB8">

    <title>@yield('titre', 'Parema Connect — Dépannage & travaux de confiance à Conakry')</title>
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/scss/site.scss', 'resources/js/site.js'])
</head>
<body>

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
            <img src="{{ asset('images/logo-mark.png') }}" alt="" width="36" height="36">
            <span>Parema<strong>Connect</strong></span>
        </a>

        <button class="pc-nav__burger" type="button" aria-label="Ouvrir le menu" data-nav-toggle>
            <span></span><span></span><span></span>
        </button>

        <nav class="pc-nav__links" data-nav-links aria-label="Navigation principale">
            <a href="#fonctionnement">Comment ça marche</a>
            <a href="#metiers">Métiers</a>
            <a href="#securite">Sécurité</a>
            <a href="#equipe">Équipe</a>
            <a href="{{ route('site.telecharger') }}" class="pc-btn pc-btn--primary pc-btn--sm">
                <i class="bi bi-download" aria-hidden="true"></i> Télécharger l'app
            </a>
        </nav>
    </div>
</header>

<main id="contenu">
    @yield('contenu')
</main>

<footer class="pc-footer">
    <div class="pc-container pc-footer__grid">
        <div class="pc-footer__brand">
            <a href="{{ route('site.accueil') }}" class="pc-nav__brand pc-nav__brand--light">
                <img src="{{ asset('images/logo-mark.png') }}" alt="" width="36" height="36">
                <span>Parema<strong>Connect</strong></span>
            </a>
            <p>Dépannage, installation et travaux de confiance à Conakry. Des techniciens vérifiés, des prix clairs, un paiement sécurisé.</p>
            <p class="pc-footer__made">Fièrement conçu en Guinée 🇬🇳</p>
        </div>

        <div class="pc-footer__col">
            <h3>Plateforme</h3>
            <a href="#fonctionnement">Comment ça marche</a>
            <a href="#metiers">Nos métiers</a>
            <a href="#securite">Sécurité</a>
            <a href="#equipe">L'équipe</a>
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
            @if (($contact['email'] ?? '') !== '')
                <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>
            @endif
            @if (($contact['whatsapp'] ?? '') !== '')
                <a href="{{ route('site.contact') }}">WhatsApp</a>
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
