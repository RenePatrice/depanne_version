<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Catalog\Actions\ToggleService;
use App\Domain\Catalog\Actions\UpsertService;
use App\Domain\Catalog\Actions\UpsertServiceCategory;
use App\Domain\Catalog\Data\Specialty;
use App\Domain\Catalog\Models\Service;
use App\Domain\Catalog\Models\ServiceCategory;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Catalogue et tarifs (§6). Les prix modifiés ici ne s'appliquent qu'aux
 * demandes à venir : chaque ticket porte son propre instantané (ADR-0013).
 */
final class CatalogueController extends Controller
{
    public function index(): View
    {
        return view('catalogue.index', [
            'categories' => ServiceCategory::query()
                ->with(['services' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
                ->orderBy('sort_order')
                ->get(),
            'specialites' => Specialty::cases(),
            // Nombre de tickets par prestation : on ne désactive pas à l'aveugle
            // une prestation qui représente l'essentiel du volume.
            'usages' => DB::table('tickets')
                ->select('service_id', DB::raw('COUNT(*) AS n'))
                ->groupBy('service_id')
                ->pluck('n', 'service_id'),
            'historique' => Activity::query()
                ->where('log_name', 'catalogue')
                ->with('causer')
                ->latest()
                ->limit(15)
                ->get(),
        ]);
    }

    public function enregistrerCategorie(Request $request, UpsertServiceCategory $action): RedirectResponse
    {
        $donnees = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:300'],
            'icon' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ], attributes: ['name' => 'nom de la catégorie', 'color' => 'couleur']);

        try {
            $action->execute($donnees);
        } catch (DomainException $e) {
            return back()->withErrors(['code' => $e->getMessage()])->withInput();
        }

        return redirect()->route('catalogue')->with('statut', 'Catégorie créée.');
    }

    public function modifierCategorie(Request $request, ServiceCategory $categorie, UpsertServiceCategory $action): RedirectResponse
    {
        $donnees = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:300'],
            'icon' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ], attributes: ['name' => 'nom de la catégorie']);

        $action->execute($donnees, $categorie);

        return redirect()->route('catalogue')->with('statut', 'Catégorie mise à jour.');
    }

    public function enregistrerPrestation(Request $request, UpsertService $action): RedirectResponse
    {
        try {
            $action->execute($this->reglesPrestation($request));
        } catch (DomainException $e) {
            return back()->withErrors(['base_price_gnf' => $e->getMessage()])->withInput();
        }

        return redirect()->route('catalogue')->with('statut', 'Prestation créée.');
    }

    public function modifierPrestation(Request $request, Service $prestation, UpsertService $action): RedirectResponse
    {
        $ancienPrix = $prestation->base_price_gnf;

        try {
            $action->execute($this->reglesPrestation($request), $prestation);
        } catch (DomainException $e) {
            return back()->withErrors(['base_price_gnf' => $e->getMessage()])->withInput();
        }

        $message = $prestation->base_price_gnf !== $ancienPrix
            ? 'Prestation mise à jour. Le nouveau prix ne s\'applique qu\'aux demandes à venir.'
            : 'Prestation mise à jour.';

        return redirect()->route('catalogue')->with('statut', $message);
    }

    public function basculerPrestation(Service $prestation, ToggleService $action): RedirectResponse
    {
        $action->execute($prestation);

        return redirect()->route('catalogue')->with(
            'statut',
            $prestation->is_active
                ? 'Prestation réactivée : elle réapparaît dans le catalogue mobile.'
                : 'Prestation désactivée : elle disparaît du catalogue mobile, les tickets passés sont conservés.',
        );
    }

    /** @return array<string, mixed> */
    private function reglesPrestation(Request $request): array
    {
        return $request->validate([
            'category_id' => ['required', 'integer', 'exists:service_categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'included' => ['nullable', 'string', 'max:2000'],
            'excluded' => ['nullable', 'string', 'max:2000'],
            'base_price_gnf' => ['required', 'integer', 'min:1000', 'max:50000000'],
            'estimated_duration_min' => ['required', 'integer', 'min:5', 'max:1440'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ], attributes: [
            'category_id' => 'catégorie',
            'name' => 'nom de la prestation',
            'base_price_gnf' => 'prix de référence',
            'estimated_duration_min' => 'durée estimée',
        ]);
    }
}
