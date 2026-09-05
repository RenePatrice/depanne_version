<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Notifications\Channels\LogSmsProvider;
use App\Domain\Notifications\Contracts\SmsProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Liaison des interfaces de fournisseurs externes à leur implémentation
 * (ADR-0005). Le choix se fait par `config/depanne.php`, donc par variable
 * d'environnement : passer d'un pilote simulé au vrai fournisseur ne demande
 * aucune modification de code.
 */
final class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsProvider::class, function (): SmsProvider {
            return match (config('depanne.sms.provider')) {
                // Les passerelles réelles se brancheront ici ; tant qu'aucune
                // n'est choisie, le code part dans laravel.log.
                default => new LogSmsProvider,
            };
        });
    }
}
