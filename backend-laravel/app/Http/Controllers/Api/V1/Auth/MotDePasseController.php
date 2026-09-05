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
 * l'API ne doit pas permettre de savoir qui utilise Dépanne-Moi.
 */
final class MotDePasseController extends Controller
{
    /**
     * Demander un code.
     *
     * Envoie un code à six chiffres par SMS, valable dix minutes. En
     * développement, le code est écrit dans `laravel.log`.
     */
    public function demander(Request $request, ResetPassword $reinitialiser): JsonResponse
    {
        $donnees = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ], attributes: ['phone' => 'numéro de téléphone']);

        try {
            $reinitialiser->demander($donnees['phone'], (string) $request->ip());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        }

        return response()->json([
            'message' => 'Si un compte existe avec ce numéro, un code vient d\'être envoyé par SMS.',
            'expire_dans' => 600,
        ]);
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
