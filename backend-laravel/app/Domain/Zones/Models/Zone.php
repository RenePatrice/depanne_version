<?php

declare(strict_types=1);

namespace App\Domain\Zones\Models;

use App\Domain\Tickets\Models\Ticket;
use Clickbar\Magellan\Data\Geometries\Polygon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Zone de déploiement et sa grille tarifaire de déplacement (§8.2).
 * Les trois paramètres sont modifiables en back-office ; ils sont figés sur le
 * ticket au moment de la publication.
 */
final class Zone extends Model
{
    use LogsActivity;

    protected $fillable = [
        'code', 'name', 'commune', 'boundary',
        'base_travel_fee_gnf', 'price_per_km_gnf', 'included_km', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'boundary' => Polygon::class,
            'base_travel_fee_gnf' => 'integer',
            'price_per_km_gnf' => 'integer',
            'included_km' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Trace des modifications, lisible dans le journal d'audit (§6). */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('zones')
            ->logOnly(['name', 'commune', 'base_travel_fee_gnf', 'price_per_km_gnf', 'included_km', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $evenement): string => match ($evenement) {
                'created' => 'Zone créée : '.$this->name,
                'updated' => 'Zone modifiée : '.$this->name,
                'deleted' => 'Zone supprimée : '.$this->name,
                default => 'Zone '.$evenement,
            });
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @param  Builder<Zone>  $query
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
