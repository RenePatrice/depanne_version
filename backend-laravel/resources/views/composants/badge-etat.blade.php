{{-- Badge d'état de ticket : couleur + pastille, jamais la couleur seule. --}}
<span class="dm-badge dm-badge--{{ $etat->color() }}">{{ $etat->label() }}</span>
