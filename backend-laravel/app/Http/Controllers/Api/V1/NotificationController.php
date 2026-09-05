<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Models\AppNotification;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Centre de notifications de l'application mobile (§7.4).
 *
 * Il lit la table plutôt que de dépendre du push : sur le réseau de Conakry,
 * un message perdu est un cas courant, et l'utilisateur doit pouvoir retrouver
 * ce qu'il a manqué. C'est aussi le repli quand le WebSocket ne s'ouvre pas.
 */
final class NotificationController extends Controller
{
    /** Mes notifications, les plus récentes d'abord. */
    public function index(Request $request): JsonResponse
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        $notifications = AppNotification::query()
            ->pour($utilisateur)
            ->when($request->boolean('non_lues'), fn ($q) => $q->nonLues())
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'non_lues' => AppNotification::query()->pour($utilisateur)->nonLues()->count(),
            'notifications' => $notifications->map(static fn (AppNotification $n): array => [
                'id' => $n->id,
                'type' => $n->type,
                'titre' => $n->data['titre'] ?? null,
                'corps' => $n->data['corps'] ?? null,
                'donnees' => collect($n->data)->except(['titre', 'corps', 'urgente'])->all(),
                'lue' => $n->read_at !== null,
                'recue_le' => $n->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * Marquer comme lues.
     *
     * Sans identifiants, tout est marqué : c'est le geste « vider le badge »,
     * et l'application ne devrait pas avoir à énumérer cinquante identifiants
     * pour l'obtenir.
     */
    public function marquerLues(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'ids' => ['nullable', 'array', 'max:100'],
            'ids.*' => ['string', 'uuid'],
        ]);

        /** @var User $utilisateur */
        $utilisateur = $request->user();

        $marquees = AppNotification::query()
            ->pour($utilisateur)
            ->nonLues()
            ->when(isset($valide['ids']), fn ($q) => $q->whereIn('id', $valide['ids']))
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Notifications marquées comme lues.', 'marquees' => $marquees]);
    }
}
