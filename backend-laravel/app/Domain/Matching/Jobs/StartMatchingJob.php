<?php

declare(strict_types=1);

namespace App\Domain\Matching\Jobs;

use App\Domain\Matching\Actions\SolicitNextTechnician;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Lance la recherche d'un technicien pour une demande publiée.
 *
 * La publication n'attend pas le matching : le client doit voir sa demande
 * partir immédiatement, pas patienter le temps d'une requête PostGIS, d'un
 * scoring et d'un envoi de notification. La recherche part donc dans la file,
 * juste après le commit.
 *
 * Le travail réel est dans `SolicitNextTechnician`, qui est rejouable : ce job
 * n'est qu'un déclencheur.
 */
final class StartMatchingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(private readonly int $ticketId) {}

    public function handle(SolicitNextTechnician $suivant): void
    {
        /** @var Ticket|null $ticket */
        $ticket = Ticket::query()->with(['service.category', 'zone'])->find($this->ticketId);

        if ($ticket === null) {
            return;
        }

        $suivant->execute($ticket);
    }
}
