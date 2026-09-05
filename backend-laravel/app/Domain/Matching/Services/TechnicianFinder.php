<?php

declare(strict_types=1);

namespace App\Domain\Matching\Services;

use App\Domain\Accounts\Data\UserStatus;
use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Matching\Data\Candidat;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * Présélection géographique des techniciens (§8.3, étapes 1 et 2).
 *
 * Le filtrage et le tri par distance sont faits **en SQL**, par PostGIS. Ce
 * n'est pas une optimisation prématurée : charger tous les techniciens en ligne
 * pour les trier en PHP ferait grandir la mémoire avec le parc, et
 * `ST_DWithin` s'appuie sur l'index GiST posé en A1 — un filtre PHP ne le
 * peut pas.
 *
 * La position retenue est la **dernière position connue** quand elle est
 * fraîche, sinon le point de rattachement déclaré. Un technicien qui a coupé
 * son GPS il y a deux heures ne doit pas être considéré comme étant encore
 * là où il était.
 */
final class TechnicianFinder
{
    /** Au-delà, la dernière position connue n'est plus crédible. */
    private const FRAICHEUR_POSITION_MINUTES = 15;

    /**
     * Candidats à l'intérieur du rayon, du plus proche au plus lointain.
     *
     * @param  array<int, int>  $exclus  techniciens déjà sollicités pour ce ticket
     * @return array<int, Candidat>
     */
    public function autour(Ticket $ticket, int $rayonKm, array $exclus = [], int $limite = 10): array
    {
        $point = $ticket->location;

        if ($point === null || $ticket->service === null) {
            return [];
        }

        $specialite = $ticket->service->category?->code->value;

        if ($specialite === null) {
            return [];
        }

        $lignes = DB::connection()->select($this->requete(), [
            'lng' => $point->getLongitude(),
            'lat' => $point->getLatitude(),
            'lng2' => $point->getLongitude(),
            'lat2' => $point->getLatitude(),
            'rayon' => $rayonKm * 1000,
            'specialite' => json_encode([$specialite]),
            'fraicheur' => self::FRAICHEUR_POSITION_MINUTES,
            'statut' => UserStatus::ACTIF->value,
            'verification' => VerificationStatus::VALIDE->value,
            'exclus' => '{'.implode(',', $exclus === [] ? [0] : $exclus).'}',
            'etats_actifs' => '{'.implode(',', array_map(
                static fn (TicketState $e): string => $e->value,
                array_filter(TicketState::cases(), static fn (TicketState $e): bool => $e->isActive()),
            )).'}',
            'limite' => $limite,
        ]);

        return array_map(
            static fn (object $l): Candidat => new Candidat(
                technicienId: (int) $l->user_id,
                nom: (string) $l->full_name,
                distanceKm: round((float) $l->distance_m / 1000, 2),
                note: (float) $l->rating,
                tauxAcceptation: (float) $l->acceptance_rate,
                tauxAnnulation: (float) $l->cancellation_rate,
                interventions: (int) $l->jobs_completed,
                latitude: (float) $l->lat,
                longitude: (float) $l->lng,
            ),
            $lignes,
        );
    }

    /**
     * Les cinq conditions du §8.3 étape 1, plus la fraîcheur de position.
     *
     * `NOT EXISTS` plutôt qu'une jointure pour l'intervention en cours : un
     * technicien n'en a qu'une au plus, et l'anti-jointure s'arrête au premier
     * enregistrement trouvé.
     */
    private function requete(): string
    {
        return <<<'SQL'
            WITH position_utile AS (
                SELECT
                    tp.user_id,
                    tp.rating_avg,
                    tp.jobs_completed,
                    tp.acceptance_rate,
                    tp.cancellation_rate,
                    CASE
                        WHEN tp.last_known_location IS NOT NULL
                         AND tp.last_position_at > NOW() - (:fraicheur || ' minutes')::interval
                        THEN tp.last_known_location
                        ELSE tp.base_location
                    END AS position
                FROM technician_profiles tp
                WHERE tp.is_online = true
                  AND tp.verification_status = :verification
                  AND tp.specialties @> :specialite::jsonb
                  AND NOT (tp.user_id = ANY(:exclus::bigint[]))
            )
            SELECT
                u.id AS user_id,
                u.full_name,
                p.rating_avg  AS rating,
                p.jobs_completed,
                p.acceptance_rate,
                p.cancellation_rate,
                ST_Y(p.position::geometry) AS lat,
                ST_X(p.position::geometry) AS lng,
                ST_Distance(p.position, ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography) AS distance_m
            FROM position_utile p
            JOIN users u ON u.id = p.user_id
            WHERE p.position IS NOT NULL
              AND u.status = :statut
              AND u.deleted_at IS NULL
              AND ST_DWithin(
                    p.position,
                    ST_SetSRID(ST_MakePoint(:lng2, :lat2), 4326)::geography,
                    :rayon
                  )
              AND NOT EXISTS (
                    SELECT 1 FROM tickets t
                    WHERE t.technician_id = u.id
                      AND t.state = ANY(:etats_actifs::text[])
                  )
            ORDER BY distance_m ASC
            LIMIT :limite
        SQL;
    }
}
