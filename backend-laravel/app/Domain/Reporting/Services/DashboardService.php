<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Matching\Data\MatchResponse;
use App\Domain\Reporting\Data\Periode;
use App\Domain\Tickets\Data\TicketState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Lectures agrégées du tableau de bord (§6).
 *
 * Chaque méthode fait **une** requête groupée : le tableau de bord est la page
 * la plus consultée du back-office, il ne doit pas coûter trente requêtes.
 * Les trous de la série temporelle sont comblés en PHP plutôt qu'avec une
 * `generate_series` SQL, pour rester lisible et portable vers Supabase.
 */
final class DashboardService
{
    /**
     * Cartes KPI de l'en-tête.
     *
     * @return array<int, array{cle: string, libelle: string, valeur: int|float, format: string, evolution: float|null, icone: string, ton: string, precision: string|null}>
     */
    public function indicateurs(Periode $periode): array
    {
        $courant = $this->agregats($periode);
        $precedent = $this->agregats($periode->precedente());

        return [
            [
                'cle' => 'demandes',
                'libelle' => 'Demandes sur la période',
                'valeur' => $courant['tickets'],
                'format' => 'entier',
                'evolution' => $this->evolution($courant['tickets'], $precedent['tickets']),
                'icone' => 'bi-ticket-detailed',
                'ton' => 'primary',
                'precision' => null,
            ],
            [
                'cle' => 'actives',
                'libelle' => 'Interventions en cours',
                'valeur' => $this->interventionsActives(),
                'format' => 'entier',
                'evolution' => null,
                'icone' => 'bi-activity',
                'ton' => 'tech',
                'precision' => 'à cet instant',
            ],
            [
                'cle' => 'techniciens',
                'libelle' => 'Techniciens en ligne',
                'valeur' => $this->techniciensEnLigne(),
                'format' => 'entier',
                'evolution' => null,
                'icone' => 'bi-broadcast',
                'ton' => 'success',
                'precision' => 'à cet instant',
            ],
            [
                'cle' => 'chiffre-affaires',
                'libelle' => "Chiffre d'affaires",
                'valeur' => $courant['ca'],
                'format' => 'gnf',
                'evolution' => $this->evolution($courant['ca'], $precedent['ca']),
                'icone' => 'bi-cash-coin',
                'ton' => 'primary',
                'precision' => null,
            ],
            [
                'cle' => 'commissions',
                'libelle' => 'Commissions plateforme',
                'valeur' => $courant['commissions'],
                'format' => 'gnf',
                'evolution' => $this->evolution($courant['commissions'], $precedent['commissions']),
                'icone' => 'bi-percent',
                'ton' => 'success',
                'precision' => null,
            ],
            [
                'cle' => 'acceptation',
                'libelle' => "Taux d'acceptation",
                'valeur' => $this->tauxAcceptation($periode),
                'format' => 'pourcentage',
                'evolution' => $this->evolution(
                    $this->tauxAcceptation($periode),
                    $this->tauxAcceptation($periode->precedente()),
                ),
                'icone' => 'bi-hand-thumbs-up',
                'ton' => 'primary',
                'precision' => 'sollicitations acceptées',
            ],
            [
                'cle' => 'delai',
                'libelle' => "Délai moyen d'acceptation",
                'valeur' => $courant['delai'],
                'format' => 'duree',
                'evolution' => null,
                'icone' => 'bi-stopwatch',
                'ton' => 'warning',
                'precision' => 'entre publication et acceptation',
            ],
            [
                'cle' => 'note',
                'libelle' => 'Note moyenne',
                'valeur' => $courant['note'],
                'format' => 'note',
                'evolution' => $this->evolution($courant['note'], $precedent['note']),
                'icone' => 'bi-star',
                'ton' => 'warning',
                'precision' => 'sur les avis de la période',
            ],
        ];
    }

    /**
     * Agrégats d'une fenêtre, en une requête sur `tickets` et une sur `reviews`.
     *
     * @return array{tickets: int, ca: int, commissions: int, delai: float, note: float}
     */
    private function agregats(Periode $periode): array
    {
        $tickets = DB::table('tickets')
            ->whereBetween('created_at', [$periode->debut, $periode->fin])
            ->selectRaw('COUNT(*) AS n')
            ->selectRaw('COALESCE(SUM(total_gnf) FILTER (WHERE state IN (?, ?, ?)), 0) AS ca', [
                TicketState::TERMINEE->value, TicketState::PAYEE->value, TicketState::CLOTUREE->value,
            ])
            ->selectRaw('COALESCE(SUM(commission_gnf), 0) AS commissions')
            ->selectRaw('COALESCE(AVG(EXTRACT(EPOCH FROM (accepted_at - published_at))) FILTER (WHERE accepted_at IS NOT NULL), 0) AS delai')
            ->first();

        $note = DB::table('reviews')
            ->whereBetween('created_at', [$periode->debut, $periode->fin])
            ->avg('rating');

        return [
            'tickets' => (int) ($tickets->n ?? 0),
            'ca' => (int) ($tickets->ca ?? 0),
            'commissions' => (int) ($tickets->commissions ?? 0),
            'delai' => round((float) ($tickets->delai ?? 0), 0),
            'note' => round((float) ($note ?? 0), 2),
        ];
    }

    private function interventionsActives(): int
    {
        return DB::table('tickets')
            ->whereIn('state', array_map(
                static fn (TicketState $s): string => $s->value,
                array_filter(TicketState::cases(), static fn (TicketState $s): bool => $s->isActive()),
            ))
            ->count();
    }

    private function techniciensEnLigne(): int
    {
        return DB::table('technician_profiles')
            ->where('is_online', true)
            ->where('verification_status', VerificationStatus::VALIDE->value)
            ->count();
    }

    /** Part des sollicitations acceptées, hors annulations extérieures au technicien. */
    public function tauxAcceptation(Periode $periode): float
    {
        $ligne = DB::table('match_attempts')
            ->whereBetween('notified_at', [$periode->debut, $periode->fin])
            ->whereIn('response', [
                MatchResponse::ACCEPTE->value,
                MatchResponse::REFUSE->value,
                MatchResponse::EXPIRE->value,
            ])
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE response = ?) AS acceptees', [MatchResponse::ACCEPTE->value])
            ->first();

        $total = (int) ($ligne->total ?? 0);

        return $total === 0 ? 0.0 : round((int) $ligne->acceptees / $total * 100, 1);
    }

    /**
     * Volume quotidien : total, abouti, et sans réponse. Les jours sans demande
     * apparaissent à zéro — une courbe trouée se lit mal.
     *
     * @return array{categories: array<int, string>, series: array<int, array{name: string, data: array<int, int>, color: string}>}
     */
    public function volumeParJour(Periode $periode): array
    {
        $lignes = DB::table('tickets')
            ->whereBetween('created_at', [$periode->debut, $periode->fin])
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM-DD') AS jour")
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE state = ?) AS abouties', [TicketState::CLOTUREE->value])
            ->selectRaw('COUNT(*) FILTER (WHERE state = ?) AS sans_reponse', [TicketState::SANS_REPONSE->value])
            ->groupBy('jour')
            ->get()
            ->keyBy('jour');

        $categories = [];
        $total = [];
        $abouties = [];
        $sansReponse = [];

        for ($jour = $periode->debut; $jour->lessThanOrEqualTo($periode->fin); $jour = $jour->addDay()) {
            $cle = $jour->format('Y-m-d');
            $ligne = $lignes->get($cle);

            $categories[] = $jour->translatedFormat('d M');
            $total[] = (int) ($ligne->total ?? 0);
            $abouties[] = (int) ($ligne->abouties ?? 0);
            $sansReponse[] = (int) ($ligne->sans_reponse ?? 0);
        }

        return [
            'categories' => $categories,
            'series' => [
                ['name' => 'Demandes publiées', 'data' => $total, 'color' => '#1B6FF3'],
                ['name' => 'Interventions clôturées', 'data' => $abouties, 'color' => '#16A34A'],
                ['name' => 'Sans réponse', 'data' => $sansReponse, 'color' => '#DC2626'],
            ],
        ];
    }

    /**
     * Répartition des demandes par catégorie de prestation.
     *
     * @return array<int, array{name: string, y: int, color: string}>
     */
    public function repartitionParCategorie(Periode $periode): array
    {
        return DB::table('tickets')
            ->join('services', 'services.id', '=', 'tickets.service_id')
            ->join('service_categories', 'service_categories.id', '=', 'services.category_id')
            ->whereBetween('tickets.created_at', [$periode->debut, $periode->fin])
            ->groupBy('service_categories.name', 'service_categories.color')
            ->orderByDesc('n')
            ->get([
                'service_categories.name',
                'service_categories.color',
                DB::raw('COUNT(*) AS n'),
            ])
            ->map(static fn (object $ligne): array => [
                'name' => (string) $ligne->name,
                'y' => (int) $ligne->n,
                'color' => (string) $ligne->color,
            ])
            ->all();
    }

    /**
     * Part technicien et commission plateforme, empilées. Le pas est le jour sur
     * une période courte, la semaine au-delà : quatre-vingt-dix colonnes
     * quotidiennes seraient illisibles.
     *
     * @return array{categories: array<int, string>, series: array<int, array{name: string, data: array<int, int>, color: string}>, pas: string}
     */
    public function revenus(Periode $periode): array
    {
        $parSemaine = $periode->jours() > 31;
        $troncature = $parSemaine ? 'week' : 'day';

        $lignes = DB::table('tickets')
            ->whereNotNull('commission_gnf')
            ->whereBetween('created_at', [$periode->debut, $periode->fin])
            ->selectRaw("DATE_TRUNC('{$troncature}', created_at) AS palier")
            ->selectRaw('SUM(technician_net_gnf) AS net')
            ->selectRaw('SUM(commission_gnf) AS commission')
            ->groupBy('palier')
            ->orderBy('palier')
            ->get();

        $categories = [];
        $net = [];
        $commission = [];

        foreach ($lignes as $ligne) {
            $palier = CarbonImmutable::parse((string) $ligne->palier);

            $categories[] = $parSemaine
                ? 'sem. '.$palier->translatedFormat('d M')
                : $palier->translatedFormat('d M');
            $net[] = (int) $ligne->net;
            $commission[] = (int) $ligne->commission;
        }

        return [
            'categories' => $categories,
            'pas' => $parSemaine ? 'semaine' : 'jour',
            'series' => [
                ['name' => 'Part technicien', 'data' => $net, 'color' => '#1B6FF3'],
                ['name' => 'Commission plateforme', 'data' => $commission, 'color' => '#16A34A'],
            ],
        ];
    }

    /**
     * Carte de chaleur des demandes : heure de la journée en abscisse, jour de
     * la semaine en ordonnée. C'est elle qui dira quand renforcer l'astreinte.
     *
     * @return array{jours: array<int, string>, data: array<int, array<int, int>>, max: int}
     */
    public function heatmapHeureJour(Periode $periode): array
    {
        $lignes = DB::table('tickets')
            ->whereBetween('created_at', [$periode->debut, $periode->fin])
            // ISODOW : 1 = lundi … 7 = dimanche, ce qui correspond à la lecture
            // française de la semaine.
            ->selectRaw('EXTRACT(ISODOW FROM created_at)::int AS jour')
            ->selectRaw('EXTRACT(HOUR FROM created_at)::int AS heure')
            ->selectRaw('COUNT(*) AS n')
            ->groupBy('jour', 'heure')
            ->get();

        $data = [];
        $max = 0;

        foreach ($lignes as $ligne) {
            $valeur = (int) $ligne->n;
            $max = max($max, $valeur);
            // Highcharts attend [x, y, valeur] : heure, index du jour, compte.
            $data[] = [(int) $ligne->heure, (int) $ligne->jour - 1, $valeur];
        }

        return [
            'jours' => ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'],
            'data' => $data,
            'max' => $max,
        ];
    }

    /**
     * Dernières transitions de ticket, pour le flux d'activité.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activiteRecente(int $limite = 12): array
    {
        return DB::table('ticket_events')
            ->join('tickets', 'tickets.id', '=', 'ticket_events.ticket_id')
            ->leftJoin('users AS clients', 'clients.id', '=', 'tickets.client_id')
            ->leftJoin('users AS techniciens', 'techniciens.id', '=', 'tickets.technician_id')
            ->orderByDesc('ticket_events.created_at')
            ->limit($limite)
            ->get([
                'ticket_events.id',
                'ticket_events.to_state',
                'ticket_events.actor_type',
                'ticket_events.created_at',
                'tickets.reference',
                'tickets.total_gnf',
                'clients.full_name AS client',
                'techniciens.full_name AS technicien',
            ])
            ->map(static function (object $ligne): array {
                $etat = TicketState::tryFrom((string) $ligne->to_state);

                return [
                    'id' => (int) $ligne->id,
                    'reference' => (string) $ligne->reference,
                    'etat' => $etat?->value,
                    'etatLibelle' => $etat?->label() ?? (string) $ligne->to_state,
                    'ton' => $etat?->color() ?? 'secondary',
                    'client' => (string) ($ligne->client ?? '—'),
                    'technicien' => $ligne->technicien !== null ? (string) $ligne->technicien : null,
                    'montant' => (int) $ligne->total_gnf,
                    'horodatage' => CarbonImmutable::parse((string) $ligne->created_at)->toIso8601String(),
                ];
            })
            ->all();
    }

    /** Évolution en points de pourcentage entre deux fenêtres comparables. */
    private function evolution(int|float $courant, int|float $precedent): ?float
    {
        if ($precedent <= 0) {
            return null;
        }

        return round((($courant - $precedent) / $precedent) * 100, 1);
    }
}
