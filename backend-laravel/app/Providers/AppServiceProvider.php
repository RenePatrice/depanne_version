<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->limitesApi();
    }

    /**
     * Limites de débit de l'API mobile (§10).
     *
     * Les routes ouvertes sont limitées par IP **et** par numéro : limiter la
     * seule IP punirait tout un cybercafé, limiter le seul numéro permettrait
     * de bloquer le compte d'un tiers. Les gardes métier restent dans les
     * actions ; ces limites-ci protègent l'infrastructure.
     */
    private function limitesApi(): void
    {
        // Ces limites protègent l'infrastructure ; c'est `AuthenticateUser` qui
        // verrouille un compte au bout de cinq essais, avec un message lisible.
        // Elles sont donc volontairement plus larges que la garde métier.
        RateLimiter::for('connexion-mobile', fn (Request $request): array => [
            Limit::perMinutes(15, 30)->by('ip:'.$request->ip()),
            Limit::perMinutes(15, 12)->by('tel:'.$request->input('phone')),
        ]);

        RateLimiter::for('inscription', fn (Request $request): Limit => Limit::perHour(5)->by($request->ip()));

        RateLimiter::for('refresh', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));

        RateLimiter::for('mot-de-passe', fn (Request $request): array => [
            Limit::perHour(10)->by('ip:'.$request->ip()),
            Limit::perHour(3)->by('tel:'.$request->input('phone')),
        ]);

        // Chaque devis peut déclencher un appel Distance Matrix facturé. La
        // limite est large pour un usage normal — on compare quelques
        // prestations avant de choisir — et serrée face à un balayage
        // systématique de la grille tarifaire.
        RateLimiter::for('devis', fn (Request $request): Limit => Limit::perMinute(20)
            ->by((string) $request->user()?->getKey()));

        // Publier reste rare : une panne à la fois. Cette limite n'est qu'un
        // garde-fou ; c'est `CreateTicket` qui refuse une seconde demande
        // ouverte, avec un message compréhensible.
        RateLimiter::for('publication', fn (Request $request): Limit => Limit::perHour(10)
            ->by((string) $request->user()?->getKey()));

        // Une position toutes les huit secondes fait sept à huit appels par
        // minute (§10). La limite laisse la place aux reprises après coupure
        // réseau, fréquentes à Conakry, sans ouvrir la route à un client qui
        // l'inonderait.
        RateLimiter::for('position', fn (Request $request): Limit => Limit::perMinute(30)
            ->by((string) $request->user()?->getKey()));
    }
}
