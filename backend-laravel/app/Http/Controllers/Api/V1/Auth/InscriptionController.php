<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Accounts\Actions\IssueTokenPair;
use App\Domain\Accounts\Actions\RegisterUser;
use App\Domain\Accounts\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DossierTechnicienRequest;
use App\Http\Requests\Api\InscriptionRequest;
use App\Http\Resources\ProfilTechnicienResource;
use App\Http\Resources\UtilisateurResource;
use DomainException;
use Illuminate\Http\JsonResponse;

/**
 * Inscription en trois étapes (§4).
 *
 * Les étapes 1 et 2 créent le compte et renvoient immédiatement un couple de
 * jetons : le nouvel inscrit est connecté, il n'a pas à ressaisir ce qu'il
 * vient de taper. L'étape 3 se fait donc authentifié.
 */
final class InscriptionController extends Controller
{
    /**
     * Créer un compte.
     *
     * Étapes 1 et 2 du parcours : la casquette choisie et les informations
     * personnelles. Un technicien devra ensuite compléter son dossier.
     */
    public function store(
        InscriptionRequest $request,
        RegisterUser $inscrire,
        IssueTokenPair $jetons,
    ): JsonResponse {
        try {
            $utilisateur = $inscrire->execute($request->validated());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $couple = $jetons->delivrer(
            $utilisateur,
            (string) $request->string('device_name', 'mobile'),
            $request->ip(),
        );

        $utilisateur->load(['clientProfile', 'technicianProfile']);

        return response()->json([
            'message' => $utilisateur->is_technician
                ? 'Compte créé. Complète ton dossier pour être vérifié.'
                : 'Compte créé. Bienvenue sur Dépanne-Moi.',
            'utilisateur' => new UtilisateurResource($utilisateur),
            'jetons' => $couple,
            'etape_suivante' => $utilisateur->is_technician ? 'dossier_technicien' : 'accueil',
        ], 201);
    }

    /**
     * Compléter le dossier professionnel.
     *
     * Étape 3, réservée aux techniciens : spécialités, zone d'intervention et
     * pièces justificatives. Le compte reste en attente de validation tant que
     * le back-office n'a pas tranché.
     */
    public function dossierTechnicien(
        DossierTechnicienRequest $request,
        RegisterUser $inscrire,
    ): JsonResponse {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        try {
            $profil = $inscrire->completerDossierTechnicien($utilisateur, $request->validated());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Dossier envoyé. Tu recevras une notification dès qu\'il sera examiné.',
            'profil_technicien' => new ProfilTechnicienResource($profil),
        ]);
    }
}
