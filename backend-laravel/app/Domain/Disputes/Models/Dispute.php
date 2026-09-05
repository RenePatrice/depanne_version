<?php

declare(strict_types=1);

namespace App\Domain\Disputes\Models;

use App\Domain\Accounts\Models\User;
use App\Domain\Disputes\Data\DisputePriority;
use App\Domain\Disputes\Data\DisputeReason;
use App\Domain\Disputes\Data\DisputeStatus;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Dispute extends Model
{
    protected $fillable = [
        'reference', 'ticket_id', 'opened_by', 'reason', 'description', 'evidence',
        'status', 'priority', 'sla_due_at', 'resolution', 'resolution_note',
        'refund_gnf', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => DisputeReason::class,
            'evidence' => 'array',
            'status' => DisputeStatus::class,
            'priority' => DisputePriority::class,
            'sla_due_at' => 'datetime',
            'refund_gnf' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return HasMany<DisputeMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(DisputeMessage::class)->orderBy('created_at');
    }

    /** @return BelongsTo<User, $this> */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [DisputeStatus::OUVERT, DisputeStatus::EN_COURS]);
    }

    /** Le délai de traitement est dépassé : à remonter en tête de file. */
    public function isOverdue(): bool
    {
        return $this->status->isOpen()
            && $this->sla_due_at !== null
            && $this->sla_due_at->isPast();
    }
}
