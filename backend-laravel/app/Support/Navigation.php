<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Accounts\Models\AdminUser;

/**
 * Lecture de la navigation du back-office (config/backoffice.php), filtrée par
 * les permissions de l'administrateur connecté.
 */
final class Navigation
{
    /**
     * Groupes et items visibles par cet administrateur. Un groupe dont plus
     * aucun item n'est autorisé disparaît entièrement.
     *
     * @return array<int, array{groupe: string, items: array<int, array<string, mixed>>}>
     */
    public static function forAdmin(?AdminUser $admin): array
    {
        $groupes = [];

        foreach (self::all() as $groupe) {
            $items = array_values(array_filter(
                $groupe['items'],
                static fn (array $item): bool => $admin?->can($item['permission']) ?? false,
            ));

            if ($items !== []) {
                $groupes[] = ['groupe' => $groupe['groupe'], 'items' => $items];
            }
        }

        return $groupes;
    }

    /** @return array<int, array{groupe: string, items: array<int, array<string, mixed>>}> */
    public static function all(): array
    {
        /** @var array<int, array{groupe: string, items: array<int, array<string, mixed>>}> $navigation */
        $navigation = config('backoffice.navigation', []);

        return $navigation;
    }
}
