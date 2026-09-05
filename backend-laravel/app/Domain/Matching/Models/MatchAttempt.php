<?php

declare(strict_types=1);

namespace App\Domain\Matching\Models;

use App\Domain\Accounts\Models\User;
use App\Domain\Matching\Data\MatchResponse;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une sollicitation de technicien (§8.3). La diffusion étant séquentielle,
 * ces lignes racontent l'ordre exact des sollicitations et le score qui l'a produit.
 */
final class MatchAttempt extends Model
{
    protected $fillable = [
        'ticket_id', 'technician_id', 'cycle', 'radius_km', 'position',
        'score', 'score_breakdown', 'distance_km', 'response', 'refusal_reason',
        'notified_at', 'expires_at', 'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'cycle' => 'integer',
            'radius_km' => 'integer',
            'position' => 'integer',
            'score' => 'float',
            'score_breakdown' => 'array',
            'distance_km' => 'float',
            'response' => MatchResponse::class,
            'notified_at' => 'datetime',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /** Temps de réponse du technicien, en secondes. */
    public function responseDelaySeconds(): ?int
    {
        return $this->responded_at === null
            ? null
            : (int) $this->notified_at->diffInSeconds($this->responded_at);
    }
}
