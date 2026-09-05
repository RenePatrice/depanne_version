<?php

declare(strict_types=1);

namespace App\Domain\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Paramètre modifiable en back-office. Rien de ce qui est ici ne doit être
 * codé en dur ailleurs : taux de commission, rayons et délais du matching,
 * pondérations du score, fenêtres de séquestre et de litige.
 *
 * La lecture passe par le cache : ces valeurs sont lues à chaque cycle de
 * matching et à chaque calcul de prix.
 */
final class AppSetting extends Model
{
    /** Clés attendues par le domaine. Les valeurs par défaut sont dans le seeder. */
    public const COMMISSION_RATE = 'commission_rate';

    public const MATCH_INITIAL_RADIUS_KM = 'match_initial_radius_km';

    public const MATCH_RADIUS_STEP_KM = 'match_radius_step_km';

    public const MATCH_MAX_RADIUS_KM = 'match_max_radius_km';

    public const MATCH_RESPONSE_SECONDS = 'match_response_seconds';

    public const MATCH_MAX_CYCLES = 'match_max_cycles';

    public const MATCH_CANDIDATES_PER_CYCLE = 'match_candidates_per_cycle';

    public const SCORE_WEIGHTS = 'score_weights';

    public const NEWCOMER_RATING = 'newcomer_rating';

    public const NEWCOMER_JOBS_THRESHOLD = 'newcomer_jobs_threshold';

    public const ESCROW_AUTO_RELEASE_HOURS = 'escrow_auto_release_hours';

    public const DISPUTE_WINDOW_HOURS = 'dispute_window_hours';

    public const CANCELLATION_FEE_GNF = 'cancellation_fee_gnf';

    public const WITHDRAWAL_MIN_GNF = 'withdrawal_min_gnf';

    public const TRAVEL_FEE_ROUNDING_GNF = 'travel_fee_rounding_gnf';

    public const HAVERSINE_ROAD_FACTOR = 'haversine_road_factor';

    public const CGU = 'cgu';

    public const POLITIQUE_CONFIDENTIALITE = 'politique_confidentialite';

    public const NOTIF_TICKET_PUBLIE = 'notif_ticket_publie';

    public const NOTIF_TICKET_ACCEPTE = 'notif_ticket_accepte';

    public const NOTIF_TECHNICIEN_ARRIVE = 'notif_technicien_arrive';

    public const NOTIF_PAIEMENT_RECU = 'notif_paiement_recu';

    private const CACHE_PREFIX = 'app_settings:';

    public $timestamps = true;

    protected $fillable = ['key', 'value', 'group', 'label', 'description', 'type', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /**
     * Les valeurs sont stockées en JSON pour supporter aussi bien un entier
     * qu'un tableau de pondérations. La valeur scalaire est enveloppée dans
     * `['v' => …]` afin que `value` reste toujours un objet JSON valide.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(self::CACHE_PREFIX.$key, static function () use ($key, $default): mixed {
            $setting = self::query()->where('key', $key)->first();

            if ($setting === null) {
                return $default;
            }

            $value = $setting->value;

            return array_key_exists('v', $value) ? $value['v'] : $value;
        });
    }

    public static function put(string $key, mixed $value, ?int $updatedBy = null): void
    {
        self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => ['v' => $value], 'updated_by' => $updatedBy],
        );

        Cache::forget(self::CACHE_PREFIX.$key);
    }

    public static function flushCache(): void
    {
        foreach (self::query()->pluck('key') as $key) {
            Cache::forget(self::CACHE_PREFIX.$key);
        }
    }
}
