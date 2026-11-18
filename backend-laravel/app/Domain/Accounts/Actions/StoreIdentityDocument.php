<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dépôt d'une pièce du dossier technicien : recto, verso, selfie.
 *
 * Trois règles :
 *
 * - le fichier va sur le disque **privé** (`filesystems.default`), jamais sur
 *   le disque public : il n'est lisible que par la route signée du
 *   back-office (ADR-0036) ;
 * - il est **ré-encodé** en JPEG : les métadonnées EXIF — dont la position
 *   GPS de la prise de vue — disparaissent, et un fichier piégé ne survit pas
 *   au décodage ;
 * - il est rangé sous `identity-docs/{id}/` : le dossier n'accepte que des
 *   chemins de ce préfixe, si bien qu'un technicien ne peut pas désigner la
 *   pièce d'un autre.
 *
 * L'action renvoie le **chemin** à joindre au dossier, pas une URL : il n'y a
 * pas d'URL publique vers une pièce d'identité.
 */
final class StoreIdentityDocument
{
    public const TYPES = ['recto', 'verso', 'selfie'];

    /** Côté le plus long : assez pour lire une pièce, pas plus. */
    private const COTE_MAX = 1600;

    public static function dossierDe(int $utilisateurId): string
    {
        return 'identity-docs/'.$utilisateurId.'/';
    }

    /** @throws DomainException */
    public function execute(User $utilisateur, string $type, UploadedFile $fichier): string
    {
        if (! $utilisateur->is_technician) {
            throw new DomainException('Ce compte n\'a pas de casquette technicien.');
        }

        if (! in_array($type, self::TYPES, true)) {
            throw new DomainException('Pièce inconnue.');
        }

        $source = @imagecreatefromstring((string) file_get_contents($fichier->getRealPath()));

        if ($source === false) {
            throw new DomainException('Cette photo n\'a pas pu être lue. Reprenez-la en JPEG ou PNG.');
        }

        $largeur = imagesx($source);
        $hauteur = imagesy($source);
        $echelle = min(1.0, self::COTE_MAX / max($largeur, $hauteur));
        $l = max(1, (int) round($largeur * $echelle));
        $h = max(1, (int) round($hauteur * $echelle));

        $cible = imagecreatetruecolor($l, $h);
        imagecopyresampled($cible, $source, 0, 0, 0, 0, $l, $h, $largeur, $hauteur);

        ob_start();
        imagejpeg($cible, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($cible);

        $chemin = self::dossierDe((int) $utilisateur->getKey()).$type.'-'.Str::uuid()->toString().'.jpg';
        Storage::disk((string) config('filesystems.default'))->put($chemin, $jpeg);

        return $chemin;
    }
}
