<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Service;

/**
 * Active ou désactive une prestation. Une prestation désactivée disparaît du
 * catalogue mobile mais reste attachée aux tickets passés : on ne supprime
 * jamais une prestation qui a servi.
 */
final class ToggleService
{
    public function execute(Service $service): Service
    {
        $service->forceFill(['is_active' => ! $service->is_active])->save();

        return $service;
    }
}
