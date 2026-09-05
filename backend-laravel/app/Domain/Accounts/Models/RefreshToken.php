<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jeton de rafraîchissement rotatif (ADR-0003). Le jeton en clair n'existe
 * qu'une fois, dans la réponse HTTP : la base ne garde qu'un hash SHA-256.
 */
final class RefreshToken extends Model
{
    protected $fillable = [
        'user_id', 'token_hash', 'replaced_by_id', 'device_name',
        'ip_address', 'expires_at', 'revoked_at', 'last_used_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Le hash est déterministe : c'est ce qui permet de retrouver le jeton présenté. */
    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * @param  Builder<RefreshToken>  $query
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
