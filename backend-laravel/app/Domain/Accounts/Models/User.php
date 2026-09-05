<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Models;

use App\Domain\Accounts\Data\UserStatus;
use App\Domain\Chat\Models\Message;
use App\Domain\Reviews\Models\Review;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Withdrawal;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Compte de l'application mobile. Le téléphone en E.164 est l'identifiant de
 * connexion (ADR-0003). Un même compte peut porter les deux casquettes :
 * `is_client` et `is_technician` ne sont pas exclusifs.
 *
 * Les administrateurs du back-office sont dans une table et un guard séparés
 * (voir AdminUser).
 */
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'phone',
        'password',
        'full_name',
        'email',
        'avatar_url',
        'is_client',
        'is_technician',
        'status',
        'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_client' => 'boolean',
            'is_technician' => 'boolean',
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------------ relations --

    /** @return HasOne<ClientProfile, $this> */
    public function clientProfile(): HasOne
    {
        return $this->hasOne(ClientProfile::class);
    }

    /** @return HasOne<TechnicianProfile, $this> */
    public function technicianProfile(): HasOne
    {
        return $this->hasOne(TechnicianProfile::class);
    }

    /** @return HasMany<Address, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /** @return HasMany<RefreshToken, $this> */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function ticketsAsClient(): HasMany
    {
        return $this->hasMany(Ticket::class, 'client_id');
    }

    /** @return HasMany<Ticket, $this> */
    public function ticketsAsTechnician(): HasMany
    {
        return $this->hasMany(Ticket::class, 'technician_id');
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<Withdrawal, $this> */
    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class, 'technician_id');
    }

    /** @return HasMany<Review, $this> */
    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'technician_id');
    }

    /** @return HasMany<Review, $this> */
    public function reviewsWritten(): HasMany
    {
        return $this->hasMany(Review::class, 'client_id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    // -------------------------------------------------------------- helpers --

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIF;
    }

    /** Prénom seul, pour la salutation de l'accueil : « Bonjour Mariama ». */
    public function firstName(): string
    {
        return explode(' ', trim($this->full_name))[0];
    }
}
