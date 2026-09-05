<?php

declare(strict_types=1);

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    /*
     * Which routes to document. String or array form; use Scramble::routes() for custom selection.
     *
     * 'api_path' => [
     *     'include' => 'api',
     *     'exclude' => ['api/internal'],
     * ],
     *
     * Without *, patterns match path segments (api matches api and api/users, not apiary).
     * With *, Str::is is used (e.g. api/v*).
     *
     * One static include → default server is /{include} and paths are stripped (/users).
     * Multiple includes or wildcards → server defaults to / and paths stay full (/api/users).
     * Override with `servers`, or use Scramble::registerApi() for separate bases.
     */
    'api_path' => 'api/v1',

    /*
     * Your API domain. By default, app domain is used. This is also a part of the default API routes
     * matcher, so when implementing your own, make sure you use this config if needed.
     */
    'api_domain' => null,

    /*
     * The path where your OpenAPI specification will be exported.
     */
    'export_path' => 'api.json',

    /*
     * Cache configuration for the generated OpenAPI document.
     *
     * Use `scramble:cache` to warm the cache and `scramble:clear` to invalidate it.
     */
    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    'info' => [
        /*
         * API version.
         */
        'version' => env('API_VERSION', '1.0.0'),

        /*
         * Description rendered on the home page of the API documentation (`/docs/api`).
         */
        'description' => <<<'MD'
        API de l'application mobile Dépanne-Moi — mise en relation entre clients
        et techniciens vérifiés à Conakry.

        ## Authentification

        Toutes les routes protégées attendent un en-tête
        `Authorization: Bearer <access_token>`. L'access token expire en
        **15 minutes** ; le jeton de rafraîchissement, valable 30 jours, permet
        d'en obtenir un nouveau via `POST /api/v1/auth/refresh`.

        Le jeton de rafraîchissement **tourne à chaque usage** : la réponse en
        renvoie toujours un nouveau, et l'ancien cesse immédiatement d'être
        valable. Présenter deux fois le même révoque toutes les sessions du
        compte — c'est le signe qu'une copie circule.

        ## Conventions

        - Les numéros de téléphone sont en E.164 (`+224XXXXXXXXX`) ; l'API
          accepte les formes locales et les normalise.
        - Les montants sont des **entiers de francs guinéens**, sans décimale.
        - Les horodatages sont en ISO 8601, fuseau `Africa/Conakry` (UTC+0).
        - Les messages d'erreur sont en français et destinés à être affichés
          tels quels à l'utilisateur.
        - Chaque réponse porte un en-tête `X-Request-Id`. Renvoyez-le tel quel
          dans vos signalements : il relie la requête à tout ce qu'elle a
          déclenché côté serveur. Vous pouvez aussi le fournir vous-même.

        ## Codes de réponse

        | Code | Ce qu'il veut dire |
        |---|---|
        | `401` | Jeton absent, expiré ou révoqué. Rafraîchir, puis réessayer. |
        | `403` | Casquette insuffisante — un client sur une route technicien. |
        | `404` | La ressource n'existe pas **ou ne vous concerne pas**. L'API ne distingue pas les deux : confirmer l'existence d'un ticket à qui devine sa référence en dirait déjà trop. |
        | `409` | Conflit d'attribution : la demande vient d'être prise par un autre technicien. |
        | `422` | Règle métier ou validation. Le champ `message` est affichable tel quel ; `errors` détaille par champ. |
        | `429` | Débit dépassé. L'en-tête `Retry-After` donne le délai. |

        ## Le prix n'est ferme qu'à l'acceptation

        `POST /devis` et la publication renvoient une **estimation** : les frais
        de déplacement dépendent de la distance entre le client et le technicien,
        et aucun technicien n'est encore assigné. Le devis porte `ferme: false`.

        Le montant définitif est fixé quand un technicien accepte, à partir de sa
        position réelle. Le ticket porte alors `prix.ferme: true`, et le client
        reçoit une notification `TICKET_ACCEPTE` contenant `total_ferme: true`.

        **L'écran mobile doit afficher « à partir de » avant l'acceptation, et
        annoncer le total au moment où il devient ferme.** Sans cela, la première
        facture plus élevée que l'estimation deviendra le premier litige.

        ## Temps réel

        Les canaux privés Reverb complètent l'API :

        - `utilisateur.{id}` — notifications, pastille de message non lu ;
        - `ticket.{id}` — messages du chat, réservé aux deux parties.

        Chaque écran temps réel doit d'abord charger son instantané par l'API,
        puis suivre les messages. Sans WebSocket, une interrogation périodique
        prend le relais — et l'écran doit le dire.

        ## Ce qui est simulé pendant le pilote

        | Fonction | État |
        |---|---|
        | Paiement Mobile Money | **Simulé.** Aucun débit réel. Le champ `instruction` le dit. |
        | Notifications push | **Simulé.** Persistées et diffusées par Reverb, mais pas de FCM. |
        | Appel à numéro masqué | **Simulé.** La réponse porte `relais.simule: true` ; l'afficher franchement plutôt que proposer un appel qui ne partira pas. |
        | Distance routière | **Estimée** sans clé Google : à vol d'oiseau × 1,3. Le ticket porte `distance_estimee`. |
        MD,
    ],

    'ui' => [
        'title' => null,
    ],

    /*
     * Load Scramble's development tools on documentation pages. An explicit
     * SCRAMBLE_DEV_TOOLS value takes precedence over APP_DEBUG.
     */
    'dev_tools' => [
        'enabled' => env('SCRAMBLE_DEV_TOOLS', env('APP_DEBUG', false)),
    ],

    'renderer' => 'elements',

    'renderers' => [
        /*
         * Stoplight Elements config options: https://docs.stoplight.io/docs/elements/b074dc47b2826-elements-configuration-options
         */
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
        /*
         * Scalar API reference config options: https://scalar.com/products/api-references/configuration
         */
        'scalar' => [
            'view' => 'scramble::scalar',
            'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
            'theme' => 'laravel',
            'proxyUrl' => 'https://proxy.scalar.com',
            'darkMode' => false,
            'showDeveloperTools' => 'never',
            'agent' => ['disabled' => true],
            'credentials' => 'include',
        ],
    ],

    /*
     * The list of servers of the API. By default, when `null`, server URL will be created from
     * `scramble.api_path` and `scramble.api_domain` config variables. When providing an array, you
     * will need to specify the local server URL manually (if needed).
     *
     * Example of non-default config (final URLs are generated using Laravel `url` helper):
     *
     * ```php
     * 'servers' => [
     *     'Live' => 'api',
     *     'Prod' => 'https://scramble.dedoc.co/api',
     * ],
     * ```
     */
    'servers' => null,

    /**
     * Determines how Scramble stores the descriptions of enum cases.
     * Available options:
     * - 'description' – Case descriptions are stored as the enum schema's description using table formatting.
     * - 'extension' – Case descriptions are stored in the `x-enumDescriptions` enum schema extension.
     *
     *    @see https://redocly.com/docs-legacy/api-reference-docs/specification-extensions/x-enum-descriptions
     * - false - Case descriptions are ignored.
     */
    'enum_cases_description_strategy' => 'description',

    /**
     * Determines how Scramble stores the names of enum cases.
     * Available options:
     * - 'names' – Case names are stored in the `x-enumNames` enum schema extension.
     * - 'varnames' - Case names are stored in the `x-enum-varnames` enum schema extension.
     * - false - Case names are not stored.
     */
    'enum_cases_names_strategy' => false,

    /**
     * When Scramble encounters deep objects in query parameters, it flattens the parameters so the generated
     * OpenAPI document correctly describes the API. Flattening deep query parameters is relevant until
     * OpenAPI 3.2 is released and query string structure can be described properly.
     *
     * For example, this nested validation rule describes the object with `bar` property:
     * `['foo.bar' => ['required', 'int']]`.
     *
     * When `flatten_deep_query_parameters` is `true`, Scramble will document the parameter like so:
     * `{"name":"foo[bar]", "schema":{"type":"int"}, "required":true}`.
     *
     * When `flatten_deep_query_parameters` is `false`, Scramble will document the parameter like so:
     *  `{"name":"foo", "schema": {"type":"object", "properties":{"bar":{"type": "int"}}, "required": ["bar"]}, "required":true}`.
     */
    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],

    /*
     * Automatically document API security (OpenAPI `security` / `securitySchemes`) based on route
     * middleware.
     *
     * Disabled by default. Uncomment the line below to enable `MiddlewareAuthSecurityStrategy`.
     * When at least one documented route uses middleware matching the configured patterns (by default
     * `auth` and `auth:*`), bearer auth is applied globally. Routes without matching middleware are
     * marked as public (`security: []`).
     *
     * Set to `null` explicitly to disable. If you already configure security manually via
     * `afterOpenApiGenerated` / `extendOpenApi`, keep this disabled to avoid duplicate schemes.
     *
     * Customize with a class-string or [class, options]:
     *
     * 'security_strategy' => [
     *     \Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy::class,
     *     [
     *         'middleware' => ['auth', 'auth:*'],
     *         'scheme' => \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer'),
     *     ],
     * ],
     */
    // 'security_strategy' => \Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy::class,
    'security_strategy' => null,
];
