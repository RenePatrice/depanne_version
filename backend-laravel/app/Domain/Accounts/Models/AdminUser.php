<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Compte du back-office, sur son propre guard (§4). Les rôles ADMIN / SUPPORT /
 * FINANCE viennent de spatie/laravel-permission.
 */
final class AdminUser extends Authenticatable
{
    use HasRoles, Notifiable;

    /** Nom du guard utilisé par spatie/laravel-permission pour ce modèle. */
    protected string $guard_name = 'admin';

    protected $fillable = ['email', 'password', 'full_name', 'avatar_url', 'is_active'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }
}
