<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\InscriptionController;
use App\Http\Controllers\Api\V1\Auth\MotDePasseController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\ProfilController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API mobile — version 1
|--------------------------------------------------------------------------
|
| Consommée par l'application Flutter. Elle appelle les **mêmes actions de
| domaine** que le back-office : c'est tout l'intérêt du backend unique
| (ADR-0001).
|
| Les routes publiques sont volontairement peu nombreuses et toutes limitées en
| débit : ce sont les seules qu'un inconnu peut atteindre.
|
*/

Route::prefix('v1')->group(function (): void {

    // --- Ouvert ------------------------------------------------------------

    Route::post('/auth/inscription', [InscriptionController::class, 'store'])
        ->middleware('throttle:inscription')
        ->name('api.inscription');

    Route::post('/auth/connexion', [SessionController::class, 'store'])
        ->middleware('throttle:connexion-mobile')
        ->name('api.connexion');

    Route::post('/auth/refresh', [SessionController::class, 'refresh'])
        ->middleware('throttle:refresh')
        ->name('api.refresh');

    Route::post('/auth/mot-de-passe-oublie', [MotDePasseController::class, 'demander'])
        ->middleware('throttle:mot-de-passe')
        ->name('api.mot-de-passe.demander');

    Route::post('/auth/mot-de-passe-reinitialiser', [MotDePasseController::class, 'reinitialiser'])
        ->middleware('throttle:mot-de-passe')
        ->name('api.mot-de-passe.reinitialiser');

    // --- Authentifié -------------------------------------------------------

    Route::middleware('auth:sanctum')->group(function (): void {

        Route::post('/auth/deconnexion', [SessionController::class, 'destroy'])->name('api.deconnexion');

        Route::post('/auth/dossier-technicien', [InscriptionController::class, 'dossierTechnicien'])
            ->name('api.dossier-technicien');

        Route::get('/moi', [ProfilController::class, 'show'])->name('api.moi');
        Route::patch('/moi', [ProfilController::class, 'update'])->name('api.moi.modifier');
        Route::post('/moi/mot-de-passe', [ProfilController::class, 'motDePasse'])->name('api.moi.mot-de-passe');
        Route::post('/moi/seconde-casquette', [ProfilController::class, 'secondeCasquette'])
            ->name('api.moi.seconde-casquette');

        /*
         * Sonde d'API : permet à l'application Flutter de vérifier que son
         * jeton est encore valide sans charger de données.
         */
        Route::get('/ping', fn (Request $request): array => [
            'ok' => true,
            'utilisateur_id' => $request->user()?->getKey(),
            'horodatage' => now()->toIso8601String(),
        ])->name('api.ping');
    });
});
