<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Data;

/**
 * États du ticket (§8.1).
 *
 * En phase C2, spatie/laravel-model-states prendra la main sur la colonne
 * `state` avec une classe par état et des transitions déclarées. Les valeurs
 * de cette énumération sont d'ores et déjà les noms d'états définitifs : le
 * remplacement ne demandera aucune migration.
 */
enum TicketState: string
{
    case BROUILLON = 'BROUILLON';
    case PUBLIEE = 'PUBLIEE';
    case ACCEPTEE = 'ACCEPTEE';
    case EN_ROUTE = 'EN_ROUTE';
    case SUR_PLACE = 'SUR_PLACE';
    case DIAGNOSTIC_EN_ATTENTE = 'DIAGNOSTIC_EN_ATTENTE';
    case DIAGNOSTIC_VALIDE = 'DIAGNOSTIC_VALIDE';
    case DIAGNOSTIC_REFUSE = 'DIAGNOSTIC_REFUSE';
    case EN_COURS = 'EN_COURS';
    case TERMINEE = 'TERMINEE';
    case PAYEE = 'PAYEE';
    case CLOTUREE = 'CLOTUREE';
    case ANNULEE_CLIENT = 'ANNULEE_CLIENT';
    case ANNULEE_TECHNICIEN = 'ANNULEE_TECHNICIEN';
    case SANS_REPONSE = 'SANS_REPONSE';
    case LITIGE_OUVERT = 'LITIGE_OUVERT';

    public function label(): string
    {
        return match ($this) {
            self::BROUILLON => 'Brouillon',
            self::PUBLIEE => 'Publiée',
            self::ACCEPTEE => 'Acceptée',
            self::EN_ROUTE => 'En route',
            self::SUR_PLACE => 'Sur place',
            self::DIAGNOSTIC_EN_ATTENTE => 'Diagnostic en attente',
            self::DIAGNOSTIC_VALIDE => 'Diagnostic validé',
            self::DIAGNOSTIC_REFUSE => 'Diagnostic refusé',
            self::EN_COURS => 'En cours',
            self::TERMINEE => 'Terminée',
            self::PAYEE => 'Payée',
            self::CLOTUREE => 'Clôturée',
            self::ANNULEE_CLIENT => 'Annulée par le client',
            self::ANNULEE_TECHNICIEN => 'Annulée par le technicien',
            self::SANS_REPONSE => 'Sans réponse',
            self::LITIGE_OUVERT => 'Litige ouvert',
        };
    }

    /** Couleur Bootstrap du badge de statut dans le back-office. */
    public function color(): string
    {
        return match ($this) {
            self::BROUILLON => 'secondary',
            self::PUBLIEE, self::DIAGNOSTIC_EN_ATTENTE => 'warning',
            self::ACCEPTEE, self::EN_ROUTE, self::SUR_PLACE,
            self::EN_COURS, self::DIAGNOSTIC_VALIDE => 'primary',
            self::TERMINEE, self::PAYEE, self::CLOTUREE => 'success',
            self::ANNULEE_CLIENT, self::ANNULEE_TECHNICIEN,
            self::SANS_REPONSE, self::DIAGNOSTIC_REFUSE => 'danger',
            self::LITIGE_OUVERT => 'dark',
        };
    }

    /** Une intervention est en cours : le technicien est mobilisé. */
    public function isActive(): bool
    {
        return in_array($this, [
            self::ACCEPTEE, self::EN_ROUTE, self::SUR_PLACE,
            self::DIAGNOSTIC_EN_ATTENTE, self::DIAGNOSTIC_VALIDE, self::EN_COURS,
        ], true);
    }

    /** Plus aucune action n'est attendue des parties. */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::CLOTUREE, self::ANNULEE_CLIENT, self::ANNULEE_TECHNICIEN, self::SANS_REPONSE,
        ], true);
    }

    /**
     * Transitions autorisees depuis cet etat (§8.1).
     *
     * En phase C2, spatie/laravel-model-states portera la meme table sous forme
     * de classes. Jusque-la, c'est elle qui empeche une transition illegale :
     * sans garde, le back-office pourrait faire passer un ticket annule en
     * cloturé et fausser toute la comptabilite.
     *
     * @return array<int, self>
     */
    public function transitionsPossibles(): array
    {
        return match ($this) {
            self::BROUILLON => [self::PUBLIEE, self::ANNULEE_CLIENT],
            self::PUBLIEE => [self::ACCEPTEE, self::ANNULEE_CLIENT, self::SANS_REPONSE],
            self::ACCEPTEE => [self::EN_ROUTE, self::ANNULEE_CLIENT, self::ANNULEE_TECHNICIEN],
            self::EN_ROUTE => [self::SUR_PLACE, self::ANNULEE_CLIENT, self::ANNULEE_TECHNICIEN],
            self::SUR_PLACE => [self::DIAGNOSTIC_EN_ATTENTE, self::EN_COURS, self::ANNULEE_TECHNICIEN],
            self::DIAGNOSTIC_EN_ATTENTE => [self::DIAGNOSTIC_VALIDE, self::DIAGNOSTIC_REFUSE],
            self::DIAGNOSTIC_VALIDE => [self::EN_COURS],
            self::DIAGNOSTIC_REFUSE => [self::EN_COURS, self::ANNULEE_CLIENT],
            self::EN_COURS => [self::TERMINEE, self::ANNULEE_TECHNICIEN],
            self::TERMINEE => [self::PAYEE, self::LITIGE_OUVERT],
            self::PAYEE => [self::CLOTUREE, self::LITIGE_OUVERT],
            self::LITIGE_OUVERT => [self::CLOTUREE],
            self::CLOTUREE, self::ANNULEE_CLIENT,
            self::ANNULEE_TECHNICIEN, self::SANS_REPONSE => [],
        };
    }

    public function peutAllerVers(self $cible): bool
    {
        return in_array($cible, $this->transitionsPossibles(), true);
    }

    /** Une annulation reste possible tant que l'intervention n'est pas terminee. */
    public function estAnnulable(): bool
    {
        return $this->peutAllerVers(self::ANNULEE_CLIENT)
            || $this->peutAllerVers(self::ANNULEE_TECHNICIEN);
    }

    /** @return array<int, self> */
    public static function billable(): array
    {
        return [self::TERMINEE, self::PAYEE, self::CLOTUREE];
    }
}
