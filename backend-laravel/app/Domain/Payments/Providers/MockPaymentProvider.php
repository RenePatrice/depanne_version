<?php

declare(strict_types=1);

namespace App\Domain\Payments\Providers;

use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Data\IntentionPaiement;
use App\Domain\Payments\Data\NotificationPaiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Encaissement simulé, pour le développement et le pilote hors ligne.
 *
 * Aucun compte marchand n'est ouvert : ce pilote permet de **jouer le parcours
 * complet** — paiement, séquestre, libération, portefeuille, retrait — sans
 * dépendre d'un tiers. C'est ce qui rend la chaîne financière testable dès
 * maintenant, et non le jour où les clés arriveront.
 *
 * Il est fidèle sur ce qui compte :
 *
 * - il délivre une **référence unique**, comme un vrai opérateur, ce qui fait
 *   travailler pour de bon la contrainte d'unicité et l'idempotence du webhook ;
 * - il **signe** ses notifications de la même façon que le fera Orange Money —
 *   HMAC-SHA256 sur le corps brut — si bien que le code de vérification est
 *   exercé en local et ne sera pas découvert le jour du branchement ;
 * - il sait **échouer** : une référence contenant `ECHEC` produit un refus, ce
 *   qui permet de tester le chemin malheureux, celui qu'on oublie toujours.
 *
 * Ce qu'il ne fait pas : débiter qui que ce soit. Aucun écran ne doit laisser
 * croire le contraire — `simule` vaut vrai dans l'intention renvoyée.
 */
final class MockPaymentProvider implements PaymentProvider
{
    public const NOM = 'mock';

    /** Secret de signature du pilote simulé, sans valeur en production. */
    private const SECRET = 'depanne-moi-simulation';

    public function nom(): string
    {
        return self::NOM;
    }

    public function initier(
        int $montantGnf,
        string $telephonePayeur,
        string $referenceTicket,
        string $urlRetour,
    ): IntentionPaiement {
        $reference = 'SIM-'.strtoupper(Str::random(16));

        Log::info('[PAIEMENT SIMULÉ] Encaissement demandé.', [
            'reference' => $reference,
            'ticket' => $referenceTicket,
            'montant_gnf' => $montantGnf,
            'payeur' => $telephonePayeur,
        ]);

        return new IntentionPaiement(
            reference: $reference,
            urlPaiement: null,
            instruction: 'Paiement simulé : aucun débit réel. '
                .'Confirme depuis l\'écran de démonstration ou appelle le webhook.',
            brut: ['simule' => true, 'montant_gnf' => $montantGnf, 'retour' => $urlRetour],
        );
    }

    public function verifierSignature(Request $requete): bool
    {
        $signature = (string) $requete->header('X-Depanne-Signature', '');

        if ($signature === '') {
            return false;
        }

        return hash_equals($this->signer($requete->getContent()), $signature);
    }

    public function lireNotification(Request $requete): NotificationPaiement
    {
        /** @var array<string, mixed> $corps */
        $corps = $requete->json()->all();

        $reference = (string) ($corps['reference'] ?? '');

        // Une référence marquée ECHEC produit un refus : c'est ce qui rend le
        // chemin malheureux jouable sans dépendre d'un opérateur capricieux.
        $reussi = ($corps['statut'] ?? 'SUCCESS') === 'SUCCESS'
            && ! str_contains($reference, 'ECHEC');

        return new NotificationPaiement(
            reference: $reference,
            reussi: $reussi,
            montantGnf: isset($corps['montant_gnf']) ? (int) $corps['montant_gnf'] : null,
            motifEchec: $reussi ? null : (string) ($corps['motif'] ?? 'Paiement refusé (simulation).'),
            brut: $corps,
        );
    }

    /** Signature d'un corps de notification, utilisée aussi par les tests. */
    public function signer(string $corps): string
    {
        return hash_hmac('sha256', $corps, self::SECRET);
    }
}
