<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Actions;

use App\Domain\Settings\Models\AppSetting;
use App\Domain\Wallet\Data\TransactionType;
use App\Domain\Wallet\Data\WithdrawalStatus;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Withdrawal;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Workflow des demandes de retrait (§8.4) :
 * `EN_ATTENTE → APPROUVE → PAYE`, ou `REJETE` avec motif.
 *
 * Deux principes gouvernent cette action.
 *
 * Le **mouvement de portefeuille n'est écrit qu'au versement effectif**, pas à
 * l'approbation : approuver, c'est décider ; payer, c'est sortir l'argent. Un
 * retrait approuvé mais jamais versé ne doit pas amputer le solde du technicien.
 *
 * Le **solde est revérifié au moment du paiement**, dans la même transaction
 * SQL que l'écriture du mouvement. Entre la demande et le versement, le
 * technicien a pu être remboursé sur un litige : payer sans revérifier
 * creuserait un solde négatif.
 */
final class ProcessWithdrawal
{
    public function approuver(Withdrawal $retrait, int $adminId, ?string $note = null): Withdrawal
    {
        $this->exigeStatut($retrait, WithdrawalStatus::EN_ATTENTE, 'approuvée');
        $this->verifieSolde($retrait);

        $retrait->forceFill([
            'status' => WithdrawalStatus::APPROUVE,
            'note' => $note,
            'processed_by' => $adminId,
            'processed_at' => now(),
        ])->save();

        activity('finances')
            ->performedOn($retrait)
            ->withProperties(['reference' => $retrait->reference, 'montant' => $retrait->amount_gnf])
            ->log('Retrait approuvé : '.$retrait->reference);

        return $retrait;
    }

    public function rejeter(Withdrawal $retrait, string $motif, int $adminId): Withdrawal
    {
        if (! in_array($retrait->status, [WithdrawalStatus::EN_ATTENTE, WithdrawalStatus::APPROUVE], true)) {
            throw new DomainException(
                'Un retrait '.mb_strtolower($retrait->status->label()).' ne peut plus être rejeté.'
            );
        }

        $retrait->forceFill([
            'status' => WithdrawalStatus::REJETE,
            'note' => $motif,
            'processed_by' => $adminId,
            'processed_at' => now(),
        ])->save();

        activity('finances')
            ->performedOn($retrait)
            ->withProperties(['reference' => $retrait->reference, 'motif' => $motif])
            ->log('Retrait rejeté : '.$retrait->reference);

        return $retrait;
    }

    /** Versement effectué chez l'opérateur : c'est ici que le solde bouge. */
    public function marquerPaye(Withdrawal $retrait, int $adminId, ?string $reference = null): Withdrawal
    {
        $this->exigeStatut($retrait, WithdrawalStatus::APPROUVE, 'marquée payée');

        return DB::transaction(function () use ($retrait, $adminId, $reference): Withdrawal {
            $this->verifieSolde($retrait);

            $solde = Transaction::balanceFor($retrait->technician_id);

            Transaction::query()->create([
                'user_id' => $retrait->technician_id,
                'ticket_id' => null,
                'type' => TransactionType::WITHDRAWAL,
                'amount_gnf' => -$retrait->amount_gnf,
                'balance_after_gnf' => $solde - $retrait->amount_gnf,
                'description' => 'Retrait '.$retrait->reference.' vers '.$retrait->provider->label(),
                'metadata' => ['reference_operateur' => $reference],
            ]);

            $retrait->forceFill([
                'status' => WithdrawalStatus::PAYE,
                'processed_by' => $adminId,
                'paid_at' => now(),
                'note' => $reference !== null ? 'Référence opérateur : '.$reference : $retrait->note,
            ])->save();

            activity('finances')
                ->performedOn($retrait)
                ->withProperties([
                    'reference' => $retrait->reference,
                    'montant' => $retrait->amount_gnf,
                    'reference_operateur' => $reference,
                ])
                ->log('Retrait versé : '.$retrait->reference);

            return $retrait;
        });
    }

    private function exigeStatut(Withdrawal $retrait, WithdrawalStatus $attendu, string $operation): void
    {
        if ($retrait->status !== $attendu) {
            throw new DomainException(sprintf(
                'Cette demande est %s : elle ne peut pas être %s.',
                mb_strtolower($retrait->status->label()),
                $operation,
            ));
        }
    }

    private function verifieSolde(Withdrawal $retrait): void
    {
        $solde = Transaction::balanceFor($retrait->technician_id);

        if ($retrait->amount_gnf > $solde) {
            throw new DomainException(sprintf(
                'Le solde du technicien (%s GNF) ne couvre pas les %s GNF demandés.',
                number_format($solde, 0, ',', ' '),
                number_format($retrait->amount_gnf, 0, ',', ' '),
            ));
        }

        $minimum = (int) AppSetting::get(AppSetting::WITHDRAWAL_MIN_GNF, 50_000);

        if ($retrait->amount_gnf < $minimum) {
            throw new DomainException(sprintf(
                'Le montant minimum de retrait est de %s GNF.',
                number_format($minimum, 0, ',', ' '),
            ));
        }
    }
}
