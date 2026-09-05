<?php

declare(strict_types=1);

namespace App\Domain\Chat\Providers;

use App\Domain\Accounts\Models\User;
use App\Domain\Chat\Contracts\MaskedCallProvider;
use Illuminate\Support\Facades\Log;

/**
 * Mise en relation simulée, pour le pilote.
 *
 * Elle renvoie un numéro relais **fictif** et le dit — `simule => true`.
 * L'application doit afficher cette mention plutôt que de proposer un appel
 * qui ne partirait pas : un bouton qui ne marche pas coûte plus cher en
 * confiance qu'une fonction annoncée comme à venir.
 *
 * La demande est journalisée : le volume d'appels souhaités pendant le pilote
 * dira si la fonction mérite un vrai fournisseur, et lequel.
 */
final class MockMaskedCallProvider implements MaskedCallProvider
{
    /** @return array{numero: string, expire_le: string, simule: bool} */
    public function ouvrir(User $appelant, User $appele, string $referenceTicket): array
    {
        Log::info('[APPEL MASQUÉ] Mise en relation demandée.', [
            'appelant' => $appelant->getKey(),
            'appele' => $appele->getKey(),
            'ticket' => $referenceTicket,
        ]);

        return [
            'numero' => '+224600000000',
            'expire_le' => now()->addMinutes(30)->toIso8601String(),
            'simule' => true,
        ];
    }
}
