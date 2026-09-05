<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Service;
use DomainException;
use Illuminate\Support\Str;

/**
 * Création et modification d'une prestation du catalogue (§6).
 *
 * Le prix de référence n'est jamais rétroactif : les tickets déjà publiés
 * portent leur propre instantané (ADR-0013). Modifier une prestation ne change
 * donc que les demandes à venir — l'action le rappelle dans sa trace d'audit.
 */
final class UpsertService
{
    /** @param  array<string, mixed>  $donnees */
    public function execute(array $donnees, ?Service $service = null): Service
    {
        $prix = (int) $donnees['base_price_gnf'];

        if ($prix <= 0) {
            throw new DomainException('Le prix de référence doit être supérieur à zéro.');
        }

        $attributs = [
            'category_id' => (int) $donnees['category_id'],
            'name' => trim((string) $donnees['name']),
            'description' => $donnees['description'] ?? null,
            'included' => $this->lignes($donnees['included'] ?? null),
            'excluded' => $this->lignes($donnees['excluded'] ?? null),
            'base_price_gnf' => $prix,
            'estimated_duration_min' => (int) $donnees['estimated_duration_min'],
            'is_active' => (bool) ($donnees['is_active'] ?? true),
            'sort_order' => (int) ($donnees['sort_order'] ?? 0),
        ];

        if ($service === null) {
            $attributs['slug'] = $this->slugUnique($attributs['name']);

            return Service::query()->create($attributs);
        }

        $service->fill($attributs)->save();

        return $service;
    }

    /**
     * Les listes « inclus » et « non inclus » sont saisies une ligne par élément :
     * c'est plus rapide que d'ajouter des champs un par un, et c'est ainsi
     * qu'elles s'affichent dans l'application mobile.
     *
     * @return array<int, string>|null
     */
    private function lignes(?string $texte): ?array
    {
        if ($texte === null || trim($texte) === '') {
            return null;
        }

        $lignes = array_values(array_filter(
            array_map(trim(...), preg_split('/\R/', $texte) ?: []),
            static fn (string $ligne): bool => $ligne !== '',
        ));

        return $lignes === [] ? null : $lignes;
    }

    private function slugUnique(string $nom): string
    {
        $base = Str::slug($nom);
        $slug = $base;
        $suffixe = 1;

        while (Service::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffixe);
        }

        return $slug;
    }
}
