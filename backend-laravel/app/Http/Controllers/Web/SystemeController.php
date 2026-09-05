<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Page d'état de l'environnement (phase A0). Elle sera remplacée par le
 * tableau de bord du back-office en phase B2.
 */
final class SystemeController extends Controller
{
    public function etat(): View
    {
        return view('systeme.etat', [
            'composants' => [
                ['nom' => 'PHP', 'valeur' => PHP_VERSION, 'attendu' => '8.4+'],
                ['nom' => 'Laravel', 'valeur' => app()->version(), 'attendu' => '13.x'],
                ['nom' => 'PostgreSQL', 'valeur' => $this->versionPostgres(), 'attendu' => '15+'],
                ['nom' => 'PostGIS', 'valeur' => $this->versionPostgis(), 'attendu' => '3.x'],
            ],
            'reglages' => [
                ['nom' => 'Fuseau horaire', 'valeur' => config('app.timezone')],
                ['nom' => 'Langue', 'valeur' => config('app.locale')],
                ['nom' => 'Hachage des mots de passe', 'valeur' => config('hashing.driver')],
                ['nom' => 'Connexion base', 'valeur' => config('database.default')],
                ['nom' => 'File d\'attente', 'valeur' => config('queue.default')],
                ['nom' => 'Cache et verrous', 'valeur' => config('cache.default')],
                ['nom' => 'Diffusion temps réel', 'valeur' => config('broadcasting.default')],
            ],
            'fournisseurs' => [
                ['nom' => 'Cartographie', 'valeur' => config('depanne.map.provider'), 'reel' => config('depanne.map.provider') === 'google'],
                ['nom' => 'Paiement Mobile Money', 'valeur' => config('depanne.payment.provider'), 'reel' => config('depanne.payment.provider') !== 'mock'],
                ['nom' => 'Notifications push', 'valeur' => config('depanne.push.provider'), 'reel' => config('depanne.push.provider') === 'fcm'],
                ['nom' => 'SMS (mot de passe oublié)', 'valeur' => config('depanne.sms.provider'), 'reel' => config('depanne.sms.provider') !== 'log'],
            ],
        ]);
    }

    private function versionPostgres(): string
    {
        try {
            return (string) DB::selectOne("select current_setting('server_version') as v")->v;
        } catch (Throwable) {
            return 'indisponible';
        }
    }

    private function versionPostgis(): string
    {
        try {
            return (string) DB::selectOne('select postgis_version() as v')->v;
        } catch (Throwable) {
            return 'indisponible';
        }
    }
}
