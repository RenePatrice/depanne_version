<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Matching\Data\Candidat;
use App\Domain\Matching\Data\MatchResponse;
use App\Domain\Matching\Jobs\HandleMatchTimeoutJob;
use App\Domain\Matching\Models\MatchAttempt;
use App\Domain\Matching\Services\TechnicianFinder;
use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Pricing\Services\PricingService;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Geo;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Le client choisit un technicien dans la liste (#2).
 *
 * C'est le pendant « tiré » de l'ancienne sollicitation « poussée » : le client
 * décide, et sa décision ouvre au technicien choisi une fenêtre de réponse de
 * 45 secondes — la même mécanique d'acceptation qu'avant (maquette technicien
 * « Accepter / Refuser »), mais déclenchée par le client.
 *
 * Trois gardes :
 *
 * - **une seule sélection vivante** à la fois : tant qu'un technicien a la main,
 *   le client n'en sollicite pas un second ;
 * - le technicien doit être **réellement disponible** au moment du choix
 *   (en ligne, validé, libre, dans le rayon, de la bonne spécialité) — on le
 *   revérifie, la liste a pu vieillir ;
 * - un technicien **déjà sollicité** pour cette demande n'est pas re-choisi
 *   (ADR-0028).
 */
final class SelectTechnician
{
    public function __construct(
        private readonly TechnicianFinder $recherche,
        private readonly PricingService $tarification,
        private readonly SendNotification $notifier,
    ) {}

    /** @throws DomainException */
    public function execute(Ticket $ticket, User $client, int $technicienId): MatchAttempt
    {
        if (! $ticket->estLeClient($client)) {
            throw new DomainException('Cette demande ne t\'appartient pas.');
        }

        if ($ticket->state->etat() !== TicketState::PUBLIEE) {
            throw new DomainException('Cette demande n\'est plus ouverte à la sélection.');
        }

        if ($this->selectionVivante($ticket)) {
            throw new DomainException('Un technicien a déjà été sollicité et dispose de quelques secondes pour répondre.');
        }

        $dejaSollicites = $this->dejaSollicites($ticket);

        if (in_array($technicienId, $dejaSollicites, true)) {
            throw new DomainException('Ce technicien a déjà été sollicité pour cette demande.');
        }

        $ticket->loadMissing(['service.rates', 'zone', 'client']);

        $rayon = (int) AppSetting::get(AppSetting::MATCH_MAX_RADIUS_KM, 15);
        $candidat = $this->retrouver($ticket, $technicienId, $rayon, $dejaSollicites);

        if ($candidat === null) {
            throw new DomainException('Ce technicien n\'est plus disponible. Choisis-en un autre.');
        }

        $delai = (int) AppSetting::get(AppSetting::MATCH_RESPONSE_SECONDS, 45);

        /** @var MatchAttempt $tentative */
        $tentative = DB::transaction(fn (): MatchAttempt => MatchAttempt::query()->create([
            'ticket_id' => $ticket->getKey(),
            'technician_id' => $candidat->technicienId,
            'cycle' => 1,
            'radius_km' => $rayon,
            'position' => count($dejaSollicites) + 1,
            'score' => 0,
            'score_breakdown' => null,
            'distance_km' => $candidat->distanceKm,
            'response' => MatchResponse::EN_ATTENTE->value,
            'notified_at' => now(),
            'expires_at' => now()->addSeconds($delai),
        ]));

        $this->prevenir($ticket, $candidat, $delai);

        HandleMatchTimeoutJob::dispatch($tentative->getKey())->delay(now()->addSeconds($delai));

        return $tentative;
    }

    private function selectionVivante(Ticket $ticket): bool
    {
        return MatchAttempt::query()
            ->where('ticket_id', $ticket->getKey())
            ->where('response', MatchResponse::EN_ATTENTE->value)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /** @param  array<int, int>  $exclus */
    private function retrouver(Ticket $ticket, int $technicienId, int $rayon, array $exclus): ?Candidat
    {
        foreach ($this->recherche->autour($ticket, $rayon, $exclus, 50) as $candidat) {
            if ($candidat->technicienId === $technicienId) {
                return $candidat;
            }
        }

        return null;
    }

    private function prevenir(Ticket $ticket, Candidat $candidat, int $delai): void
    {
        /** @var User|null $technicien */
        $technicien = User::query()->find($candidat->technicienId);

        if ($technicien === null) {
            return;
        }

        $devis = $this->tarification->pourTechnicien(
            $ticket->service->priceForTechnician($candidat->technicienId),
            $ticket->zone,
            Geo::point($candidat->latitude, $candidat->longitude),
            $ticket->location,
        );

        $this->notifier->execute(
            $technicien,
            NotificationType::NOUVELLE_DEMANDE,
            [
                'prestation' => $ticket->service->name,
                'distance' => number_format($candidat->distanceKm, 1, ',', ' ').' km',
                'total' => Money::format($devis->totalGnf),
                'delai' => $delai,
                'reference' => $ticket->reference,
            ],
            [
                'ticket_id' => $ticket->getKey(),
                'reference' => $ticket->reference,
                'distance_km' => $candidat->distanceKm,
                'expire_dans_s' => $delai,
            ],
        );
    }

    /** @return array<int, int> */
    private function dejaSollicites(Ticket $ticket): array
    {
        return MatchAttempt::query()
            ->where('ticket_id', $ticket->getKey())
            ->pluck('technician_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
