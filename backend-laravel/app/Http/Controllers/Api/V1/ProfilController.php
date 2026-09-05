<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\Actions\RegisterUser;
use App\Domain\Accounts\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\UtilisateurResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Compte de l'utilisateur connecté (§7.1, §7.2).
 */
final class ProfilController extends Controller
{
    /** Mon compte, avec les profils correspondant à mes casquettes. */
    public function show(Request $request): JsonResponse
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();
        $utilisateur->load(['clientProfile', 'technicianProfile']);

        return response()->json(['utilisateur' => new UtilisateurResource($utilisateur)]);
    }

    /** Modifier mes informations. Le téléphone n'est pas modifiable ici : c'est l'identifiant de connexion. */
    public function update(Request $request): JsonResponse
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        $donnees = $request->validate([
            'full_name' => ['sometimes', 'string', 'min:3', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190', 'unique:users,email,'.$utilisateur->id],
            'avatar_url' => ['sometimes', 'nullable', 'string', 'max:500'],
        ], attributes: ['full_name' => 'nom complet', 'email' => 'adresse e-mail']);

        $utilisateur->fill($donnees)->save();
        $utilisateur->load(['clientProfile', 'technicianProfile']);

        return response()->json([
            'message' => 'Informations mises à jour.',
            'utilisateur' => new UtilisateurResource($utilisateur),
        ]);
    }

    /**
     * Changer mon mot de passe.
     *
     * L'ancien mot de passe est exigé : sans lui, un téléphone déverrouillé
     * suffirait à verrouiller définitivement le compte de son propriétaire.
     */
    public function motDePasse(Request $request): JsonResponse
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        $donnees = $request->validate([
            'ancien_mot_de_passe' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed', 'regex:/[A-Z]/', 'regex:/\d/'],
        ], [
            'password.regex' => 'Le mot de passe doit contenir au moins une majuscule et un chiffre.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
        ], attributes: ['ancien_mot_de_passe' => 'mot de passe actuel', 'password' => 'nouveau mot de passe']);

        if (! Hash::check($donnees['ancien_mot_de_passe'], $utilisateur->password)) {
            return response()->json(['message' => 'Mot de passe actuel incorrect.'], 422);
        }

        $utilisateur->forceFill(['password' => $donnees['password']])->save();

        // Les autres appareils sont déconnectés ; celui-ci garde sa session.
        // L'API n'est jointe qu'au jeton porteur : `currentAccessToken()` est
        // toujours un jeton persisté ici, jamais un jeton de session.
        $utilisateur->tokens()->whereKeyNot($utilisateur->currentAccessToken()->getKey())->delete();
        $utilisateur->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);

        return response()->json([
            'message' => 'Mot de passe modifié. Tes autres appareils ont été déconnectés.',
        ]);
    }

    /**
     * Activer ma seconde casquette.
     *
     * Un client devient technicien avec un dossier à compléter ; un technicien
     * devient client immédiatement — rien à vérifier pour commander un dépannage.
     */
    public function secondeCasquette(Request $request, RegisterUser $inscrire): JsonResponse
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();
        $devientTechnicien = ! $utilisateur->is_technician;

        try {
            $utilisateur = $inscrire->activerSecondeCasquette($utilisateur);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $utilisateur->load(['clientProfile', 'technicianProfile']);

        return response()->json([
            'message' => $devientTechnicien
                ? 'Casquette technicien activée. Complète ton dossier pour être vérifié.'
                : 'Casquette client activée.',
            'utilisateur' => new UtilisateurResource($utilisateur),
            'etape_suivante' => $devientTechnicien ? 'dossier_technicien' : 'accueil',
        ]);
    }
}
