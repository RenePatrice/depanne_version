<?php

declare(strict_types=1);

namespace App\Domain\Site\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Membre de l'équipe présenté sur le site vitrine. Édité en back-office
 * (`site.gerer`) ; chaque modification laisse une trace dans `activity_log`.
 */
final class SiteMembre extends Model
{
    use LogsActivity;

    protected $table = 'site_membres';

    protected $fillable = ['nom', 'role', 'bio', 'photo_path', 'lien_url', 'ordre', 'actif'];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'ordre' => 'integer',
        ];
    }

    /**
     * URL publique de la photo, ou `null` si aucune n'a été déposée — la vue
     * retombe alors sur un pastille d'initiales. Jamais d'exception ici : une
     * photo manquante ne doit pas casser la page d'accueil.
     */
    public function photoUrl(): ?string
    {
        if ($this->photo_path === null || $this->photo_path === '') {
            return null;
        }

        return Storage::disk('public')->url($this->photo_path);
    }

    /** Initiales pour la pastille de repli (deux lettres au plus). */
    public function initiales(): string
    {
        $mots = preg_split('/\s+/', trim($this->nom)) ?: [];
        $lettres = array_map(static fn (string $m): string => mb_substr($m, 0, 1), $mots);

        return mb_strtoupper(implode('', array_slice($lettres, 0, 2)));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('vitrine')
            ->logOnly(['nom', 'role', 'bio', 'photo_path', 'lien_url', 'ordre', 'actif'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
