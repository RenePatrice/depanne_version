<?php

declare(strict_types=1);

namespace App\Support\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Produit le fichier d'export d'une table du back-office.
 *
 * Les exports sont **calculés côté serveur** sur la requête filtrée. En mode
 * serveur, les boutons d'export de DataTables n'exporteraient que la page
 * affichée — vingt-cinq lignes sur des milliers — ce qui donnerait un fichier
 * silencieusement faux.
 */
final class ExporteurTable
{
    /** Au-delà, un PDF devient illisible et lourd : on renvoie vers CSV ou Excel. */
    public const LIMITE_PDF = 2000;

    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $requete
     * @param  array<string, string>  $colonnes
     * @param  callable(TModel): array<string, mixed>  $ligne
     */
    public function repond(
        string $format,
        Builder $requete,
        array $colonnes,
        callable $ligne,
        string $nomFichier,
        string $titre,
    ): Response {
        $horodatage = now()->format('Y-m-d_H-i');
        $nom = "{$nomFichier}_{$horodatage}";

        return match ($format) {
            'csv' => Excel::download(new TableExport($requete, $colonnes, $ligne), "{$nom}.csv", \Maatwebsite\Excel\Excel::CSV),
            'xlsx' => Excel::download(new TableExport($requete, $colonnes, $ligne), "{$nom}.xlsx"),
            'pdf' => $this->pdf($requete, $colonnes, $ligne, $nom, $titre),
            default => abort(422, 'Format d\'export inconnu.'),
        };
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $requete
     * @param  array<string, string>  $colonnes
     * @param  callable(TModel): array<string, mixed>  $ligne
     */
    private function pdf(Builder $requete, array $colonnes, callable $ligne, string $nom, string $titre): Response
    {
        $lignes = $requete->limit(self::LIMITE_PDF)->get()
            ->map(static fn (Model $modele): array => $ligne($modele))
            ->all();

        $total = (clone $requete)->toBase()->getCountForPagination();

        $pdf = Pdf::loadView('exports.table', [
            'titre' => $titre,
            'colonnes' => $colonnes,
            'lignes' => $lignes,
            'total' => $total,
            'tronque' => $total > self::LIMITE_PDF,
            'limite' => self::LIMITE_PDF,
            'genereLe' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("{$nom}.pdf");
    }
}
