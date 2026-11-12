<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Vide la file d'attente à la demande d'un planificateur externe.
 *
 * Sur Vercel, aucun processus ne tourne en continu : sans cet appel, la fin de
 * la fenêtre de 45 s d'un technicien (`HandleMatchTimeoutJob`) et la libération
 * du séquestre (`ReleaseEscrowJob`) ne s'exécuteraient jamais. Le pg_cron de
 * Supabase appelle cette adresse à intervalle régulier.
 *
 * Trois gardes :
 *   - sans jeton configuré, l'adresse n'existe pas (404) : elle ne peut pas
 *     être atteinte par accident sur un environnement qui a son `queue:work` ;
 *   - le jeton est comparé à temps constant ;
 *   - un verrou empêche deux passes simultanées, si l'appel précédent traîne.
 */
final class TachesController extends Controller
{
    /** Durée maximale d'une passe, sous la limite d'exécution de l'hébergeur. */
    private const DUREE_MAX_S = 40;

    public function __invoke(Request $request): JsonResponse
    {
        $attendu = (string) config('depanne.taches.jeton');

        abort_if($attendu === '', 404);
        abort_unless(hash_equals($attendu, (string) $request->header('X-Taches-Jeton')), 404);

        $verrou = Cache::lock('taches:file', self::DUREE_MAX_S + 10);

        if (! $verrou->get()) {
            return response()->json(['etat' => 'deja_en_cours']);
        }

        try {
            Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-time' => self::DUREE_MAX_S,
                '--tries' => 1,
            ]);
        } finally {
            $verrou->release();
        }

        return response()->json(['etat' => 'ok']);
    }
}
