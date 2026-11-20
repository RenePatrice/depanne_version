<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Services;

use App\Domain\Accounts\Models\User;
use App\Support\ImageJpeg;
use DomainException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Photos de panne jointes par le client (lot 3).
 *
 * Déposées une à une avant la publication, sur le disque **privé**,
 * ré-encodées (EXIF et position GPS effacées), rangées sous
 * `ticket-photos/{client}/`. Le ticket ne garde que le chemin ; on ne les sert
 * que par un lien signé de vingt minutes, aux seules parties du ticket et au
 * technicien sollicité.
 */
final class PhotosTicket
{
    public const MINUTES_LIEN = 20;

    private const MINUTES_CACHE = 10;

    private const COTE_MAX = 1600;

    private const MOTIF = '#^ticket-photos/(\d+)/[0-9a-f-]{36}\.jpg$#';

    public static function dossierDe(int $clientId): string
    {
        return 'ticket-photos/'.$clientId.'/';
    }

    /** @throws DomainException */
    public function deposer(User $client, UploadedFile $fichier): string
    {
        $jpeg = ImageJpeg::depuis($fichier, self::COTE_MAX, 82);
        $chemin = self::dossierDe((int) $client->getKey()).Str::uuid()->toString().'.jpg';
        $this->disque()->put($chemin, $jpeg);

        return $chemin;
    }

    /**
     * Garde les seuls chemins déposés par ce client et encore présents : un
     * client ne peut pas joindre la photo d'un autre, ni une adresse externe.
     *
     * @param  array<int, string>  $chemins
     * @return array<int, string>
     */
    public function retenir(User $client, array $chemins): array
    {
        $retenus = [];

        foreach ($chemins as $chemin) {
            if (
                preg_match(self::MOTIF, $chemin, $m) === 1
                && (int) $m[1] === (int) $client->getKey()
                && $this->disque()->exists($chemin)
            ) {
                $retenus[] = $chemin;
            }
        }

        return array_values(array_unique($retenus));
    }

    /**
     * Liens de lecture. Un ancien ticket peut porter une adresse complète : elle
     * est rendue telle quelle.
     *
     * @param  array<int, string>|null  $chemins
     * @return array<int, string>
     */
    public function liens(?array $chemins): array
    {
        return array_values(array_map(fn (string $chemin): string => $this->lien($chemin), $chemins ?? []));
    }

    public function lien(string $chemin): string
    {
        if (str_starts_with($chemin, 'http://') || str_starts_with($chemin, 'https://')) {
            return $chemin;
        }

        // Même lien pendant dix minutes : l'écran qui relit le ticket toutes
        // les dix secondes ne retélécharge pas les photos à chaque fois. Un lien
        // servi a toujours au moins dix minutes de validité devant lui.
        return Cache::remember('photo-ticket:'.sha1($chemin), now()->addMinutes(self::MINUTES_CACHE), function () use ($chemin): string {
            $expire = now()->addMinutes(self::MINUTES_LIEN);

            if (config('filesystems.default') !== 'local') {
                return $this->disque()->temporaryUrl($chemin, $expire);
            }

            return URL::temporarySignedRoute('api.photos.ticket', $expire, ['chemin' => $chemin]);
        });
    }

    public function estUnChemin(string $chemin): bool
    {
        return preg_match(self::MOTIF, $chemin) === 1;
    }

    public function disque(): Filesystem
    {
        return Storage::disk((string) config('filesystems.default'));
    }
}
