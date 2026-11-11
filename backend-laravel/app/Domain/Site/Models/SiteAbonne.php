<?php

declare(strict_types=1);

namespace App\Domain\Site\Models;

use Illuminate\Database\Eloquent\Model;

/** Adresse inscrite à la lettre d'information du site vitrine. */
final class SiteAbonne extends Model
{
    protected $table = 'site_abonnes';

    protected $fillable = ['email', 'source'];
}
