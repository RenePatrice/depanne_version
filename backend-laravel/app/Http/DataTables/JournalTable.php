<?php

declare(strict_types=1);

namespace App\Http\DataTables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;

/**
 * Journal d'audit (§6) : toutes les actions administrateur, horodatées,
 * filtrables, **non modifiables**. Aucune écriture n'est exposée — c'est une
 * trace, pas un registre éditable.
 */
final class JournalTable
{
    public const COLONNES_EXPORT = [
        'date' => 'Date',
        'journal' => 'Journal',
        'description' => 'Action',
        'auteur' => 'Auteur',
        'cible' => 'Objet concerné',
        'changements' => 'Modifications',
    ];

    /** Journaux alimentés par le domaine, pour le filtre de l'écran. */
    public const JOURNAUX = [
        'authentification' => 'Connexions',
        'tickets' => 'Tickets',
        'comptes' => 'Comptes clients',
        'techniciens' => 'Dossiers techniciens',
        'catalogue' => 'Catalogue',
        'zones' => 'Zones',
        'configuration' => 'Configuration',
    ];

    /** @return Builder<Activity> */
    public function requete(Request $request): Builder
    {
        return Activity::query()
            ->with('causer')
            ->when($request->filled('journal'), fn (Builder $q) => $q->where('log_name', $request->string('journal')))
            ->when($request->filled('du'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->date('du')))
            ->when($request->filled('au'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->date('au')));
    }

    public function json(Request $request): JsonResponse
    {
        return DataTables::eloquent($this->requete($request))
            ->addColumn('journal', static function (Activity $activite): string {
                $nom = (string) $activite->log_name;

                return sprintf(
                    '<span class="dm-badge dm-badge--secondary">%s</span>',
                    e(self::JOURNAUX[$nom] ?? $nom),
                );
            })
            ->addColumn('auteur', static function (Activity $activite): string {
                $causer = $activite->causer;

                if ($causer === null) {
                    return '<span class="text-body-secondary small">Système</span>';
                }

                return e((string) ($causer->getAttribute('full_name') ?? $causer->getKey()));
            })
            ->addColumn('cible', static function (Activity $activite): string {
                if ($activite->subject_type === null) {
                    return '<span class="text-body-secondary small">—</span>';
                }

                return sprintf(
                    '<span class="font-monospace small">%s #%s</span>',
                    e(class_basename((string) $activite->subject_type)),
                    e((string) $activite->subject_id),
                );
            })
            ->addColumn('changements', static function (Activity $activite): string {
                // Depuis la version 5, spatie range l'avant et l'après dans une
                // colonne dédiée `attribute_changes` ; `properties` ne contient
                // plus que ce que le domaine y a mis explicitement.
                $changes = $activite->attribute_changes?->toArray() ?? [];
                $proprietes = $activite->properties->toArray();

                if (isset($changes['attributes'])) {
                    $proprietes = $changes;
                }

                if (isset($proprietes['attributes'])) {
                    $apres = (array) $proprietes['attributes'];
                    $avant = (array) ($proprietes['old'] ?? []);

                    $lignes = [];

                    foreach ($apres as $champ => $valeur) {
                        $ancien = $avant[$champ] ?? null;

                        if ($ancien === $valeur) {
                            continue;
                        }

                        $lignes[] = sprintf(
                            '<span class="font-monospace">%s</span> : %s → <strong>%s</strong>',
                            e((string) $champ),
                            e(self::apercu($ancien)),
                            e(self::apercu($valeur)),
                        );
                    }

                    return $lignes === []
                        ? '<span class="text-body-secondary small">—</span>'
                        : implode('<br>', array_slice($lignes, 0, 4));
                }

                if (isset($proprietes['avant'], $proprietes['apres'])) {
                    return sprintf(
                        '%s → <strong>%s</strong>',
                        e(self::apercu($proprietes['avant'])),
                        e(self::apercu($proprietes['apres'])),
                    );
                }

                if (isset($proprietes['motif'])) {
                    return '<span class="small">« '.e((string) $proprietes['motif']).' »</span>';
                }

                return '<span class="text-body-secondary small">—</span>';
            })
            ->editColumn('created_at', static fn (Activity $a): string => $a->created_at?->translatedFormat('d/m/Y H:i:s') ?? '—')
            ->filterColumn('auteur', static function (Builder $query, string $terme): void {
                $query->whereHasMorph('causer', '*', fn ($c) => $c->where('full_name', 'ilike', "%{$terme}%"));
            })
            ->filterColumn('journal', static fn (): null => null)
            ->filterColumn('cible', static fn (): null => null)
            ->filterColumn('changements', static fn (): null => null)
            ->rawColumns(['journal', 'auteur', 'cible', 'changements'])
            ->toJson();
    }

    /** @return array<string, string|null> */
    public function ligneExport(Activity $activite): array
    {
        $proprietes = $activite->attribute_changes?->toArray() ?? $activite->properties->toArray();
        $changements = [];

        if (isset($proprietes['attributes'])) {
            $apres = (array) $proprietes['attributes'];
            $avant = (array) ($proprietes['old'] ?? []);

            foreach ($apres as $champ => $valeur) {
                if (($avant[$champ] ?? null) !== $valeur) {
                    $changements[] = $champ.' : '.self::apercu($avant[$champ] ?? null).' -> '.self::apercu($valeur);
                }
            }
        }

        $causer = $activite->causer;

        return [
            'date' => $activite->created_at?->format('Y-m-d H:i:s'),
            'journal' => self::JOURNAUX[(string) $activite->log_name] ?? (string) $activite->log_name,
            'description' => $activite->description,
            'auteur' => $causer !== null ? (string) ($causer->getAttribute('full_name') ?? $causer->getKey()) : 'Système',
            'cible' => $activite->subject_type !== null
                ? class_basename((string) $activite->subject_type).' #'.$activite->subject_id
                : null,
            'changements' => $changements === [] ? null : implode(' | ', $changements),
        ];
    }

    /** Rend une valeur lisible sur une ligne de tableau, quelle que soit sa forme. */
    private static function apercu(mixed $valeur): string
    {
        return match (true) {
            $valeur === null => '—',
            is_bool($valeur) => $valeur ? 'oui' : 'non',
            is_array($valeur) => json_encode($valeur, JSON_UNESCAPED_UNICODE) ?: '—',
            default => mb_strimwidth((string) $valeur, 0, 60, '…'),
        };
    }
}
