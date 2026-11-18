<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Services\SuiviTechnicien;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Instantané du trajet du technicien, pour la carte du client.
 *
 * L'écran le lit une fois à l'ouverture, puis suit `technicien.position` sur
 * `ticket.{id}` ; sans WebSocket, il relit cette route en repli.
 */
final class SuiviController extends Controller
{
    public function position(Ticket $ticket, SuiviTechnicien $suivi): JsonResponse
    {
        Gate::authorize('suivre', $ticket);

        return response()->json([
            'suivi_actif' => SuiviTechnicien::suiviActif($ticket),
            'position' => $suivi->position($ticket),
        ]);
    }
}
