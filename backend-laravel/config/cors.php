<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CORS — origines autorisées
|--------------------------------------------------------------------------
|
| Laravel n'expose pas ce fichier par défaut et laisse alors passer toutes les
| origines. Ce n'est pas acceptable pour une API qui manipule des positions
| GPS, des numéros de téléphone et de l'argent.
|
| L'application Flutter, elle, n'est pas concernée : un client mobile natif
| n'envoie pas d'en-tête `Origin` et n'est pas soumis à la politique de même
| origine. Cette liste ne sert donc qu'aux navigateurs — la documentation
| OpenAPI, un éventuel outil interne, et rien d'autre.
|
| `supports_credentials` reste à false : l'API mobile s'authentifie par jeton
| Bearer, jamais par cookie. L'activer ouvrirait la porte au CSRF sur des
| routes qui n'en ont aucune protection.
|
*/

return [

    'paths' => ['api/*', 'docs/api*', 'broadcasting/auth'],

    'allowed_methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('APP_URL', 'http://localhost')))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
