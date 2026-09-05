<?php

declare(strict_types=1);

namespace App\Domain\Matching\Data;

/** Réponse d'un technicien à une sollicitation (fenêtre de 45 s, §8.3). */
enum MatchResponse: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case ACCEPTE = 'ACCEPTE';
    case REFUSE = 'REFUSE';
    case EXPIRE = 'EXPIRE';
    case ANNULE = 'ANNULE';   // le ticket a été pris ou annulé entre-temps

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente',
            self::ACCEPTE => 'Accepté',
            self::REFUSE => 'Refusé',
            self::EXPIRE => 'Expiré',
            self::ANNULE => 'Annulé',
        };
    }

    /**
     * Une sollicitation compte dans le taux d'acceptation seulement si le
     * technicien a réellement eu la main : une annulation externe ne le pénalise pas.
     */
    public function countsInAcceptanceRate(): bool
    {
        return in_array($this, [self::ACCEPTE, self::REFUSE, self::EXPIRE], true);
    }
}
