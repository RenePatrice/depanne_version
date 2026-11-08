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
                @include('site.partials.btn-telecharger', ['size' => 'pc-btn--lg'])
                <a href="#fonctionnement" class="pc-btn pc-btn--ghost pc-btn--lg">
                    <i class="bi bi-play-circle" aria-hidden="true"></i> Comment ça marche
                </a>
            </div>

            <div class="pc-hero__badges">
                <span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg> Techniciens vérifiés</span>
                <span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v5c0 4.6-3.1 7.8-7 9-3.9-1.2-7-4.4-7-9V6z"/><path d="M8.5 12l2.4 2.4L15.5 10"/></svg> Paiement sécurisé</span>
                <span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 5.6"/><path d="M20 5v5h-5"/></svg> Garantie incluse</span>
            </div>
            <p class="pc-hero__trustline">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-5.3 7-11a7 7 0 1 0-14 0c0 5.7 7 11 7 11z"/><circle cx="12" cy="10" r="2.4"/></svg>
                Une équipe guinéenne, une mission : le dépannage et les travaux à domicile, simples, sûrs et transparents.
            </p>
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
                @if (($stat['texte'] ?? null) !== null)
                    <div class="pc-stat__num">{{ $stat['texte'] }}</div>
                @else
                    <div class="pc-stat__num"
                         data-counter="{{ $stat['valeur'] }}"
                         data-suffix="{{ $stat['suffixe'] }}"
                         data-decimals="{{ $stat['cle'] === 'note' ? 1 : 0 }}">0</div>
                @endif
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
            <h2>Votre intervention en trois gestes</h2>
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

{{-- ======================= APERÇU DE L'APP ======================= --}}
<section class="pc-section pc-section--spot" id="apercu">
    <div class="pc-container">
        <div class="pc-section__head" data-reveal>
            <span class="pc-eyebrow">L'app en un coup d'œil</span>
            <h2>Du besoin à la réparation, en trois écrans</h2>
            <p>Choisissez le service, comparez les techniciens, puis suivez votre technicien en direct jusqu'à votre porte.</p>
        </div>

        <div class="pc-shots">
            {{-- 1. Catalogue --}}
            <div class="pc-shot" data-reveal>
                <div class="pc-phone pc-phone--showcase">
                    <div class="pc-phone__notch" aria-hidden="true"></div>
                    <div class="pc-phone__screen pc-scr">
                        <div class="pc-scr__head">Que faut-il réparer ?</div>
                        @foreach (array_slice($metiers, 0, 5) as $m)
                            <div class="pc-scr__row">
                                <span class="pc-scr__ico" style="--c:{{ $m['couleur'] }}"><i class="bi {{ $m['icone'] }}" aria-hidden="true"></i></span>
                                <div class="pc-scr__txt"><strong>{{ $m['nom'] }}</strong><span>Dépannage &amp; installation</span></div>
                                <i class="bi bi-chevron-right pc-scr__chev" aria-hidden="true"></i>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="pc-shot__cap">
                    <span class="pc-shot__step">1</span>
                    <strong>Choisissez le service</strong>
                    <span>Un catalogue clair, métier par métier.</span>
                </div>
            </div>

            {{-- 2. Comparaison --}}
            <div class="pc-shot" data-reveal data-reveal-delay="1">
                <div class="pc-phone pc-phone--showcase">
                    <div class="pc-phone__notch" aria-hidden="true"></div>
                    <div class="pc-phone__screen">
                        <div class="pc-appcard pc-appcard--head">
                            <img src="{{ asset('images/logo-mark.png') }}" alt="" width="32" height="32">
                            <div><strong>Techniciens disponibles</strong><span>Plomberie · Ratoma</span></div>
                        </div>
                        @foreach ([['M', 'Mamadou D.', '4,9', '1,2 km', '85 000', true], ['I', 'Ibrahima S.', '4,7', '2,0 km', '90 000', false], ['A', 'Alpha B.', '4,8', '2,6 km', '92 000', false]] as [$ini, $nom, $note, $dist, $prix, $top])
                            <div class="pc-appcard pc-tech {{ $top ? 'is-top' : '' }}">
                                <div class="pc-tech__avatar">{{ $ini }}</div>
                                <div class="pc-tech__info"><strong>{{ $nom }}</strong><span><i class="bi bi-star-fill"></i> {{ $note }} · {{ $dist }}</span></div>
                                <div class="pc-tech__prix">{{ $prix }}<small>GNF</small></div>
                            </div>
                        @endforeach
                        <div class="pc-appcard pc-appcard--cta">Prix ferme — vous choisissez</div>
                    </div>
                </div>
                <div class="pc-shot__cap">
                    <span class="pc-shot__step">2</span>
                    <strong>Comparez &amp; choisissez</strong>
                    <span>Prix, note et distance, côte à côte.</span>
                </div>
            </div>

            {{-- 3. Suivi sur la carte --}}
            <div class="pc-shot" data-reveal data-reveal-delay="2">
                <div class="pc-phone pc-phone--showcase">
                    <div class="pc-phone__notch" aria-hidden="true"></div>
                    <div class="pc-phone__screen pc-scr--map">
                        <svg class="pc-scr__mapsvg" viewBox="0 0 244 440" preserveAspectRatio="xMidYMid slice" fill="none" aria-hidden="true">
                            <rect width="244" height="440" fill="#eef3fb"/>
                            <path d="M-20 300 Q120 268 264 312 L264 440 L-20 440 Z" fill="#d7e6fb"/>
                            <g stroke="#dce5f3" stroke-width="11" stroke-linecap="round">
                                <path d="M-10 120 L254 150"/>
                                <path d="M64 -10 L96 360"/>
                                <path d="M-10 250 L254 236"/>
                            </g>
                            <g fill="#ffffff" opacity="0.7">
                                <rect x="120" y="60" width="60" height="44" rx="9"/>
                                <rect x="150" y="180" width="60" height="54" rx="9"/>
                            </g>
                            {{-- itinéraire technicien -> client --}}
                            <path d="M74 96 C 130 150, 96 230, 158 300" stroke="#2f6bed" stroke-width="5" stroke-dasharray="2 9" stroke-linecap="round" opacity="0.7"/>
                            {{-- destination (client) --}}
                            <g transform="translate(158 300)"><circle r="10" fill="#fff" stroke="#f2854c" stroke-width="3"/><circle r="4" fill="#f2854c"/></g>
                            {{-- technicien (en mouvement) --}}
                            <g transform="translate(74 96)">
                                <circle class="pc-scr__movdot" r="13" fill="#2f6bed" opacity="0.2"/>
                                <circle r="8.5" fill="#2f6bed"/><circle r="3.2" fill="#fff"/>
                            </g>
                        </svg>
                        <div class="pc-scr__track">
                            <div class="pc-scr__av">M</div>
                            <div class="pc-scr__who"><strong>Mamadou arrive</strong><span>à 4 min · 1,2 km</span></div>
                            <div class="pc-scr__act pc-scr__act--call"><i class="bi bi-telephone-fill" aria-hidden="true"></i></div>
                            <div class="pc-scr__act pc-scr__act--chat"><i class="bi bi-chat-fill" aria-hidden="true"></i></div>
                        </div>
                    </div>
                </div>
                <div class="pc-shot__cap">
                    <span class="pc-shot__step">3</span>
                    <strong>Suivez en direct</strong>
                    <span>Voyez le technicien arriver sur la carte.</span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ======================= MÉTIERS ======================= --}}
<section class="pc-section pc-section--soft" id="metiers">
    <div class="pc-container">
        <div class="pc-section__head" data-reveal>
            <span class="pc-eyebrow">Tous vos besoins</span>
            <h2>Des métiers, un seul réflexe</h2>
            <p>De la fuite d'eau au chantier du bâtiment, trouvez le bon professionnel près de chez vous.</p>
        </div>
        <div class="pc-metiers">
            @forelse ($metiers as $i => $metier)
                <a href="#telecharger" class="pc-metier" data-reveal data-reveal-delay="{{ $i % 3 }}" style="--pc-m:{{ $metier['couleur'] }}">
                    <span class="pc-metier__icon"><i class="bi {{ $metier['icone'] }}" aria-hidden="true"></i></span>
                    <span class="pc-metier__body">
                        <strong>{{ $metier['nom'] }}</strong>
                        <span class="pc-metier__desc">Dépannage &amp; installation</span>
                    </span>
                    <svg class="pc-metier__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
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
            <h2>Emportez <span class="pc-grad-text">Parema Connect</span> dans votre poche</h2>
            <p>Téléchargez l'application Android et trouvez un technicien de confiance en quelques minutes. L'inscription est gratuite.</p>

            <div class="pc-download__pills">
                @foreach (['Gratuit', 'Sans engagement', 'Android'] as $pastille)
                    <span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        {{ $pastille }}
                    </span>
                @endforeach
            </div>

            @php $aApp = ($telechargement['apk_disponible'] ?? false) || ($telechargement['playstore_disponible'] ?? false); @endphp

            @if ($aApp)
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
                </div>
                <div class="pc-download__meta">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    Android 7.0+ · sans publicité
                </div>
            @else
                <div class="pc-dlsoon">
                    <span class="pc-dlsoon__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 4h12M6 20h12M8 4v3.5L12 11l4-3.5V4M8 20v-3.5L12 13l4 3.5V20"/></svg>
                    </span>
                    <div>
                        <strong>Lancement imminent</strong>
                        <span>L'application arrive très bientôt sur Android. Revenez vite !</span>
                    </div>
                </div>
            @endif

            <a href="{{ route('site.devenir-technicien') }}" class="pc-download__tech">
                <i class="bi bi-person-badge"></i> Vous êtes artisan ? Devenez technicien partenaire →
            </a>
        </div>

        <div class="pc-download__visual" aria-hidden="true">
            <div class="pc-phone">
                <div class="pc-phone__notch"></div>
                <div class="pc-phone__screen pc-dlscreen">
                    <div class="pc-dlscreen__logo"><img src="{{ asset('images/logo-mark.png') }}" alt=""></div>
                    <strong>Parema Connect</strong>
                    <span class="pc-dlscreen__stars">★★★★★</span>
                    <span class="pc-dlscreen__meta">Dépannage &amp; travaux · Conakry</span>
                    <div class="pc-dlscreen__btn">Installer</div>
                </div>
            </div>
            <div class="pc-dlbadge pc-dlbadge--1">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.9 6.2 6.8.7-5 4.6 1.4 6.7L12 17.8 5.9 20.2l1.4-6.7-5-4.6 6.8-.7z"/></svg>
                4,8
            </div>
            <div class="pc-dlbadge pc-dlbadge--2">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                Gratuit
            </div>
        </div>
    </div>
</section>

{{-- ======================= DEUX AUDIENCES ======================= --}}
<section class="pc-section pc-section--soft" id="offres">
    <div class="pc-container">
        <div class="pc-section__head" data-reveal>
            <span class="pc-eyebrow">Deux expériences, une seule app</span>
            <h2>Que vous cherchiez un pro, ou que vous en soyez un</h2>
            <p>Parema Connect réunit clients et techniciens dans un même espace, pensé pour chacun.</p>
        </div>

        <div class="pc-duo">
            <div class="pc-duo__card pc-duo__card--client" data-reveal>
                <span class="pc-duo__badge">Clients</span>
                <div class="pc-duo__head">
                    <span class="pc-duo__icon"><i class="bi bi-person-fill" aria-hidden="true"></i></span>
                    <div>
                        <h3>Vous avez une panne</h3>
                        <p>Le bon pro, au bon prix</p>
                    </div>
                </div>
                <ul class="pc-duo__list">
                    @foreach ([
                        'Comparez plusieurs techniciens vérifiés',
                        'Prix ferme affiché avant de confirmer',
                        'Payez en espèces ou Mobile Money, en sécurité',
                        'Garantie et support après l\'intervention',
                    ] as $pt)
                        <li>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#e7efff"/><path d="M7.5 12.3l3 3 6-6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <span>{{ $pt }}</span>
                        </li>
                    @endforeach
                </ul>
                @include('site.partials.btn-telecharger', ['size' => 'pc-btn--lg'])
            </div>

            <div class="pc-duo__card pc-duo__card--tech" data-reveal data-reveal-delay="1">
                <span class="pc-duo__badge">Techniciens</span>
                <div class="pc-duo__head">
                    <span class="pc-duo__icon"><i class="bi bi-tools" aria-hidden="true"></i></span>
                    <div>
                        <h3>Vous êtes artisan</h3>
                        <p>Développez votre activité</p>
                    </div>
                </div>
                <ul class="pc-duo__list">
                    @foreach ([
                        'Recevez des demandes près de chez vous',
                        'Fixez vos propres tarifs par prestation',
                        'Portefeuille et retraits simples et clairs',
                        'Paiement sécurisé par la plateforme',
                    ] as $pt)
                        <li>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#ffe9db"/><path d="M7.5 12.3l3 3 6-6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <span>{{ $pt }}</span>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('site.devenir-technicien') }}" class="pc-btn pc-btn--warm pc-btn--lg">
                    <i class="bi bi-person-badge"></i> Devenir technicien
                </a>
            </div>
        </div>
    </div>
</section>

{{-- ======================= DEVENIR TECHNICIEN ======================= --}}
<section class="pc-section pc-section--soft" id="devenir-technicien">
    <div class="pc-container">
        <div class="pc-section__head" data-reveal>
            <span class="pc-eyebrow">Rejoignez le réseau</span>
            <h2>Devenir technicien partenaire</h2>
            <p>Plombier, électricien, maçon, peintre, menuisier ou carreleur ? Recevez des demandes près de chez vous et développez votre activité. L'inscription se fait dans l'application, en quelques minutes.</p>
        </div>

        <div class="pc-steps">
            @php
                $svgTech = [
                    '<path d="M12 3v11M8 10l4 4 4-4"/><path d="M5 20h14"/>',
                    '<rect x="4" y="3" width="16" height="18" rx="2.2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
                    '<path d="M12 3l7 3v5c0 4.6-3.1 7.8-7 9-3.9-1.2-7-4.4-7-9V6z"/><path d="M8.5 12l2.4 2.4L15.5 10"/>',
                ];
            @endphp
            @foreach ([
                ['Téléchargez et inscrivez-vous', 'Installez l\'app, créez votre compte technicien et choisissez vos métiers. Vous fixez vous-même vos tarifs par prestation.'],
                ['Déposez votre dossier', 'Pièce d\'identité et justificatifs : notre équipe vérifie chaque profil avant la mise en ligne.'],
                ['Recevez des demandes', 'Une fois validé, les clients de votre zone vous trouvent. Paiement sécurisé, portefeuille et retraits clairs.'],
            ] as $i => [$titre, $texte])
                <div class="pc-step" data-reveal data-reveal-delay="{{ $i % 3 }}">
                    <svg class="pc-step__corner" viewBox="0 0 40 40" fill="currentColor" aria-hidden="true">
                        <circle cx="5" cy="5" r="2.2"/><circle cx="20" cy="5" r="2.2"/><circle cx="35" cy="5" r="2.2"/>
                        <circle cx="5" cy="20" r="2.2"/><circle cx="20" cy="20" r="2.2"/><circle cx="35" cy="20" r="2.2"/>
                        <circle cx="5" cy="35" r="2.2"/><circle cx="20" cy="35" r="2.2"/><circle cx="35" cy="35" r="2.2"/>
                    </svg>
                    <span class="pc-step__num">{{ sprintf('%02d', $i + 1) }}</span>
                    <span class="pc-step__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $svgTech[$i] !!}</svg>
                    </span>
                    <h3>{{ $titre }}</h3>
                    <p>{{ $texte }}</p>
                </div>
            @endforeach
        </div>

        <div class="pc-devenir__cta" data-reveal>
            @include('site.partials.btn-telecharger', ['size' => 'pc-btn--lg', 'libelle' => 'Télécharger et m\'inscrire', 'libelleBientot' => 'Bientôt disponible'])
            <a href="{{ route('site.contact') }}" class="pc-btn pc-btn--ghost pc-btn--lg">
                <i class="bi bi-whatsapp" aria-hidden="true"></i> Une question ? Contactez-nous
            </a>
        </div>
    </div>
</section>

{{-- ======================= ÉQUIPE ======================= --}}
<section class="pc-section" id="equipe">
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

{{-- ======================= COUVERTURE CONAKRY ======================= --}}
<section class="pc-section pc-section--soft" id="zones">
    <div class="pc-container pc-zones">
        <div data-reveal>
            <span class="pc-eyebrow">Conakry, quartier par quartier</span>
            <h2>Déjà présent près de chez vous</h2>
            <p class="pc-lead">Nous couvrons un nombre croissant de zones à Conakry et nous nous étendons chaque semaine. Activez votre position dans l'app pour voir les techniciens les plus proches.</p>
            <div class="pc-zones__chips">
                @forelse ($zones as $zone)
                    <span class="pc-zones__chip">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        {{ $zone }}
                    </span>
                @empty
                @endforelse
                <span class="pc-zones__chip pc-zones__chip--soon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Bientôt dans tout Conakry
                </span>
            </div>
        </div>

        @php
            // Une épingle par commune réellement couverte : la liste vient du
            // back-office ($zones = communes actives). Les positions sont fixées
            // sur la carte stylisée, étalées pour que les étiquettes ne se
            // chevauchent pas, à l'écart du trait de côte (bas) et du badge.
            $positions = [
                'Dixinn' => ['x' => 120, 'y' => 108, 'c' => '#16a34a'],
                'Ratoma' => ['x' => 250, 'y' => 105, 'c' => '#f2854c'],
                'Kaloum' => ['x' => 80,  'y' => 200, 'c' => '#2f6bed'],
                'Matam'  => ['x' => 200, 'y' => 205, 'c' => '#0ea5a3'],
                'Matoto' => ['x' => 335, 'y' => 165, 'c' => '#7c6cf0'],
            ];
            $anims = ['', ' pc-zones__ping--2', ' pc-zones__ping--3'];
            $pinAnims = ['', ' pc-zones__pin--2', ' pc-zones__pin--3'];
            $pins = [];
            $i = 0;
            foreach ($zones as $commune) {
                if (! isset($positions[$commune])) {
                    continue;
                }
                $pins[] = $positions[$commune] + [
                    'lib' => $commune,
                    'mod' => $anims[$i % 3],
                    'pmod' => $pinAnims[$i % 3],
                ];
                $i++;
            }
        @endphp
        <div class="pc-zones__visual" data-reveal data-reveal-delay="1">
            <svg class="pc-zones__map" viewBox="0 0 400 330" fill="none" aria-hidden="true">
                <defs>
                    <linearGradient id="pcMap" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#eef3fe"/><stop offset="1" stop-color="#fff4ec"/></linearGradient>
                    <clipPath id="pcMapClip"><rect x="8" y="8" width="384" height="314" rx="26"/></clipPath>
                </defs>
                <rect x="8" y="8" width="384" height="314" rx="26" fill="url(#pcMap)" stroke="#e7ecf5"/>
                <g clip-path="url(#pcMapClip)">
                    {{-- Côte (Conakry est une presqu'île) --}}
                    <path d="M8 258 Q120 228 225 266 T392 256 L392 322 L8 322 Z" fill="#d7e6fb" opacity="0.85"/>
                    <path d="M8 268 Q120 238 225 276 T392 266" stroke="#c0d6f5" stroke-width="2.5" fill="none"/>
                    {{-- Parc --}}
                    <rect x="250" y="66" width="74" height="52" rx="13" fill="#d8f0e0"/>
                    {{-- Îlots urbains --}}
                    <g fill="#ffffff" opacity="0.72">
                        <rect x="38" y="56" width="72" height="46" rx="10"/>
                        <rect x="128" y="128" width="62" height="52" rx="10"/>
                        <rect x="56" y="150" width="52" height="64" rx="10"/>
                        <rect x="300" y="150" width="60" height="56" rx="10"/>
                    </g>
                    {{-- Grandes avenues --}}
                    <g stroke="#cdd9ee" stroke-width="8" stroke-linecap="round">
                        <path d="M28 118 L372 92"/>
                        <path d="M92 28 L124 244"/>
                        <path d="M38 204 L362 214"/>
                        <path d="M242 26 L272 250"/>
                    </g>
                    {{-- Rues secondaires --}}
                    <g stroke="#e3eaf6" stroke-width="4" stroke-linecap="round">
                        <path d="M30 168 L362 156"/>
                        <path d="M172 28 L196 246"/>
                        <path d="M60 70 L330 60"/>
                    </g>
                </g>

                {{-- Itinéraire animé traversant la presqu'île --}}
                <path class="pc-zones__route" d="M80 200 Q150 150 250 120 Q310 130 335 165"/>

                {{-- Épingles : groupe EXTERNE = position, groupe INTERNE = flottement --}}
                @foreach ($pins as $p)
                    <g class="pc-zones__marker" transform="translate({{ $p['x'] }} {{ $p['y'] }})">
                        <ellipse cx="0" cy="52" rx="11" ry="3.5" fill="#141f38" opacity="0.12"/>
                        <circle class="pc-zones__ping{{ $p['mod'] }}" cx="0" cy="49" r="7" fill="none" stroke="{{ $p['c'] }}" stroke-width="2.5"/>
                        <g class="pc-zones__pin{{ $p['pmod'] ?? '' }}">
                            <path d="M0 0c-11 0-19 8-19 19 0 14 19 30 19 30s19-16 19-30C19 8 11 0 0 0z" fill="{{ $p['c'] }}"/>
                            <circle cy="19" r="7.5" fill="#fff"/>
                        </g>
                        @if ($p['lib'] !== '')
                            <g class="pc-zones__label">
                                <rect x="-50" y="-34" width="100" height="27" rx="13.5" fill="#fff" stroke="#e9edf4"/>
                                <path d="M-5 -8 L0 -2 L5 -8 Z" fill="#fff"/>
                                <text x="0" y="-15.5" text-anchor="middle">{{ $p['lib'] }}</text>
                            </g>
                        @endif
                    </g>
                @endforeach
            </svg>

            <div class="pc-zones__badge">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-5.3 7-11a7 7 0 1 0-14 0c0 5.7 7 11 7 11z"/><circle cx="12" cy="10" r="2.4"/></svg>
                <span><strong>{{ count($zones) }}</strong> commune{{ count($zones) > 1 ? 's' : '' }} couverte{{ count($zones) > 1 ? 's' : '' }}</span>
            </div>
        </div>
    </div>
</section>

{{-- ======================= FAQ ======================= --}}
<section class="pc-section" id="faq">
    <div class="pc-container pc-faq-wrap">
        <div class="pc-faq-aside" data-reveal>
            <div class="pc-section__head">
                <span class="pc-eyebrow">Bon à savoir</span>
                <h2>Questions fréquentes</h2>
                <p>Tout ce qu'il faut savoir avant de vous lancer.</p>
            </div>
            <div class="pc-faq-contact">
                <h3>Une autre question ?</h3>
                <p>Notre équipe vous répond rapidement et avec le sourire.</p>
                <a href="{{ route('site.contact') }}" class="pc-btn pc-btn--primary">
                    <i class="bi bi-chat-dots"></i> Nous contacter
                </a>
            </div>
        </div>

        <div class="pc-faq" data-reveal data-reveal-delay="1">
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

{{-- ======================= PARTENAIRES DE PAIEMENT ======================= --}}
@php
    $paiements = [
        ['Orange Money', 'Payez directement et en sécurité depuis votre compte Orange Money.', 'bi-phone-fill', '#f97316', 'on'],
        ['MTN MoMo', 'Bientôt intégré, pour encore plus de liberté de paiement.', 'bi-wallet-fill', '#f5b100', 'soon'],
        ['Wave', 'Des frais réduits, bientôt disponible dans l\'application.', 'bi-credit-card-2-front-fill', '#1dc8e8', 'soon'],
    ];
@endphp
<section class="pc-section pc-section--soft" id="partenaires">
    <div class="pc-container">
        <div class="pc-section__head" data-reveal>
            <span class="pc-eyebrow">Nos partenaires</span>
            <h2>Partenaires de paiement</h2>
            <p>Réglez en toute sécurité via les opérateurs Mobile Money de confiance — ou en espèces, toujours tracé.</p>
        </div>

        <div class="pc-pay">
            @foreach ($paiements as $i => [$nom, $desc, $ic, $col, $etat])
                <div class="pc-pay__card" data-reveal data-reveal-delay="{{ $i }}" style="--pc-pc:{{ $col }}">
                    <span class="pc-pay__ico"><i class="bi {{ $ic }}" aria-hidden="true"></i></span>
                    <h3>{{ $nom }}</h3>
                    <p>{{ $desc }}</p>
                    @if ($etat === 'on')
                        <span class="pc-pay__status pc-pay__status--on"><span class="pc-pay__led" aria-hidden="true"></span> Disponible</span>
                    @else
                        <span class="pc-pay__status pc-pay__status--soon"><i class="bi bi-hourglass-split" aria-hidden="true"></i> Bientôt</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ======================= APPEL FINAL ======================= --}}
<section class="pc-cta">
    <div class="pc-container pc-cta__inner" data-reveal>
        <svg class="pc-cta__spark pc-cta__spark--1" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0c1 6 5 10 12 12-7 2-11 6-12 12-1-6-5-10-12-12 7-2 11-6 12-12z"/></svg>
        <svg class="pc-cta__spark pc-cta__spark--2" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0c1 6 5 10 12 12-7 2-11 6-12 12-1-6-5-10-12-12 7-2 11-6 12-12z"/></svg>
        <svg class="pc-cta__spark pc-cta__spark--3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0c1 6 5 10 12 12-7 2-11 6-12 12-1-6-5-10-12-12 7-2 11-6 12-12z"/></svg>
        <h2>Prêt à trouver le bon pro ?</h2>
        <p>Rejoignez les Guinéens qui font confiance à Parema Connect pour leurs dépannages, installations et travaux du quotidien.</p>
        <div class="pc-hero__cta pc-hero__cta--center">
            @include('site.partials.btn-telecharger', ['size' => 'pc-btn--lg'])
            @if (($contact['whatsapp'] ?? '') !== '')
                <a href="{{ route('site.contact') }}" class="pc-btn pc-btn--ghost pc-btn--lg">
                    <i class="bi bi-whatsapp"></i> Nous écrire
                </a>
            @endif
        </div>
    </div>
</section>

@endsection
