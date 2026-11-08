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
use App\Domain\Tickets\States\TicketStatus;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Zones\Models\Zone;
use Carbon\CarbonImmutable;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\ModelStates\HasStates;

/**
 * Demande d'intervention. Voir §8.1 pour le cycle de vie et §8.2 pour le prix.
 *
 * Les colonnes de prix sont un instantané pris à la publication : rejouer le
 * calcul plus tard ne doit jamais changer ce qui a été annoncé au client.
 *
 * Les trois clés étrangères effaçables sont déclarées nullables : la migration
 * les pose en `nullOnDelete`, et un ticket dont la zone ou l'adresse a été
 * supprimée doit rester lisible. Sans ces annotations, l'analyse statique croit
 * ces relations toujours présentes et laisse passer un accès à null.
 *
 * @property TicketStatus $state
 * @property int|null $technician_id
 * @property int|null $zone_id
 * @property int|null $address_id
 * @property int|null $parent_ticket_id
 * @property bool $is_warranty
 * @property CarbonImmutable|null $warranty_until
 * @property-read User|null $technician
 * @property-read Zone|null $zone
 * @property-read Address|null $address
 * @property-read Ticket|null $parent
 */
final class Ticket extends Model
{
    use \App\Support\Concerns\RouteParIdOpaque;
    use HasStates;

    protected $fillable = [
        'reference', 'parent_ticket_id', 'is_warranty', 'warranty_until',
        'client_id', 'technician_id', 'service_id', 'zone_id', 'state',
        'address_id', 'address_snapshot', 'location', 'problem_description', 'photos',
        'distance_km', 'distance_is_estimated',
        'base_price_gnf', 'short_trip_uplift_gnf', 'travel_fee_gnf', 'extra_fee_gnf',
        'proposed_extra_fee_gnf', 'total_gnf',
        'commission_gnf', 'technician_net_gnf', 'commission_rate',
        'diagnosis', 'diagnosis_photos', 'cancellation_reason', 'cancellation_fee_gnf',
        'published_at', 'accepted_at', 'en_route_at', 'arrived_at',
        'started_at', 'completed_at', 'paid_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => TicketStatus::class,
            'parent_ticket_id' => 'integer',
            'is_warranty' => 'boolean',
            'warranty_until' => 'datetime',
            'address_snapshot' => 'array',
            'location' => Point::class,
            'photos' => 'array',
            'diagnosis_photos' => 'array',
            'distance_km' => 'float',
            'distance_is_estimated' => 'boolean',
            'base_price_gnf' => 'integer',
            'short_trip_uplift_gnf' => 'integer',
            'travel_fee_gnf' => 'integer',
            'extra_fee_gnf' => 'integer',
            'proposed_extra_fee_gnf' => 'integer',
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

    /**
     * Intervention d'origine, quand ce ticket est un retour sous garantie (#4).
     *
     * @return BelongsTo<Ticket, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'parent_ticket_id');
    }

    /**
     * Les retours sous garantie rattachés à cette intervention.
     *
     * @return HasMany<Ticket, $this>
     */
    public function warranties(): HasMany
    {
        return $this->hasMany(Ticket::class, 'parent_ticket_id');
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

    /**
     * Qui a le droit de toucher à ce ticket.
     *
     * La règle était recopiée à six endroits — trois contrôleurs et trois
     * actions du domaine — avec chaque fois le même transtypage. Une seule
     * copie ici, appelée par la Policy comme par le domaine : c'est le genre de
     * règle dont deux versions finissent par diverger, et dont la divergence
     * ouvre l'accès aux données de quelqu'un d'autre.
     */
    public function estPartiePrenante(?User $utilisateur): bool
    {
        if ($utilisateur === null) {
            return false;
        }

        $id = (int) $utilisateur->getKey();

        return (int) $this->client_id === $id || (int) $this->technician_id === $id;
    }

    public function estLeClient(?User $utilisateur): bool
    {
        return $utilisateur !== null && (int) $this->client_id === (int) $utilisateur->getKey();
    }

    public function estLeTechnicien(?User $utilisateur): bool
    {
        return $utilisateur !== null
            && $this->technician_id !== null
            && (int) $this->technician_id === (int) $utilisateur->getKey();
    }

    /**
     * La garantie court-elle encore sur cette intervention (#4) ?
     *
     * Un retour sous garantie n'ouvre pas lui-même de garantie : sinon un
     * technicien de mauvaise foi pourrait enchaîner les retours indéfiniment.
     * La fenêtre est figée dans `warranty_until` à la clôture, pas relue depuis
     * le réglage : raccourcir le délai en back-office ne retire pas une
     * garantie déjà promise.
     */
    public function garantieActive(): bool
    {
        return ! $this->is_warranty
            && $this->warranty_until !== null
            && $this->warranty_until->isFuture()
            && $this->state->etat() === TicketState::CLOTUREE;
    }

    /** Délai entre la publication et l'acceptation, en secondes. */
    public function acceptanceDelaySeconds(): ?int
    {
        if ($this->published_at === null || $this->accepted_at === null) {
            return null;
        }

        return (int) $this->published_at->diffInSeconds($this->accepted_at);
    }

    /**
     * Contrôle d'intégrité du prix : le total est toujours la somme de ses
     * quatre composantes. Utilisé par les tests et par la supervision.
     */
    public function priceIsCoherent(): bool
    {
        return $this->total_gnf === $this->base_price_gnf
            + $this->short_trip_uplift_gnf
            + $this->travel_fee_gnf
            + $this->extra_fee_gnf;
    }
}
