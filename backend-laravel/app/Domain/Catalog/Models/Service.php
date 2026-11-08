<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Data\ServiceType;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Prestation à prix fixe du catalogue. `base_price_gnf` est le prix de
 * référence hors déplacement ; il est recopié sur le ticket à la publication
 * pour que la grille puisse évoluer sans toucher aux tickets existants (§8.2).
 */
final class Service extends Model
{
    use \App\Support\Concerns\RouteParIdOpaque;
    use LogsActivity;

    protected $fillable = [
        'category_id', 'slug', 'name', 'description', 'type', 'included', 'excluded',
        'base_price_gnf', 'estimated_duration_min', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => ServiceType::class,
            'included' => 'array',
            'excluded' => 'array',
            'base_price_gnf' => 'integer',
            'estimated_duration_min' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<TechnicianServiceRate, $this> */
    public function rates(): HasMany
    {
        return $this->hasMany(TechnicianServiceRate::class);
    }

    /** Le tarif d'un technicien pour cette prestation, ou le prix catalogue. */
    public function priceForTechnician(int $technicianId): int
    {
        $rate = $this->relationLoaded('rates')
            ? $this->rates->firstWhere('user_id', $technicianId)
            : $this->rates()->where('user_id', $technicianId)->first();

        return $rate instanceof TechnicianServiceRate
            ? (int) $rate->price_gnf
            : (int) $this->base_price_gnf;
    }

    /**
     * Toute modification de prix ou de description laisse une trace : c'est
     * l'« historique des modifications » du §6, et il alimente le journal d'audit.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalogue')
            ->logOnly(['name', 'description', 'type', 'base_price_gnf', 'estimated_duration_min', 'is_active', 'category_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $evenement): string => match ($evenement) {
                'created' => 'Prestation créée : '.$this->name,
                'updated' => 'Prestation modifiée : '.$this->name,
                'deleted' => 'Prestation supprimée : '.$this->name,
                default => 'Prestation '.$evenement,
            });
    }

    /** @return BelongsTo<ServiceCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @param  Builder<Service>  $query
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
