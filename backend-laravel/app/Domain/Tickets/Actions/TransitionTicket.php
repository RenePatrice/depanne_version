<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Actions;

use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Events\TicketTransitioned;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketEvent;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Seul chemin autorisé pour changer l'état d'un ticket (§8.1).
 *
 * Toute transition passe ici : la légalité est vérifiée, l'événement est
 * journalisé et le jalon correspondant est horodaté, le tout dans une seule
 * transaction SQL. Écrire `$ticket->state = …` ailleurs contournerait la garde
 * et laisserait `ticket_events` incomplet.
 */
final class TransitionTicket
{
    /** Jalon horodaté à l'entrée dans chaque état. */
    private const JALONS = [
        'PUBLIEE' => 'published_at',
        'ACCEPTEE' => 'accepted_at',
        'EN_ROUTE' => 'en_route_at',
        'SUR_PLACE' => 'arrived_at',
        'EN_COURS' => 'started_at',
        'TERMINEE' => 'completed_at',
        'PAYEE' => 'paid_at',
        'CLOTUREE' => 'closed_at',
    ];

    /**
     * @param  array<string, mixed>  $metadonnees
     *
     * @throws DomainException si la transition n'est pas autorisée
     */
    public function execute(
        Ticket $ticket,
        TicketState $cible,
        ActorType $acteur = ActorType::ADMIN,
        ?int $acteurId = null,
        array $metadonnees = [],
    ): Ticket {
        $depuis = $ticket->state->etat();

        // La légalité est vérifiée par le paquet d'états, à partir des
        // transitions déclarées dans TicketStatus::config() (§8.1).
        if (! $ticket->state->peutAllerVers($cible)) {
            throw new DomainException(sprintf(
                'Transition interdite : %s ne peut pas passer à %s.',
                $depuis->label(),
                $cible->label(),
            ));
        }

        return DB::transaction(function () use ($ticket, $depuis, $cible, $acteur, $acteurId, $metadonnees): Ticket {
            $modifications = ['state' => $cible->value];

            $jalon = self::JALONS[$cible->value] ?? null;

            // Un jalon déjà horodaté n'est pas réécrit : une reprise après
            // incident ne doit pas effacer la date d'origine.
            if ($jalon !== null && $ticket->{$jalon} === null) {
                $modifications[$jalon] = now();
            }

            $ticket->forceFill($modifications)->save();

            TicketEvent::query()->create([
                'ticket_id' => $ticket->id,
                'from_state' => $depuis,
                'to_state' => $cible,
                'actor_type' => $acteur,
                'actor_id' => $acteurId,
                'metadata' => $metadonnees === [] ? null : $metadonnees,
            ]);

            // La diffusion part après le commit : un abonné ne doit jamais
            // recevoir un état qu'une transaction annulée aurait effacé.
            DB::afterCommit(static function () use ($ticket, $depuis, $cible, $acteur): void {
                TicketTransitioned::dispatch($ticket, $depuis, $cible, $acteur);
            });

            return $ticket;
        });
    }
}
