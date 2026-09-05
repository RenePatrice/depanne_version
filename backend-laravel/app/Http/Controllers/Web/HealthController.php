<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sonde de santé (§10). Vérifie les dépendances dont l'application ne peut pas
 * se passer : la base, l'extension PostGIS et le magasin de cache — qui porte
 * aussi les verrous du matching.
 */
final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(static fn (): string => (string) DB::selectOne('select version() as v')->v),
            'postgis' => $this->check(static fn (): string => (string) DB::selectOne('select postgis_version() as v')->v),
            'cache' => $this->check(static function (): string {
                Cache::put('health:ping', 'pong', 10);

                return Cache::get('health:ping') === 'pong' ? 'ok' : 'valeur inattendue';
            }),
            'verrou' => $this->check(static function (): string {
                // Le matching séquentiel repose sur Cache::lock() (ADR-0008) :
                // on vérifie que le magasin courant sait poser un verrou atomique.
                $lock = Cache::lock('health:lock', 5);

                if (! $lock->get()) {
                    return 'verrou déjà pris';
                }

                $lock->release();

                return 'ok';
            }),
        ];

        $enBonneSante = collect($checks)->every(static fn (array $c): bool => $c['ok']);

        // La sonde est publique : elle doit servir aux outils de supervision
        // sans annoncer au premier venu la version de PostgreSQL ni le détail
        // d'une panne. Le détail n'apparaît que pour un administrateur connecté.
        $detaille = Auth::guard('admin')->check();

        return response()->json([
            'statut' => $enBonneSante ? 'ok' : 'degrade',
            'application' => config('app.name'),
            'horodatage' => now()->toIso8601String(),
            'verifications' => $detaille
                ? $checks
                : array_map(static fn (array $c): bool => $c['ok'], $checks),
        ], $enBonneSante ? 200 : 503);
    }

    /**
     * @param  callable(): string  $sonde
     * @return array{ok: bool, detail: string}
     */
    private function check(callable $sonde): array
    {
        try {
            return ['ok' => true, 'detail' => $sonde()];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }
}
