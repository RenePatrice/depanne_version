<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité du back-office (§10). Volontairement conservateurs :
 * l'interface d'administration n'a aucune raison d'être encadrée, sondée ou
 * référencée depuis l'extérieur.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Permissions-Policy', 'geolocation=(self), camera=(), microphone=()');

        return $response;
    }
}
