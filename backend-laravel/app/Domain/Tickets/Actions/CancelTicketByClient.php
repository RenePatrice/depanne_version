<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Actions;

use App\Domain\Settings\Models\AppSetting;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Annulation par le client depuis l'application (§8.1).
 *
 * Les frais ne se déclenchent qu'à partir de EN_ROUTE, et c'est délibéré :
 * tant que personne n'a bougé, annuler ne coûte rien à la plateforme ni au
 * technicien. Dès que le technicien roule, son temps est engagé, et c'est ce
 * temps que les frais compensent.
 *
 * Le montant est **figé sur le ticket** au moment de l'annulation plutôt que
 * relu au moment de l'encaissement : le paramètre peut changer entre les deux,
 * et le client doit payer ce qui lui a été annoncé.
 */
final class CancelTicketByClient
{
    public function __construct(private readonly TransitionTicket $transition) {}

    /** @throws DomainException */
    public function execute(Ticket $ticket, int $clientId, ?string $motif = null): Ticket
    {
        if ((int) $ticket->client_id !== $clientId) {
            throw new DomainException('Cette demande ne t\'appartient pas.');
        }

        if (! $ticket->state->peutAllerVers(TicketState::ANNULEE_CLIENT)) {
            throw new DomainException(sprintf(
                'Une demande %s ne peut plus être annulée. Contacte le support.',
                mb_strtolower($ticket->state->label()),
            ));
        }

        $frais = $this->frais($ticket);

        return DB::transaction(function () use ($ticket, $clientId, $motif, $frais): Ticket {
            $ticket->forceFill([
                'cancellation_reason' => $motif,
                'cancellation_fee_gnf' => $frais,
            ])->save();

            return $this->transition->execute(
                $ticket,
                TicketState::ANNULEE_CLIENT,
                ActorType::CLIENT,
                $clientId,
                ['motif' => $motif, 'frais_gnf' => $frais, 'origine' => 'application'],
            );
        });
    }

    /** Ce que coûterait l'annulation maintenant. Sert aussi à prévenir avant de confirmer. */
    public function frais(Ticket $ticket): int
    {
        $tardive = in_array($ticket->state->etat(), [
            TicketState::EN_ROUTE,
            TicketState::SUR_PLACE,
            TicketState::DIAGNOSTIC_EN_ATTENTE,
            TicketState::DIAGNOSTIC_REFUSE,
        ], true);

        return $tardive
            ? (int) AppSetting::get(AppSetting::CANCELLATION_FEE_GNF, 0)
            : 0;
    }
}
