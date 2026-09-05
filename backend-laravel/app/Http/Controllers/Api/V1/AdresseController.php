<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\Actions\ManageAddresses;
use App\Domain\Accounts\Models\Address;
use App\Domain\Accounts\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdresseRequest;
use App\Http\Resources\AdresseResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Carnet d'adresses du client (§7.2).
 *
 * Toute la logique est dans ManageAddresses ; ce contrôleur ne fait que porter
 * la requête HTTP jusqu'à elle et garder l'accès dans les clous.
 */
final class AdresseController extends Controller
{
    public function __construct(
        private readonly ManageAddresses $adresses,
    ) {}

    /**
     * Lister ses adresses.
     *
     * Chaque adresse indique si elle est dans une zone desservie : c'est ce
     * qui permet à l'application de griser une adresse hors couverture avant
     * que le client ne perde du temps à composer sa demande.
     */
    public function index(Request $request): JsonResponse
    {
        $adresses = Address::query()
            ->where('user_id', $request->user()?->getKey())
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get()
            ->map(fn (Address $a): AdresseResource => (new AdresseResource($a))
                ->couverte($this->adresses->estCouverte($a)));

        return response()->json(['adresses' => $adresses]);
    }

    /** Ajouter une adresse. */
    public function store(AdresseRequest $request): JsonResponse
    {
        /** @var User $client */
        $client = $request->user();

        try {
            /** @var array{label: string, formatted_address: string, landmark?: string|null, latitude: float, longitude: float, is_default?: bool} $donnees */
            $donnees = $request->validated();
            $adresse = $this->adresses->creer($client, $donnees);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $couverte = $this->adresses->estCouverte($adresse);

        return response()->json([
            'message' => $couverte
                ? 'Adresse enregistrée.'
                : 'Adresse enregistrée, mais nous n\'intervenons pas encore dans ce secteur.',
            'adresse' => (new AdresseResource($adresse))->couverte($couverte),
        ], 201);
    }

    /** Modifier une adresse. */
    public function update(AdresseRequest $request, Address $adresse): JsonResponse
    {
        $this->refuserSiElleNEstPasAMoi($request, $adresse);

        $adresse = $this->adresses->modifier($adresse, $request->validated());

        return response()->json([
            'message' => 'Adresse modifiée.',
            'adresse' => (new AdresseResource($adresse))->couverte($this->adresses->estCouverte($adresse)),
        ]);
    }

    /** Supprimer une adresse. */
    public function destroy(Request $request, Address $adresse): JsonResponse
    {
        $this->refuserSiElleNEstPasAMoi($request, $adresse);

        try {
            $this->adresses->supprimer($adresse);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Adresse supprimée.']);
    }

    /**
     * Une adresse qui n'appartient pas au demandeur est traitée comme
     * inexistante : répondre « interdit » confirmerait qu'elle existe.
     */
    private function refuserSiElleNEstPasAMoi(Request $request, Address $adresse): void
    {
        if ((int) $adresse->user_id !== (int) $request->user()?->getKey()) {
            abort(404);
        }
    }
}
