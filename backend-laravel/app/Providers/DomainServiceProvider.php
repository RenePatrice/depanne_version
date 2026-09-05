<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Chat\Contracts\MaskedCallProvider;
use App\Domain\Chat\Providers\MockMaskedCallProvider;
use App\Domain\Notifications\Channels\LogPushProvider;
use App\Domain\Notifications\Channels\LogSmsProvider;
use App\Domain\Notifications\Contracts\PushProvider;
use App\Domain\Notifications\Contracts\SmsProvider;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Providers\MockPaymentProvider;
use App\Domain\Payments\Providers\OrangeMoneyProvider;
use App\Domain\Pricing\Contracts\MapProvider;
use App\Domain\Pricing\Providers\GoogleDistanceMatrixProvider;
use App\Domain\Pricing\Providers\HaversineMapProvider;
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

        $this->app->bind(PushProvider::class, function (): PushProvider {
            return match (config('depanne.push.provider')) {
                // Firebase n'existe pas encore : la notification part dans
                // laravel.log. La persistance en base et la diffusion Reverb,
                // elles, fonctionnent réellement — voir SendNotification.
                default => new LogPushProvider,
            };
        });

        $this->app->bind(MaskedCallProvider::class, function (): MaskedCallProvider {
            return match (config('depanne.masked_call.provider')) {
                // Aucun opérateur n'est branché pour le pilote : le pilote
                // simulé renvoie un numéro fictif et le dit franchement.
                default => new MockMaskedCallProvider,
            };
        });

        $this->app->bind(PaymentProvider::class, function (): PaymentProvider {
            $config = config('depanne.payment.orange_money');

            // Le vrai fournisseur n'est atteignable que si les quatre clés
            // sont là. Tant qu'elles manquent — c'est le cas en local — le
            // pilote simulé prend la main, et le parcours complet reste
            // jouable sans compte marchand.
            $complet = config('depanne.payment.provider') === 'orange_money'
                && is_array($config)
                && ! in_array(null, [
                    $config['base_url'] ?? null,
                    $config['client_id'] ?? null,
                    $config['client_secret'] ?? null,
                    $config['merchant_key'] ?? null,
                ], true);

            if (! $complet) {
                return new MockPaymentProvider;
            }

            return new OrangeMoneyProvider(
                rtrim((string) $config['base_url'], '/'),
                (string) $config['client_id'],
                (string) $config['client_secret'],
                (string) $config['merchant_key'],
                (string) ($config['webhook_secret'] ?? ''),
            );
        });

        $this->app->bind(MapProvider::class, function (): MapProvider {
            $repli = new HaversineMapProvider;

            $cle = config('depanne.map.google_server_key');

            // Sans clé, on ne tente même pas l'appel : Google renverrait
            // REQUEST_DENIED sur chaque devis et le repli serait fait après
            // un aller-retour réseau inutile.
            if (config('depanne.map.provider') !== 'google' || ! is_string($cle) || $cle === '') {
                return $repli;
            }

            return new GoogleDistanceMatrixProvider(
                $repli,
                $cle,
                (int) config('depanne.map.distance_cache_ttl', 604800),
            );
        });
    }
}
