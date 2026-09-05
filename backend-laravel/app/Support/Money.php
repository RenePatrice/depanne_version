<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formatage des montants (§0.6). Le franc guinéen n'a pas de subdivision
 * utilisée : jamais de décimale, séparateur de milliers par espace insécable
 * fine pour que « 100 000 GNF » ne se coupe pas en fin de ligne.
 */
final class Money
{
    public const DEVISE = 'GNF';

    private const SEPARATEUR = "\u{202F}";

    /** 100000 → « 100 000 GNF ». */
    public static function format(?int $montant, bool $avecDevise = true): string
    {
        if ($montant === null) {
            return '—';
        }

        $nombre = number_format($montant, 0, ',', self::SEPARATEUR);

        return $avecDevise ? $nombre.self::SEPARATEUR.self::DEVISE : $nombre;
    }

    /** Version courte pour les axes de graphique : 1 250 000 → « 1,25 M ». */
    public static function abrege(int $montant): string
    {
        return match (true) {
            $montant >= 1_000_000 => number_format($montant / 1_000_000, 2, ',', self::SEPARATEUR).' M',
            $montant >= 1_000 => number_format($montant / 1_000, 0, ',', self::SEPARATEUR).' k',
            default => (string) $montant,
        };
    }

    /** Arrondi au multiple supérieur, utilisé par les frais de déplacement (§8.2). */
    public static function arrondiSuperieur(int $montant, int $pas): int
    {
        return $pas <= 0 ? $montant : (int) (ceil($montant / $pas) * $pas);
    }
}
