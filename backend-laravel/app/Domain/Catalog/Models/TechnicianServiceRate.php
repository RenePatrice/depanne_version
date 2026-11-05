<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Accounts\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tarif propre d'un technicien pour une prestation (#2).
 *
 * Sa seule raison d'être est de **remplacer le prix catalogue** pour ce
 * technicien et cette prestation. Sans ligne, le catalogue fait foi : le
 * catalogue est un défaut, le technicien reste libre de fixer son prix.
 */
final class TechnicianServiceRate extends Model
{
    protected $fillable = ['user_id', 'service_id', 'price_gnf'];

    protected function casts(): array
    {
        return ['price_gnf' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
