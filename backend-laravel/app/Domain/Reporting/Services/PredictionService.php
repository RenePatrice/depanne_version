<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Prédictions locales (#10), sans aucune API externe.
 *
 * Le cahier parle d'« IA » ; en pilote, sans infrastructure d'apprentissage ni
 * appel à un service tiers, la réponse honnête est un **modèle statistique
 * calculé sur place** : une tendance linéaire (moindres carrés) croisée avec
 * une saisonnalité par jour de semaine. C'est léger, déterministe, explicable —
 * et c'est exactement ce qui se démontre sur les données du pilote sans
 * promettre une magie qu'on ne tiendrait pas.
 *
 * Tout est public et testable sans réseau : la partie calcul (`projeter`) ne
 * touche pas la base, la partie lecture l'alimente.
 */
final class PredictionService
{
    /**
     * Prévision du volume de demandes pour les prochains jours.
     *
     * Renvoyé **prêt pour le graphe** `ligne` du tableau de bord (categories +
     * series), comme le fait DashboardService : le contrôleur présente, il ne
     * recompose pas.
     *
     * @return array{
     *     categories: array<int, string>,
     *     series: array<int, array{name: string, data: array<int, int|null>, color: string, dashStyle?: string, type?: string}>,
     *     fiabilite: string,
     *     methode: string,
     * }
     */
    public function previsionDemande(int $joursHistorique = 28, int $joursPrevus = 7): array
    {
        $joursHistorique = max(7, min(90, $joursHistorique));
        $joursPrevus = max(1, min(30, $joursPrevus));

        $fin = CarbonImmutable::now()->endOfDay();
        $debut = $fin->subDays($joursHistorique - 1)->startOfDay();

        $parJour = DB::table('tickets')
            ->whereBetween('created_at', [$debut, $fin])
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM-DD') AS jour, COUNT(*) AS total")
            ->groupBy('jour')
            ->pluck('total', 'jour');

        /** @var array<int, int> $historique */
        $historique = [];
        /** @var array<int, int> $joursSemaine 0 = lundi */
        $joursSemaine = [];
        $categories = [];

        for ($jour = $debut; $jour->lessThanOrEqualTo($fin); $jour = $jour->addDay()) {
            $historique[] = (int) ($parJour[$jour->format('Y-m-d')] ?? 0);
            $joursSemaine[] = (int) $jour->dayOfWeekIso - 1;
            $categories[] = $jour->translatedFormat('d M');
        }

        $projection = $this->projeter($historique, $joursSemaine, $joursPrevus);

        // Les dates futures prolongent l'axe ; l'historique n'a pas de prévision
        // et la prévision n'a pas d'historique, pour que les deux courbes ne se
        // chevauchent pas sur le même point.
        $historiqueAligne = array_merge($historique, array_fill(0, $joursPrevus, null));
        $previsionAlignee = array_fill(0, count($historique), null);

        $jourFutur = $fin;
        foreach ($projection['valeurs'] as $valeur) {
            $jourFutur = $jourFutur->addDay();
            $categories[] = $jourFutur->translatedFormat('d M');
            $previsionAlignee[] = $valeur;
        }

        // Le dernier point connu sert d'ancre à la prévision, sinon la courbe
        // prédite flotterait, détachée de l'historique.
        $dernierConnu = (int) end($historique);
        $previsionAlignee[count($historique) - 1] = $dernierConnu;

        return [
            'categories' => $categories,
            'series' => [
                ['name' => 'Demandes (réel)', 'data' => $historiqueAligne, 'color' => '#2F6BED'],
                [
                    'name' => 'Prévision',
                    'data' => $previsionAlignee,
                    'color' => '#F59E0B',
                    'dashStyle' => 'ShortDash',
                    'type' => 'spline',
                ],
            ],
            'fiabilite' => $this->fiabilite($historique),
            'methode' => 'Tendance linéaire et saisonnalité hebdomadaire, calculées localement.',
        ];
    }

    /**
     * Affluence prévue à un instant donné (#10, volet mobile).
     *
     * Dit au technicien si le moment est porteur, en comparant le rythme
     * historique de ce créneau (même jour de semaine, heure ± 1 h) au rythme
     * moyen. C'est la même idée que la prévision, ramenée à « maintenant,
     * est-ce que ça vaut le coup d'être en ligne ? ».
     *
     * @return array{niveau: string, libelle: string, message: string, fiabilite: string}
     */
    public function affluence(?CarbonImmutable $instant = null): array
    {
        $instant ??= CarbonImmutable::now();
        $debut = $instant->subDays(28)->startOfDay();

        $lignes = DB::table('tickets')
            ->where('created_at', '>=', $debut)
            ->selectRaw('EXTRACT(ISODOW FROM created_at)::int AS dow, EXTRACT(HOUR FROM created_at)::int AS heure, COUNT(*) AS total')
            ->groupBy('dow', 'heure')
            ->get();

        $total = (int) $lignes->sum('total');

        if ($total < 14) {
            // Trop peu d'historique pour trancher : on le dit plutôt que de
            // bluffer un niveau.
            return [
                'niveau' => 'inconnue',
                'libelle' => 'Affluence inconnue',
                'message' => 'Pas encore assez d\'historique pour estimer l\'affluence. Reste en ligne pour capter les demandes.',
                'fiabilite' => 'faible',
            ];
        }

        $dow = (int) $instant->dayOfWeekIso;
        $heure = (int) $instant->format('G');
        $heures = [($heure + 23) % 24, $heure, ($heure + 1) % 24];

        $creneau = 0;
        foreach ($lignes as $ligne) {
            if ((int) $ligne->dow === $dow && in_array((int) $ligne->heure, $heures, true)) {
                $creneau += (int) $ligne->total;
            }
        }

        // Référence : rythme moyen sur une cellule (jour × heure), multiplié par
        // les trois heures de la fenêtre et les ~4 occurrences du jour en 28 j.
        $moyenneCellule = $total / (7 * 24);
        $attendu = $moyenneCellule * 3 * 4;
        $ratio = $attendu > 0 ? $creneau / $attendu : 0.0;

        $niveau = self::niveauAffluence($ratio);

        return [
            'niveau' => $niveau,
            'libelle' => self::libelleAffluence($niveau),
            'message' => self::messageAffluence($niveau),
            'fiabilite' => $total >= 60 ? 'bonne' : 'moyenne',
        ];
    }

    /** Seuils du niveau d'affluence, isolés pour être testés sans base. */
    public static function niveauAffluence(float $ratio): string
    {
        return match (true) {
            $ratio >= 1.3 => 'forte',
            $ratio >= 0.6 => 'moyenne',
            default => 'faible',
        };
    }

    private static function libelleAffluence(string $niveau): string
    {
        return match ($niveau) {
            'forte' => 'Affluence forte',
            'moyenne' => 'Affluence moyenne',
            'faible' => 'Affluence faible',
            default => 'Affluence inconnue',
        };
    }

    private static function messageAffluence(string $niveau): string
    {
        return match ($niveau) {
            'forte' => 'C\'est un bon moment : la demande est plus forte que d\'habitude à cette heure. Reste en ligne.',
            'moyenne' => 'Demande habituelle pour ce créneau.',
            'faible' => 'Créneau plutôt calme d\'ordinaire. Les demandes peuvent se faire rares.',
            default => 'Affluence inconnue pour le moment.',
        };
    }

    /**
     * Le cœur du modèle, **sans base de données** : à partir d'une série
     * quotidienne et du jour de semaine de chaque point, projette les
     * `$joursPrevus` suivants.
     *
     * tendance : régression linéaire (moindres carrés) sur l'index du jour.
     * saisonnalité : rapport moyenne du jour de semaine / moyenne générale.
     * prévision(t) = max(0, tendance(t) × facteur_jour(t)).
     *
     * @param  array<int, int>  $historique
     * @param  array<int, int>  $joursSemaine  jour de semaine (0..6) de chaque point
     * @return array{valeurs: array<int, int>, bornes_basses: array<int, int>, bornes_hautes: array<int, int>}
     */
    public function projeter(array $historique, array $joursSemaine, int $joursPrevus): array
    {
        $n = count($historique);

        if ($n === 0) {
            $zero = array_fill(0, $joursPrevus, 0);

            return ['valeurs' => $zero, 'bornes_basses' => $zero, 'bornes_hautes' => $zero];
        }

        $moyenne = array_sum($historique) / $n;

        [$pente, $ordonnee] = $this->regressionLineaire($historique);
        $facteurs = $this->facteursSaisonniers($historique, $joursSemaine, $moyenne);

        // Erreur type résiduelle : sert à encadrer la prévision plutôt qu'à
        // donner un chiffre faussement précis.
        $ecart = $this->ecartType($historique, $moyenne);

        $valeurs = [];
        $basses = [];
        $hautes = [];

        for ($k = 0; $k < $joursPrevus; $k++) {
            $index = $n + $k;
            $tendance = $ordonnee + $pente * $index;

            // Le jour de semaine suit celui du dernier point connu.
            $jourSemaine = ($joursSemaine[$n - 1] + 1 + $k) % 7;
            $facteur = $facteurs[$jourSemaine] ?? 1.0;

            $valeur = max(0.0, $tendance * $facteur);

            $valeurs[] = (int) round($valeur);
            $basses[] = (int) round(max(0.0, $valeur - $ecart));
            $hautes[] = (int) round($valeur + $ecart);
        }

        return ['valeurs' => $valeurs, 'bornes_basses' => $basses, 'bornes_hautes' => $hautes];
    }

    /**
     * Régression linéaire par les moindres carrés : y = ordonnee + pente·x.
     *
     * @param  array<int, int>  $y
     * @return array{0: float, 1: float} [pente, ordonnée]
     */
    private function regressionLineaire(array $y): array
    {
        $n = count($y);

        if ($n < 2) {
            return [0.0, $n === 1 ? (float) $y[0] : 0.0];
        }

        $sommeX = 0.0;
        $sommeY = 0.0;
        $sommeXY = 0.0;
        $sommeXX = 0.0;

        foreach ($y as $x => $valeur) {
            $sommeX += $x;
            $sommeY += $valeur;
            $sommeXY += $x * $valeur;
            $sommeXX += $x * $x;
        }

        $denominateur = $n * $sommeXX - $sommeX * $sommeX;

        if ($denominateur === 0.0) {
            return [0.0, $sommeY / $n];
        }

        $pente = ($n * $sommeXY - $sommeX * $sommeY) / $denominateur;
        $ordonnee = ($sommeY - $pente * $sommeX) / $n;

        return [$pente, $ordonnee];
    }

    /**
     * Facteur saisonnier par jour de semaine : moyenne du jour / moyenne
     * générale. Un jour sans donnée reste neutre (1,0).
     *
     * @param  array<int, int>  $historique
     * @param  array<int, int>  $joursSemaine
     * @return array<int, float>
     */
    private function facteursSaisonniers(array $historique, array $joursSemaine, float $moyenne): array
    {
        if ($moyenne <= 0.0) {
            return [];
        }

        /** @var array<int, array<int, int>> $parJour */
        $parJour = [];
        foreach ($historique as $i => $valeur) {
            $parJour[$joursSemaine[$i]][] = $valeur;
        }

        $facteurs = [];
        foreach ($parJour as $jour => $valeurs) {
            $facteurs[$jour] = (array_sum($valeurs) / count($valeurs)) / $moyenne;
        }

        return $facteurs;
    }

    /**
     * @param  array<int, int>  $historique
     */
    private function ecartType(array $historique, float $moyenne): float
    {
        $n = count($historique);

        if ($n < 2) {
            return 0.0;
        }

        $variance = 0.0;
        foreach ($historique as $valeur) {
            $variance += ($valeur - $moyenne) ** 2;
        }

        return sqrt($variance / $n);
    }

    /**
     * Une prévision n'a de valeur que si on sait à quel point s'y fier. La
     * fiabilité tient à la quantité de données et à leur régularité.
     *
     * @param  array<int, int>  $historique
     */
    private function fiabilite(array $historique): string
    {
        $joursAvecDonnee = count(array_filter($historique, static fn (int $v): bool => $v > 0));

        if ($joursAvecDonnee < 7) {
            return 'faible';
        }

        $moyenne = array_sum($historique) / count($historique);

        if ($moyenne <= 0.0) {
            return 'faible';
        }

        // Coefficient de variation : plus la demande est régulière, plus la
        // prévision est fiable.
        $cv = $this->ecartType($historique, $moyenne) / $moyenne;

        return match (true) {
            $cv < 0.5 => 'bonne',
            $cv < 1.0 => 'moyenne',
            default => 'faible',
        };
    }
}
