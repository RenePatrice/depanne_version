<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Version publiée de l'application, pour la vérification de mise à jour.
 *
 * Publique : l'écran de démarrage la lit avant toute connexion. Elle ne dit
 * rien d'autre que ce que la vitrine affiche déjà. Le lien passe par la route
 * `/telecharger` de la vitrine, qui compte le clic et signe l'APK (PC-03).
 */
final class ApplicationController extends Controller
{
    public function version(): JsonResponse
    {
        $derniere = config('depanne.mobile.derniere_version');
        $minimale = config('depanne.mobile.version_minimale');

        return response()->json([
            'derniere_version' => is_string($derniere) && $derniere !== '' ? $derniere : null,
            'version_minimale' => is_string($minimale) && $minimale !== '' ? $minimale : null,
            'url_telechargement' => route('site.telecharger'),
        ]);
    }
}
