<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\Address;
use App\Domain\Accounts\Models\User;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Zones\Services\ZoneService;
use App\Support\Geo;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Carnet d'adresses du client (§7.2).
 *
 * Une seule classe pour les quatre opérations : elles partagent l'invariant
 * « exactement une adresse par défaut », qu'il serait facile de casser en le
 * répartissant sur quatre fichiers.
 */
final class ManageAddresses
{
    public const MAX_ADRESSES = 10;

    public function __construct(private readonly ZoneService $zones) {}

    /**
     * @param  array{label: string, formatted_address: string, landmark?: string|null, latitude: float, longitude: float, is_default?: bool}  $donnees
     *
     * @throws DomainException
     */
    public function creer(User $client, array $donnees): Address
    {
        $existantes = Address::query()->where('user_id', $client->getKey())->count();

        if ($existantes >= self::MAX_ADRESSES) {
            throw new DomainException(sprintf(
                'Tu as atteint la limite de %d adresses. Supprime-en une avant d\'en ajouter.',
                self::MAX_ADRESSES,
            ));
        }

        return DB::transaction(function () use ($client, $donnees, $existantes): Address {
            // La première adresse est celle par défaut : sans cela, le client
            // devrait faire un geste supplémentaire avant sa première demande.
            $parDefaut = ($donnees['is_default'] ?? false) || $existantes === 0;

            if ($parDefaut) {
                $this->retirerLeDefaut($client);
            }

            /** @var Address */
            return Address::query()->create([
                'user_id' => $client->getKey(),
                'label' => $donnees['label'],
                'formatted_address' => $donnees['formatted_address'],
                'landmark' => $donnees['landmark'] ?? null,
                'location' => Geo::point($donnees['latitude'], $donnees['longitude']),
                'is_default' => $parDefaut,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $donnees
     *
     * @throws DomainException
     */
    public function modifier(Address $adresse, array $donnees): Address
    {
        return DB::transaction(function () use ($adresse, $donnees): Address {
            if (($donnees['is_default'] ?? false) === true) {
                $this->retirerLeDefaut($adresse->user);
            }

            $modifications = array_filter([
                'label' => $donnees['label'] ?? null,
                'formatted_address' => $donnees['formatted_address'] ?? null,
            ], static fn (mixed $v): bool => $v !== null);

            if (array_key_exists('landmark', $donnees)) {
                $modifications['landmark'] = $donnees['landmark'];
            }

            if (isset($donnees['latitude'], $donnees['longitude'])) {
                $modifications['location'] = Geo::point(
                    (float) $donnees['latitude'],
                    (float) $donnees['longitude'],
                );
            }

            if (array_key_exists('is_default', $donnees)) {
                $modifications['is_default'] = (bool) $donnees['is_default'];
            }

            $adresse->forceFill($modifications)->save();

            return $adresse->refresh();
        });
    }

    /**
     * Suppression douce : les tickets passés gardent leur `address_snapshot`,
     * mais une intervention en cours a encore besoin de l'adresse vivante.
     *
     * @throws DomainException
     */
    public function supprimer(Address $adresse): void
    {
        $enCours = Ticket::query()
            ->where('address_id', $adresse->getKey())
            ->active()
            ->exists();

        if ($enCours) {
            throw new DomainException(
                'Une intervention est en cours à cette adresse : elle ne peut pas être supprimée maintenant.'
            );
        }

        DB::transaction(function () use ($adresse): void {
            $etaitParDefaut = $adresse->is_default;
            $client = $adresse->user;

            $adresse->delete();

            // Le carnet ne doit jamais rester sans adresse par défaut, sinon
            // la prochaine demande s'ouvre sur un champ vide.
            if ($etaitParDefaut) {
                Address::query()
                    ->where('user_id', $client->getKey())
                    ->orderByDesc('id')
                    ->limit(1)
                    ->update(['is_default' => true]);
            }
        });
    }

    /** Vrai si l'adresse tombe dans une zone desservie. */
    public function estCouverte(Address $adresse): bool
    {
        return $adresse->location !== null
            && $this->zones->pour($adresse->location) !== null;
    }

    private function retirerLeDefaut(User $client): void
    {
        Address::query()
            ->where('user_id', $client->getKey())
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
