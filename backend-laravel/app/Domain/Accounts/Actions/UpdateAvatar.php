<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photo de profil.
 *
 * L'image reçue n'est **jamais stockée telle quelle** : elle est décodée puis
 * ré-encodée en JPEG carré de 512 px. Deux raisons, toutes deux de sécurité :
 *
 * - une photo prise au téléphone porte ses métadonnées EXIF, dont souvent la
 *   **position GPS** de la prise de vue — le domicile de la personne. Le
 *   ré-encodage les efface toutes ;
 * - un fichier « image » piégé (polyglotte, script caché après les données)
 *   ne survit pas à un décodage complet suivi d'un nouvel encodage.
 *
 * Le disque `media` sert la photo publiquement : un avatar s'affiche à l'autre
 * partie d'une intervention, il n'a rien de confidentiel. Les pièces
 * d'identité, elles, restent sur le disque privé (ADR-0036).
 */
final class UpdateAvatar
{
    public const TAILLE = 512;

    private const DOSSIER = 'avatars';

    /** @throws DomainException */
    public function execute(User $utilisateur, UploadedFile $photo): User
    {
        $source = @imagecreatefromstring((string) file_get_contents($photo->getRealPath()));

        if ($source === false) {
            throw new DomainException('Cette image n\'a pas pu être lue. Essayez une photo JPEG ou PNG.');
        }

        $largeur = imagesx($source);
        $hauteur = imagesy($source);
        $cote = min($largeur, $hauteur);

        // Recadrage carré au centre, puis réduction.
        $cible = imagecreatetruecolor(self::TAILLE, self::TAILLE);
        imagecopyresampled(
            $cible,
            $source,
            0,
            0,
            intdiv($largeur - $cote, 2),
            intdiv($hauteur - $cote, 2),
            self::TAILLE,
            self::TAILLE,
            $cote,
            $cote,
        );

        ob_start();
        imagejpeg($cible, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($cible);

        $disque = Storage::disk($this->disque());
        $chemin = self::DOSSIER.'/'.Str::uuid()->toString().'.jpg';
        $disque->put($chemin, $jpeg, 'public');

        $this->effacerAncienne($utilisateur);

        $utilisateur->forceFill(['avatar_url' => $disque->url($chemin)])->save();

        return $utilisateur;
    }

    public function supprimer(User $utilisateur): User
    {
        $this->effacerAncienne($utilisateur);
        $utilisateur->forceFill(['avatar_url' => null])->save();

        return $utilisateur;
    }

    /** N'efface que ce que nous avons nous-mêmes déposé, jamais une URL tierce. */
    private function effacerAncienne(User $utilisateur): void
    {
        $ancienne = (string) $utilisateur->avatar_url;
        $prefixe = Storage::disk($this->disque())->url(self::DOSSIER.'/');

        if ($ancienne !== '' && str_starts_with($ancienne, $prefixe)) {
            $nom = basename(substr($ancienne, strlen($prefixe)));
            Storage::disk($this->disque())->delete(self::DOSSIER.'/'.$nom);
        }
    }

    private function disque(): string
    {
        return (string) config('filesystems.media');
    }
}
