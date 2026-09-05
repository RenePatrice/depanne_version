<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Data\NotificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Notification conservée en base.
 *
 * Elle est écrite **avant** toute tentative d'envoi, et indépendamment de son
 * succès. C'est ce qui permet à l'application mobile d'afficher un centre de
 * notifications fiable même quand le push n'est pas parti : sur le réseau de
 * Conakry, un push perdu est un cas courant, pas une exception.
 */
final class AppNotification extends Model
{
    use HasUuids;

    protected $table = 'notifications';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function genre(): ?NotificationType
    {
        return NotificationType::tryFrom($this->type);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePour(Builder $query, User $utilisateur): Builder
    {
        return $query
            ->where('notifiable_type', $utilisateur->getMorphClass())
            ->where('notifiable_id', $utilisateur->getKey());
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeNonLues(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
