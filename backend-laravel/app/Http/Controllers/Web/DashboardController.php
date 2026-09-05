<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Reporting\Data\Periode;
use App\Domain\Reporting\Services\DashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tableau de bord (§6). Le contrôleur choisit la période et présente ; toutes
 * les agrégations vivent dans DashboardService.
 */
final class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(Request $request): View
    {
        $periode = Periode::depuisCle($request->string('periode')->toString());

        return view('tableau-de-bord.index', [
            'periode' => $periode,
            'choixPeriodes' => Periode::choix(),
            'indicateurs' => $this->dashboard->indicateurs($periode),
            'volume' => $this->dashboard->volumeParJour($periode),
            'categories' => $this->dashboard->repartitionParCategorie($periode),
            'revenus' => $this->dashboard->revenus($periode),
            'heatmap' => $this->dashboard->heatmapHeureJour($periode),
            'tauxAcceptation' => $this->dashboard->tauxAcceptation($periode),
            'activite' => $this->dashboard->activiteRecente(),
        ]);
    }

    /**
     * Flux d'activité consommé par l'îlot React. En phase B6, Reverb poussera
     * les mêmes événements et ce point d'entrée ne servira plus qu'au premier
     * chargement et au repli si le WebSocket est indisponible.
     */
    public function activite(): JsonResponse
    {
        return response()->json([
            'evenements' => $this->dashboard->activiteRecente(15),
            'horodatage' => now()->toIso8601String(),
        ]);
    }
}
