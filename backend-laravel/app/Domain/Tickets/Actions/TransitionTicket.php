<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Actions;

use App\Domain\Settings\Models\AppSetting;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Events\TicketEtatChange;
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

            // La garantie se fige ici, à la clôture d'une intervention payante
            // (#4). C'est le seul entonnoir que toutes les clôtures traversent —
            // validation du client, libération automatique, clôture forcée.
            // Un retour sous garantie (gratuit) n'en ouvre pas lui-même.
            if (
                $cible === TicketState::CLOTUREE
                && ! $ticket->is_warranty
                && $ticket->total_gnf > 0
                && $ticket->warranty_until === null
            ) {
                $jours = (int) AppSetting::get(AppSetting::WARRANTY_DAYS, 7);

                if ($jours > 0) {
                    $modifications['warranty_until'] = now()->addDays($jours);
                }
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
                TicketEtatChange::dispatch((int) $ticket->getKey(), $cible);
            });

            return $ticket;
        });
    }
}
