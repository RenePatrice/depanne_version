<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Accounts\Models\AdminUser;
use App\Support\EmpreinteSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde les sessions du back-office contre le vol et la durée illimitée.
 *
 * À chaque requête d'un administrateur connecté :
 *   1. l'empreinte de la requête (navigateur + IP) doit être celle posée à la
 *      connexion : un cookie rejoué ailleurs est refusé ;
 *   2. la session ne doit pas dépasser sa durée maximale absolue, même si elle
 *      sert sans interruption (l'inactivité, elle, est gérée par la durée de
 *      vie de la session).
 *
 * En cas d'échec : déconnexion, destruction de la session côté serveur (le
 * cookie volé ne vaut plus rien, y compris pour son propriétaire légitime) et
 * trace dans le journal `securite`.
 */
final class ProtegerSessionAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $garde = Auth::guard('admin');

        if (! $garde->check()) {
            return $next($request);
        }

        $session = $request->session();
        $empreinte = $session->get(EmpreinteSession::CLE_EMPREINTE);
        $debut = $session->get(EmpreinteSession::CLE_DEBUT);

        // Session authentifiée sans empreinte : ouverte avant ce garde-fou (le
        // cookie de production a été renommé à son déploiement, ces sessions
        // n'existent plus) ou par les tests. On l'adopte.
        if (! is_string($empreinte) || ! is_int($debut)) {
            EmpreinteSession::poser($request);

            return $next($request);
        }

        $motif = match (true) {
            ! hash_equals($empreinte, EmpreinteSession::calculer($request)) => 'cookie de session présenté depuis un autre navigateur ou une autre adresse',
            now()->timestamp - $debut > self::dureeMaxSecondes() => 'durée maximale de session dépassée',
            default => null,
        };

        if ($motif === null) {
            return $next($request);
        }

        $admin = $garde->user();

        activity('securite')
            ->causedBy($admin instanceof AdminUser ? $admin : null)
            ->withProperties([
                'motif' => $motif,
                'ip' => $request->ip(),
                'agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ])
            ->log('Session du back-office refusée');

        $garde->logout();
        $session->invalidate();
        $session->regenerateToken();

        $message = 'Ta session a expiré ou a été utilisée depuis un autre appareil. Reconnecte-toi.';

        return $request->expectsJson()
            ? response()->json(['message' => $message], 401)
            : redirect()->route('connexion')->with('statut', $message);
    }

    private static function dureeMaxSecondes(): int
    {
        return max(1, (int) config('session.duree_max', 480)) * 60;
    }
}
