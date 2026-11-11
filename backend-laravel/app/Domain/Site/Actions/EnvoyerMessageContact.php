<?php

declare(strict_types=1);

namespace App\Domain\Site\Actions;

use App\Domain\Site\Models\SiteMessage;

/**
 * Enregistre un message du formulaire de contact. Il atterrit dans le
 * back-office (module Site), où l'équipe le lit et le marque traité.
 */
final class EnvoyerMessageContact
{
    /** @param  array{nom: string, telephone: string, email?: string|null, sujet: string, message: string}  $donnees */
    public function execute(array $donnees): SiteMessage
    {
        $email = trim((string) ($donnees['email'] ?? ''));

        return SiteMessage::query()->create([
            'nom' => trim($donnees['nom']),
            'telephone' => trim($donnees['telephone']),
            'email' => $email === '' ? null : mb_strtolower($email),
            'sujet' => array_key_exists($donnees['sujet'], SiteMessage::SUJETS) ? $donnees['sujet'] : 'autre',
            'message' => trim($donnees['message']),
        ]);
    }

    public function basculerTraite(SiteMessage $message): SiteMessage
    {
        $message->forceFill(['traite_at' => $message->estTraite() ? null : now()])->save();

        return $message;
    }
}
