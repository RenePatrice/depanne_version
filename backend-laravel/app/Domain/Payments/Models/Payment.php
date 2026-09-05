<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Data\PaymentMethod;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paiement Mobile Money. `provider_ref` porte la contrainte UNIQUE qui rend le
 * webhook idempotent : un événement rejoué ne peut pas capturer deux fois (§8.4).
 */
final class Payment extends Model
{
    protected $fillable = [
        'ticket_id', 'provider', 'provider_ref', 'method', 'payer_phone',
        'amount_gnf', 'currency', 'status', 'failure_reason',
        'webhook_payload', 'captured_at', 'released_at', 'auto_release_at',
    ];

    protected $hidden = ['webhook_payload'];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount_gnf' => 'integer',
            'webhook_payload' => 'array',
            'captured_at' => 'datetime',
            'released_at' => 'datetime',
            'auto_release_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Fonds capturés dont l'échéance de libération automatique est atteinte.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDueForRelease(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::CAPTUREE)
            ->whereNotNull('auto_release_at')
            ->where('auto_release_at', '<=', now());
    }

    public function isInEscrow(): bool
    {
        return $this->status === PaymentStatus::CAPTUREE;
    }
}
