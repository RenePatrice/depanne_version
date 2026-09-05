<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Domain\Accounts\Data\UserStatus;
use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\Address;
use App\Domain\Accounts\Models\ClientProfile;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Data\Specialty;
use App\Support\Geo;
use Illuminate\Database\Seeder;

/**
 * Population du pilote (§10) : 20 plombiers, 20 électriciens, 20 clients.
 *
 * Les techniciens sont répartis dans les trois zones de Ratoma avec des profils
 * volontairement contrastés — des vétérans bien notés, des débutants sans avis,
 * quelques dossiers en attente de validation et un rejeté — pour que la file de
 * validation, le scoring et les statistiques aient quelque chose à montrer.
 */
final class UserSeeder extends Seeder
{
    /** Quartiers de Ratoma, avec un point représentatif. */
    private const NEIGHBOURHOODS = [
        ['Kipé', 9.5980, -13.6430],
        ['Nongo', 9.5890, -13.6510],
        ['Taouyah', 9.5820, -13.6360],
        ['Kaporo', 9.6210, -13.6280],
        ['Sonfonia', 9.6420, -13.6020],
        ['Lambanyi', 9.6280, -13.6150],
        ['Hamdallaye', 9.5580, -13.6480],
        ['Cosa', 9.5710, -13.6390],
        ['Koloma', 9.5650, -13.6300],
        ['Ratoma centre', 9.5900, -13.6250],
    ];

    private const LANDMARKS = [
        'près de la mosquée',
        'en face de la pharmacie',
        'derrière le marché',
        'à côté de la station-service',
        'près de l\'école primaire',
        'au carrefour, immeuble bleu',
        'près du terrain de football',
        'à 100 m du dispensaire',
    ];

    public function run(): void
    {
        $this->createTechnicians(Specialty::PLOMBERIE, 20);
        $this->createTechnicians(Specialty::ELECTRICITE, 20);
        $this->createClients(20);
    }

    private function createTechnicians(Specialty $specialty, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            // Un technicien sur dix porte aussi la casquette client (§4).
            $user = User::factory()
                ->state($i % 10 === 9 ? ['is_client' => true, 'is_technician' => true] : ['is_client' => false, 'is_technician' => true])
                ->create();

            [$quartier, $lat, $lng] = self::NEIGHBOURHOODS[$i % count(self::NEIGHBOURHOODS)];
            $lat += fake()->randomFloat(4, -0.012, 0.012);
            $lng += fake()->randomFloat(4, -0.012, 0.012);

            $profile = $this->profileShape($i, $count);

            // Deux techniciens sur vingt cumulent les deux spécialités.
            $specialties = $i % 10 === 4
                ? [Specialty::PLOMBERIE->value, Specialty::ELECTRICITE->value]
                : [$specialty->value];

            $radius = fake()->randomElement([5, 7, 10]);

            TechnicianProfile::query()->create([
                'user_id' => $user->id,
                'specialties' => $specialties,
                'verification_status' => $profile['verification'],
                'rejection_reason' => $profile['verification'] === VerificationStatus::REJETE
                    ? "Pièce d'identité illisible : recto flou et date d'expiration masquée."
                    : null,
                'verified_at' => $profile['verification'] === VerificationStatus::VALIDE
                    ? now()->subDays(fake()->numberBetween(20, 300))
                    : null,
                'id_doc_front_url' => 'identity-docs/demo/'.$user->id.'-recto.jpg',
                'id_doc_back_url' => 'identity-docs/demo/'.$user->id.'-verso.jpg',
                'selfie_url' => 'identity-docs/demo/'.$user->id.'-selfie.jpg',
                'base_location' => Geo::point($lat, $lng),
                'service_area' => Geo::boundingBox(
                    $lat - $radius * 0.009,
                    $lng - $radius * 0.009,
                    $lat + $radius * 0.009,
                    $lng + $radius * 0.009,
                ),
                'service_radius_km' => $radius,
                'last_known_location' => Geo::point($lat, $lng),
                'last_position_at' => now()->subMinutes(fake()->numberBetween(1, 240)),
                'is_online' => $profile['online'],
                'rating_avg' => $profile['rating'],
                'reviews_count' => $profile['reviews'],
                'jobs_completed' => $profile['jobs'],
                'acceptance_rate' => $profile['acceptance'],
                'cancellation_rate' => $profile['cancellation'],
            ]);

            if ($user->is_client) {
                ClientProfile::query()->create(['user_id' => $user->id]);
                $this->createAddress($user, $quartier, $lat, $lng, 'Domicile', true);
            }
        }
    }

    /**
     * Répartition volontairement contrastée des profils.
     *
     * @return array<string, mixed>
     */
    private function profileShape(int $index, int $count): array
    {
        // Deux dossiers en attente et un rejeté par lot de vingt : la file de
        // validation du back-office ne doit jamais être vide en démonstration.
        if ($index === $count - 1) {
            return ['verification' => VerificationStatus::REJETE, 'online' => false,
                'rating' => 0.0, 'reviews' => 0, 'jobs' => 0, 'acceptance' => 0.0, 'cancellation' => 0.0];
        }

        if ($index >= $count - 3) {
            return ['verification' => VerificationStatus::EN_ATTENTE_VALIDATION, 'online' => false,
                'rating' => 0.0, 'reviews' => 0, 'jobs' => 0, 'acceptance' => 0.0, 'cancellation' => 0.0];
        }

        // Débutants : validés mais sans historique — ils reçoivent la note
        // neutre de 4,0 dans le scoring tant qu'ils n'ont pas 5 interventions.
        if ($index >= $count - 6) {
            $jobs = fake()->numberBetween(0, 4);

            return ['verification' => VerificationStatus::VALIDE, 'online' => fake()->boolean(60),
                'rating' => $jobs > 0 ? fake()->randomFloat(2, 3.5, 5.0) : 0.0,
                'reviews' => $jobs, 'jobs' => $jobs,
                'acceptance' => fake()->randomFloat(4, 0.5, 1.0), 'cancellation' => 0.0];
        }

        $jobs = fake()->numberBetween(8, 140);

        return [
            'verification' => VerificationStatus::VALIDE,
            'online' => fake()->boolean(55),
            'rating' => fake()->randomFloat(2, 3.4, 5.0),
            'reviews' => (int) round($jobs * fake()->randomFloat(2, 0.55, 0.9)),
            'jobs' => $jobs,
            'acceptance' => fake()->randomFloat(4, 0.45, 0.98),
            'cancellation' => fake()->randomFloat(4, 0.0, 0.12),
        ];
    }

    private function createClients(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->create([
                // Un client sur vingt est suspendu, pour que l'écran de gestion
                // des clients montre les deux cas.
                'status' => $i === $count - 1 ? UserStatus::SUSPENDU : UserStatus::ACTIF,
                'email' => fake()->boolean(40) ? fake()->unique()->safeEmail() : null,
            ]);

            [$quartier, $lat, $lng] = self::NEIGHBOURHOODS[$i % count(self::NEIGHBOURHOODS)];
            $lat += fake()->randomFloat(4, -0.010, 0.010);
            $lng += fake()->randomFloat(4, -0.010, 0.010);

            $home = $this->createAddress($user, $quartier, $lat, $lng, 'Domicile', true);

            // Un client sur trois a aussi enregistré une adresse de bureau.
            if (fake()->boolean(35)) {
                [$autre, $lat2, $lng2] = self::NEIGHBOURHOODS[($i + 3) % count(self::NEIGHBOURHOODS)];
                $this->createAddress($user, $autre, $lat2, $lng2, 'Bureau', false);
            }

            ClientProfile::query()->create([
                'user_id' => $user->id,
                'loyalty_points' => fake()->numberBetween(0, 450),
                'default_address_id' => $home->id,
            ]);
        }
    }

    private function createAddress(User $user, string $quartier, float $lat, float $lng, string $label, bool $isDefault): Address
    {
        return Address::query()->create([
            'user_id' => $user->id,
            'label' => $label,
            'formatted_address' => $quartier.', Ratoma, Conakry',
            'landmark' => fake()->randomElement(self::LANDMARKS).' de '.$quartier,
            'location' => Geo::point($lat, $lng),
            'is_default' => $isDefault,
        ]);
    }

    /** @return array<int, array{0: string, 1: float, 2: float}> */
    public static function neighbourhoods(): array
    {
        return self::NEIGHBOURHOODS;
    }
}
