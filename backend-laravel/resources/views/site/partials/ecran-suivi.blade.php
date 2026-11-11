{{-- Écran d'aperçu « suivi en direct » : carte stylisée + feuille du technicien. --}}
<div class="pc-ui pc-ui--map">
    <svg class="pc-ui__map" viewBox="0 0 260 520" preserveAspectRatio="xMidYMid slice" fill="none" aria-hidden="true">
        <rect width="260" height="520" fill="#eef2f9"/>
        <path d="M-20 330 Q120 296 280 344 L280 520 L-20 520 Z" fill="#dbe8fb"/>
        <rect x="150" y="60" width="86" height="64" rx="16" fill="#dff3e6"/>
        <g stroke="#ffffff" stroke-width="13" stroke-linecap="round">
            <path d="M-10 140 L270 172"/><path d="M70 -10 L104 380"/><path d="M-10 270 L270 252"/><path d="M190 -10 L206 330"/>
        </g>
        <g fill="#ffffff" opacity="0.75">
            <rect x="16" y="52" width="40" height="64" rx="10"/><rect x="118" y="196" width="60" height="42" rx="10"/><rect x="16" y="184" width="42" height="56" rx="10"/>
        </g>
        <path class="pc-ui__route" d="M80 104 C 136 150, 96 232, 166 292" stroke="#2f6bed" stroke-width="5" stroke-linecap="round"/>
        <g transform="translate(166 292)"><circle r="16" fill="#f2854c" opacity="0.18"/><circle r="9" fill="#fff" stroke="#f2854c" stroke-width="3.5"/></g>
        <g transform="translate(80 104)">
            <circle class="pc-scr__movdot" r="17" fill="#2f6bed" opacity="0.2"/>
            <circle r="10" fill="#2f6bed" stroke="#fff" stroke-width="3"/>
        </g>
    </svg>
    <div class="pc-ui__status"><span>9:41</span><span><i class="bi bi-reception-4"></i> <i class="bi bi-wifi"></i> <i class="bi bi-battery-full"></i></span></div>
    <div class="pc-ui__eta"><i class="bi bi-geo-alt-fill"></i> Arrivée dans <strong>4 min</strong></div>
    <div class="pc-ui__sheet">
        <span class="pc-ui__handle"></span>
        <div class="pc-ui-tech pc-ui-tech--plain">
            <span class="pc-ui-tech__av">M</span>
            <div class="pc-ui-tech__info">
                <strong>Mamadou D. <i class="bi bi-patch-check-fill"></i></strong>
                <span><i class="bi bi-star-fill"></i> 4,9 · Plombier</span>
            </div>
            <span class="pc-ui__act pc-ui__act--call"><i class="bi bi-telephone-fill"></i></span>
            <span class="pc-ui__act"><i class="bi bi-chat-fill"></i></span>
        </div>
        <div class="pc-ui__steps">
            <span class="is-done">Accepté</span><span class="is-on">En route</span><span>Sur place</span>
        </div>
    </div>
</div>
