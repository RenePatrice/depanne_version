<?php

declare(strict_types=1);

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Normalisation des numéros guinéens (§0.6, ADR-0003).
 *
 * Le téléphone est l'identifiant de connexion : il doit être stocké sous une
 * seule forme, sans quoi « 620 12 34 56 », « +224620123456 » et « 00224620123456 »
 * créeraient trois comptes pour la même personne.
 */
final class Telephone
{
    public const PAYS = 'GN';

    /** Renvoie le numéro en E.164, ou null s'il n'est pas exploitable. */
    public static function normaliser(?string $saisie): ?string
    {
        if ($saisie === null || trim($saisie) === '') {
            return null;
        }

        $utilitaire = PhoneNumberUtil::getInstance();

        try {
            $numero = $utilitaire->parse(trim($saisie), self::PAYS);
        } catch (NumberParseException) {
            return null;
        }

        if (! $utilitaire->isValidNumber($numero)) {
            return null;
        }

        return $utilitaire->format($numero, PhoneNumberFormat::E164);
    }

    public static function estValide(?string $saisie): bool
    {
        return self::normaliser($saisie) !== null;
    }

    /** Affichage lisible : +224 620 12 34 56. */
    public static function formatter(?string $e164): string
    {
        if ($e164 === null) {
            return '—';
        }

        $utilitaire = PhoneNumberUtil::getInstance();

        try {
            return $utilitaire->format($utilitaire->parse($e164, self::PAYS), PhoneNumberFormat::INTERNATIONAL);
        } catch (NumberParseException) {
            return $e164;
        }
    }

    /**
     * Masque un numéro dans un message : +224620123456 → +224 62● ●● ●● 56.
     * Sert au chat (§7.3) et aux traces qui ne doivent pas tout révéler.
     */
    public static function masquer(string $e164): string
    {
        $chiffres = preg_replace('/\D/', '', $e164) ?? '';

        if (mb_strlen($chiffres) < 6) {
            return str_repeat('●', mb_strlen($chiffres));
        }

        return mb_substr($chiffres, 0, 5).str_repeat('●', mb_strlen($chiffres) - 7).mb_substr($chiffres, -2);
    }
}
