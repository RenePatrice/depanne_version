<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Data\Specialty;
use App\Domain\Catalog\Models\ServiceCategory;
use DomainException;

/**
 * Création et modification d'une catégorie.
 *
 * Le code d'une catégorie est aussi la spécialité déclarée par les techniciens
 * (`technician_profiles.specialties`). Le changer casserait le filtrage du
 * matching pour tous les techniciens existants : il est donc figé après création.
 */
final class UpsertServiceCategory
{
    /** @param  array<string, mixed>  $donnees */
    public function execute(array $donnees, ?ServiceCategory $categorie = null): ServiceCategory
    {
        $attributs = [
            'name' => trim((string) $donnees['name']),
            'description' => $donnees['description'] ?? null,
            'icon' => $donnees['icon'] ?? 'bi-tools',
            'color' => $donnees['color'] ?? '#1B6FF3',
            'sort_order' => (int) ($donnees['sort_order'] ?? 0),
            'is_active' => (bool) ($donnees['is_active'] ?? true),
        ];

        if ($categorie !== null) {
            $categorie->fill($attributs)->save();

            return $categorie;
        }

        $code = Specialty::tryFrom((string) ($donnees['code'] ?? ''));

        if ($code === null) {
            throw new DomainException(
                'Le code de catégorie doit correspondre à une spécialité connue : PLOMBERIE ou ELECTRICITE.'
            );
        }

        return ServiceCategory::query()->create([...$attributs, 'code' => $code]);
    }
}
