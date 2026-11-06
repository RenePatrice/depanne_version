<?php

declare(strict_types=1);

namespace App\Domain\Site\Actions;

use App\Domain\Settings\Models\AppSetting;

/**
 * Enregistre les textes et réglages du site vitrine, saisis en back-office.
 *
 * Chaque clé est connue (déclarée par le seeder) ; `AppSetting::put` refuse
 * toute clé inventée. Les valeurs vides sont conservées telles quelles — un
 * lien de téléchargement vidé masque simplement le bouton.
 */
final class EnregistrerContenuSite
{
    private const CLES = [
        AppSetting::SITE_HERO_TITRE,
        AppSetting::SITE_HERO_SOUS_TITRE,
        AppSetting::SITE_ANNONCE,
        AppSetting::SITE_TELECHARGEMENT_ACTIF,
        AppSetting::SITE_APK_URL,
        AppSetting::SITE_PLAYSTORE_URL,
        AppSetting::SITE_CONTACT_EMAIL,
        AppSetting::SITE_CONTACT_WHATSAPP,
    ];

    /** @param array<string, mixed> $valeurs */
    public function execute(array $valeurs, ?int $parQui = null): void
    {
        foreach (self::CLES as $cle) {
            if (! array_key_exists($cle, $valeurs)) {
                continue;
            }

            $valeur = $valeurs[$cle];

            if ($cle === AppSetting::SITE_TELECHARGEMENT_ACTIF) {
                $valeur = (bool) $valeur;
            } elseif (is_string($valeur)) {
                $valeur = trim($valeur);
            }

            AppSetting::put($cle, $valeur, $parQui);
        }
    }
}
