<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Payments\Data\PaymentMethod;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Wallet\Data\WithdrawalStatus;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Withdrawal;
use App\Support\Money;
use App\Support\Telephone;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Demande de retrait, déposée par le technicien (§8.4, étape 6).
 *
 * Elle ne touche pas au solde : c'est `ProcessWithdrawal::marquerPaye()` qui
 * écrit le mouvement, au versement effectif. Ici on ne fait que déposer une
 * demande dans la file du back-office.
 *
 * Le **montant disponible** n'est pas le solde brut : il en retranche ce qui
 * est déjà engagé dans des demandes non encore versées. Sans cela, un
 * technicien pourrait déposer trois demandes de la totalité de son solde et
 * le support en paierait trois.
 */
final class RequestWithdrawal
{
    /** @throws DomainException */
    public function execute(User $technicien, int $montantGnf, string $numero, PaymentMethod $operateur): Withdrawal
    {
        if (! $technicien->is_technician) {
            throw new DomainException('Seuls les techniciens ont un portefeuille.');
        }

        $numeroNormalise = Telephone::normaliser($numero);

        if ($numeroNormalise === null) {
            throw new DomainException('Ce numéro Mobile Money n\'est pas valide.');
        }

        $minimum = (int) AppSetting::get(AppSetting::WITHDRAWAL_MIN_GNF, 50_000);

        if ($montantGnf < $minimum) {
            throw new DomainException(sprintf(
                'Le retrait minimum est de %s.',
                Money::format($minimum),
            ));
        }

        return DB::transaction(function () use ($technicien, $montantGnf, $numeroNormalise, $operateur): Withdrawal {
            $disponible = $this->disponible($technicien);

            if ($montantGnf > $disponible) {
                throw new DomainException(sprintf(
                    'Tu peux retirer %s au maximum. %s',
                    Money::format($disponible),
                    $this->engage($technicien) > 0
                        ? 'Une demande précédente est encore en cours de traitement.'
                        : '',
                ));
            }

            /** @var Withdrawal */
            return Withdrawal::query()->create([
                'reference' => $this->reference(),
                'technician_id' => $technicien->getKey(),
                'amount_gnf' => $montantGnf,
                'mobile_money_number' => $numeroNormalise,
                'provider' => $operateur->value,
                'status' => WithdrawalStatus::EN_ATTENTE->value,
                'requested_at' => now(),
            ]);
        });
    }

    /** Solde moins ce qui est déjà engagé dans des demandes ouvertes. */
    public function disponible(User $technicien): int
    {
        return max(0, Transaction::balanceFor((int) $technicien->getKey()) - $this->engage($technicien));
    }

    /** Montant des demandes déposées ou approuvées, mais pas encore versées. */
    public function engage(User $technicien): int
    {
        return (int) Withdrawal::query()
            ->where('technician_id', $technicien->getKey())
            ->whereIn('status', [
                WithdrawalStatus::EN_ATTENTE->value,
                WithdrawalStatus::APPROUVE->value,
            ])
            ->sum('amount_gnf');
    }

    private function reference(): string
    {
        $numero = DB::selectOne("SELECT nextval('withdrawal_reference_seq') AS n");

        return sprintf('RET-%s-%s', now()->format('Y'), str_pad((string) ($numero->n ?? 1), 5, '0', STR_PAD_LEFT));
    }
}
