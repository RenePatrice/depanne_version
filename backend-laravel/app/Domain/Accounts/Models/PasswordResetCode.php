<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Code SMS à 6 chiffres pour la réinitialisation du mot de passe — seul usage
 * de l'OTP dans le produit (§4). Stocké hashé, valable 10 minutes,
 * 5 tentatives au maximum.
 */
final class PasswordResetCode extends Model
{
    public const MAX_ATTEMPTS = 5;

    public const LIFETIME_MINUTES = 10;

    public $timestamps = false;

    protected $fillable = ['phone', 'code_hash', 'attempts', 'expires_at', 'consumed_at', 'created_at'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->attempts < self::MAX_ATTEMPTS
            && $this->expires_at->isFuture();
    }
}
