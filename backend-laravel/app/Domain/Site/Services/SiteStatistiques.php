<?php

declare(strict_types=1);

namespace App\Domain\Site\Services;

use App\Domain\Reporting\Data\Periode;
use App\Domain\Site\Data\TypeEvenement;
use Illuminate\Support\Facades\DB;

/**
 * Agrégats de fréquentation du site vitrine, lus au tableau de bord (#« vue
 * globale »). Une requête groupée par appel, comme le reste du reporting.
 */
final class SiteStatistiques
{
    /**
     * Résumé d'une période : visites, visiteurs uniques (empreintes d'IP
     * distinctes) et total par type de clic, avec l'évolution des visites par
     * rapport à la période comparable précédente.
     *
     * @return array{
     *     visites: int,
     *     visiteurs: int,
     *     total_clics: int,
     *     evolution: float|null,
     *     clics: array<int, array{cle: string, libelle: string, icone: string, total: int}>
     * }
     */
    public function resume(Periode $periode): array
    {
        $comptes = $this->comptesParType($periode);
        $visites = $comptes[TypeEvenement::VISITE->value] ?? 0;
        $visitesPrecedentes = $this->comptesParType($periode->precedente())[TypeEvenement::VISITE->value] ?? 0;

        $clics = [];
        $totalClics = 0;

        foreach (TypeEvenement::clics() as $type) {
            $total = $comptes[$type->value] ?? 0;
            $totalClics += $total;
            $clics[] = [
                'cle' => $type->value,
                'libelle' => $type->libelle(),
                'icone' => $type->icone(),
                'total' => $total,
            ];
        }

        return [
            'visites' => $visites,
            'visiteurs' => $this->visiteursUniques($periode),
            'total_clics' => $totalClics,
            'evolution' => $this->evolution($visites, $visitesPrecedentes),
            'clics' => $clics,
        ];
    }

    /**
     * Série quotidienne des visites, au format attendu par `data-graphique`.
     *
     * @return array{categories: array<int, string>, series: array<int, array{name: string, data: array<int, int>, color: string}>}
     */
    public function serieParJour(Periode $periode): array
    {
        $lignes = DB::table('site_evenements')
            ->where('type', TypeEvenement::VISITE->value)
            ->whereBetween('created_at', [$periode->debut, $periode->fin])
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM-DD') AS jour")
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('jour')
            ->get()
            ->keyBy('jour');

        $categories = [];
        $data = [];

        for ($jour = $periode->debut; $jour->lessThanOrEqualTo($periode->fin); $jour = $jour->addDay()) {
            $categories[] = $jour->translatedFormat('d M');
            $data[] = (int) ($lignes->get($jour->format('Y-m-d'))->total ?? 0);
        }

        return [
            'categories' => $categories,
            'series' => [
                ['name' => 'Visites', 'data' => $data, 'color' => '#2F6BED'],
            ],
        ];
    }

    /** @return array<string, int> compte par `type` sur la période */
    private function comptesParType(Periode $periode): array
    {
        return DB::table('site_evenements')
            ->whereBetween('created_at', [$periode->debut, $periode->fin])
            ->selectRaw('type, COUNT(*) AS total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->map(static fn ($t): int => (int) $t)
            ->all();
    }

    private function visiteursUniques(Periode $periode): int
    {
        return (int) DB::table('site_evenements')
            ->whereBetween('created_at', [$periode->debut, $periode->fin])
            ->whereNotNull('ip_hash')
            ->distinct()
            ->count('ip_hash');
    }

    private function evolution(int $courant, int $precedent): ?float
    {
        if ($precedent === 0) {
            return null;
        }

        return round((($courant - $precedent) / $precedent) * 100, 1);
    }
}
