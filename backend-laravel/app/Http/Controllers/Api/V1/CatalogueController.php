<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\Models\ServiceCategory;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Zones\Models\Zone;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategorieResource;
use Illuminate\Http\JsonResponse;

/**
 * Catalogue et données de référence de l'application mobile (§7.2).
 *
 * Ces trois réponses ne dépendent d'aucun utilisateur : elles sont ouvertes,
 * pour que l'écran d'accueil s'affiche avant même la connexion, et mises en
 * cache côté client.
 */
final class CatalogueController extends Controller
{
    /**
     * Catalogue des prestations.
     *
     * Renvoie les catégories actives et, dans chacune, ses prestations
     * actives triées comme en back-office.
     */
    public function index(): JsonResponse
    {
        $categories = ServiceCategory::query()
            ->where('is_active', true)
            ->with(['services' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'categories' => CategorieResource::collection($categories),
        ]);
    }

    /**
     * Zones desservies.
     *
     * Le contour n'est pas renvoyé : l'application n'a pas à dessiner la zone,
     * seulement à annoncer où l'on intervient. C'est aussi quelques dizaines
     * de kilo-octets épargnés sur un réseau mobile guinéen.
     */
    public function zones(): JsonResponse
    {
        $zones = Zone::query()->active()->orderBy('name')->get();

        return response()->json([
            'zones' => $zones->map(static fn (Zone $zone): array => [
                'id' => $zone->id,
                'code' => $zone->code,
                'nom' => $zone->name,
                'commune' => $zone->commune,
                'km_inclus' => $zone->included_km,
            ])->all(),
        ]);
    }

    /**
     * Textes légaux et réglages publics.
     *
     * Servis par l'API plutôt qu'embarqués dans l'application : corriger une
     * CGU ne doit pas demander une publication sur les stores.
     */
    public function reglages(): JsonResponse
    {
        return response()->json([
            'cgu' => AppSetting::get(AppSetting::CGU, ''),
            'politique_confidentialite' => AppSetting::get(AppSetting::POLITIQUE_CONFIDENTIALITE, ''),
            'frais_annulation_gnf' => (int) AppSetting::get(AppSetting::CANCELLATION_FEE_GNF, 0),
            'fenetre_litige_heures' => (int) AppSetting::get(AppSetting::DISPUTE_WINDOW_HOURS, 72),
            'delai_reponse_technicien_s' => (int) AppSetting::get(AppSetting::MATCH_RESPONSE_SECONDS, 45),
        ]);
    }
}
