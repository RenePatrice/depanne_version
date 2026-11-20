<?php

declare(strict_types=1);

namespace App\Support;

use DomainException;
use Illuminate\Http\UploadedFile;

/**
 * Ré-encode une photo reçue en JPEG redimensionné.
 *
 * Le décodage puis le ré-encodage par GD effacent les métadonnées EXIF — dont
 * la position GPS de la prise de vue — et un fichier piégé ne survit pas au
 * passage. Un SVG ou un fichier illisible est refusé.
 */
final class ImageJpeg
{
    /** @throws DomainException */
    public static function depuis(UploadedFile $fichier, int $coteMax, int $qualite = 85): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($fichier->getRealPath()));

        if ($source === false) {
            throw new DomainException('Cette photo n\'a pas pu être lue. Reprenez-la en JPEG ou PNG.');
        }

        $largeur = imagesx($source);
        $hauteur = imagesy($source);
        $echelle = min(1.0, $coteMax / max($largeur, $hauteur));
        $l = max(1, (int) round($largeur * $echelle));
        $h = max(1, (int) round($hauteur * $echelle));

        $cible = imagecreatetruecolor($l, $h);
        imagecopyresampled($cible, $source, 0, 0, 0, 0, $l, $h, $largeur, $hauteur);

        ob_start();
        imagejpeg($cible, null, $qualite);
        $jpeg = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($cible);

        return $jpeg;
    }
}
