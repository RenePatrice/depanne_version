<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Zones\Actions\UpsertZone;
use App\Domain\Zones\Models\Zone;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Zones de déploiement et grille tarifaire du déplacement (§6, §8.2).
 *
 * L'emprise est tracée à la souris dans l'îlot React ; elle arrive ici sous
 * forme de sommets, et c'est le domaine qui la valide et la transforme en
 * polygone PostGIS.
 */
final class ZoneController extends Controller
{
    public function index(): View
    {
        $zones = Zone::query()->orderBy('name')->get();

        $tickets = DB::table('tickets')
            ->select('zone_id', DB::raw('COUNT(*) AS n'))
            ->groupBy('zone_id')
            ->pluck('n', 'zone_id');

        return view('zones.index', [
            'zones' => $zones,
            'ticketsParZone' => $tickets,
            'zonesJson' => $zones->map(fn (Zone $zone): array => [
                'id' => $zone->id,
                'code' => $zone->code,
                'nom' => $zone->name,
                'commune' => $zone->commune,
                'active' => $zone->is_active,
                'tarifBase' => $zone->base_travel_fee_gnf,
                'prixKm' => $zone->price_per_km_gnf,
                'kmInclus' => $zone->included_km,
                'tickets' => (int) ($tickets[$zone->id] ?? 0),
                'sommets' => $this->sommets($zone),
            ])->values(),
        ]);
    }

    public function enregistrer(Request $request, UpsertZone $action): RedirectResponse
    {
        $donnees = $this->regles($request);

        try {
            $action->execute($donnees, $donnees['sommets']);
        } catch (DomainException $e) {
            return back()->withErrors(['sommets' => $e->getMessage()])->withInput();
        }

        return redirect()->route('zones')->with('statut', 'Zone créée.');
    }

    public function modifier(Request $request, Zone $zone, UpsertZone $action): RedirectResponse
    {
        $donnees = $this->regles($request);

        try {
            $action->execute($donnees, $donnees['sommets'], $zone);
        } catch (DomainException $e) {
            return back()->withErrors(['sommets' => $e->getMessage()])->withInput();
        }

        return redirect()->route('zones')->with('statut', 'Zone « '.$zone->name.' » mise à jour.');
    }

    public function basculer(Zone $zone, UpsertZone $action): RedirectResponse
    {
        $rattaches = $action->ticketsRattaches($zone);
        $action->basculer($zone);

        $message = $zone->is_active
            ? 'Zone « '.$zone->name.' » réactivée.'
            : 'Zone « '.$zone->name.' » désactivée : aucune nouvelle demande n\'y sera acceptée.'
                .($rattaches > 0 ? ' Ses '.$rattaches.' tickets restent consultables.' : '');

        return redirect()->route('zones')->with('statut', $message);
    }

    /** @return array<string, mixed> */
    private function regles(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'commune' => ['nullable', 'string', 'max:80'],
            'base_travel_fee_gnf' => ['required', 'integer', 'min:0', 'max:10000000'],
            'price_per_km_gnf' => ['required', 'integer', 'min:0', 'max:1000000'],
            'included_km' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'sommets' => ['required', 'array', 'min:3'],
            'sommets.*.lat' => ['required', 'numeric'],
            'sommets.*.lng' => ['required', 'numeric'],
        ], attributes: [
            'name' => 'nom de la zone',
            'base_travel_fee_gnf' => 'tarif de base',
            'price_per_km_gnf' => 'prix au kilomètre',
            'sommets' => 'tracé de la zone',
        ]);
    }

    /**
     * Sommets de l'emprise, dans l'ordre, prêts à être redessinés.
     *
     * @return array<int, array{lat: float, lng: float}>
     */
    private function sommets(Zone $zone): array
    {
        $anneau = $zone->boundary?->getLineStrings()[0] ?? null;

        if ($anneau === null) {
            return [];
        }

        return array_map(
            static fn ($point): array => [
                'lat' => $point->getLatitude(),
                'lng' => $point->getLongitude(),
            ],
            $anneau->getPoints(),
        );
    }
}
