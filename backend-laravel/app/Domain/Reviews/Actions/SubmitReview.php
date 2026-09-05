<?php

declare(strict_types=1);

namespace App\Domain\Reviews\Actions;

use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Reviews\Models\Review;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Évaluation laissée par le client (§7.2).
 *
 * L'avis n'est pas un ornement : il pèse **30 % du score de matching** (§8.3),
 * donc il décide de qui reçoit les courses suivantes. D'où trois garde-fous.
 *
 * Seul le **client du ticket** peut noter, et seulement une intervention
 * réellement effectuée. Noter une demande annulée reviendrait à punir un
 * technicien pour un travail qu'il n'a pas fait.
 *
 * Un ticket ne se note qu'**une fois**. Sans cette règle, un client mécontent
 * pourrait faire tomber une moyenne à lui seul.
 *
 * La moyenne est **recalculée**, jamais ajustée à l'incrément (ADR-0004) : une
 * moyenne glissante dérive au premier avis supprimé, et cette dérive change
 * qui gagne sa vie.
 */
final class SubmitReview
{
    /** Les étiquettes proposées à la place d'un commentaire libre. */
    public const ETIQUETTES = [
        'ponctuel', 'travail_soigne', 'bon_conseil', 'materiel_complet',
        'prix_respecte', 'poli', 'explique_bien', 'a_nettoye',
        'en_retard', 'travail_a_refaire', 'peu_communicant',
    ];

    /** Les états où l'intervention a réellement eu lieu. */
    private const ETATS_NOTABLES = [
        TicketState::TERMINEE,
        TicketState::PAYEE,
        TicketState::CLOTUREE,
    ];

    /**
     * @param  array<int, string>  $etiquettes
     *
     * @throws DomainException
     */
    public function execute(
        Ticket $ticket,
        User $client,
        int $note,
        array $etiquettes = [],
        ?string $commentaire = null,
    ): Review {
        if (! $ticket->estLeClient($client)) {
            throw new DomainException('Seul le client de cette intervention peut la noter.');
        }

        if ($ticket->technician_id === null) {
            throw new DomainException('Cette demande n\'a pas eu de technicien.');
        }

        if (! in_array($ticket->state->etat(), self::ETATS_NOTABLES, true)) {
            throw new DomainException(
                'Tu pourras noter cette intervention une fois qu\'elle sera terminée.'
            );
        }

        if ($ticket->review()->exists()) {
            throw new DomainException('Tu as déjà noté cette intervention.');
        }

        if ($note < 1 || $note > 5) {
            throw new DomainException('La note doit être comprise entre 1 et 5.');
        }

        $etiquettes = array_intersect($etiquettes, self::ETIQUETTES);

        return DB::transaction(function () use ($ticket, $client, $note, $etiquettes, $commentaire): Review {
            /** @var Review $avis */
            $avis = Review::query()->create([
                'ticket_id' => $ticket->getKey(),
                'client_id' => $client->getKey(),
                'technician_id' => $ticket->technician_id,
                'rating' => $note,
                'tags' => $etiquettes === [] ? null : $etiquettes,
                'comment' => $commentaire,
            ]);

            $this->recalculerLaMoyenne((int) $ticket->technician_id);

            return $avis;
        });
    }

    /**
     * Moyenne et nombre d'avis, recalculés depuis la table.
     *
     * La note affichée reste la moyenne réelle ; c'est le **scoring** qui
     * substitue une note neutre aux nouveaux venus (§8.3). Confondre les deux
     * afficherait 4,0 sur le profil d'un technicien qui n'a jamais été noté,
     * ce qui serait faux.
     */
    private function recalculerLaMoyenne(int $technicienId): void
    {
        $ligne = DB::table('reviews')
            ->where('technician_id', $technicienId)
            ->selectRaw('COUNT(*) AS total, COALESCE(AVG(rating), 0) AS moyenne')
            ->first();

        TechnicianProfile::query()
            ->whereKey($technicienId)
            ->update([
                'reviews_count' => (int) ($ligne->total ?? 0),
                'rating_avg' => round((float) ($ligne->moyenne ?? 0), 2),
            ]);
    }
}
