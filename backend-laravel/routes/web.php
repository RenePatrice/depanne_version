<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\CarteLiveController;
use App\Http\Controllers\Web\CatalogueController;
use App\Http\Controllers\Web\ClientController;
use App\Http\Controllers\Web\ConfigurationController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\FinanceController;
use App\Http\Controllers\Web\HealthController;
use App\Http\Controllers\Web\JournalAuditController;
use App\Http\Controllers\Web\LitigeController;
use App\Http\Controllers\Web\RetraitController;
use App\Http\Controllers\Web\SystemeController;
use App\Http\Controllers\Web\TechnicienController;
use App\Http\Controllers\Web\TicketController;
use App\Http\Controllers\Web\ZoneController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes web — back-office Dépanne-Moi
|--------------------------------------------------------------------------
| Le guard `admin` protège tout, sauf la connexion et les deux sondes.
| Chaque module porte la permission qui le garde ; la barre latérale se
| construit à partir de config/backoffice.php.
*/

// Sonde publique, volontairement avare en détails pour un visiteur anonyme.
Route::get('/health', HealthController::class)->name('health');

Route::middleware('guest:admin')->group(function (): void {
    Route::get('/connexion', [LoginController::class, 'show'])->name('connexion');
    Route::post('/connexion', [LoginController::class, 'store']);
});

Route::middleware('auth:admin')->group(function (): void {
    Route::post('/deconnexion', [LoginController::class, 'destroy'])->name('deconnexion');

    // Diagnostic d'environnement : versions, pilotes et fournisseurs branchés.
    Route::get('/systeme', [SystemeController::class, 'etat'])->name('systeme');

    Route::middleware('permission:tableau-de-bord.voir')->group(function (): void {
        Route::get('/', DashboardController::class)->name('tableau-de-bord');

        // Alimente l'ilot React du flux d'activite (et son repli en B6).
        Route::get('/flux-activite', [DashboardController::class, 'activite'])
            ->name('flux-activite');
    });

    /*
     * Modules dont la phase n'est pas encore livrée : ils répondent, annoncent
     * leur contenu et leur phase, et restent protégés par leur permission
     * définitive. Chacun sera remplacé par son contrôleur réel sans changer
     * ni son URL ni son nom de route.
     */
    /*
     * Supervision (phase B3). Les tables tournent en mode serveur : la vue ne
     * porte que la coquille, les donnees arrivent par `donnees`, et les exports
     * sont calcules cote serveur sur la meme requete filtree.
     */
    Route::middleware('permission:tickets.voir')->group(function (): void {
        Route::get('/tickets', [TicketController::class, 'index'])->name('tickets');
        Route::get('/tickets/donnees', [TicketController::class, 'donnees'])->name('tickets.donnees');
        Route::get('/tickets/export', [TicketController::class, 'export'])->name('tickets.export');
        Route::get('/tickets/{ticket}', [TicketController::class, 'detail'])->name('tickets.detail');
    });

    Route::middleware('permission:tickets.agir')->group(function (): void {
        Route::post('/tickets/{ticket}/annuler', [TicketController::class, 'annuler'])->name('tickets.annuler');
        Route::post('/tickets/{ticket}/cloturer', [TicketController::class, 'cloturer'])->name('tickets.cloturer');
    });

    Route::middleware('permission:clients.voir')->group(function (): void {
        Route::get('/clients', [ClientController::class, 'index'])->name('clients');
        Route::get('/clients/donnees', [ClientController::class, 'donnees'])->name('clients.donnees');
        Route::get('/clients/export', [ClientController::class, 'export'])->name('clients.export');
        Route::get('/clients/{client}', [ClientController::class, 'detail'])->name('clients.detail');
    });

    Route::post('/clients/{client}/statut', [ClientController::class, 'changerStatut'])
        ->middleware('permission:clients.suspendre')
        ->name('clients.statut');

    Route::middleware('permission:techniciens.voir')->group(function (): void {
        /*
         * Pièce justificative d'un technicien (§10). L'URL est signée et
         * expire ; la signature s'ajoute au guard et à la permission, elle ne
         * les remplace pas. Le fichier est diffusé par l'application : aucune
         * adresse publique ne pointe vers une carte d'identité.
         */
        Route::get('/documents/{profil}/{piece}', [DocumentController::class, 'show'])
            ->middleware('signed')
            ->name('documents.piece');

        Route::get('/techniciens', [TechnicienController::class, 'index'])->name('techniciens');
        Route::get('/techniciens/donnees', [TechnicienController::class, 'donnees'])->name('techniciens.donnees');
        Route::get('/techniciens/export', [TechnicienController::class, 'export'])->name('techniciens.export');
        Route::get('/techniciens/validation', [TechnicienController::class, 'validation'])->name('techniciens.validation');
        Route::get('/techniciens/validation/dossiers', [TechnicienController::class, 'dossiersEnAttente'])->name('techniciens.dossiers');
        Route::get('/techniciens/{technicien}', [TechnicienController::class, 'detail'])->name('techniciens.detail');
    });

    Route::middleware('permission:techniciens.valider')->group(function (): void {
        Route::post('/techniciens/{technicien}/approuver', [TechnicienController::class, 'approuver'])->name('techniciens.approuver');
        Route::post('/techniciens/{technicien}/rejeter', [TechnicienController::class, 'rejeter'])->name('techniciens.rejeter');
    });

    Route::post('/techniciens/{technicien}/statut', [TechnicienController::class, 'changerStatut'])
        ->middleware('permission:techniciens.sanctionner')
        ->name('techniciens.statut');

    /*
     * Administration (phase B4). Le catalogue et les zones sont modifiables par
     * qui detient la permission `.modifier` ; tout le monde d'autre les consulte.
     */
    Route::middleware('permission:catalogue.voir')->group(function (): void {
        Route::get('/catalogue', [CatalogueController::class, 'index'])->name('catalogue');
    });

    Route::middleware('permission:catalogue.modifier')->group(function (): void {
        Route::post('/catalogue/categories', [CatalogueController::class, 'enregistrerCategorie'])->name('catalogue.categorie.creer');
        Route::post('/catalogue/categories/{categorie}', [CatalogueController::class, 'modifierCategorie'])->name('catalogue.categorie.modifier');
        Route::post('/catalogue/prestations', [CatalogueController::class, 'enregistrerPrestation'])->name('catalogue.prestation.creer');
        Route::post('/catalogue/prestations/{prestation}', [CatalogueController::class, 'modifierPrestation'])->name('catalogue.prestation.modifier');
        Route::post('/catalogue/prestations/{prestation}/basculer', [CatalogueController::class, 'basculerPrestation'])->name('catalogue.prestation.basculer');
    });

    Route::get('/zones', [ZoneController::class, 'index'])
        ->middleware('permission:zones.voir')
        ->name('zones');

    Route::middleware('permission:zones.modifier')->group(function (): void {
        Route::post('/zones', [ZoneController::class, 'enregistrer'])->name('zones.creer');
        Route::post('/zones/{zone}', [ZoneController::class, 'modifier'])->name('zones.modifier');
        Route::post('/zones/{zone}/basculer', [ZoneController::class, 'basculer'])->name('zones.basculer');
    });

    Route::get('/configuration', [ConfigurationController::class, 'index'])
        ->middleware('permission:configuration.voir')
        ->name('configuration');

    Route::post('/configuration', [ConfigurationController::class, 'enregistrer'])
        ->middleware('permission:configuration.modifier')
        ->name('configuration.enregistrer');

    // Journal d'audit : lecture seule, aucune route d'ecriture n'est exposee.
    Route::middleware('permission:journal-audit.voir')->group(function (): void {
        Route::get('/journal-audit', [JournalAuditController::class, 'index'])->name('journal-audit');
        Route::get('/journal-audit/donnees', [JournalAuditController::class, 'donnees'])->name('journal-audit.donnees');
        Route::get('/journal-audit/export', [JournalAuditController::class, 'export'])->name('journal-audit.export');
    });

    /*
     * Operations (phase B5). Les finances et les retraits sont reserves aux
     * roles ADMIN et FINANCE ; les litiges relevent d'ADMIN et de SUPPORT.
     */
    Route::middleware('permission:finances.voir')->group(function (): void {
        Route::get('/finances', [FinanceController::class, 'index'])->name('finances');
    });

    Route::get('/finances/export', [FinanceController::class, 'export'])
        ->middleware('permission:finances.exporter')
        ->name('finances.export');

    Route::middleware('permission:retraits.approuver')->group(function (): void {
        Route::get('/retraits', [RetraitController::class, 'index'])->name('retraits');
        Route::get('/retraits/donnees', [RetraitController::class, 'donnees'])->name('retraits.donnees');
        Route::get('/retraits/export', [RetraitController::class, 'export'])->name('retraits.export');
        Route::get('/retraits/{retrait}', [RetraitController::class, 'detail'])->name('retraits.detail');
        Route::post('/retraits/{retrait}/decider', [RetraitController::class, 'decider'])->name('retraits.decider');
    });

    Route::middleware('permission:litiges.voir')->group(function (): void {
        Route::get('/litiges', [LitigeController::class, 'index'])->name('litiges');
        Route::get('/litiges/{litige}', [LitigeController::class, 'detail'])->name('litiges.detail');
    });

    Route::middleware('permission:litiges.resoudre')->group(function (): void {
        Route::post('/litiges/{litige}/prendre-en-charge', [LitigeController::class, 'prendreEnCharge'])->name('litiges.prendre');
        Route::post('/litiges/{litige}/messages', [LitigeController::class, 'ecrire'])->name('litiges.ecrire');
        Route::post('/litiges/{litige}/resoudre', [LitigeController::class, 'resoudre'])->name('litiges.resoudre');
    });

    /*
     * Supervision live (phase B6). L'ecran recoit un instantane complet puis se
     * met a jour par Reverb ; les deux points d'entree JSON servent aussi de
     * repli quand le WebSocket est indisponible.
     */
    Route::middleware('permission:carte-live.voir')->group(function (): void {
        Route::get('/carte-live', [CarteLiveController::class, 'index'])->name('carte-live');
        Route::get('/carte-live/donnees', [CarteLiveController::class, 'donnees'])->name('carte-live.donnees');
        Route::get('/carte-live/compteurs', [CarteLiveController::class, 'compteursLive'])->name('carte-live.compteurs');
    });
});
