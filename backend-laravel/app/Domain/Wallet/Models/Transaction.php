<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Models;

use App\Domain\Accounts\Models\User;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Wallet\Data\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mouvement de portefeuille. C'est la seule source de vérité du solde
 * (ADR-0004) : rien d'autre ne doit prétendre savoir combien un technicien a
 * gagné.
 */
final class Transaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'ticket_id', 'type', 'amount_gnf',
        'balance_after_gnf', 'description', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount_gnf' => 'integer',
            'balance_after_gnf' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Solde d'un utilisateur : la somme de ses mouvements, calculée en base.
     * Ne jamais remplacer cet appel par la lecture d'un champ `solde`.
     */
    public static function balanceFor(int $userId): int
    {
        return (int) self::query()->where('user_id', $userId)->sum('amount_gnf');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeEarnings(Builder $query): Builder
    {
        return $query->whereIn('type', [TransactionType::EARNING, TransactionType::TIP]);
    }
}
