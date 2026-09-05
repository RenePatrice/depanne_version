<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Contracts;

/**
 * Envoi d'un SMS (ADR-0005).
 *
 * Le produit n'envoie qu'un seul type de SMS : le code à six chiffres de
 * réinitialisation de mot de passe (§4). Tout le reste passe par la
 * notification push, qui ne coûte rien.
 */
interface SmsProvider
{
    /** @return bool vrai si le message a été accepté par la passerelle */
    public function envoyer(string $telephoneE164, string $message): bool;
}
