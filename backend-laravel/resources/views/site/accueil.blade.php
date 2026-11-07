@extends('layouts.site')

@section('contenu')

{{-- ======================= HÉRO ======================= --}}
<section class="pc-hero">
    <div class="pc-hero__dots" aria-hidden="true"></div>
    <svg class="pc-hero__blob pc-hero__blob--1" viewBox="0 0 200 200" aria-hidden="true">
        <defs><linearGradient id="pcBlob1" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#a9c6ff"/><stop offset="1" stop-color="#c9b6ff"/>
        </linearGradient></defs>
        <g transform="translate(100 100)"><path fill="url(#pcBlob1)" d="M38.6,-57.8C51.5,-50.9,64.2,-42.1,70.3,-29.8C76.4,-17.5,75.9,-1.7,71.3,12.2C66.7,26.1,58,38.1,46.7,47.9C35.4,57.7,21.5,65.3,5.9,69.2C-9.7,73.1,-27,73.3,-40.9,66.1C-54.8,58.9,-65.3,44.3,-70.6,28.4C-75.9,12.5,-76,-4.7,-71.2,-20.3C-66.4,-35.9,-56.7,-49.9,-43.9,-57.3C-31.1,-64.7,-15.5,-65.5,-0.6,-64.6C14.4,-63.7,28.7,-61.1,38.6,-57.8Z"/></g>
    </svg>
    <svg class="pc-hero__blob pc-hero__blob--2" viewBox="0 0 200 200" aria-hidden="true">
        <defs><linearGradient id="pcBlob2" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#ffd0a8"/><stop offset="1" stop-color="#ffb3b3"/>
        </linearGradient></defs>
        <g transform="translate(100 100)"><path fill="url(#pcBlob2)" d="M42.7,-62.9C54.4,-55.6,62.3,-42.5,67.4,-28.5C72.5,-14.4,74.8,0.6,71.3,14.3C67.8,28,58.5,40.4,46.7,49.9C34.9,59.4,20.6,66,4.9,69.3C-10.8,72.6,-27.9,72.6,-41.8,65.4C-55.7,58.2,-66.4,43.8,-71.3,28C-76.2,12.2,-75.3,-5,-69.5,-19.9C-63.7,-34.8,-53,-47.4,-40,-54.8C-27,-62.2,-11.7,-64.4,2.5,-68C16.7,-71.6,31.1,-70.2,42.7,-62.9Z"/></g>
    </svg>
    <svg class="pc-hero__ring" viewBox="0 0 100 100" fill="none" aria-hidden="true"><circle cx="50" cy="50" r="46" stroke="currentColor" stroke-width="2" stroke-dasharray="5 10"/></svg>
    <svg class="pc-hero__spark pc-hero__spark--1" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0c1 6 5 10 12 12-7 2-11 6-12 12-1-6-5-10-12-12 7-2 11-6 12-12z"/></svg>
    <svg class="pc-hero__spark pc-hero__spark--2" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0c1 6 5 10 12 12-7 2-11 6-12 12-1-6-5-10-12-12 7-2 11-6 12-12z"/></svg>

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
                <div class="pc-hero__avatars" aria-hidden="true"><span>P</span><span>M</span><span>S</span><span>+</span></div>
                <p>Une équipe guinéenne, une mission : le dépannage simple, sûr et transparent.</p>
            </div>
        </div>

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
            <div class="pc-phone__badge pc-phone__badge--1" aria-hidden="true"><i class="bi bi-shield-check"></i> Paiement sécurisé</div>
            <div class="pc-phone__badge pc-phone__badge--2" aria-hidden="true"><i class="bi bi-lightning-charge-fill"></i> En quelques minutes</div>
        </div>
    </div>
</section>

{{-- ======================= STATISTIQUES ======================= --}}
<section class="pc-stats" aria-label="Chiffres de la plateforme">
    <div class="pc-container pc-stats__grid">
        @foreach ($statistiques as $stat)
            <div class="pc-stat" data-reveal data-reveal-delay="{{ $loop->index % 4 }}">
                <div class="pc-stat__icon"><i class="bi {{ $stat['icone'] }}" aria-hidden="true"></i></div>
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
            @php
                $svgEtapes = [
                    '<rect x="4" y="3" width="16" height="18" rx="2.2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
                    '<circle cx="9" cy="8.5" r="3"/><circle cx="17" cy="10.5" r="2.2"/><path d="M3.5 19.5c0-3 2.4-5 5.5-5s5.5 2 5.5 5"/><path d="M15.5 19.5c.2-2 1.4-3.3 3.3-3.3"/>',
                    '<path d="M12 3l7 3v5c0 4.6-3.1 7.8-7 9-3.9-1.2-7-4.4-7-9V6z"/><path d="M8.5 12l2.4 2.4L15.5 10"/>',
                ];
            @endphp
            @foreach ([
                ['Décrivez la panne', 'Choisissez le métier, ajoutez une photo si besoin, indiquez votre quartier. En moins d\'une minute.'],
                ['Comparez les techniciens', 'Plusieurs techniciens vérifiés vous répondent : prix ferme, note et distance. Vous choisissez.'],
                ['C\'est réparé, en sécurité', 'Suivi en temps réel, paiement sécurisé et garantie après intervention. Tout reste dans l\'app.'],
            ] as $i => [$titre, $texte])
                <div class="pc-step" data-reveal data-reveal-delay="{{ $i }}">
                    <svg class="pc-step__corner" viewBox="0 0 40 40" fill="currentColor" aria-hidden="true">
                        <circle cx="5" cy="5" r="2.2"/><circle cx="20" cy="5" r="2.2"/><circle cx="35" cy="5" r="2.2"/>
                        <circle cx="5" cy="20" r="2.2"/><circle cx="20" cy="20" r="2.2"/><circle cx="35" cy="20" r="2.2"/>
                        <circle cx="5" cy="35" r="2.2"/><circle cx="20" cy="35" r="2.2"/><circle cx="35" cy="35" r="2.2"/>
                    </svg>
                    <span class="pc-step__num">{{ sprintf('%02d', $i + 1) }}</span>
                    <span class="pc-step__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $svgEtapes[$i] !!}</svg>
                    </span>
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
                <div class="pc-metier" data-reveal data-reveal-delay="{{ $i % 4 }}" style="--pc-m:{{ $metier['couleur'] }}">
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
            <p class="pc-lead">Fini le technicien imposé. Parema Connect vous montre plusieurs professionnels disponibles — prix, note, distance — et vous laisse décider.</p>
            <ul class="pc-list">
                @foreach ([
                    ['Prix ferme', 'affiché avant de confirmer, jamais de facture surprise.'],
                    ['Note et avis réels', 'laissés par de vrais clients après intervention.'],
                    ['Distance et temps d\'arrivée', 'estimés pour chaque technicien proche.'],
                    ['Paiement au choix', 'espèces ou Mobile Money, toujours tracé.'],
                ] as [$fort, $suite])
                    <li>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="11" fill="#e6f6ec"/>
                            <path d="M7.5 12.3l3 3 6-6.5" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><strong>{{ $fort }}</strong> {{ $suite }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="pc-split__visual" data-reveal data-reveal-delay="1">
            <svg class="pc-market__blob" viewBox="0 0 200 200" aria-hidden="true">
                <defs><linearGradient id="pcBlobM" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#b9ccff"/><stop offset="1" stop-color="#d7c6ff"/>
                </linearGradient></defs>
                <g transform="translate(100 100)"><path fill="url(#pcBlobM)" d="M38.6,-57.8C51.5,-50.9,64.2,-42.1,70.3,-29.8C76.4,-17.5,75.9,-1.7,71.3,12.2C66.7,26.1,58,38.1,46.7,47.9C35.4,57.7,21.5,65.3,5.9,69.2C-9.7,73.1,-27,73.3,-40.9,66.1C-54.8,58.9,-65.3,44.3,-70.6,28.4C-75.9,12.5,-76,-4.7,-71.2,-20.3C-66.4,-35.9,-56.7,-49.9,-43.9,-57.3C-31.1,-64.7,-15.5,-65.5,-0.6,-64.6C14.4,-63.7,28.7,-61.1,38.6,-57.8Z"/></g>
            </svg>

            <div class="pc-glasscard pc-market">
                <div class="pc-market__head">
                    <span><span class="pc-dot pc-dot--blue pc-pulse"></span> 3 techniciens disponibles</span>
                    <span class="pc-market__live">en direct</span>
                </div>

                <div class="pc-market__list">
                    @foreach ([
                        ['M', 'Mamadou D.', '4,9', '1,2 km', '~10 min', '85 000', true],
                        ['I', 'Ibrahima S.', '4,7', '2,0 km', '~15 min', '90 000', false],
                        ['A', 'Alpha B.', '4,8', '2,6 km', '~18 min', '92 000', false],
                    ] as [$ini, $nom, $note, $dist, $eta, $prix, $best])
                        <div class="pc-mrow {{ $best ? 'is-best' : '' }}">
                            @if ($best)<span class="pc-mrow__ribbon">Meilleur choix</span>@endif
                            <div class="pc-mrow__av">{{ $ini }}</div>
                            <div class="pc-mrow__info">
                                <strong>{{ $nom }}</strong>
                                <span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.9 6.2 6.8.7-5 4.6 1.4 6.7L12 17.8 5.9 20.2l1.4-6.7-5-4.6 6.8-.7z"/></svg>
                                    {{ $note }} · {{ $dist }} · {{ $eta }}
                                </span>
                            </div>
                            <div class="pc-mrow__price">{{ $prix }}<small>GNF</small></div>
                        </div>
                    @endforeach
                </div>

                <div class="pc-market__cta" aria-hidden="true">
                    Choisir Mamadou
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </div>
                <div class="pc-market__note">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v5c0 4.6-3.1 7.8-7 9-3.9-1.2-7-4.4-7-9V6z"/><path d="M8.5 12l2.4 2.4L15.5 10"/></svg>
                    Prix ferme — aucune surprise
                </div>
            </div>

            <div class="pc-market__badge" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                Note vérifiée
            </div>
        </div>
    </div>
</section>

{{-- divider vers section teintée --}}
<div class="pc-divider" style="background:var(--pc-bg)" aria-hidden="true">
    <svg viewBox="0 0 1440 70" preserveAspectRatio="none"><path fill="#f7f5ff" d="M0,40 C240,72 480,72 720,46 C960,20 1200,20 1440,44 L1440,70 L0,70 Z"/></svg>
</div>

{{-- ======================= SÉCURITÉ (bento) ======================= --}}
<section class="pc-section pc-section--spot" id="securite">
    <div class="pc-container">
        <div class="pc-section__head" data-reveal>
            <span class="pc-eyebrow">Votre tranquillité d'abord</span>
            <h2>Pensé pour la confiance, protégé par la sécurité</h2>
            <p>La plateforme la plus exigeante de Guinée en matière de sécurité — pour les clients comme pour les techniciens.</p>
        </div>

        @php
            $svgSecu = [
                '<circle cx="12" cy="8" r="3.4"/><path d="M5 20c0-3.5 3.1-6 7-6 1.3 0 2.5.3 3.6.8"/><path d="M15.5 19l2 2 3.5-4"/>',
                '<rect x="5" y="10" width="14" height="10" rx="2.2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><circle cx="12" cy="15" r="1.4"/>',
                '<path d="M20 11a8 8 0 1 0-2.3 5.6"/><path d="M20 5v5h-5"/>',
                '<path d="M4 6h16v10H8l-4 3z"/><path d="M8.5 11h7M8.5 8.5h4"/>',
                '<path d="M12 21s7-5.3 7-11a7 7 0 1 0-14 0c0 5.7 7 11 7 11z"/><circle cx="12" cy="10" r="2.4"/>',
                '<rect x="5" y="10" width="14" height="10" rx="2.2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
            ];
        @endphp
        <div class="pc-bento">
            @foreach ([
                ['Techniciens vérifiés', 'Pièce d\'identité et dossier validés par notre équipe avant toute intervention.'],
                ['Paiement sécurisé', 'Séquestre Mobile Money ou espèces confirmées : l\'argent n\'est libéré qu\'une fois le travail fait.'],
                ['Garantie incluse', 'Une panne qui revient ? Le même technicien repasse gratuitement dans le délai de garantie.'],
                ['Échanges protégés', 'Messagerie intégrée, coordonnées masquées : tout reste dans un cadre sûr et traçable.'],
                ['Suivi en temps réel', 'Vous voyez le technicien arriver sur la carte, comme pour une course.'],
                ['Données respectées', 'GPS collecté seulement pendant une intervention, documents chiffrés et à accès limité.'],
            ] as $i => [$titre, $texte])
                <div class="pc-bento__item" data-reveal data-reveal-delay="{{ $i % 3 }}">
                    <svg class="pc-bento__corner" viewBox="0 0 44 44" fill="currentColor" aria-hidden="true">
                        <circle cx="6" cy="6" r="2.4"/><circle cx="22" cy="6" r="2.4"/><circle cx="38" cy="6" r="2.4"/>
                        <circle cx="6" cy="22" r="2.4"/><circle cx="22" cy="22" r="2.4"/><circle cx="38" cy="22" r="2.4"/>
                        <circle cx="6" cy="38" r="2.4"/><circle cx="22" cy="38" r="2.4"/><circle cx="38" cy="38" r="2.4"/>
                    </svg>
                    <span class="pc-bento__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $svgSecu[$i] !!}</svg>
                    </span>
                    <h3>{{ $titre }}</h3>
                    <p>{{ $texte }}</p>
                    <span class="pc-bento__more">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        Inclus
                    </span>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- divider vers fond blanc --}}
<div class="pc-divider" style="background:#f6f8fc" aria-hidden="true">
    <svg viewBox="0 0 1440 70" preserveAspectRatio="none"><path fill="#ffffff" d="M0,44 C240,20 480,20 720,44 C960,68 1200,68 1440,40 L1440,70 L0,70 Z"/></svg>
</div>

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
                <div class="pc-phone__screen pc-phone__screen--brand"><img src="{{ asset('images/logo.png') }}" alt=""></div>
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

{{-- ======================= FAQ ======================= --}}
<section class="pc-section" id="faq">
    <div class="pc-container">
        <div class="pc-section__head" data-reveal>
            <span class="pc-eyebrow">Bon à savoir</span>
            <h2>Questions fréquentes</h2>
        </div>
        <div class="pc-faq" data-reveal>
            @foreach ([
                ['Parema Connect, c\'est gratuit ?', 'Télécharger l\'application et publier une demande est gratuit. Vous ne payez que l\'intervention, au prix ferme affiché avant de confirmer.'],
                ['Comment sont choisis les techniciens ?', 'Chaque technicien fournit une pièce d\'identité et un dossier validés par notre équipe. Vous voyez ensuite sa note, sa distance et son prix avant de choisir.'],
                ['Comment se passe le paiement ?', 'Au choix : espèces ou Mobile Money. En Mobile Money, l\'argent est gardé en séquestre et libéré une fois l\'intervention validée.'],
                ['Et si la panne revient ?', 'Une garantie couvre l\'intervention : si la panne réapparaît dans le délai, le même technicien repasse gratuitement.'],
                ['Je suis artisan, puis-je m\'inscrire ?', 'Oui ! Téléchargez l\'application, créez votre profil technicien et déposez votre dossier. Après validation, vous recevez des demandes près de chez vous.'],
            ] as [$q, $r])
                <details class="pc-faq__item">
                    <summary>{{ $q }}</summary>
                    <p>{{ $r }}</p>
                </details>
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
