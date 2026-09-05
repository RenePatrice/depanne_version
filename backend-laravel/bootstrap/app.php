<?php

declare(strict_types=1);

use App\Http\Middleware\IdentifiantDeCorrelation;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        // Un visiteur non authentifié sur une route du back-office est renvoyé
        // vers l'écran de connexion, pas vers une route « login » inexistante.
        $middleware->redirectGuestsTo(fn (): string => route('connexion'));
        $middleware->redirectUsersTo(fn (): string => route('tableau-de-bord'));

        // En-têtes de sécurité (§10). Le back-office ne doit jamais être
        // affichable dans une iframe tierce.
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        /*
         * Identifiant de corrélation sur toutes les requêtes (§10). Il relie
         * les traces d'une même intervention à travers la requête HTTP, les
         * jobs différés et le webhook de paiement — et revient au client dans
         * l'en-tête de réponse, pour qu'un problème signalé soit retrouvable.
         */
        $middleware->prepend(IdentifiantDeCorrelation::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * L'API mobile ne renvoie jamais de HTML : un ecran de connexion
         * Laravel dans une reponse JSON serait illisible pour Flutter, et le
         * message d'erreur en francais est ce que l'utilisateur verra.
         */
        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request): bool => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(static function (AuthenticationException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Session expiree. Rafraichis ton jeton ou reconnecte-toi.',
            ], 401);
        });

        // Une regle metier violee est une erreur de saisie du point de vue de
        // l'application : 422, avec le message du domaine tel quel.
        $exceptions->render(static function (DomainException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => $e->getMessage()], 422);
        });
    })->create();
