<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\TechnicianProfile;
use DomainException;

/**
 * Décision sur un dossier de technicien : approbation ou rejet motivé (§6).
 *
 * Tant qu'un dossier n'est pas approuvé, le technicien n'apparaît jamais dans
 * le matching — c'est la garantie que le cahier des charges appelle
 * « techniciens vérifiés ». Un rejet exige un motif : le technicien doit savoir
 * quoi corriger pour représenter son dossier.
 */
final class ReviewTechnicianApplication
{
    public function approuver(TechnicianProfile $profil, int $adminId): TechnicianProfile
    {
        $this->refuseSiDejaTranche($profil);

        $profil->forceFill([
            'verification_status' => VerificationStatus::VALIDE,
            'rejection_reason' => null,
            'verified_at' => now(),
            'verified_by' => $adminId,
        ])->save();

        activity('techniciens')
            ->performedOn($profil)
            ->withProperties(['admin_id' => $adminId])
            ->log('Dossier technicien approuvé');

        return $profil;
    }

    public function rejeter(TechnicianProfile $profil, string $motif, int $adminId): TechnicianProfile
    {
        $this->refuseSiDejaTranche($profil);

        $profil->forceFill([
            'verification_status' => VerificationStatus::REJETE,
            'rejection_reason' => $motif,
            'verified_at' => null,
            'verified_by' => $adminId,
            // Un dossier rejeté ne doit surtout pas rester dans le matching.
            'is_online' => false,
        ])->save();

        activity('techniciens')
            ->performedOn($profil)
            ->withProperties(['motif' => $motif, 'admin_id' => $adminId])
            ->log('Dossier technicien rejeté');

        return $profil;
    }

    private function refuseSiDejaTranche(TechnicianProfile $profil): void
    {
        if ($profil->verification_status !== VerificationStatus::EN_ATTENTE_VALIDATION) {
            throw new DomainException(
                'Ce dossier est déjà '.mb_strtolower($profil->verification_status->label())
                .' : rouvre-le depuis la fiche du technicien pour le réexaminer.'
            );
        }
    }
}
