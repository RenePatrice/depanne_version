<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Matching\Data\Candidat;
use App\Domain\Matching\Data\MatchResponse;
use App\Domain\Matching\Jobs\HandleMatchTimeoutJob;
use App\Domain\Matching\Models\MatchAttempt;
use App\Domain\Matching\Services\MatchScorer;
use App\Domain\Matching\Services\TechnicianFinder;
use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sollicite le technicien suivant (§8.3, étapes 4 à 6).
 *
 * Le cœur de la diffusion séquentielle : **un seul technicien à la fois**.
 * C'est ce qui distingue Dépanne-Moi d'une place de marché où dix techniciens
 * reçoivent la même demande et où neuf perdent leur temps. Chacun a une fenêtre
 * exclusive de 45 secondes ; l'expiration est portée par un job différé.
 *
 * L'action est **rejouable sans dommage**. Elle est appelée à la publication,
 * à chaque refus et à chaque expiration ; à chaque fois elle repart de l'état
 * du ticket et de la liste des personnes déjà sollicitées. Un job dupliqué par
 * la file ne produit donc pas deux sollicitations.
 */
final class SolicitNextTechnician
{
    public function __construct(
        private readonly TechnicianFinder $recherche,
        private readonly MatchScorer $scoring,
        private readonly SendNotification $notifier,
        private readonly AbandonMatching $abandon,
    ) {}

    public function execute(Ticket $ticket): ?MatchAttempt
    {
        // L'action est appelée depuis trois endroits — publication, refus,
        // expiration — qui ne chargent pas les mêmes relations. Elle déclare
        // donc ce dont elle a besoin plutôt que de faire confiance à
        // l'appelant : le chargement paresseux lève hors production.
        $ticket->loadMissing(['service.category', 'zone', 'client']);

        // Le ticket a pu être accepté ou annulé pendant que ce job attendait
        // son tour dans la file. On ne sollicite jamais pour rien.
        if (! $ticket->state->etat()->peutAllerVers(TicketState::ACCEPTEE)) {
            return null;
        }

        if ($this->attenteEnCours($ticket)) {
            return null;
        }

        $dejaSollicites = $this->dejaSollicites($ticket);
        $delai = (int) AppSetting::get(AppSetting::MATCH_RESPONSE_SECONDS, 45);

        foreach ($this->cyclesRestants($ticket) as $cycle => $rayon) {
            $candidats = $this->recherche->autour(
                $ticket,
                $rayon,
                $dejaSollicites,
                (int) AppSetting::get(AppSetting::MATCH_CANDIDATES_PER_CYCLE, 10),
            );

            if ($candidats === []) {
                continue;
            }

            $meilleur = $this->scoring->classer($candidats, $rayon)[0];

            return $this->enregistrer($ticket, $meilleur, $cycle, $rayon, $delai, count($dejaSollicites) + 1);
        }

        // Tous les cycles ont été parcourus sans trouver personne.
        $this->abandon->execute($ticket);

        return null;
    }

    /**
     * Une sollicitation vivante interdit d'en ouvrir une seconde : c'est
     * exactement l'invariant du §8.3. Sans ce garde-fou, deux jobs concurrents
     * mobiliseraient deux techniciens sur la même demande.
     */
    private function attenteEnCours(Ticket $ticket): bool
    {
        return MatchAttempt::query()
            ->where('ticket_id', $ticket->getKey())
            ->where('response', MatchResponse::EN_ATTENTE->value)
            ->where('expires_at', '>', now())
            ->exists();
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

    /**
     * Les cycles qu'il reste à parcourir, avec leur rayon.
     *
     * Un technicien déjà sollicité n'est **jamais** re-sollicité, même au cycle
     * suivant : le client attend, et brûler encore 45 secondes sur quelqu'un
     * qui n'a pas répondu, c'est du temps pris sur lui. Élargir le rayon sert à
     * trouver des gens nouveaux, pas à insister auprès des mêmes.
     *
     * @return array<int, int> cycle => rayon en km
     */
    private function cyclesRestants(Ticket $ticket): array
    {
        $initial = (int) AppSetting::get(AppSetting::MATCH_INITIAL_RADIUS_KM, 5);
        $pas = (int) AppSetting::get(AppSetting::MATCH_RADIUS_STEP_KM, 5);
        $max = (int) AppSetting::get(AppSetting::MATCH_MAX_RADIUS_KM, 15);
        $cycles = (int) AppSetting::get(AppSetting::MATCH_MAX_CYCLES, 3);

        $atteint = (int) MatchAttempt::query()->where('ticket_id', $ticket->getKey())->max('cycle');

        $plan = [];

        for ($cycle = max(1, $atteint); $cycle <= $cycles; $cycle++) {
            $plan[$cycle] = min($max, $initial + ($cycle - 1) * $pas);
        }

        return $plan;
    }

    /**
     * @param  array{candidat: Candidat, score: float, detail: array<string, float>}  $meilleur
     */
    private function enregistrer(
        Ticket $ticket,
        array $meilleur,
        int $cycle,
        int $rayon,
        int $delai,
        int $position,
    ): MatchAttempt {
        $candidat = $meilleur['candidat'];

        /** @var MatchAttempt $tentative */
        $tentative = DB::transaction(fn (): MatchAttempt => MatchAttempt::query()->create([
            'ticket_id' => $ticket->getKey(),
            'technician_id' => $candidat->technicienId,
            'cycle' => $cycle,
            'radius_km' => $rayon,
            'position' => $position,
            'score' => $meilleur['score'],
            'score_breakdown' => $meilleur['detail'],
            'distance_km' => $candidat->distanceKm,
            'response' => MatchResponse::EN_ATTENTE->value,
            'notified_at' => now(),
            'expires_at' => now()->addSeconds($delai),
        ]));

        $this->prevenir($ticket, $candidat->technicienId, $candidat->distanceKm, $delai);

        // L'expiration est portée par un job différé plutôt que par un
        // balayage périodique : la fenêtre doit se fermer à la seconde près,
        // pas au prochain passage du scheduler.
        HandleMatchTimeoutJob::dispatch($tentative->getKey())->delay(now()->addSeconds($delai));

        Log::info('Matching : technicien sollicité.', [
            'ticket' => $ticket->reference,
            'technicien' => $candidat->technicienId,
            'cycle' => $cycle,
            'rayon_km' => $rayon,
            'distance_km' => $candidat->distanceKm,
            'score' => $meilleur['score'],
        ]);

        return $tentative;
    }

    private function prevenir(Ticket $ticket, int $technicienId, float $distanceKm, int $delai): void
    {
        /** @var User|null $technicien */
        $technicien = User::query()->find($technicienId);

        if ($technicien === null) {
            return;
        }

        $this->notifier->execute(
            $technicien,
            NotificationType::NOUVELLE_DEMANDE,
            [
                'prestation' => $ticket->service->name,
                'distance' => number_format($distanceKm, 1, ',', ' ').' km',
                'total' => Money::format($ticket->total_gnf),
                'delai' => $delai,
                'reference' => $ticket->reference,
            ],
            [
                'ticket_id' => $ticket->getKey(),
                'reference' => $ticket->reference,
                'distance_km' => $distanceKm,
                'expire_dans_s' => $delai,
            ],
        );
    }
}
