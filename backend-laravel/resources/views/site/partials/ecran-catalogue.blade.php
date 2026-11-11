{{-- Écran d'aperçu « accueil / catalogue » : les métiers viennent du back-office. --}}
<div class="pc-ui">
    <div class="pc-ui__status"><span>9:41</span><span><i class="bi bi-reception-4"></i> <i class="bi bi-wifi"></i> <i class="bi bi-battery-full"></i></span></div>
    <div class="pc-ui__body">
        <div class="pc-ui__top">
            <div class="pc-ui__title"><span>Bonjour 👋</span><strong>Que faut-il réparer ?</strong></div>
            <span class="pc-ui__round"><i class="bi bi-bell"></i><em></em></span>
        </div>
        <div class="pc-ui__search"><i class="bi bi-search"></i> Fuite, prise, peinture…</div>
        <div class="pc-ui__grid">
            @foreach (array_slice($metiers, 0, 6) as $m)
                <div class="pc-ui-tile" style="--c:{{ $m['couleur'] }}">
                    <span class="pc-ui-tile__ico"><i class="bi {{ $m['icone'] }}"></i></span>
                    <strong>{{ $m['nom'] }}</strong>
                </div>
            @endforeach
        </div>
    </div>
    <div class="pc-ui__tabs">
        <span class="is-on"><i class="bi bi-house-door-fill"></i>Accueil</span>
        <span><i class="bi bi-receipt"></i>Demandes</span>
        <span><i class="bi bi-chat-dots"></i>Messages</span>
        <span><i class="bi bi-person"></i>Profil</span>
    </div>
</div>
