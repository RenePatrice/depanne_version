<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Models;

use App\Domain\Accounts\Models\Address;
use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use App\Domain\Chat\Models\Message;
use App\Domain\Disputes\Models\Dispute;
use App\Domain\Matching\Models\MatchAttempt;
use App\Domain\Payments\Models\Payment;
use App\Domain\Reviews\Models\Review;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Zones\Models\Zone;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Demande d'intervention. Voir §8.1 pour le cycle de vie et §8.2 pour le prix.
 *
 * Les colonnes de prix sont un instantané pris à la publication : rejouer le
 * calcul plus tard ne doit jamais changer ce qui a été annoncé au client.
 */
final class Ticket extends Model
{
    protected $fillable = [
        'reference', 'client_id', 'technician_id', 'service_id', 'zone_id', 'state',
        'address_id', 'address_snapshot', 'location', 'problem_description', 'photos',
        'distance_km', 'distance_is_estimated',
        'base_price_gnf', 'travel_fee_gnf', 'extra_fee_gnf', 'total_gnf',
        'commission_gnf', 'technician_net_gnf', 'commission_rate',
        'diagnosis', 'diagnosis_photos', 'cancellation_reason', 'cancellation_fee_gnf',
        'published_at', 'accepted_at', 'en_route_at', 'arrived_at',
        'started_at', 'completed_at', 'paid_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => TicketState::class,
            'address_snapshot' => 'array',
            'location' => Point::class,
            'photos' => 'array',
            'diagnosis_photos' => 'array',
            'distance_km' => 'float',
            'distance_is_estimated' => 'boolean',
            'base_price_gnf' => 'integer',
            'travel_fee_gnf' => 'integer',
            'extra_fee_gnf' => 'integer',
            'total_gnf' => 'integer',
            'commission_gnf' => 'integer',
            'technician_net_gnf' => 'integer',
            'commission_rate' => 'float',
            'cancellation_fee_gnf' => 'integer',
            'published_at' => 'datetime',
            'accepted_at' => 'datetime',
            'en_route_at' => 'datetime',
            'arrived_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'paid_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------------- relations --

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

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Zone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /** @return BelongsTo<Address, $this> */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /** @return HasMany<TicketEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class)->orderBy('created_at');
    }

    /** @return HasMany<MatchAttempt, $this> */
    public function matchAttempts(): HasMany
    {
        return $this->hasMany(MatchAttempt::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasOne<Review, $this> */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /** @return HasMany<Dispute, $this> */
    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    // ---------------------------------------------------------------- scopes --

    /**
     * Interventions en cours : un technicien est mobilisé dessus.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('state', self::stateValues(
            static fn (TicketState $s): bool => $s->isActive()
        ));
    }

    /**
     * Tickets qui portent du chiffre d'affaires.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBillable(Builder $query): Builder
    {
        return $query->whereIn('state', array_map(
            static fn (TicketState $s): string => $s->value,
            TicketState::billable(),
        ));
    }

    /**
     * @param  callable(TicketState): bool  $filter
     * @return array<int, string>
     */
    private static function stateValues(callable $filter): array
    {
        return array_values(array_map(
            static fn (TicketState $s): string => $s->value,
            array_filter(TicketState::cases(), $filter),
        ));
    }

    // --------------------------------------------------------------- helpers --

    /** Délai entre la publication et l'acceptation, en secondes. */
    public function acceptanceDelaySeconds(): ?int
    {
        if ($this->published_at === null || $this->accepted_at === null) {
            return null;
        }

        return (int) $this->published_at->diffInSeconds($this->accepted_at);
    }

    /**
     * Contrôle d'intégrité du prix : le total est toujours la somme de ses trois
     * composantes. Utilisé par les tests et par la supervision.
     */
    public function priceIsCoherent(): bool
    {
        return $this->total_gnf === $this->base_price_gnf + $this->travel_fee_gnf + $this->extra_fee_gnf;
    }
}
