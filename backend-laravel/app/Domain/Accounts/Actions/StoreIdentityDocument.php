<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\User;
use App\Support\ImageJpeg;
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

        $jpeg = ImageJpeg::depuis($fichier, self::COTE_MAX);

        $chemin = self::dossierDe((int) $utilisateur->getKey()).$type.'-'.Str::uuid()->toString().'.jpg';
        Storage::disk((string) config('filesystems.default'))->put($chemin, $jpeg);

        return $chemin;
    }
}
