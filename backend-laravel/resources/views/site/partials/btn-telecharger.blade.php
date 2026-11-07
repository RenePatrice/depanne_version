{{--
    Bouton « Télécharger » intelligent.
    Actif (primary) dès qu'un lien APK ou Play Store est configuré ; sinon grisé
    et non cliquable (« Bientôt disponible »). Pilotage depuis le back-office.

    Variables attendues : $telechargement (du contrôleur, partagé à la vue).
    Options : $size (classe pc-btn--sm|--lg), $libelle, $libelleBientot.
--}}
@php
    $dispo = ($telechargement['apk_disponible'] ?? false) || ($telechargement['playstore_disponible'] ?? false);
@endphp
@if ($dispo)
    <a href="{{ route('site.telecharger') }}" class="pc-btn pc-btn--primary {{ $size ?? '' }}">
        <i class="bi bi-download" aria-hidden="true"></i> {{ $libelle ?? "Télécharger l'application" }}
    </a>
@else
    <span class="pc-btn pc-btn--disabled {{ $size ?? '' }}" role="button" aria-disabled="true" title="Bientôt disponible sur Android">
        <i class="bi bi-hourglass-split" aria-hidden="true"></i> {{ $libelleBientot ?? 'Bientôt disponible' }}
    </span>
@endif
