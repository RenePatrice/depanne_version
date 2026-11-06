<?php

declare(strict_types=1);

namespace App\Domain\Site\Actions;

use App\Domain\Site\Data\TypeEvenement;
use App\Domain\Site\Models\SiteEvenement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Enregistre un événement de fréquentation du site vitrine.
 *
 * La mesure est un **bonus** : elle ne doit jamais faire échouer l'affichage
 * d'une page ni le téléchargement de l'application. Toute anomalie est avalée
 * (au pire on perd une ligne de statistique), jamais propagée à l'appelant —
 * même posture que `PushProvider` côté domaine.
 */
final class EnregistrerEvenement
{
    /** @param array<string, mixed> $meta */
    public function execute(TypeEvenement $type, Request $request, array $meta = []): void
    {
        try {
            SiteEvenement::query()->create([
                'type' => $type->value,
                'chemin' => mb_substr((string) $request->path(), 0, 255),
                'referent' => $this->referent($request),
                'meta' => $meta === [] ? null : $meta,
                'ip_hash' => $this->empreinteIp($request),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Événement vitrine non enregistré : '.$e->getMessage());
        }
    }

    /**
     * Empreinte de l'IP : haché avec la clé de l'application, jamais stockée en
     * clair. Sert à estimer les visiteurs uniques sans pister personne.
     */
    private function empreinteIp(Request $request): ?string
    {
        $ip = $request->ip();

        if ($ip === null || $ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    /** Hôte du référent seulement — on ne conserve pas d'URL complète. */
    private function referent(Request $request): ?string
    {
        $referent = $request->headers->get('referer');

        if ($referent === null) {
            return null;
        }

        return mb_substr((string) (parse_url($referent, PHP_URL_HOST) ?: ''), 0, 255) ?: null;
    }
}
