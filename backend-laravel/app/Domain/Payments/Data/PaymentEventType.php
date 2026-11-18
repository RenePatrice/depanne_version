<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

/** Étapes inscrites au journal d'un paiement (ADR-0047). */
enum PaymentEventType: string
{
    case DECLARE = 'DECLARE';
    case CONFIRME = 'CONFIRME';
    case CONTESTE = 'CONTESTE';
    case ANNULE = 'ANNULE';
    case RELANCE = 'RELANCE';
    case ARBITRE = 'ARBITRE';
    case COMMISSION = 'COMMISSION';
    case INITIE = 'INITIE';
    case CAPTURE = 'CAPTURE';
    case ECHEC = 'ECHEC';
    case LIBERE = 'LIBERE';
    case REMBOURSE = 'REMBOURSE';

    public function label(): string
    {
        return match ($this) {
            self::DECLARE => 'Paiement déclaré',
            self::CONFIRME => 'Paiement confirmé',
            self::CONTESTE => 'Paiement contesté',
            self::ANNULE => 'Déclaration annulée',
            self::RELANCE => 'Relance envoyée',
            self::ARBITRE => 'Décision du support',
            self::COMMISSION => 'Commission inscrite',
            self::INITIE => 'Paiement lancé',
            self::CAPTURE => 'Encaissé par l\'opérateur',
            self::ECHEC => 'Refusé par l\'opérateur',
            self::LIBERE => 'Fonds versés au technicien',
            self::REMBOURSE => 'Remboursement',
        };
    }

    /** Icône Bootstrap Icons, pour la frise du back-office. */
    public function icone(): string
    {
        return match ($this) {
            self::DECLARE => 'bi-pencil-square',
            self::CONFIRME, self::CAPTURE => 'bi-check2-circle',
            self::CONTESTE, self::ECHEC => 'bi-exclamation-octagon',
            self::ANNULE => 'bi-x-circle',
            self::RELANCE => 'bi-bell',
            self::ARBITRE => 'bi-shield-check',
            self::COMMISSION => 'bi-percent',
            self::INITIE => 'bi-phone',
            self::LIBERE => 'bi-wallet2',
            self::REMBOURSE => 'bi-arrow-counterclockwise',
        };
    }

    /**
     * La commission ne figure jamais côté client (§7, « ce que l'API ne montre
     * pas ») : cet événement est réservé au technicien et au back-office.
     */
    public function visiblePourLeClient(): bool
    {
        return $this !== self::COMMISSION;
    }
}
