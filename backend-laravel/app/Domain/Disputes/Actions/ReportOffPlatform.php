<?php

declare(strict_types=1);

namespace App\Domain\Disputes\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use DomainException;

/**
 * Signalement d'une sollicitation « hors plateforme » (#3).
 *
 * Le risque business n°1 reste le contournement : un technicien qui demande
 * d'annuler la demande pour régler « directement », hors de l'application. Le
 * chat le combat déjà en masquant les coordonnées ; ce signalement ouvre la
 * voie explicite — le client (ou le technicien) nomme la tentative, et elle
 * atterrit dans le journal de sécurité du back-office.
 *
 * On ne bloque personne et on ne tranche rien ici : on **trace**. Comme pour le
 * masquage (ADR-0030), refuser mécaniquement pousserait à recommencer sans
 * laisser d'empreinte ; une trace visible au support est ce qui a de la valeur.
 * C'est au support de rapprocher les signalements et d'agir sur un compte.
 */
final class ReportOffPlatform
{
    /** @throws DomainException */
    public function execute(Ticket $ticket, User $auteur, string $description): void
    {
        if (! $ticket->estPartiePrenante($auteur)) {
            throw new DomainException('Cette intervention ne te concerne pas.');
        }

        // Un signalement n'a de sens que sur une demande vivante ou récente :
        // sur un brouillon, personne n'a encore été mis en relation.
        if ($ticket->state->etat() === TicketState::BROUILLON) {
            throw new DomainException('Cette demande n\'a pas encore de technicien.');
        }

        $estClient = $ticket->estLeClient($auteur);

        // Le signalé est l'autre partie : c'est son compte que le support
        // regardera. Le plus souvent le client signale le technicien, mais
        // l'inverse reste possible.
        $signaleId = $estClient ? $ticket->technician_id : $ticket->client_id;
        $signale = $signaleId !== null ? User::query()->find($signaleId) : null;

        $log = activity('securite')
            ->withProperties([
                'ticket' => $ticket->reference,
                'auteur_role' => $estClient ? 'client' : 'technicien',
                'auteur_id' => (int) $auteur->getKey(),
                'description' => mb_substr($description, 0, 1000),
            ]);

        if ($signale !== null) {
            $log = $log->performedOn($signale);
        }

        $log->log('Signalement hors-plateforme sur '.$ticket->reference);
    }
}
