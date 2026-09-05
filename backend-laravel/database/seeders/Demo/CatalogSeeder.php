<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Domain\Catalog\Data\Specialty;
use App\Domain\Catalog\Models\Service;
use App\Domain\Catalog\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Catalogue du pilote : deux catégories, vingt prestations à prix fixes.
 * Les montants sont en GNF entiers et reflètent des ordres de grandeur
 * plausibles pour Conakry.
 */
final class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $plomberie = ServiceCategory::query()->updateOrCreate(
            ['code' => Specialty::PLOMBERIE],
            [
                'name' => 'Plomberie',
                'description' => 'Fuites, débouchages, robinetterie, sanitaires et chauffe-eau.',
                'icon' => 'bi-droplet-half',
                'color' => '#1B6FF3',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $electricite = ServiceCategory::query()->updateOrCreate(
            ['code' => Specialty::ELECTRICITE],
            [
                'name' => 'Électricité',
                'description' => 'Pannes, tableaux, prises, éclairage et raccordements.',
                'icon' => 'bi-lightning-charge',
                'color' => '#F59E0B',
                'sort_order' => 2,
                'is_active' => true,
            ],
        );

        $this->createServices($plomberie->id, self::plumbingServices());
        $this->createServices($electricite->id, self::electricalServices());
    }

    /** @param  array<int, array<string, mixed>>  $services */
    private function createServices(int $categoryId, array $services): void
    {
        foreach ($services as $index => $service) {
            Service::query()->updateOrCreate(
                ['slug' => Str::slug($service['name'])],
                [
                    'category_id' => $categoryId,
                    'name' => $service['name'],
                    'description' => $service['description'],
                    'included' => $service['included'],
                    'excluded' => $service['excluded'],
                    'base_price_gnf' => $service['price'],
                    'estimated_duration_min' => $service['duration'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    private static function plumbingServices(): array
    {
        $mainOeuvre = ['Déplacement du technicien', 'Diagnostic sur place', "Main-d'œuvre", 'Garantie 30 jours'];
        $pieces = ['Pièces de rechange', 'Travaux de maçonnerie'];

        return [
            ['name' => 'Réparation de fuite de robinet', 'price' => 50_000, 'duration' => 30,
                'description' => 'Un robinet qui goutte, une fuite au niveau du mitigeur ou du joint : remplacement du joint et réglage.',
                'included' => array_merge($mainOeuvre, ['Joints standards']), 'excluded' => $pieces],
            ['name' => 'Débouchage évier ou lavabo', 'price' => 75_000, 'duration' => 45,
                'description' => 'Évacuation lente ou bouchée : démontage du siphon, furet et nettoyage complet.',
                'included' => array_merge($mainOeuvre, ['Furet manuel']), 'excluded' => $pieces],
            ['name' => 'Débouchage de WC', 'price' => 90_000, 'duration' => 45,
                'description' => "Cuvette bouchée ou évacuation difficile, avec contrôle de l'écoulement après intervention.",
                'included' => $mainOeuvre, 'excluded' => ['Curage de fosse septique', 'Pièces de rechange']],
            ['name' => 'Remplacement de robinet', 'price' => 85_000, 'duration' => 60,
                'description' => "Dépose de l'ancien robinet et pose du neuf, avec mise en eau et contrôle d'étanchéité.",
                'included' => $mainOeuvre, 'excluded' => ['Robinet (fourni par le client)', 'Modification de la tuyauterie']],
            ['name' => "Réparation de chasse d'eau", 'price' => 70_000, 'duration' => 45,
                'description' => 'Chasse qui fuit, qui ne se remplit plus ou qui coule en continu : réglage ou remplacement du mécanisme.',
                'included' => $mainOeuvre, 'excluded' => $pieces],
            ['name' => 'Réparation de fuite sur tuyauterie apparente', 'price' => 120_000, 'duration' => 90,
                'description' => 'Fuite sur canalisation visible : coupure, réparation ou remplacement de la section abîmée.',
                'included' => array_merge($mainOeuvre, ['Raccords standards']), 'excluded' => ['Recherche de fuite encastrée', 'Travaux de maçonnerie']],
            ['name' => 'Remplacement de siphon', 'price' => 60_000, 'duration' => 30,
                'description' => 'Siphon fissuré, corrodé ou qui fuit : dépose et remplacement.',
                'included' => $mainOeuvre, 'excluded' => ['Siphon (fourni par le client)']],
            ['name' => 'Installation de douche', 'price' => 200_000, 'duration' => 180,
                'description' => "Pose complète d'un ensemble de douche : receveur, robinetterie, flexible et raccordements.",
                'included' => array_merge($mainOeuvre, ['Raccordements eau chaude et froide']), 'excluded' => ['Équipement de douche', 'Carrelage', 'Travaux de maçonnerie']],
            ['name' => 'Réparation de chauffe-eau', 'price' => 180_000, 'duration' => 120,
                'description' => 'Chauffe-eau qui ne chauffe plus, qui fuit ou qui disjoncte : diagnostic et réparation.',
                'included' => array_merge($mainOeuvre, ['Détartrage léger']), 'excluded' => ['Résistance, thermostat ou cuve', 'Remplacement complet']],
            ['name' => 'Installation de lavabo', 'price' => 150_000, 'duration' => 120,
                'description' => "Pose et raccordement d'un lavabo avec sa robinetterie et son évacuation.",
                'included' => $mainOeuvre, 'excluded' => ['Lavabo et robinetterie', 'Percement de mur porteur']],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function electricalServices(): array
    {
        $mainOeuvre = ['Déplacement du technicien', 'Diagnostic sur place', "Main-d'œuvre", 'Garantie 30 jours'];

        return [
            ['name' => 'Recherche de panne de courant', 'price' => 60_000, 'duration' => 45,
                'description' => "Plus de courant dans une pièce ou dans tout le logement : recherche de l'origine et remise en service.",
                'included' => array_merge($mainOeuvre, ['Test de continuité']), 'excluded' => ['Pièces de rechange', 'Reprise complète du réseau']],
            ['name' => 'Remplacement de disjoncteur', 'price' => 110_000, 'duration' => 60,
                'description' => 'Disjoncteur qui saute sans raison ou qui ne réarme plus : dépose et remplacement.',
                'included' => $mainOeuvre, 'excluded' => ['Disjoncteur (fourni par le client)']],
            ['name' => 'Installation de prise électrique', 'price' => 55_000, 'duration' => 45,
                'description' => "Ajout ou remplacement d'une prise, avec contrôle de la mise à la terre.",
                'included' => $mainOeuvre, 'excluded' => ['Prise', 'Saignée dans le mur']],
            ['name' => "Remplacement d'interrupteur", 'price' => 45_000, 'duration' => 30,
                'description' => "Interrupteur cassé, qui chauffe ou qui ne commande plus l'éclairage.",
                'included' => $mainOeuvre, 'excluded' => ['Interrupteur (fourni par le client)']],
            ['name' => 'Installation de luminaire', 'price' => 65_000, 'duration' => 45,
                'description' => "Pose et raccordement d'un plafonnier ou d'une applique, fixation comprise.",
                'included' => array_merge($mainOeuvre, ['Fixation au plafond']), 'excluded' => ['Luminaire', 'Reprise du câblage']],
            ['name' => 'Recherche de court-circuit', 'price' => 100_000, 'duration' => 90,
                'description' => 'Court-circuit récurrent : identification du circuit fautif et sécurisation.',
                'included' => array_merge($mainOeuvre, ['Contrôle circuit par circuit']), 'excluded' => ['Réparation lourde', 'Pièces de rechange']],
            ['name' => 'Installation de ventilateur de plafond', 'price' => 120_000, 'duration' => 90,
                'description' => "Fixation, raccordement et équilibrage d'un ventilateur de plafond.",
                'included' => array_merge($mainOeuvre, ['Fixation renforcée']), 'excluded' => ['Ventilateur', 'Renforcement de la structure du plafond']],
            ['name' => 'Remplacement de tableau électrique', 'price' => 350_000, 'duration' => 240,
                'description' => 'Tableau vétuste ou sous-dimensionné : remplacement complet et repérage des circuits.',
                'included' => array_merge($mainOeuvre, ['Repérage et étiquetage des circuits']), 'excluded' => ['Tableau et disjoncteurs', 'Mise aux normes du réseau']],
            ['name' => 'Raccordement de climatiseur', 'price' => 250_000, 'duration' => 180,
                'description' => "Alimentation dédiée et raccordement électrique d'un climatiseur déjà posé.",
                'included' => array_merge($mainOeuvre, ['Ligne dédiée', 'Protection dédiée']), 'excluded' => ['Climatiseur', 'Pose du groupe extérieur', 'Charge en gaz']],
            ['name' => 'Mise à la terre', 'price' => 180_000, 'duration' => 150,
                'description' => "Installation ou reprise d'une prise de terre, avec mesure de la résistance.",
                'included' => array_merge($mainOeuvre, ['Mesure de résistance']), 'excluded' => ['Piquet de terre et câble', 'Terrassement']],
        ];
    }
}
