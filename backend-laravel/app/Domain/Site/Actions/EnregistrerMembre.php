<?php

declare(strict_types=1);

namespace App\Domain\Site\Actions;

use App\Domain\Site\Models\SiteMembre;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Crée ou met à jour un membre de l'équipe du site vitrine, avec sa photo.
 *
 * Le remplacement d'une photo supprime l'ancienne : un fichier orphelin par
 * édition finirait par encombrer le disque. Le fichier est stocké dans l'espace
 * public (servi par le lien `storage`), sous un nom aléatoire.
 */
final class EnregistrerMembre
{
    /** @param array{nom: string, role: string, bio?: ?string, lien_url?: ?string, ordre?: int, actif?: bool} $donnees */
    public function execute(array $donnees, ?UploadedFile $photo = null, ?SiteMembre $membre = null): SiteMembre
    {
        $membre ??= new SiteMembre();

        $membre->fill([
            'nom' => $donnees['nom'],
            'role' => $donnees['role'],
            'bio' => $donnees['bio'] ?? null,
            'lien_url' => $donnees['lien_url'] ?? null,
            'ordre' => $donnees['ordre'] ?? $membre->ordre ?? 0,
            'actif' => $donnees['actif'] ?? $membre->actif ?? true,
        ]);

        if ($photo !== null) {
            $ancienne = $membre->photo_path;
            $membre->photo_path = $photo->store('equipe', (string) config('filesystems.media'));

            if ($ancienne !== null && $ancienne !== '') {
                Storage::disk((string) config('filesystems.media'))->delete($ancienne);
            }
        }

        $membre->save();

        return $membre;
    }
}
