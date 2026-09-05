<?php

declare(strict_types=1);

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\Data\IntentionPaiement;
use App\Domain\Payments\Data\NotificationPaiement;
use Illuminate\Http\Request;

/**
 * Encaissement Mobile Money (§8.4, ADR-0005).
 *
 * Le contrat est volontairement étroit : trois gestes, pas un de plus. Tout ce
 * qui relève du métier — le séquestre, la répartition, l'écriture au grand
 * livre — reste dans le domaine et ne dépend d'aucun fournisseur. Changer
 * d'opérateur ne doit toucher qu'une classe.
 *
 * Contrairement à `MapProvider` et `PushProvider`, celui-ci **a le droit de
 * lever**. Un devis qui se dégrade en estimation reste utile ; un paiement qui
 * se dégrade n'a aucun sens. Mieux vaut dire au client « le paiement n'a pas
 * abouti, réessaie » que de le laisser croire qu'il a payé.
 */
interface PaymentProvider
{
    /** Identifiant du fournisseur, écrit tel quel dans `payments.provider`. */
    public function nom(): string;

    /**
     * Demande un encaissement.
     *
     * Le retour porte la référence côté opérateur — celle qui reviendra dans
     * la notification — et, le cas échéant, l'adresse vers laquelle envoyer le
     * client pour qu'il confirme.
     *
     * @throws \RuntimeException si l'opérateur refuse la demande
     */
    public function initier(
        int $montantGnf,
        string $telephonePayeur,
        string $referenceTicket,
        string $urlRetour,
    ): IntentionPaiement;

    /**
     * Vérifie que la notification vient bien de l'opérateur.
     *
     * C'est la seule chose qui sépare un encaissement d'un cadeau : sans
     * signature vérifiée, n'importe qui connaissant l'adresse du webhook
     * pourrait déclarer un paiement reçu.
     */
    public function verifierSignature(Request $requete): bool;

    /**
     * Traduit la notification de l'opérateur en une forme que le domaine
     * comprend. Chaque opérateur a son vocabulaire ; le domaine n'en connaît
     * aucun.
     */
    public function lireNotification(Request $requete): NotificationPaiement;
}
