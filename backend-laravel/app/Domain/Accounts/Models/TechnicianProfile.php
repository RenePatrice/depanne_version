<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Models;

use App\Domain\Accounts\Data\VerificationStatus;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Data\Geometries\Polygon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dossier professionnel du technicien : spécialités, zone d'intervention,
 * pièces justificatives et statistiques de matching.
 *
 * Les quatre statistiques (note, taux d'acceptation, taux d'annulation, nombre
 * d'interventions) sont dénormalisées ici parce que le scoring les lit à chaque
 * cycle de matching : elles sont recalculées par le domaine, jamais saisies.
 */
final class TechnicianProfile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id', 'specialties', 'verification_status', 'rejection_reason',
        'verified_at', 'verified_by', 'id_doc_front_url', 'id_doc_back_url',
        'selfie_url', 'service_area', 'base_location', 'service_radius_km',
        'last_known_location', 'last_position_at', 'is_online',
        'rating_avg', 'reviews_count', 'jobs_completed',
        'acceptance_rate', 'cancellation_rate',
    ];

    protected function casts(): array
    {
        return [
            'specialties' => 'array',
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
            'last_position_at' => 'datetime',
            'service_area' => Polygon::class,
            'base_location' => Point::class,
            'last_known_location' => Point::class,
            'service_radius_km' => 'integer',
            'is_online' => 'boolean',
            'rating_avg' => 'float',
            'reviews_count' => 'integer',
            'jobs_completed' => 'integer',
            'acceptance_rate' => 'float',
            'cancellation_rate' => 'float',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ---------------------------------------------------------------- scopes --

    /**
     * Techniciens éligibles au matching : en ligne et validés (§8.3, étape 1).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_online', true)
            ->where('verification_status', VerificationStatus::VALIDE);
    }

    /**
     * @param  Builder<TechnicianProfile>  $query
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithSpecialty(Builder $query, string $specialty): Builder
    {
        return $query->whereJsonContains('specialties', $specialty);
    }

    // --------------------------------------------------------------- helpers --

    /**
     * Note utilisée par le scoring. Un technicien qui n'a pas encore cinq
     * interventions reçoit la note neutre de 4,0 (§8.3) : sans cela, un nouveau
     * venu partirait avec un score de zéro et ne serait jamais sollicité.
     */
    public function effectiveRating(): float
    {
        return $this->jobs_completed < 5 ? 4.0 : $this->rating_avg;
    }

    public function isNewcomer(): bool
    {
        return $this->jobs_completed < 5;
    }
}
