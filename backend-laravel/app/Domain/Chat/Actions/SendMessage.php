<?php

declare(strict_types=1);

namespace App\Domain\Chat\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Chat\Data\ResultatMasquage;
use App\Domain\Chat\Events\MessageEnvoye;
use App\Domain\Chat\Filters\ContactMaskingFilter;
use App\Domain\Chat\Models\Message;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Envoi d'un message dans le chat d'une intervention (§7.3).
 *
 * Le chat n'est ouvert **qu'entre l'acceptation et la clôture**. Avant, les
 * deux parties n'ont rien à se dire — le technicien n'est pas encore choisi.
 * Après, la conversation est close : la rouvrir permettrait de reprendre
 * contact des semaines plus tard, hors de tout cadre, et c'est exactement ce
 * que le §11 appelle le risque n°1.
 *
 * Tout message passe par le masquage. Le texte d'origine est conservé pour le
 * support, et n'est jamais renvoyé à l'application : c'est `Message::$hidden`
 * qui le garantit, à un seul endroit plutôt qu'à chaque sérialisation.
 */
final class SendMessage
{
    public const LONGUEUR_MAX = 2000;

    /** Le chat est ouvert dans ces états, et seulement ceux-là. */
    private const ETATS_OUVERTS = [
        TicketState::ACCEPTEE,
        TicketState::EN_ROUTE,
        TicketState::SUR_PLACE,
        TicketState::DIAGNOSTIC_EN_ATTENTE,
        TicketState::DIAGNOSTIC_VALIDE,
        TicketState::DIAGNOSTIC_REFUSE,
        TicketState::EN_COURS,
        TicketState::TERMINEE,
        TicketState::PAYEE,
        TicketState::LITIGE_OUVERT,
    ];

    public function __construct(private readonly ContactMaskingFilter $filtre) {}

    /**
     * @return array{message: Message, masquage: ResultatMasquage}
     *
     * @throws DomainException
     */
    public function execute(Ticket $ticket, User $expediteur, string $contenu): array
    {
        $this->verifierParticipation($ticket, $expediteur);
        $this->verifierOuverture($ticket);

        $contenu = trim($contenu);

        if ($contenu === '') {
            throw new DomainException('Le message est vide.');
        }

        $masquage = $this->filtre->appliquer(mb_substr($contenu, 0, self::LONGUEUR_MAX));

        $message = DB::transaction(function () use ($ticket, $expediteur, $masquage): Message {
            /** @var Message $message */
            $message = Message::query()->create([
                'ticket_id' => $ticket->getKey(),
                'sender_id' => $expediteur->getKey(),
                'content' => $masquage->texteMasque,
                // L'original n'est stocké que s'il diffère : conserver deux
                // fois le même texte pour chaque « je suis arrivé » doublerait
                // la table sans rien apprendre au support.
                'original_content' => $masquage->aEteMasque() ? $masquage->texteOriginal : null,
                'is_flagged' => $masquage->aEteMasque(),
                'flag_reason' => $masquage->raisonCourte(),
            ]);

            return $message;
        });

        MessageEnvoye::dispatch(
            (int) $ticket->getKey(),
            (int) $message->getKey(),
            (int) $expediteur->getKey(),
            $expediteur->full_name,
            $masquage->texteMasque,
            $this->destinataire($ticket, $expediteur),
        );

        return ['message' => $message, 'masquage' => $masquage];
    }

    /** Marque comme lus les messages reçus sur ce ticket. */
    public function marquerLus(Ticket $ticket, User $lecteur): int
    {
        $this->verifierParticipation($ticket, $lecteur);

        return Message::query()
            ->where('ticket_id', $ticket->getKey())
            ->where('sender_id', '!=', $lecteur->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /** @throws DomainException */
    private function verifierParticipation(Ticket $ticket, User $utilisateur): void
    {
        if (! $ticket->estPartiePrenante($utilisateur)) {
            throw new DomainException('Cette conversation ne te concerne pas.');
        }
    }

    /** @throws DomainException */
    private function verifierOuverture(Ticket $ticket): void
    {
        if (in_array($ticket->state->etat(), self::ETATS_OUVERTS, true)) {
            return;
        }

        throw new DomainException($ticket->state->etat()->isFinal()
            ? 'Cette intervention est terminée : la conversation est close. '
                .'Ouvre une réclamation si tu as encore besoin d\'aide.'
            : 'La conversation s\'ouvrira dès qu\'un technicien aura accepté ta demande.');
    }

    private function destinataire(Ticket $ticket, User $expediteur): ?int
    {
        $moi = (int) $expediteur->getKey();

        $autre = $moi === (int) $ticket->client_id
            ? $ticket->technician_id
            : $ticket->client_id;

        return $autre === null ? null : (int) $autre;
    }
}
