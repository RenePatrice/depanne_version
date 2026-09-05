<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Contracts\SmsProvider;
use App\Support\Telephone;
use Illuminate\Support\Facades\Log;

/**
 * Pilote de développement : le SMS est écrit dans `laravel.log` au lieu d'être
 * envoyé (§4). C'est ce qui permet de dérouler le parcours « mot de passe
 * oublié » sans passerelle ni carte SIM.
 *
 * Le numéro est journalisé masqué : un fichier de log finit toujours par être
 * lu par quelqu'un d'autre que son auteur.
 */
final class LogSmsProvider implements SmsProvider
{
    public function envoyer(string $telephoneE164, string $message): bool
    {
        Log::channel(config('logging.default'))->info('SMS simulé', [
            'destinataire' => Telephone::masquer($telephoneE164),
            'message' => $message,
        ]);

        return true;
    }
}
