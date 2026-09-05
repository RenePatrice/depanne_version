<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Data\Specialty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class ServiceCategory extends Model
{
    use LogsActivity;

    protected $fillable = ['code', 'name', 'description', 'icon', 'color', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'code' => Specialty::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Trace des modifications, lisible dans le journal d'audit (§6). */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalogue')
            ->logOnly(['name', 'description', 'icon', 'color', 'is_active', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $evenement): string => match ($evenement) {
                'created' => 'Catégorie créée : '.$this->name,
                'updated' => 'Catégorie modifiée : '.$this->name,
                'deleted' => 'Catégorie supprimée : '.$this->name,
                default => 'Catégorie '.$evenement,
            });
    }

    /** @return HasMany<Service, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }

    /**
     * @param  Builder<ServiceCategory>  $query
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
