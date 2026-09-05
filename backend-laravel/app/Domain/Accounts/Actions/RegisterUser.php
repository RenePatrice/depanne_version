<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Data\UserStatus;
use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\ClientProfile;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Data\Specialty;
use App\Support\Geo;
use App\Support\Telephone;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Inscription en trois étapes (§4).
 *
 * Les étapes 1 et 2 — « je suis… » et « mes informations » — créent le compte.
 * L'étape 3 ne concerne que les techniciens : spécialités, zone d'intervention
 * et pièces justificatives. Elle est **séparée** parce qu'un technicien peut
 * abandonner en cours de route, et qu'un compte à moitié créé serait pire
 * qu'un dossier incomplet.
 *
 * Un technicien reste en `EN_ATTENTE_VALIDATION` jusqu'à approbation en
 * back-office : c'est ce qui fait tenir la promesse de « techniciens vérifiés ».
 */
final class RegisterUser
{
    /**
     * @param  array<string, mixed>  $donnees
     *
     * @throws DomainException si le téléphone n'est pas exploitable
     */
    public function execute(array $donnees): User
    {
        $telephone = Telephone::normaliser((string) $donnees['phone']);

        if ($telephone === null) {
            throw new DomainException('Ce numéro de téléphone n\'est pas valide en Guinée.');
        }

        $estTechnicien = (bool) ($donnees['is_technician'] ?? false);

        return DB::transaction(function () use ($donnees, $telephone, $estTechnicien): User {
            $utilisateur = User::query()->create([
                'phone' => $telephone,
                'password' => (string) $donnees['password'],
                'full_name' => trim((string) $donnees['full_name']),
                'email' => isset($donnees['email']) && $donnees['email'] !== ''
                    ? mb_strtolower(trim((string) $donnees['email']))
                    : null,
                // Un compte n'est jamais créé avec les deux casquettes : la
                // seconde s'active depuis les paramètres, une fois la première
                // éprouvée.
                'is_client' => ! $estTechnicien,
                'is_technician' => $estTechnicien,
                'status' => UserStatus::ACTIF,
            ]);

            if ($estTechnicien) {
                TechnicianProfile::query()->create([
                    'user_id' => $utilisateur->id,
                    'specialties' => [],
                    'verification_status' => VerificationStatus::EN_ATTENTE_VALIDATION,
                ]);
            } else {
                ClientProfile::query()->create(['user_id' => $utilisateur->id]);
            }

            activity('comptes')
                ->performedOn($utilisateur)
                ->withProperties(['casquette' => $estTechnicien ? 'technicien' : 'client'])
                ->log('Inscription');

            return $utilisateur;
        });
    }

    /**
     * Étape 3 : le dossier professionnel du technicien.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function completerDossierTechnicien(User $utilisateur, array $donnees): TechnicianProfile
    {
        if (! $utilisateur->is_technician) {
            throw new DomainException('Ce compte n\'a pas de casquette technicien.');
        }

        $utilisateur->loadMissing('technicianProfile');
        $profil = $utilisateur->technicianProfile;

        if ($profil === null) {
            throw new DomainException('Dossier technicien introuvable.');
        }

        if ($profil->verification_status === VerificationStatus::VALIDE) {
            throw new DomainException(
                'Ton dossier est déjà validé : passe par le support pour le modifier.'
            );
        }

        $specialites = $this->specialites($donnees['specialties'] ?? []);
        $rayon = (int) ($donnees['service_radius_km'] ?? 5);

        if ($rayon < 1 || $rayon > 30) {
            throw new DomainException('Le rayon d\'intervention doit être compris entre 1 et 30 km.');
        }

        $latitude = (float) $donnees['latitude'];
        $longitude = (float) $donnees['longitude'];

        $profil->forceFill([
            'specialties' => $specialites,
            'base_location' => Geo::point($latitude, $longitude),
            // La zone déclarée est un carré autour du point de départ : le
            // technicien la dessine avec un rayon, pas avec un polygone.
            'service_area' => Geo::boundingBox(
                $latitude - $rayon * 0.009,
                $longitude - $rayon * 0.009,
                $latitude + $rayon * 0.009,
                $longitude + $rayon * 0.009,
            ),
            'service_radius_km' => $rayon,
            'id_doc_front_url' => $donnees['id_doc_front_url'] ?? $profil->id_doc_front_url,
            'id_doc_back_url' => $donnees['id_doc_back_url'] ?? $profil->id_doc_back_url,
            'selfie_url' => $donnees['selfie_url'] ?? $profil->selfie_url,
            // Un dossier rejeté qui se complète repart en attente.
            'verification_status' => VerificationStatus::EN_ATTENTE_VALIDATION,
            'rejection_reason' => null,
        ])->save();

        activity('techniciens')
            ->performedOn($profil)
            ->withProperties(['specialites' => $specialites, 'rayon_km' => $rayon])
            ->log('Dossier technicien complété');

        return $profil;
    }

    /**
     * Active la seconde casquette depuis les paramètres (§4).
     * Un client devient technicien avec un dossier à valider ; un technicien
     * devient client immédiatement — rien à vérifier pour commander un dépannage.
     */
    public function activerSecondeCasquette(User $utilisateur): User
    {
        return DB::transaction(function () use ($utilisateur): User {
            if (! $utilisateur->is_technician) {
                $utilisateur->forceFill(['is_technician' => true])->save();

                TechnicianProfile::query()->firstOrCreate(
                    ['user_id' => $utilisateur->id],
                    ['specialties' => [], 'verification_status' => VerificationStatus::EN_ATTENTE_VALIDATION],
                );
            } elseif (! $utilisateur->is_client) {
                $utilisateur->forceFill(['is_client' => true])->save();
                ClientProfile::query()->firstOrCreate(['user_id' => $utilisateur->id]);
            } else {
                throw new DomainException('Ce compte porte déjà les deux casquettes.');
            }

            activity('comptes')
                ->performedOn($utilisateur)
                ->log('Seconde casquette activée');

            return $utilisateur->refresh();
        });
    }

    /**
     * @param  array<int, string>|mixed  $brut
     * @return array<int, string>
     */
    private function specialites(mixed $brut): array
    {
        $codes = array_values(array_unique(array_filter(
            array_map(strval(...), (array) $brut),
            static fn (string $code): bool => Specialty::tryFrom($code) !== null,
        )));

        if ($codes === []) {
            throw new DomainException('Choisis au moins une spécialité : plomberie ou électricité.');
        }

        return $codes;
    }
}
