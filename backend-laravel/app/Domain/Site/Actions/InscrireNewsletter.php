<?php

declare(strict_types=1);

namespace App\Domain\Site\Actions;

use App\Domain\Site\Models\SiteAbonne;

/**
 * Inscription à la lettre d'information.
 *
 * Idempotente : une adresse déjà inscrite n'est ni dupliquée ni signalée. Le
 * site répond la même chose dans les deux cas — le formulaire ne doit pas
 * servir à savoir si une adresse figure déjà dans la liste.
 */
final class InscrireNewsletter
{
    public function execute(string $email, ?string $source = null): SiteAbonne
    {
        return SiteAbonne::query()->firstOrCreate(
            ['email' => mb_strtolower(trim($email))],
            ['source' => $source === null ? null : mb_substr($source, 0, 40)],
        );
    }
}
