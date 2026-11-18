<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Data\PaymentChannel;
use App\Domain\Payments\Data\PaymentMethod;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Concerns\RouteParIdOpaque;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Paiement d'une intervention, direct (espèces ou transfert Mobile Money au
 * technicien) ou via l'application (séquestre).
 *
 * `provider_ref` porte la contrainte UNIQUE qui rend le webhook idempotent : un
 * événement rejoué ne peut pas capturer deux fois (§8.4). Chaque étape est
 * écrite dans `events` (ADR-0047).
 */
final class Payment extends Model
{
    use RouteParIdOpaque;

    /** Statuts d'un paiement encore vivant : un second ne peut pas s'ouvrir. */
    public const STATUTS_OUVERTS = [
        PaymentStatus::ATTENTE_CONFIRMATION,
        PaymentStatus::CONTESTEE,
        PaymentStatus::CONFIRMEE,
        PaymentStatus::CAPTUREE,
        PaymentStatus::LIBEREE,
    ];

    protected $fillable = [
        'ticket_id', 'provider', 'provider_ref', 'channel', 'method', 'payer_phone',
        'amount_gnf', 'currency', 'status', 'failure_reason',
        'webhook_payload', 'captured_at', 'released_at', 'auto_release_at',
        'declared_by', 'transaction_ref', 'client_confirmed_at', 'technician_confirmed_at',
        'contested_at', 'contested_by', 'contest_reason', 'reminded_at',
    ];

    protected $hidden = ['webhook_payload'];

    protected function casts(): array
    {
        return [
            'channel' => PaymentChannel::class,
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'declared_by' => ActorType::class,
            'contested_by' => ActorType::class,
            'amount_gnf' => 'integer',
            'webhook_payload' => 'array',
            'captured_at' => 'datetime',
            'released_at' => 'datetime',
            'auto_release_at' => 'datetime',
            'client_confirmed_at' => 'datetime',
            'technician_confirmed_at' => 'datetime',
            'contested_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return HasMany<PaymentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->orderBy('id');
    }

    /**
     * Fonds capturés dont l'échéance de libération automatique est atteinte.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDueForRelease(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::CAPTUREE)
            ->whereNotNull('auto_release_at')
            ->where('auto_release_at', '<=', now());
    }

    public function isInEscrow(): bool
    {
        return $this->status === PaymentStatus::CAPTUREE;
    }

    public function estDirect(): bool
    {
        return $this->channel === PaymentChannel::DIRECT;
    }

    /**
     * La partie dont on attend la confirmation, ou `null` si plus rien n'est
     * attendu d'elle. Le client passe en premier : c'est sa parole qui engage
     * (ADR-0047).
     */
    public function attenteDe(): ?ActorType
    {
        if ($this->status !== PaymentStatus::ATTENTE_CONFIRMATION) {
            return null;
        }

        if ($this->client_confirmed_at === null) {
            return ActorType::CLIENT;
        }

        return $this->technician_confirmed_at === null ? ActorType::TECHNICIEN : null;
    }
}
