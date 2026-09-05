<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Matching\Data\MatchResponse;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * Recalcule les statistiques qui alimentent le scoring (§8.3).
 *
 * Elles sont **recalculées**, jamais incrémentées : le même raisonnement que
 * pour le solde du portefeuille (ADR-0004). Un compteur incrémenté dérive au
 * premier job rejoué, et une dérive du taux d'acceptation change qui reçoit les
 * courses — donc qui gagne sa vie.
 *
 * Le taux d'acceptation ne compte que les sollicitations où le technicien a
 * réellement eu la main : une demande annulée par le client pendant sa fenêtre
 * ne le pénalise pas.
 */
final class RecalculateTechnicianStats
{
    public function execute(User $technicien): void
    {
        $profil = $technicien->technicianProfile;

        if ($profil === null) {
            return;
        }

        $profil->forceFill([
            'acceptance_rate' => $this->tauxAcceptation($technicien),
            'cancellation_rate' => $this->tauxAnnulation($technicien),
            'jobs_completed' => $this->interventionsAchevees($technicien),
        ])->save();
    }

    private function tauxAcceptation(User $technicien): float
    {
        $comptees = array_map(
            static fn (MatchResponse $r): string => $r->value,
            array_filter(MatchResponse::cases(), static fn (MatchResponse $r): bool => $r->countsInAcceptanceRate()),
        );

        $ligne = DB::table('match_attempts')
            ->where('technician_id', $technicien->getKey())
            ->whereIn('response', $comptees)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE response = ?) AS acceptees', [MatchResponse::ACCEPTE->value])
            ->first();

        $total = (int) ($ligne->total ?? 0);

        // Un technicien sans historique part à 1,00 plutôt qu'à 0 : sinon il ne
        // serait jamais sollicité, donc n'aurait jamais d'historique.
        return $total === 0 ? 1.0 : round((int) ($ligne->acceptees ?? 0) / $total, 4);
    }

    private function tauxAnnulation(User $technicien): float
    {
        $ligne = DB::table('tickets')
            ->where('technician_id', $technicien->getKey())
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE state = ?) AS annulees', [TicketState::ANNULEE_TECHNICIEN->value])
            ->first();

        $total = (int) ($ligne->total ?? 0);

        return $total === 0 ? 0.0 : round((int) ($ligne->annulees ?? 0) / $total, 4);
    }

    private function interventionsAchevees(User $technicien): int
    {
        return Ticket::query()
            ->where('technician_id', $technicien->getKey())
            ->whereIn('state', [TicketState::PAYEE->value, TicketState::CLOTUREE->value])
            ->count();
    }
}
