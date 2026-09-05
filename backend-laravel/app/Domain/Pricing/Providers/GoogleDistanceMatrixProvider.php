<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Providers;

use App\Domain\Pricing\Contracts\MapProvider;
use App\Domain\Pricing\Data\Distance;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Distance routière réelle via Google Distance Matrix (§8.2).
 *
 * Trois précautions, chacune pour une raison précise :
 *
 * 1. **Le repli est systématique.** Toute réponse anormale — réseau coupé,
 *    quota dépassé, statut ZERO_RESULTS, JSON inattendu — rend la main au
 *    calcul Haversine plutôt qu'une erreur. Un client ne doit jamais voir
 *    « impossible de calculer le prix » parce qu'un tiers a hoqueté.
 *
 * 2. **Le résultat est mis en cache sur des coordonnées arrondies.** Deux
 *    adresses distantes de quelques mètres donnent la même clé : à Conakry,
 *    les demandes se concentrent sur quelques quartiers, et chaque appel est
 *    facturé.
 *
 * 3. **Le délai est court.** Mieux vaut une estimation immédiate qu'un devis
 *    exact au bout de dix secondes ; l'utilisateur attend un prix, pas une
 *    précision au mètre.
 */
final class GoogleDistanceMatrixProvider implements MapProvider
{
    private const URL = 'https://maps.googleapis.com/maps/api/distancematrix/json';

    private const TIMEOUT_SECONDES = 4;

    /** ~11 m de résolution : suffisant pour regrouper les appels d'une même rue. */
    private const PRECISION_CLE = 4;

    public function __construct(
        private readonly HaversineMapProvider $repli,
        private readonly string $cle,
        private readonly int $dureeCacheSecondes,
    ) {}

    public function distance(Point $depart, Point $arrivee): Distance
    {
        $origine = $this->coordonnees($depart);
        $destination = $this->coordonnees($arrivee);

        $km = Cache::remember(
            $this->cleCache($origine, $destination),
            $this->dureeCacheSecondes,
            fn (): ?float => $this->interroger($origine, $destination),
        );

        if ($km === null) {
            return $this->repli->distance($depart, $arrivee);
        }

        return Distance::mesuree($km, 'google');
    }

    /** @return array{float, float} */
    private function coordonnees(Point $point): array
    {
        return [(float) $point->getLatitude(), (float) $point->getLongitude()];
    }

    /**
     * @param  array{float, float}  $origine
     * @param  array{float, float}  $destination
     * @return float|null les kilomètres, ou null si la réponse est inutilisable
     */
    private function interroger(array $origine, array $destination): ?float
    {
        try {
            $reponse = Http::timeout(self::TIMEOUT_SECONDES)
                ->retry(1, 200)
                ->get(self::URL, [
                    'origins' => implode(',', $origine),
                    'destinations' => implode(',', $destination),
                    'mode' => 'driving',
                    'units' => 'metric',
                    'key' => $this->cle,
                ]);

            if ($reponse->failed()) {
                return $this->abandonner('réponse HTTP '.$reponse->status());
            }

            $corps = $reponse->json();

            if (! is_array($corps) || ($corps['status'] ?? null) !== 'OK') {
                return $this->abandonner('statut '.(is_array($corps) ? ($corps['status'] ?? '?') : '?'));
            }

            $element = $corps['rows'][0]['elements'][0] ?? null;

            if (! is_array($element) || ($element['status'] ?? null) !== 'OK') {
                return $this->abandonner('trajet introuvable entre les deux points');
            }

            $metres = $element['distance']['value'] ?? null;

            if (! is_numeric($metres)) {
                return $this->abandonner('distance absente de la réponse');
            }

            return (float) $metres / 1000;
        } catch (Throwable $e) {
            return $this->abandonner($e->getMessage());
        }
    }

    /**
     * Trace l'échec puis rend null. Le repli est silencieux pour l'utilisateur
     * mais jamais pour l'exploitation : une dérive du taux de repli signale un
     * problème de quota ou de clé bien avant qu'un client ne se plaigne.
     */
    private function abandonner(string $raison): null
    {
        Log::warning('Distance Matrix indisponible, repli sur Haversine.', ['raison' => $raison]);

        return null;
    }

    /**
     * @param  array{float, float}  $origine
     * @param  array{float, float}  $destination
     */
    private function cleCache(array $origine, array $destination): string
    {
        return sprintf(
            'distance:%s:%s',
            $this->arrondir($origine),
            $this->arrondir($destination),
        );
    }

    /** @param array{float, float} $point */
    private function arrondir(array $point): string
    {
        return round($point[0], self::PRECISION_CLE).','.round($point[1], self::PRECISION_CLE);
    }
}
