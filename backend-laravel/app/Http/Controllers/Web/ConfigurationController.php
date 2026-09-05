<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Settings\Actions\UpdateSettings;
use App\Domain\Settings\Models\AppSetting;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Configuration de la plateforme (§6).
 *
 * Tout ce qui est réglable ici commande le calcul de prix, le matching ou la
 * répartition financière. Le contrôleur ne fait que présenter et transmettre :
 * la validation métier — bornes, cohérences croisées, signe des pondérations —
 * vit dans `UpdateSettings`.
 */
final class ConfigurationController extends Controller
{
    /** Ordre et intitulés des onglets, du plus structurant au plus éditorial. */
    private const GROUPES = [
        'matching' => ['libelle' => 'Matching', 'icone' => 'bi-diagram-3',
            'resume' => 'Rayons, délai de réponse et pondérations du score. Ce sont eux qui décident quel technicien est sollicité, et dans quel ordre.'],
        'finances' => ['libelle' => 'Finances', 'icone' => 'bi-percent',
            'resume' => 'Commission, séquestre et seuil de retrait.'],
        'tarifs' => ['libelle' => 'Tarifs', 'icone' => 'bi-calculator',
            'resume' => "Arrondi des frais de déplacement et facteur de repli quand l'API de cartographie ne répond pas."],
        'litiges' => ['libelle' => 'Litiges', 'icone' => 'bi-exclamation-diamond',
            'resume' => 'Fenêtre pendant laquelle un client peut ouvrir une réclamation.'],
        'notifications' => ['libelle' => 'Notifications', 'icone' => 'bi-bell',
            'resume' => "Textes envoyés à chaque étape. Les variables entre accolades sont remplacées à l'envoi."],
        'contenus' => ['libelle' => 'Textes légaux', 'icone' => 'bi-file-text',
            'resume' => "Affichés à l'inscription et dans le profil de l'application mobile."],
    ];

    public function index(): View
    {
        $parametres = AppSetting::query()->orderBy('key')->get()->groupBy('group');

        return view('configuration.index', [
            'groupes' => collect(self::GROUPES)
                ->map(fn (array $meta, string $cle): array => [
                    ...$meta,
                    'cle' => $cle,
                    'parametres' => $parametres->get($cle, collect()),
                ])
                ->filter(fn (array $groupe): bool => $groupe['parametres']->isNotEmpty())
                ->values(),
        ]);
    }

    public function enregistrer(Request $request, UpdateSettings $action): RedirectResponse
    {
        /** @var array<string, mixed> $valeurs */
        $valeurs = $request->input('parametres', []);

        // Les cases à cocher absentes valent « faux » : sans ce rattrapage,
        // décocher une option ne l'enregistrerait jamais.
        foreach (AppSetting::query()->where('type', 'boolean')->pluck('key') as $cle) {
            $valeurs[$cle] ??= false;
        }

        try {
            $modifiees = $action->execute($valeurs, (int) $request->user('admin')?->id);
        } catch (DomainException $e) {
            return back()->withErrors(['parametres' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('configuration')
            ->with('statut', $modifiees === []
                ? 'Aucune modification à enregistrer.'
                : count($modifiees).' paramètre'.(count($modifiees) > 1 ? 's' : '').' mis à jour.');
    }
}
