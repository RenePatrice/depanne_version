<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Accounts\Models\User;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Wallet\Models\Transaction;

/**
 * Commission due par un technicien (#1, paiement direct).
 *
 * Quand le client paie **en espèces**, le technicien encaisse tout : la
 * commission 10 % lui reste alors **à devoir**. Elle est inscrite au grand
 * livre comme un mouvement `COMMISSION` négatif, sans `EARNING` en face — le
 * portefeuille n'a jamais tenu cet argent. La dette est donc simplement la part
 * négative du solde.
 *
 * Les paiements Mobile Money in-app, eux, retiennent la commission à la source
 * (le séquestre écrit `EARNING` et `COMMISSION`, le net reste retirable) : ils
 * ne créent pas de dette, et leurs gains **compensent** une dette d'espèces.
 *
 * Au-delà d'un plafond, le technicien ne peut plus accepter de demandes tant
 * qu'il n'a pas réglé : c'est la seule pression qui garde la plateforme payée
 * quand l'argent ne transite pas par elle.
 */
final class CommissionLedger
{
    /** Commission restant due, en GNF (0 si le solde est positif). */
    public function due(User $technicien): int
    {
        return max(0, -Transaction::balanceFor((int) $technicien->getKey()));
    }

    public function plafond(): int
    {
        return (int) AppSetting::get(AppSetting::COMMISSION_DEBT_CAP_GNF, 100_000);
    }

    /** Le technicien a atteint le plafond : il doit régler avant d'accepter. */
    public function estBloque(User $technicien): bool
    {
        return $this->due($technicien) >= $this->plafond();
    }
}
