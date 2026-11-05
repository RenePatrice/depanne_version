<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Data;

use App\Domain\Settings\Models\AppSetting;

/**
 * Les notifications que la plateforme sait émettre.
 *
 * Chaque type connaît la clé de paramètre qui porte son texte, quand il y en a
 * une : les libellés destinés au client sont modifiables en back-office (§6),
 * pour qu'une formulation maladroite se corrige sans déploiement. Les messages
 * purement opérationnels — sollicitation d'un technicien, alerte support —
 * gardent un texte fixe : ils ne sont pas du marketing.
 */
enum NotificationType: string
{
    case TICKET_PUBLIE = 'TICKET_PUBLIE';
    case TICKET_ACCEPTE = 'TICKET_ACCEPTE';
    case TECHNICIEN_EN_ROUTE = 'TECHNICIEN_EN_ROUTE';
    case TECHNICIEN_ARRIVE = 'TECHNICIEN_ARRIVE';
    case INTERVENTION_TERMINEE = 'INTERVENTION_TERMINEE';
    case PAIEMENT_RECU = 'PAIEMENT_RECU';
    case DIAGNOSTIC_PROPOSE = 'DIAGNOSTIC_PROPOSE';
    case DIAGNOSTIC_TRANCHE = 'DIAGNOSTIC_TRANCHE';
    case TICKET_ANNULE = 'TICKET_ANNULE';
    case AUCUN_TECHNICIEN = 'AUCUN_TECHNICIEN';
    case TECHNICIEN_REMPLACE = 'TECHNICIEN_REMPLACE';

    case NOUVELLE_DEMANDE = 'NOUVELLE_DEMANDE';
    case DEMANDE_EXPIREE = 'DEMANDE_EXPIREE';
    case DEMANDE_ATTRIBUEE = 'DEMANDE_ATTRIBUEE';
    case DEMANDE_RETIREE = 'DEMANDE_RETIREE';

    public function titre(): string
    {
        return match ($this) {
            self::TICKET_PUBLIE => 'Demande publiée',
            self::TICKET_ACCEPTE => 'Technicien trouvé',
            self::TECHNICIEN_EN_ROUTE => 'Technicien en route',
            self::TECHNICIEN_ARRIVE => 'Technicien arrivé',
            self::INTERVENTION_TERMINEE => 'Intervention terminée',
            self::PAIEMENT_RECU => 'Paiement reçu',
            self::DIAGNOSTIC_PROPOSE => 'Supplément proposé',
            self::DIAGNOSTIC_TRANCHE => 'Réponse du client',
            self::TICKET_ANNULE => 'Demande annulée',
            self::AUCUN_TECHNICIEN => 'Aucun technicien disponible',
            self::TECHNICIEN_REMPLACE => 'Nouveau technicien recherché',
            self::NOUVELLE_DEMANDE => 'Nouvelle demande',
            self::DEMANDE_EXPIREE => 'Demande expirée',
            self::DEMANDE_ATTRIBUEE => 'Demande attribuée',
            self::DEMANDE_RETIREE => 'Demande retirée',
        };
    }

    /** Clé du paramètre qui porte le texte, si celui-ci est modifiable. */
    public function cleParametre(): ?string
    {
        return match ($this) {
            self::TICKET_PUBLIE => AppSetting::NOTIF_TICKET_PUBLIE,
            self::TICKET_ACCEPTE => AppSetting::NOTIF_TICKET_ACCEPTE,
            self::TECHNICIEN_ARRIVE => AppSetting::NOTIF_TECHNICIEN_ARRIVE,
            self::PAIEMENT_RECU => AppSetting::NOTIF_PAIEMENT_RECU,
            default => null,
        };
    }

    /** Texte de repli, utilisé quand aucun paramètre ne porte ce type. */
    public function gabaritParDefaut(): string
    {
        return match ($this) {
            self::TICKET_PUBLIE => 'Ta demande {reference} est publiée.',
            self::TICKET_ACCEPTE => '{technicien} a accepté ta demande {reference}.',
            self::TECHNICIEN_EN_ROUTE => '{technicien} est en route pour {reference}.',
            self::TECHNICIEN_ARRIVE => '{technicien} est arrivé à l\'adresse indiquée.',
            self::INTERVENTION_TERMINEE => 'L\'intervention {reference} est terminée. Total : {total}.',
            self::PAIEMENT_RECU => 'Paiement de {total} reçu pour {reference}.',
            self::DIAGNOSTIC_PROPOSE => '{technicien} propose un supplément de {supplement} '
                .'pour {reference}. Nouveau total : {total}. À toi de valider.',
            self::DIAGNOSTIC_TRANCHE => 'Le client a {decision} le supplément pour {reference}.',
            self::TICKET_ANNULE => 'La demande {reference} a été annulée.',
            self::AUCUN_TECHNICIEN => 'Aucun technicien n\'est disponible pour {reference} '
                .'pour le moment. Tu peux réessayer dans quelques minutes.',
            self::NOUVELLE_DEMANDE => '{prestation} à {distance} — {total}. '
                .'Tu as {delai} secondes pour répondre.',
            self::DEMANDE_EXPIREE => 'Tu n\'as pas répondu à temps pour {reference}.',
            self::DEMANDE_ATTRIBUEE => 'La demande {reference} a été attribuée à un autre technicien.',
            self::TECHNICIEN_REMPLACE => 'Ton technicien ne pourra pas venir pour {reference}. '
                .'Nous en cherchons un autre, ta demande reste active.',
            self::DEMANDE_RETIREE => 'Le support t\'a retiré la demande {reference}. Motif : {motif}',
        };
    }

    /**
     * Une notification urgente doit réveiller le téléphone. Seule la
     * sollicitation l'est : elle a une fenêtre de 45 secondes, tout le reste
     * peut attendre que l'utilisateur regarde son écran.
     */
    public function estUrgente(): bool
    {
        // La sollicitation a une fenêtre de 45 secondes ; le supplément
        // immobilise un technicien devant une porte. Les deux doivent réveiller
        // le téléphone, le reste peut attendre qu'on regarde l'écran.
        return $this === self::NOUVELLE_DEMANDE || $this === self::DIAGNOSTIC_PROPOSE;
    }
}
