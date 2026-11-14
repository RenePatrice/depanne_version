<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité des routes web (§10). Volontairement conservateurs : le
 * back-office n'a aucune raison d'être encadré, sondé ou référencé depuis
 * l'extérieur, et le site vitrine n'a besoin que d'une poignée d'origines.
 *
 * Deux défenses majeures sont posées ici :
 *   - une Content-Security-Policy stricte (PC-01), avec un nonce par requête
 *     pour les rares scripts en ligne (le poseur de thème avant premier rendu) ;
 *   - le retrait de l'en-tête `X-Powered-By` (PC-02), qui divulguait la pile.
 */
final class SecurityHeaders
{
    /** Nonce CSP de la requête courante, lu par les gabarits Blade. */
    private static ?string $nonce = null;

    public function handle(Request $request, Closure $next): Response
    {
        // Le nonce est tiré AVANT le rendu de la vue : les scripts en ligne le
        // réclament au moment où Blade les écrit. Un nonce par requête rend
        // inexploitable tout script injecté qui ne le connaît pas.
        self::$nonce = base64_encode(random_bytes(18));

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Permissions-Policy', 'geolocation=(self), camera=(), microphone=()');

        // On ne divulgue ni la version de PHP ni le framework : une empreinte en
        // moins pour qui cherche une CVE connue (PC-02). `header_remove` agit au
        // niveau du SAPI, où `expose_php` ajoute l'en-tête ; le retrait du sac
        // Symfony couvre le cas où le runtime l'y recopie.
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        // Le site vitrine est public et doit être référencé ; le back-office,
        // jamais. Seules les routes `site.*` autorisent l'indexation.
        if ($request->routeIs('site.*')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->headers->set('X-Robots-Tag', 'index, follow');
        } else {
            $response->headers->set('Referrer-Policy', 'same-origin');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        // La CSP et HSTS ne sont posées qu'en production : en local, le serveur
        // de développement Vite injecte des scripts en ligne et se sert en HTTP,
        // que ces règles casseraient sans rien protéger de réel.
        if (app()->environment('production')) {
            $response->headers->set('Content-Security-Policy', $this->politiqueContenu());
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload',
            );
        }

        return $response;
    }

    /** Nonce CSP de la requête, pour les scripts en ligne des gabarits. */
    public static function nonce(): string
    {
        return self::$nonce ??= base64_encode(random_bytes(18));
    }

    /**
     * Politique de contenu. Les scripts applicatifs sont groupés par Vite et
     * servis depuis l'origine ('self') ; les seuls scripts en ligne (poseurs de
     * thème) portent le nonce. Les polices viennent de Google Fonts, les images
     * (tuiles OpenStreetMap, photos d'équipe Supabase) sont passives — un `img`
     * n'exécute rien, d'où un `img-src` large mais borné au HTTPS.
     */
    private function politiqueContenu(): string
    {
        $nonce = self::nonce();

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            // Bootstrap et les îlots posent des attributs `style` en ligne ;
            // `unsafe-inline` ne concerne que le style, jamais l'exécution.
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            'font-src \'self\' https://fonts.gstatic.com',
            "img-src 'self' data: blob: https:",
            "connect-src 'self'",
            'upgrade-insecure-requests',
        ]);
    }
}
