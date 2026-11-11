<?php

declare(strict_types=1);

namespace App\Domain\Site\Data;

/**
 * Nature d'un événement de fréquentation du site vitrine.
 *
 * L'énumération est la source unique : le JavaScript du site n'envoie que ces
 * valeurs (une valeur inconnue est ignorée à l'enregistrement), et le tableau
 * de bord lit les mêmes libellés.
 */
enum TypeEvenement: string
{
    case VISITE = 'visite';
    case CLIC_TELECHARGEMENT = 'clic_telechargement';
    case CLIC_PLAYSTORE = 'clic_playstore';
    case CLIC_DEVENIR_TECHNICIEN = 'clic_devenir_technicien';
    case CLIC_CONTACT = 'clic_contact';
    case MESSAGE_CONTACT = 'message_contact';
    case INSCRIPTION_NEWSLETTER = 'inscription_newsletter';

    public function libelle(): string
    {
        return match ($this) {
            self::VISITE => 'Visites',
            self::CLIC_TELECHARGEMENT => 'Clics téléchargement (APK)',
            self::CLIC_PLAYSTORE => 'Clics Play Store',
            self::CLIC_DEVENIR_TECHNICIEN => 'Clics « Devenir technicien »',
            self::CLIC_CONTACT => 'Clics WhatsApp',
            self::MESSAGE_CONTACT => 'Messages de contact',
            self::INSCRIPTION_NEWSLETTER => 'Inscriptions newsletter',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::VISITE => 'bi-eye',
            self::CLIC_TELECHARGEMENT => 'bi-android2',
            self::CLIC_PLAYSTORE => 'bi-google-play',
            self::CLIC_DEVENIR_TECHNICIEN => 'bi-person-badge',
            self::CLIC_CONTACT => 'bi-whatsapp',
            self::MESSAGE_CONTACT => 'bi-envelope',
            self::INSCRIPTION_NEWSLETTER => 'bi-newspaper',
        };
    }

    /**
     * Les clics mesurables (hors simple visite), pour les cartes du dashboard.
     *
     * @return array<int, self>
     */
    public static function clics(): array
    {
        return [
            self::CLIC_TELECHARGEMENT,
            self::CLIC_PLAYSTORE,
            self::CLIC_DEVENIR_TECHNICIEN,
            self::CLIC_CONTACT,
            self::MESSAGE_CONTACT,
            self::INSCRIPTION_NEWSLETTER,
        ];
    }
}
