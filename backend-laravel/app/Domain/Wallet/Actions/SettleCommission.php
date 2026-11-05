<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Wallet\Data\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Services\CommissionLedger;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Le technicien règle sa commission due (#1).
 *
 * Il paie la plateforme par Mobile Money in-app ; le règlement est inscrit en
 * `SETTLEMENT` positif, ce qui réduit sa dette et le débloque. On ne règle
 * jamais plus que ce qui est dû.
 *
 * > **Pilote** : le paiement est simulé (succès immédiat). En production, il
 * > passera par `PaymentProvider` et son webhook, seule autorité sur un
 * > encaissement (ADR-0032) — seul ce point changera.
 */
final class SettleCommission
{
    public function __construct(private readonly CommissionLedger $ledger) {}

    /**
     * @return int la commission restant due après règlement
     *
     * @throws DomainException
     */
    public function execute(User $technicien, int $montant): int
    {
        $due = $this->ledger->due($technicien);

        if ($due <= 0) {
            throw new DomainException('Tu n\'as aucune commission à régler.');
        }

        $montant = min(max(0, $montant), $due);

        if ($montant <= 0) {
            throw new DomainException('Le montant à régler doit être supérieur à zéro.');
        }

        return DB::transaction(function () use ($technicien, $montant): int {
            $solde = Transaction::balanceFor((int) $technicien->getKey());

            Transaction::query()->create([
                'user_id' => $technicien->getKey(),
                'ticket_id' => null,
                'type' => TransactionType::SETTLEMENT,
                'amount_gnf' => $montant,
                'balance_after_gnf' => $solde + $montant,
                'description' => 'Règlement de commission',
            ]);

            return $this->ledger->due($technicien->fresh());
        });
    }
}
