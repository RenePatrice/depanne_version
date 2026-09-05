<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Models\AppSetting;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Mise à jour des paramètres pilotables depuis le back-office (§6, §8.2, §8.3).
 *
 * Ces valeurs commandent le calcul de prix, le matching et la répartition
 * financière : elles sont validées ici, une par une, selon leur type déclaré.
 * Une saisie fautive ne doit pas se découvrir en production, au moment où un
 * ticket est publié.
 */
final class UpdateSettings
{
    /** Bornes de sécurité, indépendantes de ce que le formulaire propose. */
    private const BORNES = [
        AppSetting::COMMISSION_RATE => [0.0, 0.5],
        AppSetting::MATCH_INITIAL_RADIUS_KM => [1, 50],
        AppSetting::MATCH_RADIUS_STEP_KM => [1, 50],
        AppSetting::MATCH_MAX_RADIUS_KM => [1, 100],
        AppSetting::MATCH_RESPONSE_SECONDS => [10, 600],
        AppSetting::MATCH_MAX_CYCLES => [1, 10],
        AppSetting::MATCH_CANDIDATES_PER_CYCLE => [1, 50],
        AppSetting::NEWCOMER_RATING => [1.0, 5.0],
        AppSetting::NEWCOMER_JOBS_THRESHOLD => [0, 50],
        AppSetting::ESCROW_AUTO_RELEASE_HOURS => [1, 168],
        AppSetting::DISPUTE_WINDOW_HOURS => [1, 720],
        AppSetting::HAVERSINE_ROAD_FACTOR => [1.0, 3.0],
    ];

    /**
     * @param  array<string, mixed>  $valeurs  clé de paramètre => valeur brute du formulaire
     * @return array<int, string> clés effectivement modifiées
     */
    public function execute(array $valeurs, int $adminId): array
    {
        $parametres = AppSetting::query()
            ->whereIn('key', array_keys($valeurs))
            ->get()
            ->keyBy('key');

        $modifiees = [];

        DB::transaction(function () use ($valeurs, $parametres, $adminId, &$modifiees): void {
            foreach ($valeurs as $cle => $brut) {
                $parametre = $parametres->get($cle);

                if ($parametre === null) {
                    throw new DomainException("Paramètre inconnu : {$cle}.");
                }

                $valeur = $this->convertir($cle, (string) $parametre->type, $brut, (string) $parametre->label);
                $ancienne = AppSetting::get($cle);

                if ($ancienne === $valeur) {
                    continue;
                }

                AppSetting::put($cle, $valeur, $adminId);
                $modifiees[] = $cle;

                activity('configuration')
                    ->performedOn($parametre)
                    ->withProperties(['cle' => $cle, 'avant' => $ancienne, 'apres' => $valeur])
                    ->log('Paramètre modifié : '.$parametre->label);
            }
        });

        $this->verifierCoherence();

        return $modifiees;
    }

    private function convertir(string $cle, string $type, mixed $brut, string $libelle): mixed
    {
        $valeur = match ($type) {
            'integer' => (int) $brut,
            'decimal' => (float) str_replace(',', '.', (string) $brut),
            'boolean' => (bool) $brut,
            'json' => is_array($brut) ? $this->ponderations($brut) : $brut,
            default => (string) $brut,
        };

        if (isset(self::BORNES[$cle]) && (is_int($valeur) || is_float($valeur))) {
            [$min, $max] = self::BORNES[$cle];

            if ($valeur < $min || $valeur > $max) {
                throw new DomainException(sprintf(
                    '« %s » doit être compris entre %s et %s.',
                    $libelle,
                    rtrim(rtrim(number_format((float) $min, 2, ',', ' '), '0'), ','),
                    rtrim(rtrim(number_format((float) $max, 2, ',', ' '), '0'), ','),
                ));
            }
        }

        return $valeur;
    }

    /**
     * Pondérations du score de matching. La proximité, la note et le taux
     * d'acceptation tirent vers le haut ; l'annulation pénalise, donc son poids
     * est négatif. Inverser un signe suffirait à récompenser les techniciens qui
     * annulent le plus.
     *
     * @param  array<string, mixed>  $brut
     * @return array<string, float>
     */
    private function ponderations(array $brut): array
    {
        $attendues = ['proximity', 'rating', 'acceptance', 'cancellation'];
        $poids = [];

        foreach ($attendues as $nom) {
            if (! array_key_exists($nom, $brut)) {
                throw new DomainException("Pondération manquante : {$nom}.");
            }

            $poids[$nom] = round((float) str_replace(',', '.', (string) $brut[$nom]), 4);
        }

        foreach (['proximity', 'rating', 'acceptance'] as $nom) {
            if ($poids[$nom] < 0) {
                throw new DomainException("La pondération « {$nom} » doit être positive.");
            }
        }

        if ($poids['cancellation'] > 0) {
            throw new DomainException(
                "La pondération d'annulation doit être négative : c'est une pénalité, pas un bonus."
            );
        }

        $somme = $poids['proximity'] + $poids['rating'] + $poids['acceptance'];

        if (abs($somme - 1.0) > 0.001) {
            throw new DomainException(sprintf(
                'Les trois pondérations positives doivent totaliser 1,00 — elles totalisent %s.',
                number_format($somme, 2, ',', ' '),
            ));
        }

        return $poids;
    }

    /**
     * Cohérences croisées : un rayon initial supérieur au rayon maximum, ou un
     * pas d'élargissement plus grand que la marge disponible, bloquerait le
     * matching sans message d'erreur visible.
     */
    private function verifierCoherence(): void
    {
        $initial = (int) AppSetting::get(AppSetting::MATCH_INITIAL_RADIUS_KM, 5);
        $max = (int) AppSetting::get(AppSetting::MATCH_MAX_RADIUS_KM, 15);

        if ($initial > $max) {
            throw new DomainException(
                'Le rayon initial ne peut pas dépasser le rayon maximum.'
            );
        }
    }
}
