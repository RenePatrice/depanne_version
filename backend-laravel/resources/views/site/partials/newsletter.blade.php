{{--
    Inscription à la lettre d'information, présente en pied de chaque page.
    Le serveur répond la même chose que l'adresse soit nouvelle ou déjà inscrite.
--}}
<section class="pc-news" id="newsletter" aria-labelledby="pc-news-titre">
    <svg class="pc-news__deco" viewBox="0 0 120 120" fill="none" aria-hidden="true">
        <path d="M14 44 60 76l46-32" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
        <rect x="12" y="30" width="96" height="64" rx="14" stroke="currentColor" stroke-width="5"/>
    </svg>

    <div class="pc-news__text">
        <h2 id="pc-news-titre">Restez <em>informé</em></h2>
        <p>Lancement de l'application, nouveaux quartiers couverts, nouveaux métiers : recevez l'essentiel par e-mail. Pas de publicité, désinscription sur simple demande.</p>
    </div>

    @if (session('newsletter_inscrit'))
        <div class="pc-news__ok" role="status">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            <span><strong>C'est noté, merci !</strong> Vous recevrez nos prochaines nouvelles.</span>
        </div>
    @else
        <form class="pc-news__form" method="POST" action="{{ route('site.newsletter') }}">
            @csrf
            <input type="hidden" name="source" value="{{ request()->route()?->getName() }}">
            {{-- Pot de miel : invisible pour un humain, rempli par les robots. --}}
            <input class="pc-pot" type="text" name="site_web" tabindex="-1" autocomplete="off" aria-hidden="true">

            <label class="pc-sr" for="pc-news-email">Votre adresse e-mail</label>
            <div class="pc-news__champ">
                <i class="bi bi-envelope" aria-hidden="true"></i>
                <input id="pc-news-email" type="email" name="email" placeholder="vous@exemple.com" maxlength="160"
                       value="{{ old('email') }}" required autocomplete="email">
                <button type="submit" class="pc-btn pc-btn--primary">S'inscrire</button>
            </div>
            @error('email', 'newsletter')
                <p class="pc-champ__erreur" role="alert">{{ $message }}</p>
            @enderror
        </form>
    @endif
</section>
