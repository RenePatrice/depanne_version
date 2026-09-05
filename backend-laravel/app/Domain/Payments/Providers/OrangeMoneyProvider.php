<?php

declare(strict_types=1);

namespace App\Domain\Payments\Providers;

use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Data\IntentionPaiement;
use App\Domain\Payments\Data\NotificationPaiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Orange Money Web Payment — fournisseur du pilote (décision client).
 *
 * ┌───────────────────────────────────────────────────────────────────────────┐
 * │  CETTE CLASSE N'A JAMAIS PARLÉ À L'API RÉELLE.                             │
 * │                                                                           │
 * │  Aucun compte marchand n'est ouvert à ce jour. La mécanique est écrite —  │
 * │  jeton OAuth2 mis en cache, demande d'encaissement, vérification de        │
 * │  signature, lecture de la notification — mais **les noms de champs        │
 * │  doivent être confirmés contre la documentation Orange** avant toute       │
 * │  mise en ligne. Chaque endroit concerné porte la mention `À CONFIRMER`.    │
 * │                                                                           │
 * │  Tant que `PAYMENT_PROVIDER` ne vaut pas `orange_money` et que les quatre  │
 * │  clés ne sont pas renseignées, le conteneur lie `MockPaymentProvider` :    │
 * │  cette classe ne peut pas être atteinte par accident.                     │
 * └───────────────────────────────────────────────────────────────────────────┘
 *
 * Ce qui, en revanche, est certain et déjà exercé par les tests : la forme du
 * contrat, l'idempotence du webhook, le séquestre et la répartition. Le jour
 * du branchement, seul ce fichier bouge.
 *
 * ### Ce qu'il faudra vérifier avant la première vraie transaction
 *
 * 1. Le nom exact des champs de la réponse d'initiation (`pay_token`,
 *    `payment_url`, `notif_token` selon les versions).
 * 2. L'algorithme et l'en-tête de signature des notifications. Orange a
 *    historiquement utilisé un jeton de notification plutôt qu'un HMAC : si
 *    c'est le cas, `verifierSignature()` doit comparer ce jeton, pas un hachage.
 * 3. L'unité du montant. Le GNF n'a pas de centime, mais certaines API
 *    attendent malgré tout une valeur multipliée par cent.
 * 4. Le comportement en cas de rejeu : Orange renvoie-t-il la même notification
 *    plusieurs fois ? Le domaine y résiste déjà, mais il faut le savoir.
 */
final class OrangeMoneyProvider implements PaymentProvider
{
    public const NOM = 'orange_money';

    private const TIMEOUT_SECONDES = 15;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $merchantKey,
        private readonly string $webhookSecret,
    ) {}

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
        $reponse = Http::withToken($this->jeton())
            ->timeout(self::TIMEOUT_SECONDES)
            ->acceptJson()
            ->post($this->baseUrl.'/webpayment', [
                // À CONFIRMER : noms de champs et unité du montant.
                'merchant_key' => $this->merchantKey,
                'currency' => 'GNF',
                'order_id' => $referenceTicket,
                'amount' => $montantGnf,
                'return_url' => $urlRetour,
                'cancel_url' => $urlRetour,
                'notif_url' => route('api.paiements.webhook'),
                'lang' => 'fr',
                'reference' => $referenceTicket,
                'customer_msisdn' => $telephonePayeur,
            ]);

        if ($reponse->failed()) {
            Log::error('Orange Money : initiation refusée.', [
                'statut' => $reponse->status(),
                'ticket' => $referenceTicket,
            ]);

            // Le contrat autorise à lever ici, et c'est voulu : un paiement qui
            // se dégrade silencieusement laisserait le client croire qu'il a
            // payé.
            throw new RuntimeException('Le paiement n\'a pas pu être lancé. Réessaie dans un instant.');
        }

        /** @var array<string, mixed> $corps */
        $corps = $reponse->json();

        // À CONFIRMER : `pay_token` est la clé de rapprochement dans les
        // versions connues, mais elle a changé de nom entre deux révisions.
        $reference = (string) ($corps['pay_token'] ?? $corps['payment_token'] ?? '');

        if ($reference === '') {
            throw new RuntimeException('Réponse de paiement inexploitable.');
        }

        return new IntentionPaiement(
            reference: $reference,
            urlPaiement: isset($corps['payment_url']) ? (string) $corps['payment_url'] : null,
            instruction: 'Confirme le paiement sur ton téléphone.',
            brut: $corps,
        );
    }

    public function verifierSignature(Request $requete): bool
    {
        // À CONFIRMER : en-tête et algorithme. Si Orange utilise un jeton de
        // notification plutôt qu'un HMAC, c'est ce jeton qu'il faut comparer.
        $signature = (string) $requete->header('X-Orange-Signature', '');

        if ($signature === '' || $this->webhookSecret === '') {
            return false;
        }

        $attendue = hash_hmac('sha256', $requete->getContent(), $this->webhookSecret);

        // Comparaison à temps constant : un `===` laisserait fuir la signature
        // attendue, octet par octet, à qui mesure le temps de réponse.
        return hash_equals($attendue, $signature);
    }

    public function lireNotification(Request $requete): NotificationPaiement
    {
        /** @var array<string, mixed> $corps */
        $corps = $requete->json()->all();

        // À CONFIRMER : valeurs de statut. `SUCCESS` et `FAILED` sont les
        // libellés attendus, mais certaines intégrations renvoient `SUCCESSFUL`.
        $statut = strtoupper((string) ($corps['status'] ?? ''));

        return new NotificationPaiement(
            reference: (string) ($corps['pay_token'] ?? $corps['txnid'] ?? ''),
            reussi: in_array($statut, ['SUCCESS', 'SUCCESSFUL', 'COMPLETED'], true),
            montantGnf: isset($corps['amount']) ? (int) $corps['amount'] : null,
            motifEchec: $statut === '' ? 'Statut absent de la notification.' : 'Paiement '.$statut,
            brut: $corps,
        );
    }

    /**
     * Jeton OAuth2, mis en cache jusqu'à un peu avant son expiration.
     *
     * Le demander à chaque encaissement ferait un aller-retour de plus sur
     * chaque paiement, sur un réseau où chaque aller-retour compte.
     */
    private function jeton(): string
    {
        return Cache::remember('orange-money:jeton', 3000, function (): string {
            $reponse = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->timeout(self::TIMEOUT_SECONDES)
                ->post($this->baseUrl.'/oauth/v3/token', ['grant_type' => 'client_credentials']);

            if ($reponse->failed()) {
                throw new RuntimeException('Authentification Orange Money impossible.');
            }

            return (string) $reponse->json('access_token');
        });
    }
}
