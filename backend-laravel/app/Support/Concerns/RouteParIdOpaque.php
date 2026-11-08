<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Support\OpaqueId;
use Illuminate\Database\Eloquent\Model;

/**
 * Lie le modèle aux URLs du **back-office** par un identifiant OPAQUE (non
 * séquentiel, non devinable) au lieu de son ID entier.
 *
 *  - `getRouteKey()` : les liens générés (`route($nom, $modele)`) portent le
 *    jeton opaque.
 *  - `resolveRouteBinding()` : décode le jeton à l'entrée ; un jeton invalide
 *    donne 404.
 *
 * L'**API mobile** (`api/*`) continue d'utiliser les IDs entiers : elle est
 * déjà protégée par ses Policies et le 404 systématique (ADR-0035), et change
 * de clé de route casserait le client Flutter.
 *
 * @mixin Model
 */
trait RouteParIdOpaque
{
    public function getRouteKey(): string
    {
        return OpaqueId::encode((int) $this->getKey());
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if (request()->is('api/*')) {
            return parent::resolveRouteBinding($value, $field);
        }

        $id = OpaqueId::decode((string) $value);

        if ($id === null) {
            return null;
        }

        return $this->newQuery()->where($field ?? $this->getKeyName(), $id)->first();
    }

    public function resolveChildRouteBinding($childType, $value, $field = null): ?Model
    {
        if (request()->is('api/*')) {
            return parent::resolveChildRouteBinding($childType, $value, $field);
        }

        $id = OpaqueId::decode((string) $value);

        return $id === null
            ? null
            : parent::resolveChildRouteBinding($childType, (string) $id, $field);
    }
}
