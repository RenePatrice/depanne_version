<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Empreinte d'une session du back-office : ce qui la lie à l'appareil qui l'a
 * ouverte.
 *
 * Sans elle, un cookie de session copié (poste partagé, extension malveillante,
 * capture réseau) fonctionnait tel quel dans un autre navigateur, sur une autre
 * machine : c'est le vol de session. L'empreinte combine le navigateur et
 * l'adresse IP, signés avec la clé de l'application ; elle est posée à la
 * connexion et revérifiée à chaque requête (ProtegerSessionAdmin).
 *
 * L'empreinte n'est jamais lisible par le client : elle vit dans la session,
 * côté serveur, et seul son condensé y figure.
 */
final class EmpreinteSession
{
    public const CLE_EMPREINTE = 'securite.empreinte';

    public const CLE_DEBUT = 'securite.connecte_le';

    public static function calculer(Request $request): string
    {
        $parties = [(string) $request->userAgent()];

        // Lier l'adresse IP rend un cookie volé inutilisable depuis un autre
        // réseau. Désactivable (SESSION_LIER_IP=false) si les administrateurs
        // changent d'adresse en cours de journée (réseau mobile instable).
        if ((bool) config('session.lier_ip', true)) {
            $parties[] = (string) $request->ip();
        }

        return hash_hmac('sha256', implode('|', $parties), (string) config('app.key'));
    }

    /** Pose l'empreinte et l'heure d'ouverture, juste après la connexion. */
    public static function poser(Request $request): void
    {
        $request->session()->put(self::CLE_EMPREINTE, self::calculer($request));
        $request->session()->put(self::CLE_DEBUT, now()->timestamp);
    }
}
