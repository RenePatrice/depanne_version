<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Pricing\Data\Devis;
use App\Domain\Pricing\Services\PricingService;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Le client tranche sur le supplément proposé (§8.1).
 *
 * C'est le seul moment où le total d'un ticket accepté peut changer, et il ne
 * change que sur un geste explicite du client. Un refus n'annule pas
 * l'intervention : le technicien est sur place, il peut faire ce qui était
 * prévu au départ — c'est le sens de la transition DIAGNOSTIC_REFUSE → EN_COURS
 * conservée dans la table du §8.1.
 *
 * En cas d'acceptation, la répartition est **entièrement recalculée** :
 * commission et net technicien portent sur le nouveau total. Ajouter le
 * supplément au total sans reventiler laisserait la plateforme prélever sa
 * commission sur l'ancien montant, et le grand livre cesserait d'être juste.
 */
final class RespondToDiagnostic
{
    public function __construct(
        private readonly TransitionTicket $transition,
        private readonly PricingService $tarification,
        private readonly SendNotification $notifier,
    ) {}

    /** @throws DomainException */
    public function execute(Ticket $ticket, User $client, bool $accepte): Ticket
    {
        if (! $ticket->estLeClient($client)) {
            throw new DomainException('Seul le client de cette intervention peut répondre.');
        }

        if ($ticket->state->etat() !== TicketState::DIAGNOSTIC_EN_ATTENTE) {
            throw new DomainException('Aucun supplément n\'attend ta réponse sur cette intervention.');
        }

        $propose = $ticket->proposed_extra_fee_gnf;

        if ($propose === null || $propose <= 0) {
            throw new DomainException('Le supplément proposé est introuvable. Contacte le support.');
        }

        return DB::transaction(function () use ($ticket, $client, $accepte, $propose): Ticket {
            if ($accepte) {
                $devis = $this->tarification->avecSupplement($ticket, $propose);

                $ticket->forceFill([
                    'extra_fee_gnf' => $devis->supplementGnf,
                    'total_gnf' => $devis->totalGnf,
                    'commission_gnf' => $devis->commissionGnf,
                    'technician_net_gnf' => $devis->netTechnicienGnf,
                    'commission_rate' => $devis->tauxCommission,
                ])->save();
            }

            // La proposition est consommée dans les deux cas : la laisser en
            // place ferait croire à un supplément encore en attente, et le
            // technicien pourrait la soumettre une seconde fois.
            $ticket->forceFill(['proposed_extra_fee_gnf' => null])->save();

            $ticket = $this->transition->execute(
                $ticket,
                $accepte ? TicketState::DIAGNOSTIC_VALIDE : TicketState::DIAGNOSTIC_REFUSE,
                ActorType::CLIENT,
                (int) $client->getKey(),
                [
                    'supplement_gnf' => $propose,
                    'accepte' => $accepte,
                    'total_gnf' => $ticket->total_gnf,
                ],
            );

            DB::afterCommit(function () use ($ticket, $accepte): void {
                $technicien = $ticket->technician;

                if ($technicien === null) {
                    return;
                }

                $this->notifier->execute(
                    $technicien,
                    NotificationType::DIAGNOSTIC_TRANCHE,
                    [
                        'reference' => $ticket->reference,
                        'decision' => $accepte ? 'accepté' : 'refusé',
                    ],
                    [
                        'ticket_id' => $ticket->getKey(),
                        'accepte' => $accepte,
                        'total_gnf' => $ticket->total_gnf,
                    ],
                );
            });

            return $ticket;
        });
    }

    /**
     * Ce que coûterait l'acceptation, sans rien engager.
     *
     * Sert à l'écran du client : il doit voir le nouveau total **avant** de
     * décider, pas le découvrir après avoir appuyé sur « J'accepte ».
     */
    public function apercu(Ticket $ticket): ?Devis
    {
        $propose = $ticket->proposed_extra_fee_gnf;

        return ($propose === null || $propose <= 0)
            ? null
            : $this->tarification->avecSupplement($ticket, $propose);
    }
}
