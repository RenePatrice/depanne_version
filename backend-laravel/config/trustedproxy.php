<?php

declare(strict_types=1);

/*
 * Mandataires de confiance.
 *
 * Derrière un hébergeur qui termine le HTTPS à notre place (Vercel), l'adresse
 * réelle du visiteur et le schéma arrivent dans les en-têtes `X-Forwarded-*`.
 * Sans leur faire confiance, toutes les requêtes sembleraient venir de la même
 * machine : les limites de débit par IP et le verrouillage des connexions
 * frapperaient tout le monde à la fois.
 *
 * Vide par défaut — en local il n'y a pas de mandataire, et faire confiance à
 * ces en-têtes laisserait n'importe qui déclarer l'adresse de son choix.
 * `TRUSTED_PROXIES=*` sur Vercel, ou une liste d'adresses séparées par des
 * virgules.
 */
return [
    'proxies' => env('TRUSTED_PROXIES'),
];
