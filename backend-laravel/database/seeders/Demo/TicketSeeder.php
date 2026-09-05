<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\Address;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use App\Domain\Disputes\Data\DisputePriority;
use App\Domain\Disputes\Data\DisputeReason;
use App\Domain\Disputes\Data\DisputeStatus;
use App\Domain\Disputes\Models\Dispute;
use App\Domain\Matching\Data\MatchResponse;
use App\Domain\Matching\Models\MatchAttempt;
use App\Domain\Payments\Data\PaymentMethod;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Reviews\Models\Review;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketEvent;
use App\Domain\Wallet\Data\TransactionType;
use App\Domain\Wallet\Data\WithdrawalStatus;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Withdrawal;
use App\Domain\Zones\Models\Zone;
use App\Support\Geo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Environ 150 tickets répartis sur les trois derniers mois, dans tous les états
 * du cycle de vie, avec leur journal de transitions, leurs sollicitations de
 * matching, leur chat, leur paiement, leurs mouvements de portefeuille, leurs
 * avis et quelques litiges.
 *
 * Le prix de chaque ticket est calculé selon la règle du §8.2 — prix fixe de la
 * prestation + frais de déplacement arrondis au millier supérieur — pour que le
 * tableau de bord et les exports comptables reposent sur des chiffres justes.
 */
final class TicketSeeder extends Seeder
{
    private const TOTAL = 150;

    /** Répartition des états. La somme fait TOTAL. */
    private const DISTRIBUTION = [
        'CLOTUREE' => 85,
        'PAYEE' => 10,
        'TERMINEE' => 8,
        'EN_COURS' => 5,
        'SUR_PLACE' => 3,
        'EN_ROUTE' => 4,
        'ACCEPTEE' => 4,
        'PUBLIEE' => 5,
        'DIAGNOSTIC_EN_ATTENTE' => 3,
        'ANNULEE_CLIENT' => 8,
        'ANNULEE_TECHNICIEN' => 4,
        'SANS_REPONSE' => 6,
        'LITIGE_OUVERT' => 5,
    ];

    private const REVIEW_TAGS = [
        'Ponctuel', 'Travail propre', 'Professionnel', 'Bon conseil',
        'Prix respecté', 'Explique bien', 'Rapide', 'Matériel de qualité',
    ];

    /** @var array<int, Service> */
    private array $services = [];

    /** @var array<int, Zone> */
    private array $zones = [];

    /** @var array<int, array{user: User, profile: TechnicianProfile, lat: float, lng: float}> */
    private array $technicians = [];

    /** @var array<int, array{user: User, address: Address, lat: float, lng: float}> */
    private array $clients = [];

    private int $sequence = 0;

    public function run(): void
    {
        $this->loadReferenceData();

        if ($this->technicians === [] || $this->clients === []) {
            $this->command?->warn('Aucun technicien validé ou aucun client : lance UserSeeder avant.');

            return;
        }

        $states = $this->shuffledStates();

        foreach ($states as $index => $state) {
            // Les tickets s'étalent sur 90 jours, avec une densité plus forte
            // sur les deux dernières semaines : les graphiques de tendance du
            // tableau de bord doivent montrer une courbe, pas un plateau.
            $daysAgo = $index % 3 === 0
                ? fake()->numberBetween(0, 14)
                : fake()->numberBetween(0, 90);

            $this->createTicket($state, CarbonImmutable::now()
                ->subDays($daysAgo)
                ->setTime(fake()->numberBetween(7, 20), fake()->numberBetween(0, 59)));
        }

        $this->createWithdrawals();
        $this->refreshTechnicianStats();
        $this->refreshClientCounters();
    }

    private function loadReferenceData(): void
    {
        $this->services = Service::query()->with('category')->get()->all();
        $this->zones = Zone::query()->get()->all();

        $profiles = TechnicianProfile::query()
            ->where('verification_status', VerificationStatus::VALIDE)
            ->with('user')
            ->get();

        foreach ($profiles as $profile) {
            $point = $profile->base_location;

            if ($point === null || $profile->user === null) {
                continue;
            }

            $this->technicians[] = [
                'user' => $profile->user,
                'profile' => $profile,
                'lat' => $point->getLatitude(),
                'lng' => $point->getLongitude(),
            ];
        }

        $addresses = Address::query()
            ->whereHas('user', fn ($q) => $q->where('is_client', true))
            ->with('user')
            ->get();

        foreach ($addresses as $address) {
            if ($address->user === null || $address->location === null) {
                continue;
            }

            $this->clients[] = [
                'user' => $address->user,
                'address' => $address,
                'lat' => $address->location->getLatitude(),
                'lng' => $address->location->getLongitude(),
            ];
        }
    }

    /** @return array<int, TicketState> */
    private function shuffledStates(): array
    {
        $states = [];

        foreach (self::DISTRIBUTION as $name => $count) {
            for ($i = 0; $i < $count; $i++) {
                $states[] = TicketState::from($name);
            }
        }

        shuffle($states);

        return array_slice($states, 0, self::TOTAL);
    }

    private function createTicket(TicketState $state, CarbonImmutable $createdAt): void
    {
        $client = fake()->randomElement($this->clients);
        $service = fake()->randomElement($this->services);
        $zone = $this->resolveZone($client['lat'], $client['lng']);

        // Un technicien est affecté sauf si personne n'a jamais accepté.
        $needsTechnician = ! in_array($state, [TicketState::PUBLIEE, TicketState::SANS_REPONSE], true);
        $technician = $needsTechnician ? $this->pickTechnician($service) : null;

        $distanceKm = $technician === null
            ? Geo::estimatedRoadKm($client['lat'], $client['lng'], $client['lat'] + 0.02, $client['lng'] + 0.02)
            : Geo::estimatedRoadKm($technician['lat'], $technician['lng'], $client['lat'], $client['lng']);

        $travelFee = $this->travelFee($zone, $distanceKm);

        // Un ticket sur six a donné lieu à un supplément de diagnostic accepté.
        $hasExtra = $state === TicketState::DIAGNOSTIC_EN_ATTENTE
            || (in_array($state, [TicketState::CLOTUREE, TicketState::PAYEE], true) && fake()->boolean(16));
        $extraFee = $hasExtra ? fake()->numberBetween(25, 120) * 1_000 : 0;

        $basePrice = $service->base_price_gnf;
        $total = $basePrice + $travelFee + $extraFee;

        $timeline = $this->buildTimeline($state, $createdAt, $service->estimated_duration_min);

        $ticket = Ticket::query()->create([
            'reference' => $this->nextReference($createdAt),
            'client_id' => $client['user']->id,
            'technician_id' => $technician['user']->id ?? null,
            'service_id' => $service->id,
            'zone_id' => $zone?->id,
            'state' => $state->value,
            'address_id' => $client['address']->id,
            'address_snapshot' => $client['address']->toSnapshot(),
            'location' => Geo::point($client['lat'], $client['lng']),
            'problem_description' => $this->problemDescription($service->name),
            'photos' => $this->photos($createdAt),
            'distance_km' => $distanceKm,
            'distance_is_estimated' => true,   // aucun appel Google en démonstration
            'base_price_gnf' => $basePrice,
            'travel_fee_gnf' => $travelFee,
            'extra_fee_gnf' => $extraFee,
            'total_gnf' => $total,
            'diagnosis' => $hasExtra ? $this->diagnosis() : null,
            'cancellation_reason' => $this->cancellationReason($state),
            'cancellation_fee_gnf' => $state === TicketState::ANNULEE_CLIENT && fake()->boolean(30)
                ? (int) AppSetting::get(AppSetting::CANCELLATION_FEE_GNF, 20_000)
                : 0,
            'created_at' => $createdAt,
            'updated_at' => $timeline['last'],
            ...$timeline['milestones'],
        ]);

        $this->createEvents($ticket, $timeline['transitions']);
        $this->createMatchAttempts($ticket, $technician, $state, $createdAt, $distanceKm);

        // Un technicien affecte implique que le ticket a depasse la publication.
        if ($technician !== null) {
            $this->createMessages($ticket, $client['user'], $technician['user'], $timeline['milestones']['accepted_at'] ?? $createdAt);
        }

        if (in_array($state, [TicketState::PAYEE, TicketState::CLOTUREE, TicketState::LITIGE_OUVERT], true)) {
            $this->settle($ticket, $state, $timeline);
        }

        if ($state === TicketState::CLOTUREE && fake()->boolean(78)) {
            $this->createReview($ticket, $timeline['last']);
        }

        if ($state === TicketState::LITIGE_OUVERT) {
            $this->createDispute($ticket, $timeline['last']);
        }
    }

    // ------------------------------------------------------------- timeline --

    /**
     * Construit les jalons du ticket en fonction de son état, avec des durées
     * plausibles : le tableau de bord affiche un délai moyen d'acceptation, il
     * doit être crédible.
     *
     * @return array{milestones: array<string, CarbonImmutable|null>, transitions: array<int, TicketState>, last: CarbonImmutable}
     */
    private function buildTimeline(TicketState $state, CarbonImmutable $createdAt, int $durationMin): array
    {
        $milestones = [
            'published_at' => null, 'accepted_at' => null, 'en_route_at' => null,
            'arrived_at' => null, 'started_at' => null, 'completed_at' => null,
            'paid_at' => null, 'closed_at' => null,
        ];
        $transitions = [];
        $cursor = $createdAt;

        $milestones['published_at'] = $cursor;
        $transitions[] = TicketState::PUBLIEE;

        if ($state === TicketState::PUBLIEE || $state === TicketState::SANS_REPONSE) {
            if ($state === TicketState::SANS_REPONSE) {
                // Trois cycles de 45 s sur dix candidats : un peu plus de 20 min.
                $cursor = $cursor->addMinutes(23);
                $transitions[] = TicketState::SANS_REPONSE;
            }

            return ['milestones' => $milestones, 'transitions' => $transitions, 'last' => $cursor];
        }

        $cursor = $cursor->addSeconds(fake()->numberBetween(20, 400));
        $milestones['accepted_at'] = $cursor;
        $transitions[] = TicketState::ACCEPTEE;

        if ($state === TicketState::ACCEPTEE) {
            return ['milestones' => $milestones, 'transitions' => $transitions, 'last' => $cursor];
        }

        if ($state === TicketState::ANNULEE_CLIENT || $state === TicketState::ANNULEE_TECHNICIEN) {
            $cursor = $cursor->addMinutes(fake()->numberBetween(2, 40));
            $transitions[] = $state;

            return ['milestones' => $milestones, 'transitions' => $transitions, 'last' => $cursor];
        }

        $cursor = $cursor->addMinutes(fake()->numberBetween(2, 12));
        $milestones['en_route_at'] = $cursor;
        $transitions[] = TicketState::EN_ROUTE;

        if ($state === TicketState::EN_ROUTE) {
            return ['milestones' => $milestones, 'transitions' => $transitions, 'last' => $cursor];
        }

        $cursor = $cursor->addMinutes(fake()->numberBetween(8, 45));
        $milestones['arrived_at'] = $cursor;
        $transitions[] = TicketState::SUR_PLACE;

        if ($state === TicketState::SUR_PLACE) {
            return ['milestones' => $milestones, 'transitions' => $transitions, 'last' => $cursor];
        }

        if ($state === TicketState::DIAGNOSTIC_EN_ATTENTE) {
            $cursor = $cursor->addMinutes(fake()->numberBetween(5, 20));
            $transitions[] = TicketState::DIAGNOSTIC_EN_ATTENTE;

            return ['milestones' => $milestones, 'transitions' => $transitions, 'last' => $cursor];
        }

        $cursor = $cursor->addMinutes(fake()->numberBetween(3, 15));
        $milestones['started_at'] = $cursor;
        $transitions[] = TicketState::EN_COURS;

        if ($state === TicketState::EN_COURS) {
            return ['milestones' => $milestones, 'transitions' => $transitions, 'last' => $cursor];
        }

        $cursor = $cursor->addMinutes((int) round($durationMin * fake()->randomFloat(2, 0.7, 1.6)));
        $milestones['completed_at'] = $cursor;
        $transitions[] = TicketState::TERMINEE;

        if ($state === TicketState::TERMINEE) {
            return ['milestones' => $milestones, 'transitions' => $transitions, 'last' => $cursor];
        }

        $cursor = $cursor->addMinutes(fake()->numberBetween(1, 25));
        $milestones['paid_at'] = $cursor;
        $transitions[] = TicketState::PAYEE;

        if ($state === TicketState::PAYEE) {
            return ['milestones' => $milestones, 'transitions' => $transitions, 'last' => $cursor];
        }

        if ($state === TicketState::LITIGE_OUVERT) {
            $cursor = $cursor->addHours(fake()->numberBetween(2, 60));
            $transitions[] = TicketState::LITIGE_OUVERT;

            return ['milestones' => $milestones, 'transitions' => $transitions, 'last' => $cursor];
        }

        // Clôture : validation du client, ou libération automatique à 24 h.
        $cursor = $cursor->addMinutes(fake()->boolean(70)
            ? fake()->numberBetween(2, 180)
            : 24 * 60);
        $milestones['closed_at'] = $cursor;
        $transitions[] = TicketState::CLOTUREE;

        return ['milestones' => $milestones, 'transitions' => $transitions, 'last' => $cursor];
    }

    // --------------------------------------------------------- sous-objets --

    /** @param  array<int, TicketState>  $transitions */
    private function createEvents(Ticket $ticket, array $transitions): void
    {
        $from = null;
        $cursor = CarbonImmutable::parse($ticket->created_at);

        foreach ($transitions as $to) {
            $cursor = $cursor->addSeconds(fake()->numberBetween(30, 900));

            TicketEvent::query()->create([
                'ticket_id' => $ticket->id,
                'from_state' => $from,
                'to_state' => $to,
                'actor_type' => $this->actorFor($to),
                'actor_id' => match ($this->actorFor($to)) {
                    ActorType::CLIENT => $ticket->client_id,
                    ActorType::TECHNICIEN => $ticket->technician_id,
                    default => null,
                },
                'created_at' => $cursor,
            ]);

            $from = $to;
        }
    }

    private function actorFor(TicketState $state): ActorType
    {
        return match ($state) {
            TicketState::PUBLIEE, TicketState::ANNULEE_CLIENT, TicketState::CLOTUREE,
            TicketState::LITIGE_OUVERT, TicketState::PAYEE => ActorType::CLIENT,
            TicketState::ACCEPTEE, TicketState::EN_ROUTE, TicketState::SUR_PLACE,
            TicketState::EN_COURS, TicketState::TERMINEE, TicketState::DIAGNOSTIC_EN_ATTENTE,
            TicketState::ANNULEE_TECHNICIEN => ActorType::TECHNICIEN,
            default => ActorType::SYSTEME,
        };
    }

    /** @param  array{user: User, profile: TechnicianProfile, lat: float, lng: float}|null  $accepted */
    private function createMatchAttempts(Ticket $ticket, ?array $accepted, TicketState $state, CarbonImmutable $createdAt, float $distanceKm): void
    {
        $delay = (int) AppSetting::get(AppSetting::MATCH_RESPONSE_SECONDS, 45);

        // Quelques refus avant l'acceptation : c'est le comportement réel du
        // matching séquentiel, et cela alimente les taux d'acceptation.
        $refusals = $state === TicketState::SANS_REPONSE
            ? fake()->numberBetween(6, 10)
            : fake()->numberBetween(0, 3);

        $cursor = $createdAt;
        $position = 0;
        $used = [];

        for ($i = 0; $i < $refusals; $i++) {
            $candidate = fake()->randomElement($this->technicians);

            if (in_array($candidate['user']->id, $used, true) || $candidate['user']->id === $ticket->technician_id) {
                continue;
            }

            $used[] = $candidate['user']->id;
            $position++;

            $expired = fake()->boolean(45);
            $cursor = $cursor->addSeconds($expired ? $delay : fake()->numberBetween(5, $delay - 1));

            MatchAttempt::query()->create([
                'ticket_id' => $ticket->id,
                'technician_id' => $candidate['user']->id,
                'cycle' => (int) ceil($position / 10),
                'radius_km' => 5 * (int) ceil($position / 10),
                'position' => $position,
                'score' => fake()->randomFloat(4, 0.35, 0.92),
                'score_breakdown' => $this->scoreBreakdown(),
                'distance_km' => round($distanceKm * fake()->randomFloat(2, 0.6, 1.8), 2),
                'response' => $expired ? MatchResponse::EXPIRE : MatchResponse::REFUSE,
                'refusal_reason' => $expired ? null : fake()->randomElement([
                    'Déjà sur une autre intervention', 'Trop loin', 'Fin de journée',
                ]),
                'notified_at' => $cursor->subSeconds($delay),
                'expires_at' => $cursor,
                'responded_at' => $expired ? null : $cursor,
                'created_at' => $cursor,
                'updated_at' => $cursor,
            ]);
        }

        if ($accepted === null) {
            return;
        }

        $position++;
        $respondedAt = $ticket->accepted_at !== null
            ? CarbonImmutable::parse($ticket->accepted_at)
            : $cursor->addSeconds(20);

        MatchAttempt::query()->create([
            'ticket_id' => $ticket->id,
            'technician_id' => $accepted['user']->id,
            'cycle' => (int) ceil($position / 10),
            'radius_km' => 5 * (int) ceil($position / 10),
            'position' => $position,
            'score' => fake()->randomFloat(4, 0.55, 0.98),
            'score_breakdown' => $this->scoreBreakdown(),
            'distance_km' => $distanceKm,
            'response' => MatchResponse::ACCEPTE,
            'notified_at' => $respondedAt->subSeconds(fake()->numberBetween(5, 40)),
            'expires_at' => $respondedAt->addSeconds($delay),
            'responded_at' => $respondedAt,
            'created_at' => $respondedAt,
            'updated_at' => $respondedAt,
        ]);
    }

    /** @return array<string, float> */
    private function scoreBreakdown(): array
    {
        return [
            'proximity' => fake()->randomFloat(3, 0.2, 1.0),
            'rating' => fake()->randomFloat(3, 0.6, 1.0),
            'acceptance' => fake()->randomFloat(3, 0.4, 1.0),
            'cancellation' => fake()->randomFloat(3, 0.0, 0.15),
        ];
    }

    private function createMessages(Ticket $ticket, User $client, User $technician, mixed $from): void
    {
        $cursor = CarbonImmutable::parse($from);
        $exchanges = fake()->numberBetween(2, 7);

        $clientLines = [
            'Bonjour, vous arrivez dans combien de temps ?',
            "D'accord, je vous attends.",
            'Le portail est ouvert, montez au premier étage.',
            "C'est la fuite sous l'évier de la cuisine.",
            'Merci beaucoup pour le travail.',
            'Est-ce que ça va tenir longtemps ?',
        ];

        $technicianLines = [
            'Bonjour, je suis en route, environ 20 minutes.',
            'Je suis devant le portail.',
            "J'ai vu le problème, je vous explique sur place.",
            "C'est réparé, je vous montre avant de partir.",
            'Merci à vous, bonne journée.',
            'Oui, la pièce est neuve, vous avez 30 jours de garantie.',
        ];

        for ($i = 0; $i < $exchanges; $i++) {
            $fromClient = $i % 2 === 0;
            $cursor = $cursor->addMinutes(fake()->numberBetween(1, 18));

            // Un message sur douze tente de faire sortir la relation de
            // l'application : c'est le risque n° 1 du §11, il doit être visible
            // dans la démonstration du back-office.
            $isBypass = fake()->boolean(8);

            $content = $isBypass
                ? 'Appelez-moi directement au ●●●●●●●●● ce sera plus simple'
                : ($fromClient ? fake()->randomElement($clientLines) : fake()->randomElement($technicianLines));

            $ticketMessage = [
                'ticket_id' => $ticket->id,
                'sender_id' => $fromClient ? $client->id : $technician->id,
                'content' => $content,
                'original_content' => $isBypass ? 'Appelez-moi directement au 622145879 ce sera plus simple' : null,
                'is_flagged' => $isBypass,
                'flag_reason' => $isBypass ? 'TELEPHONE' : null,
                'read_at' => fake()->boolean(85) ? $cursor->addMinutes(1) : null,
                'created_at' => $cursor,
                'updated_at' => $cursor,
            ];

            DB::table('messages')->insert($ticketMessage);
        }
    }

    /** @param  array{milestones: array<string, CarbonImmutable|null>, transitions: array<int, TicketState>, last: CarbonImmutable}  $timeline */
    private function settle(Ticket $ticket, TicketState $state, array $timeline): void
    {
        $paidAt = $timeline['milestones']['paid_at'] ?? $timeline['last'];
        $method = fake()->boolean(72) ? PaymentMethod::ORANGE_MONEY : PaymentMethod::MTN_MOMO;
        $released = $state === TicketState::CLOTUREE;

        Payment::query()->create([
            'ticket_id' => $ticket->id,
            'provider' => 'mock',
            'provider_ref' => 'MOCK-'.strtoupper(fake()->unique()->bothify('??######')),
            'method' => $method,
            'payer_phone' => '+224'.fake()->numerify('#########'),
            'amount_gnf' => $ticket->total_gnf,
            'status' => $released ? PaymentStatus::LIBEREE : PaymentStatus::CAPTUREE,
            'captured_at' => $paidAt,
            'released_at' => $released ? ($timeline['milestones']['closed_at'] ?? $timeline['last']) : null,
            'auto_release_at' => CarbonImmutable::parse($paidAt)->addHours(
                (int) AppSetting::get(AppSetting::ESCROW_AUTO_RELEASE_HOURS, 24)
            ),
            'created_at' => $paidAt,
            'updated_at' => $timeline['last'],
        ]);

        if (! $released || $ticket->technician_id === null) {
            return;
        }

        // Répartition 90 / 10 sur des entiers : la commission est arrondie, le
        // net technicien est le reste — la somme fait toujours le total.
        $rate = (float) AppSetting::get(AppSetting::COMMISSION_RATE, 0.10);
        $commission = (int) round($ticket->total_gnf * $rate);
        $net = $ticket->total_gnf - $commission;

        $ticket->forceFill([
            'commission_gnf' => $commission,
            'technician_net_gnf' => $net,
            'commission_rate' => $rate,
        ])->save();

        $closedAt = $timeline['milestones']['closed_at'] ?? $timeline['last'];

        $this->recordTransaction($ticket->technician_id, $ticket->id, TransactionType::EARNING, $ticket->total_gnf,
            'Intervention '.$ticket->reference, $closedAt);
        $this->recordTransaction($ticket->technician_id, $ticket->id, TransactionType::COMMISSION, -$commission,
            'Commission plateforme ('.(int) round($rate * 100).' %) — '.$ticket->reference, $closedAt);
    }

    private function recordTransaction(int $userId, ?int $ticketId, TransactionType $type, int $amount, string $description, mixed $at): void
    {
        $balance = (int) DB::table('transactions')->where('user_id', $userId)->sum('amount_gnf');

        Transaction::query()->create([
            'user_id' => $userId,
            'ticket_id' => $ticketId,
            'type' => $type,
            'amount_gnf' => $amount,
            'balance_after_gnf' => $balance + $amount,
            'description' => $description,
            'created_at' => $at,
        ]);
    }

    private function createReview(Ticket $ticket, mixed $at): void
    {
        if ($ticket->technician_id === null) {
            return;
        }

        // Note tirée vers le haut : sur une place de marché de dépannage, les
        // avis réels sont très majoritairement positifs.
        $rating = fake()->randomElement([5, 5, 5, 5, 4, 4, 4, 3, 2]);
        $tip = $rating === 5 && fake()->boolean(18) ? fake()->numberBetween(5, 30) * 1_000 : 0;

        Review::query()->create([
            'ticket_id' => $ticket->id,
            'client_id' => $ticket->client_id,
            'technician_id' => $ticket->technician_id,
            'rating' => $rating,
            'tags' => fake()->randomElements(self::REVIEW_TAGS, fake()->numberBetween(1, 3)),
            'comment' => $rating >= 4
                ? fake()->randomElement([
                    'Très bon travail, je recommande.',
                    'Rapide et soigneux, rien à redire.',
                    'Ponctuel et professionnel.',
                    null,
                ])
                : fake()->randomElement([
                    'Travail correct mais beaucoup de retard.',
                    'Le problème est revenu deux jours après.',
                ]),
            'tip_gnf' => $tip,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        if ($tip > 0) {
            $this->recordTransaction($ticket->technician_id, $ticket->id, TransactionType::TIP, $tip,
                'Pourboire — '.$ticket->reference, $at);
        }
    }

    private function createDispute(Ticket $ticket, mixed $at): void
    {
        $priority = fake()->randomElement(DisputePriority::cases());
        $openedAt = CarbonImmutable::parse($at);
        $resolved = fake()->boolean(40);

        Dispute::query()->create([
            'reference' => 'LIT-'.$openedAt->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'ticket_id' => $ticket->id,
            'opened_by' => $ticket->client_id,
            'reason' => fake()->randomElement(DisputeReason::cases()),
            'description' => fake()->randomElement([
                'La fuite est revenue le lendemain matin, au même endroit.',
                'Le technicien a demandé un supplément non prévu avant de partir.',
                "L'intervention a duré trois heures de plus que le délai annoncé.",
                "Une prise a été cassée pendant l'intervention.",
            ]),
            'evidence' => ['intervention-photos/demo/litige-'.$ticket->id.'-1.jpg'],
            'status' => $resolved
                ? fake()->randomElement([DisputeStatus::RESOLU, DisputeStatus::REJETE])
                : fake()->randomElement([DisputeStatus::OUVERT, DisputeStatus::EN_COURS]),
            'priority' => $priority,
            'sla_due_at' => $openedAt->addHours($priority->slaHours()),
            'resolution' => $resolved ? fake()->randomElement(['REMBOURSEMENT_PARTIEL', 'AUCUNE_ACTION', 'AVERTISSEMENT']) : null,
            'resolution_note' => $resolved ? 'Décision prise après échange avec les deux parties.' : null,
            'refund_gnf' => $resolved && fake()->boolean(35) ? (int) round($ticket->total_gnf * 0.3 / 1000) * 1000 : 0,
            'resolved_at' => $resolved ? $openedAt->addHours(fake()->numberBetween(2, 70)) : null,
            'created_at' => $openedAt,
            'updated_at' => $openedAt,
        ]);
    }

    /**
     * Quelques demandes de retrait, dans les quatre statuts du workflow, pour
     * que la file des versements du back-office soit démontrable.
     */
    private function createWithdrawals(): void
    {
        $balances = DB::table('transactions')
            ->select('user_id', DB::raw('SUM(amount_gnf) as solde'))
            ->groupBy('user_id')
            ->having(DB::raw('SUM(amount_gnf)'), '>', 200_000)
            ->get();

        $minimum = (int) AppSetting::get(AppSetting::WITHDRAWAL_MIN_GNF, 50_000);
        $index = 0;

        foreach ($balances as $row) {
            if (! fake()->boolean(55)) {
                continue;
            }

            $index++;
            $status = match ($index % 4) {
                0 => WithdrawalStatus::EN_ATTENTE,
                1 => WithdrawalStatus::PAYE,
                2 => WithdrawalStatus::APPROUVE,
                default => WithdrawalStatus::REJETE,
            };

            $amount = min((int) $row->solde, fake()->numberBetween($minimum / 1000, 400) * 1_000);
            $requestedAt = CarbonImmutable::now()->subDays(fake()->numberBetween(0, 45));

            Withdrawal::query()->create([
                'reference' => 'RET-'.$requestedAt->format('Y').'-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'technician_id' => $row->user_id,
                'amount_gnf' => $amount,
                'mobile_money_number' => '+224'.fake()->numerify('#########'),
                'provider' => fake()->boolean(70) ? PaymentMethod::ORANGE_MONEY : PaymentMethod::MTN_MOMO,
                'status' => $status,
                'note' => $status === WithdrawalStatus::REJETE
                    ? 'Le numéro Mobile Money ne correspond pas au titulaire du compte.'
                    : null,
                'requested_at' => $requestedAt,
                'processed_at' => $status === WithdrawalStatus::EN_ATTENTE ? null : $requestedAt->addHours(fake()->numberBetween(2, 48)),
                'paid_at' => $status === WithdrawalStatus::PAYE ? $requestedAt->addHours(fake()->numberBetween(4, 72)) : null,
                'created_at' => $requestedAt,
                'updated_at' => $requestedAt,
            ]);

            if ($status === WithdrawalStatus::PAYE) {
                $this->recordTransaction((int) $row->user_id, null, TransactionType::WITHDRAWAL, -$amount,
                    'Retrait Mobile Money', $requestedAt->addHours(6));
            }
        }
    }

    // --------------------------------------------------- cohérence des stats --

    /**
     * Les statistiques des techniciens sont recalculées à partir des tickets
     * réellement générés : sans cela, la note affichée sur une fiche ne
     * correspondrait pas aux avis listés juste en dessous.
     */
    private function refreshTechnicianStats(): void
    {
        foreach ($this->technicians as $technician) {
            $userId = $technician['user']->id;

            $reviews = DB::table('reviews')->where('technician_id', $userId)
                ->selectRaw('COUNT(*) as n, COALESCE(AVG(rating), 0) as moyenne')->first();

            $jobs = DB::table('tickets')->where('technician_id', $userId)
                ->whereIn('state', [TicketState::CLOTUREE->value, TicketState::PAYEE->value])->count();

            $attempts = DB::table('match_attempts')->where('technician_id', $userId)
                ->whereIn('response', [
                    MatchResponse::ACCEPTE->value, MatchResponse::REFUSE->value, MatchResponse::EXPIRE->value,
                ])->count();

            $accepted = DB::table('match_attempts')->where('technician_id', $userId)
                ->where('response', MatchResponse::ACCEPTE->value)->count();

            $cancelled = DB::table('tickets')->where('technician_id', $userId)
                ->where('state', TicketState::ANNULEE_TECHNICIEN->value)->count();

            $handled = $jobs + $cancelled;

            TechnicianProfile::query()->where('user_id', $userId)->update([
                'rating_avg' => round((float) ($reviews->moyenne ?? 0), 2),
                'reviews_count' => (int) ($reviews->n ?? 0),
                'jobs_completed' => $jobs,
                'acceptance_rate' => $attempts > 0 ? round($accepted / $attempts, 4) : 0,
                'cancellation_rate' => $handled > 0 ? round($cancelled / $handled, 4) : 0,
            ]);
        }
    }

    private function refreshClientCounters(): void
    {
        DB::statement(<<<'SQL'
            UPDATE client_profiles cp
            SET tickets_count = COALESCE(t.n, 0)
            FROM (SELECT client_id, COUNT(*) AS n FROM tickets GROUP BY client_id) t
            WHERE t.client_id = cp.user_id
        SQL);
    }

    // ------------------------------------------------------------- fabrique --

    /** @return array{user: User, profile: TechnicianProfile, lat: float, lng: float}|null */
    private function pickTechnician(Service $service): ?array
    {
        $code = $service->category?->code?->value;

        $eligible = array_values(array_filter(
            $this->technicians,
            static fn (array $t): bool => $code === null || in_array($code, $t['profile']->specialties, true),
        ));

        return $eligible === [] ? null : fake()->randomElement($eligible);
    }

    private function resolveZone(float $lat, float $lng): ?Zone
    {
        foreach (ZoneSeeder::definitions() as $definition) {
            [$minLat, $minLng, $maxLat, $maxLng] = $definition['box'];

            if ($lat >= $minLat && $lat <= $maxLat && $lng >= $minLng && $lng <= $maxLng) {
                foreach ($this->zones as $zone) {
                    if ($zone->code === $definition['code']) {
                        return $zone;
                    }
                }
            }
        }

        return $this->zones[0] ?? null;
    }

    /**
     * Frais de déplacement selon la règle du §8.2, arrondis au millier supérieur.
     * Cette logique sera portée par PricingService en phase C2 ; elle est
     * reproduite ici pour que les données de démonstration soient justes.
     */
    private function travelFee(?Zone $zone, float $distanceKm): int
    {
        if ($zone === null) {
            return 15_000;
        }

        $billableKm = max(0.0, $distanceKm - $zone->included_km);
        $raw = $zone->base_travel_fee_gnf + (int) round($billableKm * $zone->price_per_km_gnf);
        $rounding = (int) AppSetting::get(AppSetting::TRAVEL_FEE_ROUNDING_GNF, 1_000);

        return (int) (ceil($raw / $rounding) * $rounding);
    }

    private function nextReference(CarbonImmutable $createdAt): string
    {
        $this->sequence++;

        return 'DM-'.$createdAt->format('Y').'-'.str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT);
    }

    private function problemDescription(string $serviceName): string
    {
        return fake()->randomElement([
            'Le problème a commencé hier soir.',
            "Ça dure depuis deux jours et ça s'aggrave.",
            "C'est urgent, il y a de l'eau partout.",
            'Merci de venir dans la matinée si possible.',
            "J'ai déjà essayé de réparer moi-même sans succès.",
        ]).' ('.$serviceName.')';
    }

    /** @return array<int, string> */
    private function photos(CarbonImmutable $createdAt): array
    {
        $count = fake()->numberBetween(0, 3);
        $photos = [];

        for ($i = 1; $i <= $count; $i++) {
            $photos[] = 'intervention-photos/demo/'.$createdAt->format('Y/m').'/probleme-'.fake()->uuid().'.jpg';
        }

        return $photos;
    }

    private function diagnosis(): string
    {
        return fake()->randomElement([
            "Le flexible d'alimentation est fissuré sur toute sa longueur, il faut le remplacer et non le réparer.",
            "Le tableau n'a pas de protection différentielle : ajout d'un disjoncteur 30 mA nécessaire.",
            'La canalisation est corrodée sur environ un mètre, remplacement de la section indispensable.',
            'Le thermostat du chauffe-eau est hors service, pièce à changer.',
        ]);
    }

    private function cancellationReason(TicketState $state): ?string
    {
        return match ($state) {
            TicketState::ANNULEE_CLIENT => fake()->randomElement([
                'Problème résolu entre-temps',
                'Délai trop long',
                'Plus disponible aujourd\'hui',
            ]),
            TicketState::ANNULEE_TECHNICIEN => fake()->randomElement([
                'Intervention précédente prolongée',
                'Véhicule en panne',
                'Adresse introuvable',
            ]),
            default => null,
        };
    }
}
