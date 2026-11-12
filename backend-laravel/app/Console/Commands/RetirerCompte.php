<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Accounts\Actions\PurgeAccount;
use App\Domain\Accounts\Models\User;
use App\Support\Telephone;
use Illuminate\Console\Command;

/**
 * Retire définitivement un compte de l'application mobile, pour réutiliser son
 * numéro pendant les tests :
 *
 *   php artisan compte:retirer 620123456
 *   php artisan compte:retirer +224620123456 --force
 *
 * Refusé en production : là-bas, un compte se suspend depuis le back-office et
 * son historique (demandes, paiements, avis) reste lisible.
 */
final class RetirerCompte extends Command
{
    protected $signature = 'compte:retirer
                            {telephone : numéro du compte, sous n\'importe quelle forme}
                            {--force : sans demander de confirmation}';

    protected $description = 'Retire définitivement un compte mobile et ses données (phase de test uniquement)';

    public function handle(PurgeAccount $purge): int
    {
        if ($this->laravel->environment('production')) {
            $this->error('Refusé en production. Suspendez plutôt le compte depuis le back-office.');

            return self::FAILURE;
        }

        $telephone = Telephone::normaliser((string) $this->argument('telephone'));

        if ($telephone === null) {
            $this->error('Ce numéro n\'est pas un numéro guinéen valide.');

            return self::FAILURE;
        }

        // `withTrashed` : un compte mis à la corbeille garde son numéro, il doit
        // pouvoir être retiré lui aussi.
        $user = User::withTrashed()->where('phone', $telephone)->first();

        if ($user === null) {
            $this->info("Aucun compte avec le numéro {$telephone} : il est déjà libre.");

            return self::SUCCESS;
        }

        $casquettes = array_filter([
            $user->is_client ? 'client' : null,
            $user->is_technician ? 'technicien' : null,
        ]);

        $this->line("Compte : <info>{$user->full_name}</info> ({$telephone}) — ".implode(' + ', $casquettes));
        $this->table(['Sera supprimé', 'Nombre'], collect($purge->apercu($user))
            ->map(fn (int $n, string $quoi): array => [$quoi, $n])->values()->all());

        if (! $this->option('force') && ! $this->confirm('Retirer ce compte définitivement ?')) {
            $this->line('Rien n\'a été supprimé.');

            return self::SUCCESS;
        }

        $purge->execute($user);

        $this->info("Compte retiré. Le numéro {$telephone} peut de nouveau s'inscrire.");

        return self::SUCCESS;
    }
}
