<?php

declare(strict_types=1);

namespace App\Domain\Chat\Models;

use App\Domain\Accounts\Models\User;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message du chat in-app. `content` est la version affichée, déjà passée par le
 * masquage des coordonnées ; `original_content` conserve le texte d'origine pour
 * le support, et n'est jamais renvoyé à l'application mobile.
 */
final class Message extends Model
{
    protected $fillable = [
        'ticket_id', 'sender_id', 'content', 'original_content',
        'is_flagged', 'flag_reason', 'read_at',
    ];

    protected $hidden = ['original_content'];

    protected function casts(): array
    {
        return [
            'is_flagged' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Messages signalés, remontés au back-office (risque de contournement, §11).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFlagged(Builder $query): Builder
    {
        return $query->where('is_flagged', true);
    }
}
