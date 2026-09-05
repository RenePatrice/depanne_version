<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Accounts\Models\TechnicianProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Consultation d'une pièce justificative de technicien (§10).
 *
 * Trois gardes se cumulent, et aucune n'est de trop pour une carte d'identité :
 *
 * 1. La **signature** de l'URL, valable quinze minutes. Un lien copié dans un
 *    e-mail ou un ticket de support cesse de fonctionner tout seul.
 * 2. Le **guard admin** : la signature ne remplace pas l'authentification, elle
 *    s'y ajoute. Un lien qui fuiterait ne servirait à personne d'extérieur.
 * 3. La **permission** `techniciens.voir`, comme le reste du module.
 *
 * Le fichier est **diffusé par l'application**, jamais servi en statique : il
 * n'existe aucune adresse publique vers ces images, et le disque de stockage
 * peut rester entièrement privé.
 *
 * C'est aussi ce qui rend la file de validation démontrable en local, où le
 * disque ne sait pas signer d'URL lui-même — cas où l'interface affichait
 * jusqu'ici un encart d'indisponibilité.
 */
final class DocumentController extends Controller
{
    /** Les seules pièces consultables, et leur colonne. */
    private const PIECES = [
        'recto' => 'id_doc_front_url',
        'verso' => 'id_doc_back_url',
        'selfie' => 'selfie_url',
    ];

    public function show(Request $request, TechnicianProfile $profil, string $piece): StreamedResponse
    {
        abort_unless($request->user()?->can('techniciens.voir') === true, 403);

        $colonne = self::PIECES[$piece] ?? null;

        abort_if($colonne === null, 404);

        $chemin = $profil->{$colonne};

        abort_if(! is_string($chemin) || $chemin === '', 404);

        $disque = Storage::disk(config('filesystems.default'));

        abort_unless($disque->exists($chemin), 404);

        // `response()` plutôt que `download()` : le back-office affiche la pièce
        // à côté du selfie pour les comparer, il ne la télécharge pas. L'entête
        // interdit toute mise en cache — une carte d'identité n'a rien à faire
        // dans le cache d'un navigateur partagé.
        return $disque->response($chemin, null, [
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
