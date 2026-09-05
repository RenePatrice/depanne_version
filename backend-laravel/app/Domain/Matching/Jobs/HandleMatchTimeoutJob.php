<?php

declare(strict_types=1);

namespace App\Domain\Matching\Jobs;

use App\Domain\Matching\Actions\RecalculateTechnicianStats;
use App\Domain\Matching\Actions\SolicitNextTechnician;
use App\Domain\Matching\Data\MatchResponse;
use App\Domain\Matching\Models\MatchAttempt;
use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Ferme une fenêtre de réponse restée sans suite (§8.3, étape 4).
 *
 * Planifié pour 45 secondes plus tard au moment de la sollicitation. Il est
 * **idempotent par construction** : il ne fait quelque chose que si la
 * sollicitation est encore EN_ATTENTE. Un job rejoué après un incident de file
 * ne rouvre donc rien, et n'écrase pas la réponse d'un technicien qui aurait
 * répondu entre-temps.
 *
 * Le technicien est prévenu de son expiration. Ce n'est pas une politesse : son
 * taux d'acceptation vient de baisser, et il doit savoir pourquoi.
 */
final class HandleMatchTimeoutJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(private readonly int $tentativeId) {}

    public function handle(
        SolicitNextTechnician $suivant,
        SendNotification $notifier,
        RecalculateTechnicianStats $statistiques,
    ): void {
        /** @var MatchAttempt|null $tentative */
        $tentative = MatchAttempt::query()->with(['ticket', 'technician'])->find($this->tentativeId);

        if ($tentative === null || $tentative->response !== MatchResponse::EN_ATTENTE) {
            return;
        }

        $tentative->forceFill([
            'response' => MatchResponse::EXPIRE,
            'responded_at' => now(),
        ])->save();

        // Une expiration compte dans le taux d'acceptation : elle doit être
        // reportée tout de suite, sinon le technicien garderait son score
        // jusqu'à sa prochaine réponse et serait sollicité à tort entre-temps.
        $statistiques->execute($tentative->technician);

        $notifier->execute(
            $tentative->technician,
            NotificationType::DEMANDE_EXPIREE,
            ['reference' => $tentative->ticket->reference],
            ['ticket_id' => $tentative->ticket->getKey()],
        );

        $suivant->execute($tentative->ticket);
    }
}
