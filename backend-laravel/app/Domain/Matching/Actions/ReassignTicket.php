<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Matching\Data\MatchResponse;
use App\Domain\Matching\Models\MatchAttempt;
use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Pricing\Services\PricingService;
use App\Domain\Tickets\Actions\TransitionTicket;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Réassignation par le support (§6, actions support ; ADR-0040).
 *
 * Le technicien a accepté puis ne vient pas : injoignable, panne de moto,
 * client qui appelle le support. Annuler ferait repartir le client de zéro et
 * republier la même panne ; réassigner lui garde sa demande et relance le
 * matching **sans** ce technicien — il a déjà été sollicité, il ne le sera pas
 * une seconde fois (ADR-0028).
 *
 * Possible tant que personne n'est sur place. Au-delà, l'intervention a
 * commencé chez le client et la question n'est plus d'envoyer quelqu'un
 * d'autre : c'est une annulation ou un litige.
 *
 * Le prix ferme dépendait de la position du technicien retiré : il redevient
 * une estimation, que le suivant refixera en acceptant. Les jalons de
 * l'attribution abandonnée sont effacés du ticket — sans quoi l'application
 * annoncerait un prix ferme et un technicien en route qui n'existent plus —
 * mais conservés dans l'événement de la transition : l'historique ne perd rien.
 */
final class ReassignTicket
{
    private const ETATS_REASSIGNABLES = [TicketState::ACCEPTEE, TicketState::EN_ROUTE];

    public function __construct(
        private readonly TransitionTicket $transition,
        private readonly PricingService $tarification,
        private readonly SendNotification $notifier,
        private readonly SolicitNextTechnician $suivant,
        private readonly RecalculateTechnicianStats $statistiques,
    ) {}

    public static function estReassignable(Ticket $ticket): bool
    {
        return in_array($ticket->state->etat(), self::ETATS_REASSIGNABLES, true)
            && $ticket->technician_id !== null;
    }

    /** @throws DomainException */
    public function execute(Ticket $ticket, string $motif, bool $imputeAuTechnicien, int $adminId): Ticket
    {
        if (! self::estReassignable($ticket)) {
            throw new DomainException(
                'Un ticket en '.$ticket->state->label().' ne peut pas être réassigné : '
                .'seul un technicien qui n\'est pas encore sur place peut être remplacé.'
            );
        }

        $ticket->loadMissing(['technician', 'client', 'zone']);

        /** @var User $retire */
        $retire = $ticket->technician;

        $ticket = DB::transaction(function () use ($ticket, $retire, $motif, $imputeAuTechnicien, $adminId): Ticket {
            $origine = [
                'accepted_at' => $ticket->accepted_at?->toIso8601String(),
                'en_route_at' => $ticket->en_route_at?->toIso8601String(),
                'total_gnf' => $ticket->total_gnf,
            ];

            if ($imputeAuTechnicien) {
                MatchAttempt::query()
                    ->where('ticket_id', $ticket->getKey())
                    ->where('technician_id', $retire->getKey())
                    ->where('response', MatchResponse::ACCEPTE->value)
                    ->update(['response' => MatchResponse::DESISTE->value]);
            }

            $devis = $this->tarification->reestimation($ticket);

            $ticket->forceFill([
                'technician_id' => null,
                'accepted_at' => null,
                'en_route_at' => null,
                ...($devis?->colonnesTicket() ?? []),
            ])->save();

            $ticket = $this->transition->execute($ticket, TicketState::PUBLIEE, ActorType::ADMIN, $adminId, [
                'motif' => $motif,
                'origine' => 'back-office',
                'technicien_retire' => $retire->getKey(),
                'impute_au_technicien' => $imputeAuTechnicien,
                'attribution_abandonnee' => $origine,
            ]);

            activity('tickets')
                ->performedOn($ticket)
                ->withProperties([
                    'motif' => $motif,
                    'technicien_retire' => $retire->getKey(),
                    'impute' => $imputeAuTechnicien,
                ])
                ->log('Réassignation du ticket '.$ticket->reference);

            return $ticket;
        });

        $this->prevenir($ticket, $retire, $motif);
        $this->statistiques->execute($retire);

        // Hors transaction : la sollicitation programme un job d'expiration, qui
        // ne doit pas partir avant que le retour en PUBLIEE soit visible.
        $this->suivant->execute($ticket);

        return $ticket;
    }

    private function prevenir(Ticket $ticket, User $retire, string $motif): void
    {
        $this->notifier->execute(
            $retire,
            NotificationType::DEMANDE_RETIREE,
            ['reference' => $ticket->reference, 'motif' => $motif],
            ['ticket_id' => $ticket->getKey(), 'reference' => $ticket->reference],
        );

        $this->notifier->execute(
            $ticket->client,
            NotificationType::TECHNICIEN_REMPLACE,
            ['reference' => $ticket->reference],
            [
                'ticket_id' => $ticket->getKey(),
                'reference' => $ticket->reference,
                // Le prix redevient une estimation : l'application doit le
                // dire, comme elle dit « total » à l'acceptation.
                'total_gnf' => $ticket->total_gnf,
                'total_ferme' => false,
            ],
        );
    }
}
