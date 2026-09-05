<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Accounts\Data\UserStatus;
use App\Domain\Accounts\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    /** Hachage calculé une seule fois : argon2id coûte cher, et c'est voulu. */
    private static ?string $password = null;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'phone' => self::guineanPhone(),
            'password' => self::$password ??= Hash::make('motdepasse'),
            'full_name' => self::guineanName(),
            'email' => null,
            'is_client' => true,
            'is_technician' => false,
            'status' => UserStatus::ACTIF,
            'last_login_at' => fake()->optional(0.8)->dateTimeBetween('-30 days'),
        ];
    }

    public function technician(): self
    {
        return $this->state(fn (): array => [
            'is_client' => false,
            'is_technician' => true,
        ]);
    }

    /** Compte à double casquette : client qui propose aussi ses services (§4). */
    public function dualRole(): self
    {
        return $this->state(fn (): array => [
            'is_client' => true,
            'is_technician' => true,
        ]);
    }

    public function suspended(): self
    {
        return $this->state(fn (): array => ['status' => UserStatus::SUSPENDU]);
    }

    /**
     * Numéro guinéen en E.164. Les préfixes 62x sont Orange, les 66x MTN —
     * la distinction compte pour le paiement Mobile Money.
     */
    public static function guineanPhone(): string
    {
        $prefix = fake()->randomElement(['620', '621', '622', '623', '624', '625', '628', '660', '662', '664']);

        return '+224'.$prefix.fake()->numerify('######');
    }

    public static function guineanName(): string
    {
        static $firstNames = [
            'Mamadou', 'Ibrahima', 'Alpha', 'Ousmane', 'Sékou', 'Abdoulaye', 'Amadou',
            'Thierno', 'Lansana', 'Moussa', 'Fodé', 'Kabinet', 'Boubacar', 'Saïdou', 'Mory',
            'Fatoumata', 'Mariama', 'Aïssatou', 'Kadiatou', 'Djénabou', 'Aminata',
            'Néné', 'Oumou', 'Fanta', 'Sayon', 'Bountouraby', 'Hadja',
        ];

        static $lastNames = [
            'Diallo', 'Barry', 'Bah', 'Sow', 'Camara', 'Kourouma', 'Sylla', 'Keita',
            'Cissé', 'Soumah', 'Bangoura', 'Sangaré', 'Traoré', 'Doumbouya', 'Fofana',
            'Kaba', 'Baldé', 'Touré',
        ];

        return $firstNames[array_rand($firstNames)].' '.$lastNames[array_rand($lastNames)];
    }
}
