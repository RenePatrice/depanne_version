<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Accounts\Models\User;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Auth\Access\Response;

/**
 * Qui peut faire quoi sur un ticket (§10).
 *
 * Les refus sont rendus en **404 et non en 403** : répondre « interdit »
 * confirmerait l'existence du ticket à qui devine une référence. C'est la même
 * règle que celle appliquée depuis C2, mais dite une seule fois au lieu d'être
 * recopiée dans chaque contrôleur.
 *
 * Les gardes **métier** — le chat n'est ouvert qu'entre l'acceptation et la
 * clôture, on ne note qu'une intervention terminée — restent dans les actions
 * du domaine. Une Policy dit qui a le droit d'essayer ; l'action dit si l'essai
 * a un sens. Mélanger les deux rendrait les règles métier inaccessibles au
 * back-office, qui n'appelle pas les Policies de l'API.
 */
final class TicketPolicy
{
    /** Consulter le ticket, sa conversation, son paiement. */
    public function view(User $utilisateur, Ticket $ticket): Response
    {
        return $ticket->estPartiePrenante($utilisateur)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /** Écrire dans la conversation, demander une mise en relation. */
    public function participer(User $utilisateur, Ticket $ticket): Response
    {
        return $this->view($utilisateur, $ticket);
    }

    /**
     * Payer, et surtout **valider la libération du séquestre**.
     *
     * Réservé au client, et pas seulement « aux parties » : valider, c'est
     * débloquer l'argent. Ouvrir cette permission au technicien lui permettrait
     * de libérer ses propres fonds sans que le client ait rien constaté.
     */
    public function payer(User $utilisateur, Ticket $ticket): Response
    {
        return $ticket->estLeClient($utilisateur)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /** Noter l'intervention. */
    public function noter(User $utilisateur, Ticket $ticket): Response
    {
        return $this->payer($utilisateur, $ticket);
    }

    /**
     * Franchir une étape de l'intervention.
     *
     * La Policy ne garde que la porte : un inconnu reçoit un 404. Distinguer le
     * technicien assigné du client revient à `AdvanceIntervention`, qui répond
     * alors par un message lisible plutôt que par un ticket introuvable — celui
     * qui se trompe de bouton est déjà partie prenante, il n'y a rien à lui
     * cacher.
     */
    public function avancer(User $utilisateur, Ticket $ticket): Response
    {
        return $this->view($utilisateur, $ticket);
    }

    /** Ouvrir une réclamation : les deux parties le peuvent. */
    public function reclamer(User $utilisateur, Ticket $ticket): Response
    {
        return $this->view($utilisateur, $ticket);
    }
}
