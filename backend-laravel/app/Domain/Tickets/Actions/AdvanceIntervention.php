<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Services\CodeArrivee;
use App\Support\Money;
use DomainException;

/**
 * Jalons de l'intervention, déclenchés par le technicien (§8.1).
 *
 * Quatre gestes : je pars, je suis arrivé, je commence, j'ai fini. Chacun
 * passe par `TransitionTicket`, qui vérifie la légalité, horodate et journalise
 * — le technicien ne choisit donc pas un état, il franchit une étape, et la
 * machine à états refuse tout saut.
 *
 * Chaque étape prévient le client. C'est la contrepartie du §7.3 : les échanges
 * restent dans l'application, donc l'application doit dire ce qui se passe.
 */
final class AdvanceIntervention
{
    public function __construct(
        private readonly TransitionTicket $transition,
        private readonly SendNotification $notifier,
    ) {}

    /** Étapes que le technicien peut franchir lui-même. */
    private const ETAPES = [
        'en-route' => TicketState::EN_ROUTE,
        'sur-place' => TicketState::SUR_PLACE,
        'demarrer' => TicketState::EN_COURS,
        'terminer' => TicketState::TERMINEE,
    ];

    /** @return array<int, string> */
    public static function etapesDisponibles(): array
    {
        return array_keys(self::ETAPES);
    }

    /** @throws DomainException */
    public function execute(
        Ticket $ticket,
        User $technicien,
        string $etape,
        ?string $diagnostic = null,
        ?string $code = null,
    ): Ticket {
        if (! $ticket->estLeTechnicien($technicien)) {
            throw new DomainException('Cette intervention ne t\'est pas attribuée.');
        }

        $cible = self::ETAPES[$etape] ?? null;

        if ($cible === null) {
            throw new DomainException('Étape inconnue.');
        }

        if (! $ticket->state->peutAllerVers($cible)) {
            throw new DomainException(sprintf(
                'Impossible de passer à « %s » depuis « %s ».',
                $cible->label(),
                $ticket->state->label(),
            ));
        }

        $metadonnees = [];

        if ($cible === TicketState::SUR_PLACE) {
            $metadonnees['code_arrivee'] = $this->verifierCode($ticket, $technicien, $code);
        }

        if ($cible === TicketState::TERMINEE && $diagnostic !== null) {
            $ticket->forceFill(['diagnosis' => $diagnostic])->save();
        }

        $ticket = $this->transition->execute(
            $ticket,
            $cible,
            ActorType::TECHNICIEN,
            (int) $technicien->getKey(),
            $metadonnees,
        );

        $this->prevenirLeClient($ticket, $technicien, $cible);

        return $ticket;
    }

    /**
     * Code d'arrivée (lot 2). Renvoie ce que le journal du ticket retiendra.
     *
     * Le compteur est incrémenté **avant** la comparaison, par une mise à jour
     * conditionnelle : deux saisies envoyées en parallèle ne peuvent pas
     * dépasser le plafond, et une saisie juste compte comme un essai — sans
     * conséquence, elle passe.
     *
     * @throws DomainException
     */
    private function verifierCode(Ticket $ticket, User $technicien, ?string $code): string
    {
        // Ticket antérieur au lot 2 : il n'a pas de code, il passe.
        if ($ticket->arrival_code === null) {
            return 'sans_code';
        }

        if (CodeArrivee::bloque($ticket)) {
            throw new DomainException(self::MESSAGE_BLOQUE);
        }

        if ($code === null || trim($code) === '') {
            throw new DomainException("Demande au client son code d'arrivée : il l'a dans son application.");
        }

        $compte = Ticket::query()
            ->whereKey($ticket->getKey())
            ->where('arrival_code_attempts', '<', CodeArrivee::ESSAIS_MAX)
            ->increment('arrival_code_attempts');

        if ($compte === 0) {
            throw new DomainException(self::MESSAGE_BLOQUE);
        }

        $ticket->arrival_code_attempts++;

        if (CodeArrivee::correspond($ticket, $code)) {
            return 'verifie';
        }

        $restants = CodeArrivee::essaisRestants($ticket);

        if ($restants > 0) {
            throw new DomainException(sprintf(
                'Code incorrect. Il te reste %d essai%s.',
                $restants,
                $restants > 1 ? 's' : '',
            ));
        }

        // Dernier essai manqué : le support doit pouvoir le voir, et le client
        // a la main pour confirmer lui-même l'arrivée.
        activity('securite')
            ->performedOn($ticket)
            ->causedBy($technicien)
            ->withProperties(['ticket' => $ticket->reference, 'essais' => $ticket->arrival_code_attempts])
            ->log("Code d'arrivée bloqué après ".CodeArrivee::ESSAIS_MAX.' essais sur '.$ticket->reference);

        $this->notifier->execute(
            $ticket->client,
            NotificationType::ARRIVEE_A_CONFIRMER,
            ['reference' => $ticket->reference, 'technicien' => $technicien->full_name],
            ['ticket_id' => $ticket->getKey(), 'reference' => $ticket->reference],
        );

        throw new DomainException(self::MESSAGE_BLOQUE);
    }

    private const MESSAGE_BLOQUE = "Trop d'essais avec un code incorrect. Le client peut confirmer "
        .'ton arrivée depuis son application.';

    private function prevenirLeClient(Ticket $ticket, User $technicien, TicketState $cible): void
    {
        $type = match ($cible) {
            TicketState::EN_ROUTE => NotificationType::TECHNICIEN_EN_ROUTE,
            TicketState::SUR_PLACE => NotificationType::TECHNICIEN_ARRIVE,
            TicketState::TERMINEE => NotificationType::INTERVENTION_TERMINEE,
            // Le démarrage du travail n'intéresse personne : le client vient
            // d'ouvrir sa porte au technicien, il le sait déjà.
            default => null,
        };

        if ($type === null) {
            return;
        }

        $this->notifier->execute(
            $ticket->client,
            $type,
            [
                'reference' => $ticket->reference,
                'technicien' => $technicien->full_name,
                'total' => Money::format($ticket->total_gnf),
            ],
            ['ticket_id' => $ticket->getKey(), 'reference' => $ticket->reference],
        );
    }
}
