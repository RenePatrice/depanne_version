<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Les modèles vivent dans app/Domain/<Contexte>/Models/ : la convention
        // de nommage de Laravel ne sait pas retrouver leurs factories toute
        // seule. Toutes les factories restent dans Database\Factories.
        Factory::guessFactoryNamesUsing(
            static fn (string $modelName): string => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // §10 — les requêtes N+1 sont interdites : en dehors de la production,
        // toute relation chargée paresseusement lève une exception, ce qui les
        // fait remonter en développement et en test plutôt qu'en charge.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Les dates sont manipulées en immuable : un `->addDay()` ne modifie
        // jamais l'instance d'origine, ce qui évite des bugs silencieux dans
        // les calculs de délais (fenêtre de 45 s, séquestre de 24 h, litige 72 h).
        Date::use(CarbonImmutable::class);

        // Les URL signées (pièces d'identité, reçus) doivent rester valides
        // derrière un tunnel HTTPS pendant le pilote.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
