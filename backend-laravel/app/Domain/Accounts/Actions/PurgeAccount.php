<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Retire définitivement un compte et tout ce qui lui est rattaché.
 *
 * Réservé aux phases de test : c'est le seul moyen de réutiliser un numéro, la
 * règle « un numéro = un compte » étant tenue par la validation ET par l'index
 * unique de la base (un compte simplement désactivé garde son numéro).
 *
 * L'ordre compte. Les demandes, paiements, mouvements de portefeuille, retraits
 * et litiges protègent l'historique (`restrictOnDelete`) : on les retire
 * d'abord, puis le compte. Profils, adresses, jetons de rafraîchissement,
 * messages, avis et tarifs partent avec lui (`cascadeOnDelete`). Une demande
 * où le compte n'était que technicien reste, sans technicien
 * (`nullOnDelete`) : elle appartient à son client.
 */
final class PurgeAccount
{
    /**
     * Ce que la purge emportera, sans rien supprimer — pour confirmation.
     *
     * @return array<string, int>
     */
    public function apercu(User $user): array
    {
        $demandes = DB::table('tickets')->where('client_id', $user->id)->pluck('id');

        return [
            'demandes' => $demandes->count(),
            'paiements' => DB::table('payments')->whereIn('ticket_id', $demandes)->count(),
            'litiges' => $this->litiges($user->id, $demandes->all())->count(),
            'mouvements de portefeuille' => DB::table('transactions')->where('user_id', $user->id)->count(),
            'retraits' => DB::table('withdrawals')->where('technician_id', $user->id)->count(),
            'adresses' => DB::table('addresses')->where('user_id', $user->id)->count(),
        ];
    }

    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $demandes = DB::table('tickets')->where('client_id', $user->id)->pluck('id')->all();

            // Les messages d'un litige partent avec lui (cascade).
            $this->litiges($user->id, $demandes)->delete();
            DB::table('payments')->whereIn('ticket_id', $demandes)->delete();
            DB::table('transactions')->where('user_id', $user->id)->delete();
            DB::table('withdrawals')->where('technician_id', $user->id)->delete();

            // Événements, sollicitations, messages et avis suivent la demande.
            DB::table('tickets')->whereIn('id', $demandes)->delete();

            // Tables polymorphes, sans clé étrangère : à nettoyer à la main.
            DB::table('personal_access_tokens')
                ->where('tokenable_type', $user->getMorphClass())
                ->where('tokenable_id', $user->id)
                ->delete();
            DB::table('notifications')
                ->where('notifiable_type', $user->getMorphClass())
                ->where('notifiable_id', $user->id)
                ->delete();

            // Suppression réelle, pas la corbeille : sinon le numéro resterait pris.
            $user->forceDelete();
        });
    }

    /**
     * Litiges ouverts par le compte, ou portant sur ses demandes.
     *
     * @param  array<int, int|string>  $demandes
     */
    private function litiges(int $userId, array $demandes): \Illuminate\Database\Query\Builder
    {
        return DB::table('disputes')->where(function ($requete) use ($userId, $demandes): void {
            $requete->where('opened_by', $userId)->orWhereIn('ticket_id', $demandes);
        });
    }
}
