<?php

declare(strict_types=1);

namespace App\Domain\Site\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un événement de fréquentation du site vitrine (une visite, un clic).
 *
 * Il ne se modifie jamais : `created_at` seul, pas d'`updated_at`. C'est la
 * matière première des agrégats de fréquentation au tableau de bord.
 */
final class SiteEvenement extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'site_evenements';

    protected $fillable = ['type', 'chemin', 'referent', 'meta', 'ip_hash', 'created_at'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
