<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Jobs;

use App\Domain\Payments\Models\Payment;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Wallet\Services\EscrowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Libération automatique du séquestre (§8.4, étape 3).
 *
 * Planifié à la capture, pour vingt-quatre heures plus tard. Sans lui, un
 * client qui n'ouvre plus l'application bloquerait indéfiniment l'argent d'un
 * technicien qui a fait son travail.
 *
 * Le job est idempotent : `EscrowService::liberer()` ne fait rien sur un
 * paiement déjà libéré, ou sur un ticket en litige. Il peut donc croiser sans
 * dommage une validation manuelle du client.
 */
final class ReleaseEscrowJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(private readonly int $paiementId) {}

    public function handle(EscrowService $sequestre): void
    {
        /** @var Payment|null $paiement */
        $paiement = Payment::query()->with('ticket')->find($this->paiementId);

        if ($paiement === null) {
            return;
        }

        $sequestre->liberer($paiement, ActorType::SYSTEME);
    }
}
