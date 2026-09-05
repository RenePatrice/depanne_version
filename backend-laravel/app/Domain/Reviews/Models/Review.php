<?php

declare(strict_types=1);

namespace App\Domain\Reviews\Models;

use App\Domain\Accounts\Models\User;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Évaluation laissée par le client après l'intervention. Elle alimente
 * `technician_profiles.rating_avg`, qui pèse pour 30 % dans le score de
 * matching (§8.3).
 */
final class Review extends Model
{
    protected $fillable = ['ticket_id', 'client_id', 'technician_id', 'rating', 'tags', 'comment', 'tip_gnf'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'tags' => 'array',
            'tip_gnf' => 'integer',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<User, $this> */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
