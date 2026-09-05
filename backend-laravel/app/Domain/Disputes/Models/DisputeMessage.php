<?php

declare(strict_types=1);

namespace App\Domain\Disputes\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message de la messagerie interne d'un litige. Un message marqué interne n'est
 * jamais renvoyé aux parties : c'est la note que le support se laisse à lui-même.
 */
final class DisputeMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['dispute_id', 'author_type', 'author_id', 'audience', 'is_internal', 'content'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Dispute, $this> */
    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    /**
     * Messages visibles par les parties.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_internal', false);
    }
}
