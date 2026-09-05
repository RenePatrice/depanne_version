<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifiant de corrélation (§10, observabilité).
 *
 * Une intervention traverse une requête HTTP, deux jobs différés, un webhook
 * d'opérateur et plusieurs diffusions temps réel. Sans fil conducteur, relire
 * ce qui s'est passé revient à recoller des horodatages à la main.
 *
 * L'identifiant est repris de l'appelant s'il en fournit un — l'application
 * Flutter pourra le faire pour joindre ses propres traces aux nôtres — et créé
 * sinon. Il part dans le contexte de tous les logs de la requête et revient
 * dans l'en-tête de réponse, si bien qu'un utilisateur qui signale un problème
 * peut donner un identifiant que le support retrouve directement.
 *
 * Il est **régénéré** si l'appelant en propose un mal formé : accepter une
 * valeur arbitraire, c'est laisser injecter du contenu dans les journaux.
 */
final class IdentifiantDeCorrelation
{
    public const ENTETE = 'X-Request-Id';

    public function handle(Request $request, Closure $suivant): Response
    {
        $identifiant = $this->lire($request) ?? (string) Str::uuid();

        $request->attributes->set('identifiant_correlation', $identifiant);

        Log::shareContext([
            'requete' => $identifiant,
            'utilisateur' => $request->user()?->getKey(),
        ]);

        /** @var Response $reponse */
        $reponse = $suivant($request);

        $reponse->headers->set(self::ENTETE, $identifiant);

        return $reponse;
    }

    /** L'identifiant fourni par l'appelant, s'il est propre. */
    private function lire(Request $request): ?string
    {
        $propose = (string) $request->header(self::ENTETE, '');

        // Alphanumérique, tirets, 8 à 64 caractères : assez large pour un UUID
        // ou l'identifiant de trace d'un client, assez étroit pour qu'aucun
        // saut de ligne ne se retrouve dans un fichier de log.
        return preg_match('/^[A-Za-z0-9-]{8,64}$/', $propose) === 1 ? $propose : null;
    }
}
