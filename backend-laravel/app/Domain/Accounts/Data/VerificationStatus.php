<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Data;

/** Statut du dossier de vérification d'un technicien (§4, file de validation §6). */
enum VerificationStatus: string
{
    case EN_ATTENTE_VALIDATION = 'EN_ATTENTE_VALIDATION';
    case VALIDE = 'VALIDE';
    case REJETE = 'REJETE';
    case SUSPENDU = 'SUSPENDU';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE_VALIDATION => 'En attente de validation',
            self::VALIDE => 'Validé',
            self::REJETE => 'Rejeté',
            self::SUSPENDU => 'Suspendu',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EN_ATTENTE_VALIDATION => 'warning',
            self::VALIDE => 'success',
            self::REJETE, self::SUSPENDU => 'danger',
        };
    }

    /** Seuls les techniciens validés peuvent être sollicités par le matching. */
    public function canWork(): bool
    {
        return $this === self::VALIDE;
    }
}
