<?php

declare(strict_types=1);

namespace App\Domain\Matching\Services;

use App\Domain\Matching\Data\Candidat;
use App\Domain\Settings\Models\AppSetting;

/**
 * Score de sollicitation (§8.3, étape 3).
 *
 *     score = 0,40 × proximité + 0,30 × note + 0,20 × acceptation
 *           − 0,10 × annulation
 *
 * Les quatre pondérations viennent des paramètres et doivent totaliser 1,00,
 * la dernière restant négative — `UpdateSettings` refuse toute autre
 * combinaison, car inverser ce signe récompenserait les techniciens qui
 * annulent le plus.
 *
 * Chaque composante est ramenée à [0, 1] avant pondération. Sans cette
 * normalisation, une note sur 5 pèserait cinq fois son poids annoncé face à un
 * taux exprimé en fraction.
 *
 * Le détail est conservé sur la sollicitation : quand un technicien demandera
 * pourquoi il n'a pas eu une course, la réponse sera dans `score_breakdown` et
 * non dans une reconstitution approximative.
 */
final class MatchScorer
{
    /** @var array<string, float>|null */
    private ?array $ponderations = null;

    /**
     * @return array{score: float, detail: array<string, float>}
     */
    public function noter(Candidat $candidat, int $rayonKm): array
    {
        $poids = $this->ponderations();

        $noteNeutre = (float) AppSetting::get(AppSetting::NEWCOMER_RATING, 4.0);
        $seuil = (int) AppSetting::get(AppSetting::NEWCOMER_JOBS_THRESHOLD, 5);

        $composantes = [
            // Proximité : 1 sur place, 0 au bord du rayon. C'est le rayon du
            // cycle en cours qui sert d'échelle, pas une constante : au
            // troisième cycle, être à 6 km n'est plus une mauvaise nouvelle.
            'proximite' => $this->borner(1 - ($candidat->distanceKm / max(1, $rayonKm))),
            'note' => $this->borner($candidat->noteEffective($noteNeutre, $seuil) / 5),
            'acceptation' => $this->borner($candidat->tauxAcceptation),
            'annulation' => $this->borner($candidat->tauxAnnulation),
        ];

        $score = 0.0;
        $detail = [];

        foreach ($composantes as $nom => $valeur) {
            $apport = ($poids[$nom] ?? 0.0) * $valeur;

            // Deux clés par composante : sa valeur normalisée et ce qu'elle a
            // réellement apporté au score. Un technicien qui conteste son
            // classement a besoin des deux — la seconde seule ne dit pas s'il
            // a été pénalisé par sa distance ou par sa note.
            $detail[$nom] = round($valeur, 4);
            $detail[$nom.'_apport'] = round($apport, 4);

            $score += $apport;
        }

        return ['score' => round($score, 4), 'detail' => $detail];
    }

    /**
     * Classe les candidats du meilleur au moins bon.
     *
     * @param  array<int, Candidat>  $candidats
     * @return array<int, array{candidat: Candidat, score: float, detail: array<string, float>}>
     */
    public function classer(array $candidats, int $rayonKm): array
    {
        $notes = array_map(function (Candidat $c) use ($rayonKm): array {
            $note = $this->noter($c, $rayonKm);

            return ['candidat' => $c, 'score' => $note['score'], 'detail' => $note['detail']];
        }, $candidats);

        // À score égal, le plus proche passe devant : c'est le seul
        // départage qui serve à la fois le client et le technicien.
        usort($notes, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score']
                ?: $a['candidat']->distanceKm <=> $b['candidat']->distanceKm;
        });

        return $notes;
    }

    /** @return array<string, float> */
    private function ponderations(): array
    {
        if ($this->ponderations !== null) {
            return $this->ponderations;
        }

        $brut = AppSetting::get(AppSetting::SCORE_WEIGHTS, []);

        $lues = is_array($brut) ? $brut : [];

        return $this->ponderations = [
            'proximite' => (float) ($lues['proximity'] ?? 0.40),
            'note' => (float) ($lues['rating'] ?? 0.30),
            'acceptation' => (float) ($lues['acceptance'] ?? 0.20),
            'annulation' => (float) ($lues['cancellation'] ?? -0.10),
        ];
    }

    private function borner(float $valeur): float
    {
        return max(0.0, min(1.0, $valeur));
    }
}
