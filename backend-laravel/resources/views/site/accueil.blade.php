@extends('layouts.site')

@section('contenu')

{{-- ======================= HÉRO ======================= --}}
<section class="pc-hero">
    <div class="pc-hero__glow" aria-hidden="true"></div>
    <div class="pc-container pc-hero__inner">
        <div class="pc-hero__text" data-reveal>
            <span class="pc-chip">
                <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                Techniciens vérifiés · Conakry
            </span>
            <h1 class="pc-hero__title">{{ $hero['titre'] }}</h1>
            <p class="pc-hero__lede">{{ $hero['sousTitre'] }}</p>

            <div class="pc-hero__cta">
                <a href="{{ route('site.telecharger') }}" class="pc-btn pc-btn--primary pc-btn--lg">
                    <i class="bi bi-download" aria-hidden="true"></i> Télécharger l'application
                </a>
                <a href="#fonctionnement" class="pc-btn pc-btn--ghost pc-btn--lg">
                    <i class="bi bi-play-circle" aria-hidden="true"></i> Comment ça marche
                </a>
            </div>

            <div class="pc-hero__trust">
                <div class="pc-hero__avatars" aria-hidden="true">
                    <span>P</span><span>M</span><span>S</span><span>+</span>
                </div>
                <p>Une équipe guinéenne, une mission : le dépannage simple, sûr et transparent.</p>
            </div>
        </div>

        {{-- Maquette téléphone en pur CSS --}}
        <div class="pc-hero__visual" data-reveal data-reveal-delay="1">
            <div class="pc-phone">
                <div class="pc-phone__notch" aria-hidden="true"></div>
                <div class="pc-phone__screen">
                    <div class="pc-appcard pc-appcard--head">
                        <img src="{{ asset('images/logo-mark.png') }}" alt="" width="34" height="34">
                        <div>
                            <strong>Choisissez votre technicien</strong>
                            <span>Plomberie · Ratoma</span>
                        </div>
                    </div>

                    @foreach ([['Mamadou D.', '4.9', '1,2 km', '85 000', true], ['Ibrahima S.', '4.7', '2,0 km', '90 000', false], ['Alpha B.', '4.8', '2,6 km', '92 000', false]] as [$nom, $note, $dist, $prix, $top])
                        <div class="pc-appcard pc-tech {{ $top ? 'is-top' : '' }}">
                            <div class="pc-tech__avatar">{{ mb_substr($nom, 0, 1) }}</div>
                            <div class="pc-tech__info">
                                <strong>{{ $nom }}</strong>
                                <span><i class="bi bi-star-fill"></i> {{ $note }} · {{ $dist }}</span>
                            </div>
                            <div class="pc-tech__prix">{{ $prix }}<small>GNF</small></div>
                        </div>
                    @endforeach

                    <div class="pc-appcard pc-appcard--cta">Comparez et choisissez — prix ferme</div>
                </div>
            </div>
            <div class="pc-phone__badge pc-phone__badge--1" aria-hidden="true">
                <i class="bi bi-shield-check"></i> Paiement sécurisé
            </div>
            <div class="pc-phone__badge pc-phone__badge--2" aria-hidden="true">
                <i class="bi bi-lightning-charge-fill"></i> En quelques minutes
            </div>
        </div>
    </div>
</section>

{{-- ======================= STATISTIQUES ======================= --}}
<section class="pc-stats" aria-label="Chiffres de la plateforme">
    <div class="pc-container pc-stats__grid">
        @foreach ($statistiques as $stat)
            <div class="pc-stat" data-reveal>
                <i class="bi {{ $stat['icone'] }}" aria-hidden="true"></i>
                <div class="pc-stat__num"
                     data-counter="{{ $stat['valeur'] }}"
                     data-suffix="{{ $stat['suffixe'] }}"
                     data-decimals="{{ $stat['cle'] === 'note' ? 1 : 0 }}">0</div>
                <div class="pc-stat__label">{{ $stat['libelle'] }}</div>
            </div>
        @endforeach
    </div>
</section>

{{-- ======================= FONCTIONNEMENT ======================= --}}
<section class="pc-section" id="fonctionnement">
    <div class="pc-container">
        <div class="pc-section__head" data-reveal>
            <span class="pc-eyebrow">Simple comme bonjour</span>
            <h2>Un dépannage en trois gestes</h2>
            <p>Pas de marchandage interminable, pas de mauvaise surprise. Vous gardez la main du début à la fin.</p>
        </div>

        <div class="pc-steps">
            @foreach ([
                ['bi-pencil-square', 'Décrivez la panne', 'Choisissez le métier, ajoutez une photo si besoin et indiquez votre quartier. En moins d\'une minute.'],
                ['bi-people-fill', 'Comparez les techniciens', 'Plusieurs techniciens vérifiés vous répondent avec leur prix ferme, leur note et leur distance. Vous choisissez.'],
                ['bi-patch-check-fill', 'C\'est réparé, en toute sécurité', 'Suivi en temps réel, paiement sécurisé et garantie après intervention. Tout reste dans l\'application.'],
            ] as $i => [$icone, $titre, $texte])
                <div class="pc-step" data-reveal data-reveal-delay="{{ $i }}">
                    <div class="pc-step__num">{{ $i + 1 }}</div>
                    <i class="bi {{ $icone }} pc-step__icon" aria-hidden="true"></i>
                    <h3>{{ $titre }}</h3>
                    <p>{{ $texte }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ======================= MÉTIERS ======================= --}}
<section class="pc-section pc-section--alt" id="metiers">
    <div class="pc-container">
        <div class="pc-section__head" data-reveal>
            <span class="pc-eyebrow">Tous vos besoins</span>
            <h2>Des métiers, un seul réflexe</h2>
            <p>De la fuite d'eau au chantier du bâtiment, trouvez le bon professionnel près de chez vous.</p>
        </div>

        <div class="pc-metiers">
            @forelse ($metiers as $i => $metier)
                <div class="pc-metier" data-reveal data-reveal-delay="{{ $i % 4 }}"
                     style="--pc-m:{{ $metier['couleur'] }}">
                    <span class="pc-metier__icon"><i class="bi {{ $metier['icone'] }}" aria-hidden="true"></i></span>
                    <strong>{{ $metier['nom'] }}</strong>
                </div>
            @empty
                <p class="pc-muted">Les métiers s'affichent dès que le catalogue est configuré.</p>
            @endforelse
        </div>
    </div>
</section>

{{-- ======================= PLACE DE MARCHÉ ======================= --}}
<section class="pc-section">
    <div class="pc-container pc-split">
        <div class="pc-split__text" data-reveal>
            <span class="pc-eyebrow">La place de marché</span>
            <h2>Vous choisissez. Toujours.</h2>
            <p class="pc-lead">Fini le technicien imposé. Parema Connect vous montre plusieurs professionnels disponibles — leur prix, leur note, leur distance — et vous laisse décider.</p>
            <ul class="pc-list">
                <li><i class="bi bi-check-circle-fill"></i> <span><strong>Prix ferme</strong> affiché avant de confirmer, jamais de facture surprise.</span></li>
                <li><i class="bi bi-check-circle-fill"></i> <span><strong>Note et avis réels</strong> laissés par de vrais clients.</span></li>
                <li><i class="bi bi-check-circle-fill"></i> <span><strong>Distance et temps d'arrivée</strong> estimés pour chaque technicien.</span></li>
                <li><i class="bi bi-check-circle-fill"></i> <span><strong>Paiement au choix</strong> : espèces ou Mobile Money, toujours tracé.</span></li>
            </ul>
        </div>
        <div class="pc-split__visual" data-reveal data-reveal-delay="1">
            <div class="pc-glasscard">
                <div class="pc-glasscard__row"><span class="pc-dot pc-dot--blue"></span> Comparaison en direct</div>
                <div class="pc-bar"><span style="--w:92%"></span><em>Mamadou — 85 000 GNF</em></div>
                <div class="pc-bar"><span style="--w:78%"></span><em>Ibrahima — 90 000 GNF</em></div>
                <div class="pc-bar"><span style="--w:70%"></span><em>Alpha — 92 000 GNF</em></div>
                <div class="pc-glasscard__foot"><i class="bi bi-hand-index-thumb"></i> Vous décidez en un toucher</div>
            </div>
        </div>
    </div>
</section>

{{-- ======================= SÉCURITÉ ======================= --}}
<section class="pc-section pc-section--dark" id="securite">
    <div class="pc-container">
        <div class="pc-section__head pc-section__head--light" data-reveal>
            <span class="pc-eyebrow pc-eyebrow--light">Votre tranquillité d'abord</span>
            <h2>Pensé pour la confiance, protégé par la sécurité</h2>
            <p>La plateforme la plus exigeante de Guinée en matière de sécurité — pour les clients comme pour les techniciens.</p>
        </div>

        <div class="pc-features">
            @foreach ([
                ['bi-person-vcard', 'Techniciens vérifiés', 'Pièce d\'identité et dossier validés par notre équipe avant toute intervention.'],
                ['bi-shield-lock', 'Paiement sécurisé', 'Séquestre Mobile Money ou espèces confirmées : l\'argent n\'est libéré qu\'une fois le travail fait.'],
                ['bi-arrow-repeat', 'Garantie incluse', 'Une panne qui revient ? Le même technicien repasse gratuitement, dans le délai de garantie.'],
                ['bi-chat-left-dots', 'Échanges protégés', 'Messagerie intégrée et coordonnées masquées : tout reste dans un cadre sûr et traçable.'],
                ['bi-geo-alt', 'Suivi en temps réel', 'Vous voyez le technicien arriver sur la carte, comme pour une course.'],
                ['bi-lock', 'Données respectées', 'Positions GPS collectées seulement pendant une intervention, documents chiffrés et à accès limité.'],
            ] as $i => [$icone, $titre, $texte])
                <div class="pc-feature" data-reveal data-reveal-delay="{{ $i % 3 }}">
                    <span class="pc-feature__icon"><i class="bi {{ $icone }}" aria-hidden="true"></i></span>
                    <h3>{{ $titre }}</h3>
                    <p>{{ $texte }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ======================= TÉLÉCHARGEMENT ======================= --}}
<section class="pc-section pc-download" id="telecharger">
    <div class="pc-container pc-download__card" data-reveal>
        <div class="pc-download__text">
            <span class="pc-eyebrow">Disponible maintenant</span>
            <h2>Emportez Parema Connect dans votre poche</h2>
            <p>Téléchargez l'application Android et trouvez un technicien de confiance en quelques minutes. L'inscription est gratuite.</p>

            <div class="pc-download__buttons">
                @if (($telechargement['apk_disponible'] ?? false))
                    <a href="{{ route('site.telecharger') }}" class="pc-store">
                        <i class="bi bi-android2"></i>
                        <span><small>Téléchargement direct</small><strong>Fichier APK (Android)</strong></span>
                    </a>
                @endif

                @if (($telechargement['playstore_disponible'] ?? false))
                    <a href="{{ route('site.playstore') }}" class="pc-store">
                        <i class="bi bi-google-play"></i>
                        <span><small>Disponible sur</small><strong>Google Play</strong></span>
                    </a>
                @endif

                @if (! ($telechargement['apk_disponible'] ?? false) && ! ($telechargement['playstore_disponible'] ?? false))
                    <span class="pc-soon"><i class="bi bi-hourglass-split"></i> Lancement imminent — revenez très bientôt.</span>
                @endif
            </div>

            <a href="{{ route('site.devenir-technicien') }}" class="pc-download__tech">
                <i class="bi bi-person-badge"></i> Vous êtes artisan ? Devenez technicien partenaire →
            </a>
        </div>

        <div class="pc-download__visual" aria-hidden="true">
            <div class="pc-phone pc-phone--mini">
                <div class="pc-phone__screen pc-phone__screen--brand">
                    <img src="{{ asset('images/logo.png') }}" alt="">
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ======================= ÉQUIPE ======================= --}}
<section class="pc-section pc-section--alt" id="equipe">
    <div class="pc-container">
        <div class="pc-section__head" data-reveal>
            <span class="pc-eyebrow">Les visages derrière Parema</span>
            <h2>Une équipe guinéenne, engagée pour vous</h2>
            <p>Trois parcours, une même conviction : la technologie doit simplifier la vie des Guinéens.</p>
        </div>

        <div class="pc-team">
            @foreach ($equipe as $i => $membre)
                <article class="pc-member" data-reveal data-reveal-delay="{{ $i % 3 }}">
                    <div class="pc-member__photo">
                        @if ($membre->photoUrl())
                            <img src="{{ $membre->photoUrl() }}" alt="{{ $membre->nom }}" loading="lazy">
                        @else
                            <span class="pc-member__initiales">{{ $membre->initiales() }}</span>
                        @endif
                    </div>
                    <h3>{{ $membre->nom }}</h3>
                    <p class="pc-member__role">{{ $membre->role }}</p>
                    @if ($membre->bio)
                        <p class="pc-member__bio">{{ $membre->bio }}</p>
                    @endif
                    @if ($membre->lien_url)
                        <a href="{{ $membre->lien_url }}" class="pc-member__link" target="_blank" rel="noopener">
                            <i class="bi bi-linkedin"></i> En savoir plus
                        </a>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ======================= APPEL FINAL ======================= --}}
<section class="pc-cta">
    <div class="pc-container pc-cta__inner" data-reveal>
        <h2>Prêt à dépanner autrement ?</h2>
        <p>Rejoignez les Guinéens qui font confiance à Parema Connect pour leurs dépannages du quotidien.</p>
        <div class="pc-hero__cta pc-hero__cta--center">
            <a href="{{ route('site.telecharger') }}" class="pc-btn pc-btn--primary pc-btn--lg">
                <i class="bi bi-download"></i> Télécharger l'application
            </a>
            @if (($contact['whatsapp'] ?? '') !== '')
                <a href="{{ route('site.contact') }}" class="pc-btn pc-btn--ghost pc-btn--lg">
                    <i class="bi bi-whatsapp"></i> Nous écrire
                </a>
            @endif
        </div>
    </div>
</section>

@endsection
