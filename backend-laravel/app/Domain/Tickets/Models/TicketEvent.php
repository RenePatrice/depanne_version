<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Models;

use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trace immuable d'une transition d'état. Aucune mise à jour n'est prévue :
 * c'est la timeline du support et la preuve en cas de litige.
 */
final class TicketEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['ticket_id', 'from_state', 'to_state', 'actor_type', 'actor_id', 'metadata'];

    protected function casts(): array
    {
        return [
            'from_state' => TicketState::class,
            'to_state' => TicketState::class,
            'actor_type' => ActorType::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
