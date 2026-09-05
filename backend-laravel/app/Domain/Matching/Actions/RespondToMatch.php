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
use App\Support\Geo;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Réponse d'un technicien à une sollicitation (§8.3, étape 4).
 *
 * L'acceptation est le point le plus dangereux du système : deux techniciens
 * attribués au même ticket, ce sont deux personnes qui roulent vers la même
 * adresse et une seule qui sera payée. Trois protections se superposent
 * (ADR-0008) :
 *
 * 1. Un **verrou** par ticket, court, qui sérialise les acceptations
 *    concurrentes.
 * 2. Une **mise à jour conditionnelle** — `WHERE state = 'PUBLIEE'` — qui ne
 *    modifie rien si l'état a changé entre-temps. C'est elle qui garantit
 *    réellement l'unicité, même si le verrou expirait.
 * 3. La **machine à états**, qui refuserait de toute façon une seconde
 *    transition vers ACCEPTEE.
 *
 * C'est aussi le moment où le prix devient ferme : la distance facturée est
 * celle du technicien qui accepte (ADR-0026), et elle n'est connaissable
 * qu'ici.
 */
final class RespondToMatch
{
    private const VERROU_SECONDES = 10;

    private const ATTENTE_VERROU_SECONDES = 3;

    public function __construct(
        private readonly TransitionTicket $transition,
        private readonly PricingService $tarification,
        private readonly SendNotification $notifier,
        private readonly SolicitNextTechnician $suivant,
        private readonly RecalculateTechnicianStats $statistiques,
    ) {}

    /** @throws DomainException */
    public function accepter(MatchAttempt $tentative, User $technicien): Ticket
    {
        $this->verifierRecevabilite($tentative, $technicien);

        $ticket = $tentative->ticket;

        $verrou = Cache::lock('ticket:'.$ticket->getKey().':attribution', self::VERROU_SECONDES);

        if (! $verrou->block(self::ATTENTE_VERROU_SECONDES, fn (): bool => true)) {
            throw new DomainException('Demande en cours d\'attribution. Réessaie dans un instant.');
        }

        try {
            return DB::transaction(function () use ($ticket, $tentative, $technicien): Ticket {
                // Mise à jour conditionnelle : zéro ligne touchée signifie que
                // quelqu'un d'autre a été plus rapide. C'est la garantie réelle
                // d'unicité, celle qui ne dépend d'aucun verrou.
                $prises = Ticket::query()
                    ->whereKey($ticket->getKey())
                    ->where('state', TicketState::PUBLIEE->value)
                    ->whereNull('technician_id')
                    ->update(['technician_id' => $technicien->getKey()]);

                if ($prises === 0) {
                    throw new DomainException('Cette demande vient d\'être attribuée à un autre technicien.');
                }

                $ticket->refresh();

                $this->figerLePrix($ticket, $technicien);

                $tentative->forceFill([
                    'response' => MatchResponse::ACCEPTE,
                    'responded_at' => now(),
                ])->save();

                // Les sollicitations encore ouvertes n'ont plus d'objet. Les
                // clore explicitement évite qu'un job d'expiration ne relance
                // le matching sur un ticket déjà attribué.
                $this->clore($ticket, $tentative);

                $ticket = $this->transition->execute(
                    $ticket,
                    TicketState::ACCEPTEE,
                    ActorType::TECHNICIEN,
                    (int) $technicien->getKey(),
                    ['distance_km' => $ticket->distance_km, 'total_gnf' => $ticket->total_gnf],
                );

                $this->prevenirLeClient($ticket, $technicien);

                return $ticket;
            });
        } finally {
            $verrou->release();
            $this->statistiques->execute($technicien);
        }
    }

    /** @throws DomainException */
    public function refuser(MatchAttempt $tentative, User $technicien, ?string $motif = null): MatchAttempt
    {
        $this->verifierRecevabilite($tentative, $technicien);

        $tentative->forceFill([
            'response' => MatchResponse::REFUSE,
            'refusal_reason' => $motif,
            'responded_at' => now(),
        ])->save();

        $this->statistiques->execute($technicien);

        // Le suivant est sollicité tout de suite : un refus rapide ne doit pas
        // coûter au client les 45 secondes de la fenêtre abandonnée.
        $this->suivant->execute($tentative->ticket);

        return $tentative;
    }

    /**
     * Le prix ferme (§8.2, ADR-0026).
     *
     * La position retenue est celle du technicien au moment où il accepte. S'il
     * n'en a aucune — GPS coupé, profil incomplet — le prix estimé est conservé
     * tel quel : mieux vaut facturer l'estimation que refuser l'attribution et
     * laisser le client sans personne.
     */
    private function figerLePrix(Ticket $ticket, User $technicien): void
    {
        $profil = $technicien->technicianProfile;

        if ($profil === null || $ticket->location === null || $ticket->zone === null) {
            return;
        }

        $depart = $profil->last_known_location ?? $profil->base_location;

        if ($depart === null) {
            return;
        }

        $devis = $this->tarification->pourTechnicien(
            $ticket->base_price_gnf,
            $ticket->zone,
            Geo::point($depart->getLatitude(), $depart->getLongitude()),
            $ticket->location,
            $ticket->extra_fee_gnf,
        );

        $ticket->forceFill($devis->colonnesTicket())->save();
    }

    private function clore(Ticket $ticket, MatchAttempt $gagnante): void
    {
        MatchAttempt::query()
            ->where('ticket_id', $ticket->getKey())
            ->whereKeyNot($gagnante->getKey())
            ->where('response', MatchResponse::EN_ATTENTE->value)
            ->update(['response' => MatchResponse::ANNULE->value, 'responded_at' => now()]);
    }

    private function prevenirLeClient(Ticket $ticket, User $technicien): void
    {
        $client = $ticket->client;

        $this->notifier->execute(
            $client,
            NotificationType::TICKET_ACCEPTE,
            [
                'reference' => $ticket->reference,
                'technicien' => $technicien->full_name,
                'total' => Money::format($ticket->total_gnf),
            ],
            [
                'ticket_id' => $ticket->getKey(),
                'reference' => $ticket->reference,
                // Le montant devient ferme ici : l'application doit le
                // rappeler explicitement, sinon la première facture plus
                // élevée que l'estimation deviendra le premier litige.
                'total_gnf' => $ticket->total_gnf,
                'total_ferme' => true,
                'distance_km' => $ticket->distance_km,
            ],
        );
    }

    /** @throws DomainException */
    private function verifierRecevabilite(MatchAttempt $tentative, User $technicien): void
    {
        if ((int) $tentative->technician_id !== (int) $technicien->getKey()) {
            throw new DomainException('Cette demande ne t\'est pas adressée.');
        }

        if ($tentative->response !== MatchResponse::EN_ATTENTE) {
            throw new DomainException(match ($tentative->response) {
                MatchResponse::ACCEPTE => 'Tu as déjà accepté cette demande.',
                MatchResponse::REFUSE => 'Tu as déjà refusé cette demande.',
                MatchResponse::EXPIRE => 'Le délai de réponse est dépassé.',
                default => 'Cette demande a été attribuée à un autre technicien.',
            });
        }

        if ($tentative->expires_at->isPast()) {
            throw new DomainException('Le délai de réponse est dépassé.');
        }
    }
}
