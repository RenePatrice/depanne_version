@extends('layouts.site')

@section('titre', 'Contact — Parema Connect')
@section('description', 'Une question, un partenariat, envie de devenir technicien ? Écrivez à l\'équipe Parema Connect : formulaire, WhatsApp ou e-mail.')

@php
    $aWhatsapp = ($contact['whatsapp'] ?? '') !== '';
    $aEmail = ($contact['email'] ?? '') !== '';
@endphp

@section('contenu')

<section class="pc-page-hero pc-page-hero--simple">
    <div class="pc-hero__dots" aria-hidden="true"></div>
    <div class="pc-container" data-reveal>
        <nav class="pc-fil" aria-label="Fil d'Ariane">
            <a href="{{ route('site.accueil') }}">Accueil</a>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
            <span aria-current="page">Contact</span>
        </nav>
        <h1 class="pc-page-hero__title">Parlons de <em>votre besoin</em></h1>
        <p class="pc-hero__lede">Une question sur le service, un partenariat, l'envie de rejoindre nos techniciens ? Écrivez-nous : une personne de l'équipe vous répond.</p>
    </div>
</section>

<section class="pc-section pc-contact-section">
    <div class="pc-container pc-contact">

        {{-- ----------------------- Canaux directs ----------------------- --}}
        <aside class="pc-contact__canaux" data-reveal>
            @if ($aWhatsapp)
                <a class="pc-canal pc-canal--whatsapp" href="{{ route('site.whatsapp') }}">
                    <span class="pc-canal__ico"><i class="bi bi-whatsapp" aria-hidden="true"></i></span>
                    <span class="pc-canal__txt"><strong>WhatsApp</strong><span>Le plus rapide : écrivez-nous directement.</span></span>
                    <i class="bi bi-arrow-up-right pc-canal__fleche" aria-hidden="true"></i>
                </a>
            @endif
            @if ($aEmail)
                <a class="pc-canal" href="mailto:{{ $contact['email'] }}">
                    <span class="pc-canal__ico"><i class="bi bi-envelope" aria-hidden="true"></i></span>
                    <span class="pc-canal__txt"><strong>E-mail</strong><span>{{ $contact['email'] }}</span></span>
                    <i class="bi bi-arrow-up-right pc-canal__fleche" aria-hidden="true"></i>
                </a>
            @endif
            <div class="pc-canal pc-canal--info">
                <span class="pc-canal__ico"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
                <span class="pc-canal__txt"><strong>Conakry, Guinée</strong><span>Nous intervenons à domicile, quartier par quartier.</span></span>
            </div>
            <a class="pc-canal" href="{{ route('site.devenir-technicien') }}">
                <span class="pc-canal__ico"><i class="bi bi-person-badge" aria-hidden="true"></i></span>
                <span class="pc-canal__txt"><strong>Vous êtes artisan ?</strong><span>Voir comment devenir technicien partenaire.</span></span>
                <i class="bi bi-arrow-up-right pc-canal__fleche" aria-hidden="true"></i>
            </a>
        </aside>

        {{-- ----------------------- Formulaire ----------------------- --}}
        <div class="pc-formcard" id="formulaire" data-reveal data-reveal-delay="1">
            @if (session('contact_envoye'))
                <div class="pc-form__ok" role="status">
                    <span class="pc-form__ok-ico"><i class="bi bi-check-lg" aria-hidden="true"></i></span>
                    <h2>Message bien reçu</h2>
                    <p>Merci ! Notre équipe vous recontacte au numéro indiqué dès que possible.</p>
                    <a href="{{ route('site.accueil') }}" class="pc-btn pc-btn--ghost">Retour à l'accueil</a>
                </div>
            @else
                <h2>Écrivez-nous</h2>
                <p class="pc-formcard__intro">Les champs marqués d'un astérisque sont nécessaires pour vous répondre.</p>

                <form class="pc-form" method="POST" action="{{ route('site.contact.envoyer') }}" novalidate>
                    @csrf
                    {{-- Pot de miel : invisible pour un humain, rempli par les robots. --}}
                    <input class="pc-pot" type="text" name="site_web" tabindex="-1" autocomplete="off" aria-hidden="true">

                    <div class="pc-champ">
                        <label for="c-nom">Votre nom *</label>
                        <input id="c-nom" type="text" name="nom" value="{{ old('nom') }}" maxlength="120" required autocomplete="name" @error('nom') aria-invalid="true" @enderror>
                        @error('nom') <p class="pc-champ__erreur" role="alert">{{ $message }}</p> @enderror
                    </div>

                    <div class="pc-form__duo">
                        <div class="pc-champ">
                            <label for="c-tel">Téléphone *</label>
                            <input id="c-tel" type="tel" name="telephone" value="{{ old('telephone') }}" maxlength="30" required autocomplete="tel" placeholder="620 00 00 00" @error('telephone') aria-invalid="true" @enderror>
                            @error('telephone') <p class="pc-champ__erreur" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="pc-champ">
                            <label for="c-email">E-mail <span>(facultatif)</span></label>
                            <input id="c-email" type="email" name="email" value="{{ old('email') }}" maxlength="160" autocomplete="email" @error('email') aria-invalid="true" @enderror>
                            @error('email') <p class="pc-champ__erreur" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="pc-champ">
                        <label for="c-sujet">Sujet *</label>
                        <select id="c-sujet" name="sujet" required @error('sujet') aria-invalid="true" @enderror>
                            @foreach ($sujets as $cle => $libelle)
                                <option value="{{ $cle }}" @selected(old('sujet', request('sujet', 'question')) === $cle)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                        @error('sujet') <p class="pc-champ__erreur" role="alert">{{ $message }}</p> @enderror
                    </div>

                    <div class="pc-champ">
                        <label for="c-message">Votre message *</label>
                        <textarea id="c-message" name="message" rows="6" maxlength="2000" required @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea>
                        @error('message') <p class="pc-champ__erreur" role="alert">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="pc-btn pc-btn--primary pc-btn--lg">
                        <i class="bi bi-send" aria-hidden="true"></i> Envoyer le message
                    </button>
                    <p class="pc-form__note">
                        <i class="bi bi-shield-lock" aria-hidden="true"></i>
                        Vos coordonnées servent uniquement à vous répondre.
                    </p>
                </form>
            @endif
        </div>
    </div>
</section>

@endsection
