<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Models;

use App\Domain\Accounts\Models\User;
use App\Domain\Payments\Data\PaymentMethod;
use App\Domain\Wallet\Data\WithdrawalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Withdrawal extends Model
{
    protected $fillable = [
        'reference', 'technician_id', 'amount_gnf', 'mobile_money_number',
        'provider', 'status', 'note', 'processed_by',
        'requested_at', 'processed_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_gnf' => 'integer',
            'provider' => PaymentMethod::class,
            'status' => WithdrawalStatus::class,
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /**
     * File de traitement du back-office, la plus ancienne d'abord.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', WithdrawalStatus::EN_ATTENTE)->orderBy('requested_at');
    }
}
