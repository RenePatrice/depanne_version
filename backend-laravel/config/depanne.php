<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Fournisseurs externes de Dépanne-Moi
|--------------------------------------------------------------------------
|
| Chaque fournisseur est derrière une interface (ADR-0005) et se choisit par
| variable d'environnement. Les implémentations simulées permettent de
| développer et de tester le parcours complet sans aucun compte tiers.
|
| C'est le seul endroit du code où `env()` doit être appelé : ailleurs, la
| valeur disparaîtrait dès que la configuration est mise en cache.
|
*/

return [

    // --- Cartographie --------------------------------------------------------
    // google    : Distance Matrix + Directions (facturé)
    // haversine : distance à vol d'oiseau × 1,3, repli prévu au §8.2
    'map' => [
        'provider' => env('MAP_PROVIDER', 'haversine'),
        'google_server_key' => env('GOOGLE_MAPS_SERVER_KEY'),
        'google_js_key' => env('GOOGLE_MAPS_JS_KEY'),
        'distance_cache_ttl' => (int) env('MAP_DISTANCE_CACHE_TTL', 604800),
    ],

    // --- Paiement Mobile Money ----------------------------------------------
    // Orange Money est le fournisseur du pilote ; les suivants s'ajoutent
    // derrière la même interface, sans toucher au domaine.
    'payment' => [
        'provider' => env('PAYMENT_PROVIDER', 'mock'),
        'currency' => env('PAYMENT_CURRENCY', 'GNF'),

        'orange_money' => [
            'base_url' => env('ORANGE_MONEY_BASE_URL'),
            'client_id' => env('ORANGE_MONEY_CLIENT_ID'),
            'client_secret' => env('ORANGE_MONEY_CLIENT_SECRET'),
            'merchant_key' => env('ORANGE_MONEY_MERCHANT_KEY'),
            'webhook_secret' => env('ORANGE_MONEY_WEBHOOK_SECRET'),
        ],

        'mtn_momo' => [
            'base_url' => env('MTN_MOMO_BASE_URL'),
            'subscription_key' => env('MTN_MOMO_SUBSCRIPTION_KEY'),
            'api_user' => env('MTN_MOMO_API_USER'),
            'api_key' => env('MTN_MOMO_API_KEY'),
        ],
    ],

    // --- Notifications push --------------------------------------------------
    // log : écrit dans laravel.log, enregistre en base et diffuse via Reverb.
    //       C'est ce qui remplace Firebase tant qu'aucun projet FCM n'existe.
    'push' => [
        'provider' => env('PUSH_PROVIDER', 'log'),
        'fcm_project_id' => env('FCM_PROJECT_ID'),
        'fcm_credentials_path' => env('FCM_CREDENTIALS_PATH'),
    ],

    // --- SMS (uniquement le code de réinitialisation de mot de passe) --------
    'sms' => [
        'provider' => env('SMS_PROVIDER', 'log'),
        'sender_id' => env('SMS_SENDER_ID', 'DepanneMoi'),
        'api_url' => env('SMS_API_URL'),
        'api_key' => env('SMS_API_KEY'),
    ],

    // --- Appel à numéro masqué ----------------------------------------------
    'masked_call' => [
        'provider' => env('MASKED_CALL_PROVIDER', 'mock'),
    ],

];
