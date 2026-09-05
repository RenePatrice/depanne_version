<?php

declare(strict_types=1);

namespace App\Support\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export d'une table du back-office.
 *
 * Il reçoit **la requête déjà filtrée** par l'écran : un export qui ignorerait
 * les filtres affichés produirait un fichier que personne ne pourrait
 * rapprocher de ce qu'il a sous les yeux.
 *
 * Les montants restent des entiers : le tableur doit pouvoir additionner.
 *
 * @template TModel of Model
 *
 * @implements WithMapping<TModel>
 */
final class TableExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  Builder<TModel>  $requete
     * @param  array<string, string>  $colonnes  clé technique => en-tête affiché
     * @param  callable(TModel): array<string, mixed>  $ligne
     */
    public function __construct(
        private readonly Builder $requete,
        private readonly array $colonnes,
        private readonly mixed $ligne,
    ) {}

    /** @return Builder<TModel> */
    public function query(): Builder
    {
        return $this->requete;
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return array_values($this->colonnes);
    }

    /** @return array<int, mixed> */
    public function map(mixed $row): array
    {
        $valeurs = ($this->ligne)($row);

        // L'ordre des colonnes fait foi, pas l'ordre du tableau retourné.
        return array_map(
            static fn (string $cle): mixed => $valeurs[$cle] ?? null,
            array_keys($this->colonnes),
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
