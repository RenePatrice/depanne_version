<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use App\Domain\Catalog\Models\TechnicianServiceRate;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Le technicien fixe ses tarifs par prestation (#2).
 *
 * Le catalogue reste un **défaut** : un tarif absent ou remis à zéro fait
 * retomber sur le prix catalogue. Une bande de sécurité encadre le prix — ni
 * cadeau qui fausserait la concurrence, ni prix abusif — relative au prix
 * catalogue de chaque prestation. Le déplacement n'entre pas ici : il est
 * calculé par la grille de la zone au moment du devis.
 */
final class SetTechnicianRates
{
    /** Bornes du tarif, en proportion du prix catalogue. */
    private const PLANCHER = 0.5;

    private const PLAFOND = 3.0;

    /**
     * @param  array<int, int|null>  $tarifs  service_id => prix, ou null pour revenir au catalogue
     *
     * @throws DomainException
     */
    public function execute(User $technicien, array $tarifs): void
    {
        $services = Service::query()
            ->whereIn('id', array_keys($tarifs))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($technicien, $tarifs, $services): void {
            foreach ($tarifs as $serviceId => $prix) {
                /** @var Service|null $service */
                $service = $services->get($serviceId);

                if ($service === null) {
                    throw new DomainException('Prestation inconnue ou inactive.');
                }

                if ($prix === null) {
                    TechnicianServiceRate::query()
                        ->where('user_id', $technicien->getKey())
                        ->where('service_id', $serviceId)
                        ->delete();

                    continue;
                }

                $this->verifierBornes($service, $prix);

                TechnicianServiceRate::query()->updateOrCreate(
                    ['user_id' => $technicien->getKey(), 'service_id' => $serviceId],
                    ['price_gnf' => $prix],
                );
            }
        });
    }

    /** @throws DomainException */
    private function verifierBornes(Service $service, int $prix): void
    {
        $plancher = (int) floor($service->base_price_gnf * self::PLANCHER);
        $plafond = (int) ceil($service->base_price_gnf * self::PLAFOND);

        if ($prix < $plancher || $prix > $plafond) {
            throw new DomainException(sprintf(
                'Le tarif de « %s » doit être compris entre %s et %s GNF.',
                $service->name,
                number_format($plancher, 0, ',', ' '),
                number_format($plafond, 0, ',', ' '),
            ));
        }
    }

    /**
     * Bornes exposées à l'application, pour qu'elle guide la saisie.
     *
     * @return array{min: int, max: int}
     */
    public static function bornes(Service $service): array
    {
        return [
            'min' => (int) floor($service->base_price_gnf * self::PLANCHER),
            'max' => (int) ceil($service->base_price_gnf * self::PLAFOND),
        ];
    }
}
