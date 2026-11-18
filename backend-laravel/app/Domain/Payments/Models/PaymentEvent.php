<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Data\PaymentEventType;
use App\Domain\Tickets\Data\ActorType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une étape du journal d'un paiement (ADR-0047).
 *
 * Ajout seul : aucune ligne n'est jamais modifiée ni supprimée. C'est ce qui
 * permet au support de reconstituer qui a dit quoi, et quand, en cas de
 * désaccord sur un paiement en espèces.
 */
final class PaymentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'payment_id', 'ticket_id', 'type', 'actor_type', 'actor_id', 'amount_gnf', 'details',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentEventType::class,
            'actor_type' => ActorType::class,
            'actor_id' => 'integer',
            'amount_gnf' => 'integer',
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
