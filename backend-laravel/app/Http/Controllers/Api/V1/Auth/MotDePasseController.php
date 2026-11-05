<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Accounts\Actions\ResetPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReinitialisationRequest;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mot de passe oublié (§4).
 *
 * La demande répond toujours la même chose, que le numéro soit inscrit ou non :
 * l'API ne doit pas permettre de savoir qui utilise Parema Connect.
 */
final class MotDePasseController extends Controller
{
    /**
     * Demander un code.
     *
     * Envoie un code à six chiffres par **WhatsApp** (#8), valable dix minutes.
     * En pilote, le code est écrit dans `laravel.log` ; en mode test explicite
     * (`SMS_EXPOSE_TEST_CODE=true`, hors production), il est aussi renvoyé pour
     * tester sur l'appareil sans passerelle.
     */
    public function demander(Request $request, ResetPassword $reinitialiser): JsonResponse
    {
        $donnees = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ], attributes: ['phone' => 'numéro de téléphone']);

        try {
            $code = $reinitialiser->demander($donnees['phone'], (string) $request->ip());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        }

        $canal = config('depanne.sms.provider') === 'whatsapp' ? 'whatsapp' : 'sms';
        $support = (string) config('depanne.whatsapp.support_number');

        $reponse = [
            'message' => $canal === 'whatsapp'
                ? 'Si un compte existe avec ce numéro, un code vient d\'être envoyé par WhatsApp.'
                : 'Si un compte existe avec ce numéro, un code vient d\'être envoyé par SMS.',
            'canal' => $canal,
            'expire_dans' => 600,
            'wa_support_url' => $support === '' ? null : 'https://wa.me/'.$support,
        ];

        // Mode test SEULEMENT : le code est renvoyé pour pouvoir le saisir sur
        // l'appareil sans passerelle. Trois gardes cumulées, pour qu'un
        // environnement de test joignable depuis Internet ne fuite rien :
        //   1. jamais en production ;
        //   2. flag explicite, à false par défaut ;
        //   3. requête venue d'un réseau local/privé (émulateur, LAN) — jamais
        //      d'une IP publique. Sans cela, un inconnu pouvait lire le code
        //      d'un numéro cible et prendre le compte.
        if ($code !== null
            && ! app()->isProduction()
            && config('depanne.sms.expose_test_code') === true
            && $this->depuisReseauLocal($request)
        ) {
            $reponse['code_test'] = $code;
        }

        return response()->json($reponse);
    }

    /**
     * La requête vient-elle d'un réseau local/privé (loopback, RFC 1918…) ?
     *
     * Une IP **publique** fait échouer la validation avec les deux drapeaux
     * d'exclusion : on la refuse. Laravel résout `ip()` depuis la socket tant
     * qu'aucun proxy de confiance n'est déclaré, donc elle n'est pas
     * falsifiable par un simple en-tête `X-Forwarded-For`.
     */
    private function depuisReseauLocal(Request $request): bool
    {
        $ip = (string) $request->ip();

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }

    /**
     * Définir un nouveau mot de passe.
     *
     * Le code reçu par SMS est vérifié, puis toutes les sessions du compte sont
     * révoquées : si le compte était compromis, l'intrus est éjecté.
     */
    public function reinitialiser(ReinitialisationRequest $request, ResetPassword $reinitialiser): JsonResponse
    {
        try {
            $reinitialiser->reinitialiser(
                (string) $request->string('phone'),
                (string) $request->string('code'),
                (string) $request->string('password'),
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Mot de passe modifié. Connecte-toi avec ton nouveau mot de passe.',
        ]);
    }
}
