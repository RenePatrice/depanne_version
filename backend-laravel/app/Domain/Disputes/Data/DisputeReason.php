<?php

declare(strict_types=1);

namespace App\Domain\Disputes\Data;

enum DisputeReason: string
{
    case TRAVAIL_NON_CONFORME = 'TRAVAIL_NON_CONFORME';
    case SURFACTURATION = 'SURFACTURATION';
    case RETARD = 'RETARD';
    case COMPORTEMENT = 'COMPORTEMENT';
    case DEGAT_MATERIEL = 'DEGAT_MATERIEL';
    case NON_PAIEMENT = 'NON_PAIEMENT';
    case AUTRE = 'AUTRE';

    public function label(): string
    {
        return match ($this) {
            self::TRAVAIL_NON_CONFORME => 'Travail non conforme',
            self::SURFACTURATION => 'Surfacturation',
            self::RETARD => 'Retard important',
            self::COMPORTEMENT => 'Comportement inapproprié',
            self::DEGAT_MATERIEL => 'Dégât matériel',
            self::NON_PAIEMENT => 'Non-paiement',
            self::AUTRE => 'Autre motif',
        };
    }

    /** Motif ouvrable par le technicien plutôt que par le client. */
    public function isTechnicianSide(): bool
    {
        return $this === self::NON_PAIEMENT;
    }
}
