{{-- Écran d'aperçu « choisir son technicien » : maquette illustrative de l'app. --}}
<div class="pc-ui">
    <div class="pc-ui__status"><span>9:41</span><span><i class="bi bi-reception-4"></i> <i class="bi bi-wifi"></i> <i class="bi bi-battery-full"></i></span></div>
    <div class="pc-ui__body">
        <div class="pc-ui__top">
            <span class="pc-ui__round"><i class="bi bi-chevron-left"></i></span>
            <div class="pc-ui__title"><strong>Plomberie</strong><span>Ratoma · 3 disponibles</span></div>
            <span class="pc-ui__round"><i class="bi bi-sliders"></i></span>
        </div>
        <div class="pc-ui__chips">
            <span class="is-on">Recommandés</span><span>Prix</span><span>Distance</span>
        </div>
        @foreach ([['M', 'Mamadou D.', '4,9', '1,2 km', '10 min', '85 000', true], ['I', 'Ibrahima S.', '4,7', '2,0 km', '15 min', '90 000', false], ['A', 'Alpha B.', '4,8', '2,6 km', '18 min', '92 000', false]] as [$ini, $nom, $note, $dist, $eta, $prix, $top])
            <div class="pc-ui-tech {{ $top ? 'is-best' : '' }}">
                @if ($top)<span class="pc-ui-tech__tag">Meilleur choix</span>@endif
                <span class="pc-ui-tech__av">{{ $ini }}</span>
                <div class="pc-ui-tech__info">
                    <strong>{{ $nom }} <i class="bi bi-patch-check-fill"></i></strong>
                    <span><i class="bi bi-star-fill"></i> {{ $note }} · {{ $dist }} · {{ $eta }}</span>
                </div>
                <div class="pc-ui-tech__prix">{{ $prix }}<small>GNF</small></div>
            </div>
        @endforeach
        <div class="pc-ui__note">
            <i class="bi bi-shield-check"></i>
            <span><strong>Prix ferme, garantie incluse</strong>Vous ne payez qu'une fois le travail fait.</span>
        </div>
        <div class="pc-ui__cta">Choisir Mamadou <i class="bi bi-arrow-right"></i></div>
    </div>
</div>
