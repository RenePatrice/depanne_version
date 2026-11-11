<?php

declare(strict_types=1);

namespace App\Domain\Site\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Message déposé par le formulaire de contact du site vitrine. Lu et marqué
 * « traité » en back-office (`site.gerer`) ; jamais supprimé.
 */
final class SiteMessage extends Model
{
    use \App\Support\Concerns\RouteParIdOpaque;

    /** Sujets proposés au visiteur : clé stockée => libellé affiché. */
    public const SUJETS = [
        'question' => 'Une question sur le service',
        'technicien' => 'Devenir technicien partenaire',
        'partenariat' => 'Partenariat ou presse',
        'probleme' => 'Signaler un problème',
        'autre' => 'Autre',
    ];

    protected $table = 'site_messages';

    protected $fillable = ['nom', 'telephone', 'email', 'sujet', 'message', 'traite_at'];

    protected function casts(): array
    {
        return ['traite_at' => 'datetime'];
    }

    public function libelleSujet(): string
    {
        return self::SUJETS[$this->sujet] ?? 'Autre';
    }

    public function estTraite(): bool
    {
        return $this->traite_at !== null;
    }
}
