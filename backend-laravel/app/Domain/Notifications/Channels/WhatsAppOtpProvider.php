<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Contracts\SmsProvider;
use App\Support\Telephone;
use Illuminate\Support\Facades\Log;

/**
 * OTP par WhatsApp (#8), sans aucune API SMS ni API Google.
 *
 * En pilote, le code est **journalisé** comme le pilote `log` : le parcours
 * « mot de passe oublié » se déroule de bout en bout sans passerelle. En
 * production, l'envoi réel passera par une passerelle **open-source** (Baileys,
 * whatsapp-web.js) qui écrit depuis le compte WhatsApp de la plateforme — cette
 * classe sera la seule à changer, derrière la même interface `SmsProvider`.
 *
 * Le `wa.me` n'est pas un canal d'envoi (il ouvre une discussion à l'initiative
 * de l'utilisateur) : il sert uniquement au lien « besoin d'aide » côté
 * application, pas à pousser le code.
 */
final class WhatsAppOtpProvider implements SmsProvider
{
    public function envoyer(string $telephoneE164, string $message): bool
    {
        // Le numéro est journalisé masqué : un log finit toujours par être lu.
        Log::channel(config('logging.default'))->info('WhatsApp OTP simulé', [
            'destinataire' => Telephone::masquer($telephoneE164),
            'message' => $message,
        ]);

        return true;
    }
}
