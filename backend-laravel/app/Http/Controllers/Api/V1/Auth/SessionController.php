<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Accounts\Actions\AuthenticateUser;
use App\Domain\Accounts\Actions\IssueTokenPair;
use App\Domain\Accounts\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConnexionRequest;
use App\Http\Resources\UtilisateurResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Session mobile : connexion, rafraîchissement, déconnexion (§4, ADR-0003).
 */
final class SessionController extends Controller
{
    /**
     * Se connecter.
     *
     * Numéro de téléphone et mot de passe — pas d'OTP. Cinq tentatives par
     * fenêtre de quinze minutes et par couple numéro + IP.
     */
    public function store(
        ConnexionRequest $request,
        AuthenticateUser $authentifier,
        IssueTokenPair $jetons,
    ): JsonResponse {
        try {
            $utilisateur = $authentifier->execute(
                (string) $request->string('phone'),
                (string) $request->string('password'),
                (string) $request->ip(),
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        $couple = $jetons->delivrer(
            $utilisateur,
            (string) $request->string('device_name', 'mobile'),
            $request->ip(),
        );

        $utilisateur->load(['clientProfile', 'technicianProfile']);

        return response()->json([
            'message' => 'Bonjour '.$utilisateur->firstName().'.',
            'utilisateur' => new UtilisateurResource($utilisateur),
            'jetons' => $couple,
        ]);
    }

    /**
     * Rafraîchir la session.
     *
     * Échange un jeton de rafraîchissement contre un couple neuf. Le jeton
     * présenté est immédiatement révoqué : il ne sert qu'une fois.
     */
    public function refresh(Request $request, IssueTokenPair $jetons): JsonResponse
    {
        $donnees = $request->validate([
            'refresh_token' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:120'],
        ], attributes: ['refresh_token' => 'jeton de rafraîchissement']);

        try {
            $couple = $jetons->rafraichir(
                $donnees['refresh_token'],
                $donnees['device_name'] ?? null,
                $request->ip(),
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        return response()->json(['jetons' => $couple]);
    }

    /**
     * Se déconnecter.
     *
     * Révoque le jeton d'accès courant et, si le jeton de rafraîchissement est
     * transmis, celui-là aussi. Sans lui, toutes les sessions du compte tombent.
     */
    public function destroy(Request $request, IssueTokenPair $jetons): JsonResponse
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        $jetons->revoquer($utilisateur, $request->string('refresh_token')->toString() ?: null);

        return response()->json(['message' => 'Déconnecté.']);
    }
}
