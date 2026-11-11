@extends('layouts.site')

@section('titre', $metier['nom'].' à Conakry — Parema Connect')
@section('description', $metier['description'] !== '' ? $metier['description'].' Techniciens vérifiés, prix ferme, garantie incluse.' : 'Techniciens vérifiés en '.$metier['nom'].' à Conakry : prix ferme, paiement sécurisé, garantie incluse.')

@php
    $nbPrestations = count($metier['prestations']);
    $prixMin = $nbPrestations > 0 ? min(array_column($metier['prestations'], 'prix_gnf')) : null;
    $gnf = static fn (int $montant): string => number_format($montant, 0, ',', ' ').' GNF';
    $duree = static function (int $minutes): string {
        if ($minutes < 60) {
            return $minutes.' min';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m === 0 ? $h.' h' : $h.' h '.str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    };
@endphp

@section('contenu')

{{-- ======================= EN-TÊTE DU MÉTIER ======================= --}}
<section class="pc-page-hero" style="--pc-m: {{ $metier['couleur'] }}">
    <div class="pc-hero__dots" aria-hidden="true"></div>
    <div class="pc-container pc-page-hero__inner">
        <div data-reveal>
            <nav class="pc-fil" aria-label="Fil d'Ariane">
                <a href="{{ route('site.accueil') }}">Accueil</a>
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                <a href="{{ route('site.accueil') }}#metiers">Métiers</a>
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                <span aria-current="page">{{ $metier['nom'] }}</span>
            </nav>

            <h1 class="pc-page-hero__title">{{ $metier['nom'] }}, <em>sans mauvaise surprise</em></h1>
            @if ($metier['description'] !== '')
                <p class="pc-hero__lede">{{ $metier['description'] }}</p>
            @endif

            <div class="pc-hero__cta">
                @include('site.partials.btn-telecharger', ['size' => 'pc-btn--lg'])
                <a href="#prestations" class="pc-btn pc-btn--ghost pc-btn--lg">
                    <i class="bi bi-list-check" aria-hidden="true"></i> Voir les prestations
                </a>
            </div>
        </div>

        <div class="pc-page-hero__visual" data-reveal data-reveal-delay="1" aria-hidden="true">
            <span class="pc-page-hero__halo"></span>
            <span class="pc-page-hero__icone"><i class="bi {{ $metier['icone'] }}"></i></span>
        </div>
    </div>

    {{-- Chiffres réels du métier, lus dans le catalogue et les dossiers validés. --}}
    <div class="pc-container">
        <dl class="pc-faits" data-reveal>
            <div>
                <dt>Prestations au catalogue</dt>
                <dd>{{ $nbPrestations }}</dd>
            </div>
            <div>
                <dt>Techniciens vérifiés</dt>
                <dd>{{ $metier['techniciens'] }}</dd>
            </div>
            <div>
                <dt>Prix indicatif à partir de</dt>
                <dd>{{ $prixMin !== null ? $gnf($prixMin) : '—' }}</dd>
            </div>
            <div>
                <dt>Garantie</dt>
                <dd>Incluse</dd>
            </div>
        </dl>
    </div>
</section>

{{-- ======================= PRESTATIONS ======================= --}}
<section class="pc-section pc-section--soft" id="prestations">
    <div class="pc-container">
        <div class="pc-section__head" data-reveal>
            <span class="pc-eyebrow">Ce que nous prenons en charge</span>
            <h2>Les prestations en <em>{{ mb_strtolower($metier['nom']) }}</em></h2>
            <p>Les prix affichés sont indicatifs : chaque technicien propose son tarif, et vous voyez le prix ferme — déplacement compris — avant de confirmer.</p>
        </div>

        @if ($nbPrestations === 0)
            <p class="pc-muted">Les prestations de ce métier seront bientôt publiées.</p>
        @else
            <div class="pc-prestas">
                @foreach ($metier['prestations'] as $i => $p)
                    <article class="pc-presta" data-reveal data-reveal-delay="{{ $i % 3 }}" style="--pc-m: {{ $metier['couleur'] }}">
                        <header class="pc-presta__head">
                            <span class="pc-presta__type">{{ $p['type'] }}</span>
                            <span class="pc-presta__duree"><i class="bi bi-clock" aria-hidden="true"></i> env. {{ $duree($p['duree_min']) }}</span>
                        </header>
                        <h3>{{ $p['nom'] }}</h3>
                        @if ($p['description'] !== '')
                            <p>{{ $p['description'] }}</p>
                        @endif
                        @if ($p['inclus'] !== [])
                            <ul class="pc-presta__inclus">
                                @foreach ($p['inclus'] as $ligne)
                                    <li>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                        {{ $ligne }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <footer class="pc-presta__prix">
                            <span>Prix indicatif</span>
                            <strong>{{ $gnf($p['prix_gnf']) }}</strong>
                        </footer>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- ======================= DÉROULÉ ======================= --}}
<section class="pc-section">
    <div class="pc-container">
        <div class="pc-section__head" data-reveal>
            <span class="pc-eyebrow">Comment ça se passe</span>
            <h2>De la demande à la <em>garantie</em></h2>
        </div>

        <div class="pc-steps">
            @php
                $svgDeroule = [
                    '<rect x="4" y="3" width="16" height="18" rx="2.2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
                    '<circle cx="9" cy="8.5" r="3"/><circle cx="17" cy="10.5" r="2.2"/><path d="M3.5 19.5c0-3 2.4-5 5.5-5s5.5 2 5.5 5"/><path d="M15.5 19.5c.2-2 1.4-3.3 3.3-3.3"/>',
                    '<path d="M12 3l7 3v5c0 4.6-3.1 7.8-7 9-3.9-1.2-7-4.4-7-9V6z"/><path d="M8.5 12l2.4 2.4L15.5 10"/>',
                ];
            @endphp
            @foreach ([
                ['Décrivez votre besoin', 'Choisissez la prestation dans l\'application, indiquez votre adresse et, pour un dépannage, ajoutez jusqu\'à trois photos.'],
                ['Choisissez votre technicien', 'Vous voyez les professionnels disponibles près de chez vous, avec leur prix ferme, leur note et leur distance.'],
                ['Payez, puis restez couvert', 'Espèces ou Mobile Money. Si la panne revient dans le délai de garantie, le même technicien repasse sans nouveau paiement.'],
            ] as $i => [$titre, $texte])
                <div class="pc-step" data-reveal data-reveal-delay="{{ $i }}">
                    <span class="pc-step__num">{{ sprintf('%02d', $i + 1) }}</span>
                    <span class="pc-step__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $svgDeroule[$i] !!}</svg>
                    </span>
                    <h3>{{ $titre }}</h3>
                    <p>{{ $texte }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ======================= AUTRES MÉTIERS ======================= --}}
@php $autres = array_values(array_filter($metiers, static fn (array $m): bool => $m['code'] !== $metier['code'])); @endphp
@if ($autres !== [])
<section class="pc-section pc-section--soft">
    <div class="pc-container">
        <div class="pc-section__head" data-reveal>
            <span class="pc-eyebrow">Un autre besoin ?</span>
            <h2>Découvrez aussi <em>nos autres métiers</em></h2>
        </div>
        <div class="pc-metiers">
            @foreach ($autres as $i => $m)
                <a href="{{ route('site.metier', ['code' => $m['code']]) }}" class="pc-metier" data-reveal data-reveal-delay="{{ $i % 3 }}" style="--pc-m:{{ $m['couleur'] }}">
                    <span class="pc-metier__icon"><i class="bi {{ $m['icone'] }}" aria-hidden="true"></i></span>
                    <span class="pc-metier__body">
                        <strong>{{ $m['nom'] }}</strong>
                        <span class="pc-metier__desc">Voir les prestations</span>
                    </span>
                    <svg class="pc-metier__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ======================= APPEL FINAL ======================= --}}
<section class="pc-cta">
    <div class="pc-container pc-cta__inner" data-reveal>
        <h2>Un besoin en <em>{{ mb_strtolower($metier['nom']) }}</em> ?</h2>
        <p>Trouvez un technicien vérifié près de chez vous, ou posez-nous votre question : nous vous répondons rapidement.</p>
        <div class="pc-hero__cta pc-hero__cta--center">
            @include('site.partials.btn-telecharger', ['size' => 'pc-btn--lg'])
            <a href="{{ route('site.contact') }}" class="pc-btn pc-btn--ghost pc-btn--lg">
                <i class="bi bi-chat-dots" aria-hidden="true"></i> Nous contacter
            </a>
        </div>
    </div>
</section>

@endsection
