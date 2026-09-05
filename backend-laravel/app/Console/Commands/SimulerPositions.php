<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Events\TechnicianPositionUpdated;
use App\Domain\Tickets\Data\TicketState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Simule le flux de positions des techniciens.
 *
 * En production, ces positions viennent de l'application mobile toutes les huit
 * secondes pendant une intervention active (§10) — l'API correspondante arrive
 * en phase C3. D'ici là, cette commande émet exactement le même événement, si
 * bien que la carte live se démontre et se teste dès aujourd'hui, et que le
 * jour où le mobile prend le relais, rien ne change côté back-office.
 */
final class SimulerPositions extends Command
{
    protected $signature = 'demo:positions
                            {--duree=120 : durée de la simulation, en secondes}
                            {--intervalle=5 : secondes entre deux salves}';

    protected $description = 'Fait bouger les techniciens en ligne et diffuse leurs positions (démonstration)';

    /** Amplitude d'un déplacement, en degrés : environ 60 mètres. */
    private const PAS = 0.00055;

    public function handle(): int
    {
        $duree = max(5, (int) $this->option('duree'));
        $intervalle = max(1, (int) $this->option('intervalle'));
        $fin = now()->addSeconds($duree);

        $techniciens = $this->techniciensEnLigne();

        if ($techniciens === []) {
            $this->warn('Aucun technicien en ligne : rien à simuler.');
            $this->line('Lance `php artisan migrate:fresh --seed` pour repeupler la base.');

            return self::FAILURE;
        }

        $this->info(count($techniciens).' technicien(s) en ligne, simulation pendant '.$duree.' s.');
        $this->line('Ouvre la carte live dans le navigateur pour les voir bouger.');
        $this->newLine();

        $salves = 0;

        while (now()->lessThan($fin)) {
            foreach ($techniciens as $index => $technicien) {
                // Marche aléatoire douce : le marqueur dérive au lieu de sauter.
                $latitude = $technicien['latitude'] + random_int(-100, 100) / 100 * self::PAS;
                $longitude = $technicien['longitude'] + random_int(-100, 100) / 100 * self::PAS;

                $techniciens[$index]['latitude'] = $latitude;
                $techniciens[$index]['longitude'] = $longitude;

                DB::table('technician_profiles')
                    ->where('user_id', $technicien['id'])
                    ->update([
                        'last_known_location' => DB::raw(sprintf(
                            'ST_SetSRID(ST_MakePoint(%.6f, %.6f), 4326)::geography',
                            $longitude,
                            $latitude,
                        )),
                        'last_position_at' => now(),
                    ]);

                TechnicianPositionUpdated::dispatch(
                    $technicien['id'],
                    $technicien['nom'],
                    round($latitude, 6),
                    round($longitude, 6),
                    $technicien['ticket'] !== null,
                    $technicien['ticket'],
                );
            }

            $salves++;
            $this->line(sprintf(
                '  salve %d — %d positions diffusées (%s)',
                $salves,
                count($techniciens),
                now()->format('H:i:s'),
            ));

            sleep($intervalle);
        }

        $this->newLine();
        $this->info('Simulation terminée : '.$salves.' salves.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{id: int, nom: string, latitude: float, longitude: float, ticket: string|null}>
     */
    private function techniciensEnLigne(): array
    {
        $etatsActifs = array_map(
            static fn (TicketState $e): string => $e->value,
            array_filter(TicketState::cases(), static fn (TicketState $e): bool => $e->isActive()),
        );

        return DB::table('technician_profiles')
            ->join('users', 'users.id', '=', 'technician_profiles.user_id')
            ->where('technician_profiles.is_online', true)
            ->where('technician_profiles.verification_status', VerificationStatus::VALIDE->value)
            ->whereNotNull('technician_profiles.base_location')
            ->select([
                'users.id', 'users.full_name',
                DB::raw('ST_Y(technician_profiles.base_location::geometry) AS latitude'),
                DB::raw('ST_X(technician_profiles.base_location::geometry) AS longitude'),
            ])
            ->selectSub(
                DB::table('tickets')
                    ->selectRaw('reference')
                    ->whereColumn('tickets.technician_id', 'users.id')
                    ->whereIn('tickets.state', $etatsActifs)
                    ->limit(1),
                'ticket',
            )
            ->get()
            ->map(static fn (object $ligne): array => [
                'id' => (int) $ligne->id,
                'nom' => (string) $ligne->full_name,
                'latitude' => (float) $ligne->latitude,
                'longitude' => (float) $ligne->longitude,
                'ticket' => $ligne->ticket !== null ? (string) $ligne->ticket : null,
            ])
            ->all();
    }
}
