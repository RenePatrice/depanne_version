<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * Identifiant opaque, stable et non devinable, pour les URLs du back-office.
 *
 * L'ID entier séquentiel (ex. 31) est chiffré en AES-128 avec une clé dérivée
 * de `APP_KEY`, puis encodé en base64url : « /techniciens/Qm2x… ». Impossible à
 * deviner ou à énumérer, mais **stable** (une même fiche donne toujours le même
 * jeton, donc les liens restent partageables) et réversible.
 *
 * Déterministe à dessein (AES-ECB, sans IV) : on ne cherche pas l'indistinguable
 * cryptographique — les routes sont déjà derrière le guard admin — mais à
 * supprimer toute énumération d'IDs séquentiels. L'API mobile, elle, continue
 * d'utiliser les IDs entiers (cf. RouteParIdOpaque).
 */
final class OpaqueId
{
    private const CIPHER = 'aes-128-ecb';

    public static function encode(int $id): string
    {
        $cipher = openssl_encrypt(pack('J', $id), self::CIPHER, self::key(), OPENSSL_RAW_DATA);

        if ($cipher === false) {
            return (string) $id; // repli improbable : ne jamais casser une URL
        }

        return rtrim(strtr(base64_encode($cipher), '+/', '-_'), '=');
    }

    public static function decode(string $token): ?int
    {
        $raw = base64_decode(strtr($token, '-_', '+/'), true);

        if ($raw === false || $raw === '') {
            return null;
        }

        $plain = openssl_decrypt($raw, self::CIPHER, self::key(), OPENSSL_RAW_DATA);

        if ($plain === false || strlen($plain) !== 8) {
            return null;
        }

        /** @var array<int, int>|false $unpacked */
        $unpacked = unpack('J', $plain);

        return $unpacked === false ? null : (int) $unpacked[1];
    }

    /** Clé AES-128 (16 octets) dérivée de APP_KEY. */
    private static function key(): string
    {
        return substr(hash('sha256', (string) Config::get('app.key'), true), 0, 16);
    }
}
