<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Models;

use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Address extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'label', 'formatted_address', 'landmark', 'location', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'location' => Point::class,
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Copie figée sur le ticket. L'adresse peut être supprimée par le client :
     * l'historique de l'intervention doit rester lisible sans elle.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'label' => $this->label,
            'formatted_address' => $this->formatted_address,
            'landmark' => $this->landmark,
            'latitude' => $this->location?->getLatitude(),
            'longitude' => $this->location?->getLongitude(),
        ];
    }
}
