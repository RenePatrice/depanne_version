<?php

declare(strict_types=1);

use App\Domain\Reporting\Services\PredictionService;

/*
|--------------------------------------------------------------------------
| Prédictions locales (#10) — le cœur du modèle, sans base de données
|--------------------------------------------------------------------------
|
| `projeter()` est pur : une série d'entrée, une projection en sortie. On le
| teste ici sans démarrer l'application, comme le veut tests/Unit.
|
*/

function joursSemaine(int $n, int $depart = 0): array
{
    return array_map(static fn (int $i): int => ($depart + $i) % 7, range(0, $n - 1));
}

it('projette exactement le nombre de jours demandé, jamais négatif', function (): void {
    $service = new PredictionService;

    $historique = [2, 4, 3, 5, 1, 0, 6, 2, 4, 3, 5, 1, 0, 6];
    $projection = $service->projeter($historique, joursSemaine(count($historique)), 7);

    expect($projection['valeurs'])->toHaveCount(7)
        ->and($projection['bornes_basses'])->toHaveCount(7)
        ->and($projection['bornes_hautes'])->toHaveCount(7);

    foreach ($projection['valeurs'] as $valeur) {
        expect($valeur)->toBeGreaterThanOrEqual(0);
    }
    foreach ($projection['bornes_basses'] as $basse) {
        expect($basse)->toBeGreaterThanOrEqual(0);
    }
});

it('reconduit une demande parfaitement stable', function (): void {
    $service = new PredictionService;

    // Dix jours à exactement 4 demandes : pas de tendance, pas de saison, pas
    // d'incertitude — la prévision est 4, sans marge.
    $historique = array_fill(0, 10, 4);
    $projection = $service->projeter($historique, joursSemaine(10), 3);

    expect($projection['valeurs'])->toBe([4, 4, 4])
        ->and($projection['bornes_basses'])->toBe([4, 4, 4])
        ->and($projection['bornes_hautes'])->toBe([4, 4, 4]);
});

it('projette plus haut une demande qui monte qu’une demande qui baisse', function (): void {
    $service = new PredictionService;

    // Même multiset de valeurs et mêmes jours de semaine : seule la tendance
    // (pente +1 contre −1) distingue les deux, ce qui isole son effet de la
    // saisonnalité.
    $croissant = range(0, 13);
    $decroissant = array_reverse($croissant);
    $jours = joursSemaine(14);

    $monte = $service->projeter($croissant, $jours, 7);
    $baisse = $service->projeter($decroissant, $jours, 7);

    expect(array_sum($monte['valeurs']))
        ->toBeGreaterThan(array_sum($baisse['valeurs']));
});

it('capte une saisonnalité hebdomadaire', function (): void {
    $service = new PredictionService;

    // Deux semaines où le jour 0 est très chargé (20) et les autres calmes (2).
    $historique = [];
    $jours = [];
    for ($semaine = 0; $semaine < 3; $semaine++) {
        for ($j = 0; $j < 7; $j++) {
            $historique[] = $j === 0 ? 20 : 2;
            $jours[] = $j;
        }
    }

    // Le premier jour prédit retombe sur le jour 0 (chargé), le suivant sur le
    // jour 1 (calme) : le modèle doit prédire bien plus haut le jour chargé.
    $projection = $service->projeter($historique, $jours, 7);

    expect($projection['valeurs'][0])->toBeGreaterThanOrEqual(8)
        ->and($projection['valeurs'][0])
        ->toBeGreaterThan($projection['valeurs'][1] * 3);
});

it('ne casse pas sur une série vide', function (): void {
    $service = new PredictionService;

    expect($service->projeter([], [], 5)['valeurs'])->toBe([0, 0, 0, 0, 0]);
});
