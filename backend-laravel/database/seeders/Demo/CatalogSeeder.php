<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Domain\Catalog\Data\ServiceType;
use App\Domain\Catalog\Data\Specialty;
use App\Domain\Catalog\Models\Service;
use App\Domain\Catalog\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Catalogue du pilote : plomberie et électricité, plus les métiers du BTP
 * ouverts en #5 (maçonnerie, peinture, menuiserie, carrelage). Prix fixes en
 * GNF entiers, reflétant des ordres de grandeur plausibles pour Conakry.
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

        // Métiers du BTP (#5) : le catalogue s'ouvre aux corps d'état du
        // bâtiment. Majoritairement des installations — il n'y a rien à
        // photographier avant la venue de l'artisan.
        $maconnerie = ServiceCategory::query()->updateOrCreate(
            ['code' => Specialty::MACONNERIE],
            [
                'name' => 'Maçonnerie',
                'description' => 'Murs, enduits, dalles, scellements et petites reprises.',
                'icon' => 'bi-bricks',
                'color' => '#8B5E3C',
                'sort_order' => 3,
                'is_active' => true,
            ],
        );

        $peinture = ServiceCategory::query()->updateOrCreate(
            ['code' => Specialty::PEINTURE],
            [
                'name' => 'Peinture',
                'description' => 'Peinture intérieure et extérieure, enduits de lissage, reprises.',
                'icon' => 'bi-palette',
                'color' => '#7C3AED',
                'sort_order' => 4,
                'is_active' => true,
            ],
        );

        $menuiserie = ServiceCategory::query()->updateOrCreate(
            ['code' => Specialty::MENUISERIE],
            [
                'name' => 'Menuiserie',
                'description' => 'Portes, fenêtres, serrures, meubles et ajustements bois ou alu.',
                'icon' => 'bi-hammer',
                'color' => '#B45309',
                'sort_order' => 5,
                'is_active' => true,
            ],
        );

        $carrelage = ServiceCategory::query()->updateOrCreate(
            ['code' => Specialty::CARRELAGE],
            [
                'name' => 'Carrelage',
                'description' => 'Pose de carrelage, faïence, joints et reprises de sol.',
                'icon' => 'bi-grid-3x3-gap',
                'color' => '#0D9488',
                'sort_order' => 6,
                'is_active' => true,
            ],
        );

        $this->createServices($plomberie->id, self::plumbingServices());
        $this->createServices($electricite->id, self::electricalServices());
        $this->createServices($maconnerie->id, self::masonryServices());
        $this->createServices($peinture->id, self::paintingServices());
        $this->createServices($menuiserie->id, self::carpentryServices());
        $this->createServices($carrelage->id, self::tilingServices());
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
                    // Dépannage par défaut (#2) : une installation n'a rien à
                    // photographier avant la venue du technicien.
                    'type' => $service['type'] ?? ServiceType::DEPANNAGE->value,
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
            ['name' => 'Installation de douche', 'price' => 200_000, 'duration' => 180, 'type' => ServiceType::INSTALLATION->value,
                'description' => "Pose complète d'un ensemble de douche : receveur, robinetterie, flexible et raccordements.",
                'included' => array_merge($mainOeuvre, ['Raccordements eau chaude et froide']), 'excluded' => ['Équipement de douche', 'Carrelage', 'Travaux de maçonnerie']],
            ['name' => 'Réparation de chauffe-eau', 'price' => 180_000, 'duration' => 120,
                'description' => 'Chauffe-eau qui ne chauffe plus, qui fuit ou qui disjoncte : diagnostic et réparation.',
                'included' => array_merge($mainOeuvre, ['Détartrage léger']), 'excluded' => ['Résistance, thermostat ou cuve', 'Remplacement complet']],
            ['name' => 'Installation de lavabo', 'price' => 150_000, 'duration' => 120, 'type' => ServiceType::INSTALLATION->value,
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
            ['name' => 'Installation de prise électrique', 'price' => 55_000, 'duration' => 45, 'type' => ServiceType::INSTALLATION->value,
                'description' => "Ajout ou remplacement d'une prise, avec contrôle de la mise à la terre.",
                'included' => $mainOeuvre, 'excluded' => ['Prise', 'Saignée dans le mur']],
            ['name' => "Remplacement d'interrupteur", 'price' => 45_000, 'duration' => 30,
                'description' => "Interrupteur cassé, qui chauffe ou qui ne commande plus l'éclairage.",
                'included' => $mainOeuvre, 'excluded' => ['Interrupteur (fourni par le client)']],
            ['name' => 'Installation de luminaire', 'price' => 65_000, 'duration' => 45, 'type' => ServiceType::INSTALLATION->value,
                'description' => "Pose et raccordement d'un plafonnier ou d'une applique, fixation comprise.",
                'included' => array_merge($mainOeuvre, ['Fixation au plafond']), 'excluded' => ['Luminaire', 'Reprise du câblage']],
            ['name' => 'Recherche de court-circuit', 'price' => 100_000, 'duration' => 90,
                'description' => 'Court-circuit récurrent : identification du circuit fautif et sécurisation.',
                'included' => array_merge($mainOeuvre, ['Contrôle circuit par circuit']), 'excluded' => ['Réparation lourde', 'Pièces de rechange']],
            ['name' => 'Installation de ventilateur de plafond', 'price' => 120_000, 'duration' => 90, 'type' => ServiceType::INSTALLATION->value,
                'description' => "Fixation, raccordement et équilibrage d'un ventilateur de plafond.",
                'included' => array_merge($mainOeuvre, ['Fixation renforcée']), 'excluded' => ['Ventilateur', 'Renforcement de la structure du plafond']],
            ['name' => 'Remplacement de tableau électrique', 'price' => 350_000, 'duration' => 240, 'type' => ServiceType::INSTALLATION->value,
                'description' => 'Tableau vétuste ou sous-dimensionné : remplacement complet et repérage des circuits.',
                'included' => array_merge($mainOeuvre, ['Repérage et étiquetage des circuits']), 'excluded' => ['Tableau et disjoncteurs', 'Mise aux normes du réseau']],
            ['name' => 'Raccordement de climatiseur', 'price' => 250_000, 'duration' => 180, 'type' => ServiceType::INSTALLATION->value,
                'description' => "Alimentation dédiée et raccordement électrique d'un climatiseur déjà posé.",
                'included' => array_merge($mainOeuvre, ['Ligne dédiée', 'Protection dédiée']), 'excluded' => ['Climatiseur', 'Pose du groupe extérieur', 'Charge en gaz']],
            ['name' => 'Mise à la terre', 'price' => 180_000, 'duration' => 150, 'type' => ServiceType::INSTALLATION->value,
                'description' => "Installation ou reprise d'une prise de terre, avec mesure de la résistance.",
                'included' => array_merge($mainOeuvre, ['Mesure de résistance']), 'excluded' => ['Piquet de terre et câble', 'Terrassement']],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function masonryServices(): array
    {
        $base = ['Déplacement du technicien', 'Diagnostic sur place', "Main-d'œuvre", 'Garantie 30 jours'];
        $materiaux = ['Ciment, sable et agrégats', 'Évacuation des gravats'];

        return [
            ['name' => 'Rebouchage de fissure', 'price' => 70_000, 'duration' => 60,
                'description' => 'Reprise de fissures sur mur intérieur ou extérieur : ouverture, nettoyage et rebouchage.',
                'included' => array_merge($base, ['Enduit de rebouchage']), 'excluded' => ['Reprise de structure porteuse']],
            ['name' => 'Scellement et petits travaux', 'price' => 90_000, 'duration' => 90,
                'description' => 'Scellement de support, pose de tasseau, reprise ponctuelle de maçonnerie.',
                'included' => $base, 'excluded' => $materiaux],
            ['name' => 'Montage de mur en agglos', 'price' => 350_000, 'duration' => 300, 'type' => ServiceType::INSTALLATION->value,
                'description' => "Élévation d'un mur de séparation en parpaings, jointoiement compris.",
                'included' => $base, 'excluded' => $materiaux],
            ['name' => 'Enduit et crépi', 'price' => 250_000, 'duration' => 240, 'type' => ServiceType::INSTALLATION->value,
                'description' => "Application d'enduit ou de crépi sur mur, dressage et finition.",
                'included' => $base, 'excluded' => array_merge($materiaux, ['Échafaudage'])],
            ['name' => 'Coulage de dalle', 'price' => 400_000, 'duration' => 360, 'type' => ServiceType::INSTALLATION->value,
                'description' => "Préparation et coulage d'une dalle béton, mise à niveau et talochage.",
                'included' => $base, 'excluded' => array_merge($materiaux, ['Ferraillage', 'Coffrage'])],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function paintingServices(): array
    {
        $base = ['Déplacement du technicien', 'Diagnostic sur place', "Main-d'œuvre", 'Protection du sol et du mobilier', 'Garantie 30 jours'];

        return [
            ['name' => 'Reprise de peinture localisée', 'price' => 60_000, 'duration' => 60,
                'description' => "Retouche d'un mur abîmé, trace d'humidité séchée ou éclat : ponçage et remise en peinture.",
                'included' => $base, 'excluded' => ['Peinture (fournie par le client)']],
            ['name' => 'Peinture d\'une pièce', 'price' => 300_000, 'duration' => 300, 'type' => ServiceType::INSTALLATION->value,
                'description' => "Peinture complète d'une pièce standard : préparation, sous-couche et deux couches.",
                'included' => $base, 'excluded' => ['Peinture et sous-couche', 'Rebouchage important']],
            ['name' => 'Enduit de lissage', 'price' => 180_000, 'duration' => 180, 'type' => ServiceType::INSTALLATION->value,
                'description' => 'Lissage des murs avant peinture : enduit, ponçage et dépoussiérage.',
                'included' => $base, 'excluded' => ['Enduit (fourni par le client)']],
            ['name' => 'Peinture de façade', 'price' => 500_000, 'duration' => 420, 'type' => ServiceType::INSTALLATION->value,
                'description' => "Peinture extérieure d'une façade : nettoyage, traitement et mise en peinture.",
                'included' => $base, 'excluded' => ['Peinture façade', 'Échafaudage', 'Reprise d\'enduit']],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function carpentryServices(): array
    {
        $base = ['Déplacement du technicien', 'Diagnostic sur place', "Main-d'œuvre", 'Garantie 30 jours'];

        return [
            ['name' => 'Réparation de porte ou fenêtre', 'price' => 70_000, 'duration' => 60,
                'description' => 'Porte qui ferme mal, gonds descellés, fenêtre qui frotte : réglage et reprise.',
                'included' => $base, 'excluded' => ['Pièces de rechange']],
            ['name' => 'Remplacement de serrure', 'price' => 90_000, 'duration' => 60,
                'description' => "Dépose de l'ancienne serrure et pose de la neuve, avec essai des clés.",
                'included' => $base, 'excluded' => ['Serrure (fournie par le client)']],
            ['name' => 'Pose de porte intérieure', 'price' => 200_000, 'duration' => 150, 'type' => ServiceType::INSTALLATION->value,
                'description' => "Pose d'un bloc-porte intérieur : fixation du bâti, réglage et quincaillerie.",
                'included' => $base, 'excluded' => ['Bloc-porte', 'Reprise de maçonnerie']],
            ['name' => 'Pose de fenêtre', 'price' => 280_000, 'duration' => 180, 'type' => ServiceType::INSTALLATION->value,
                'description' => "Pose et réglage d'une fenêtre bois ou aluminium, étanchéité comprise.",
                'included' => $base, 'excluded' => ['Fenêtre', 'Habillage', 'Reprise de maçonnerie']],
            ['name' => 'Montage de meuble', 'price' => 80_000, 'duration' => 90, 'type' => ServiceType::INSTALLATION->value,
                'description' => "Assemblage et fixation d'un meuble en kit, mise de niveau comprise.",
                'included' => $base, 'excluded' => ['Meuble (fourni par le client)']],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function tilingServices(): array
    {
        $base = ['Déplacement du technicien', 'Diagnostic sur place', "Main-d'œuvre", 'Garantie 30 jours'];
        $materiaux = ['Carrelage et colle', 'Évacuation des gravats'];

        return [
            ['name' => 'Reprise de joints', 'price' => 70_000, 'duration' => 90,
                'description' => 'Joints de carrelage noircis ou abîmés : grattage et refaçonnage.',
                'included' => array_merge($base, ['Joint']), 'excluded' => ['Remplacement de carreaux']],
            ['name' => 'Remplacement de carreaux cassés', 'price' => 90_000, 'duration' => 120,
                'description' => 'Dépose des carreaux fêlés et pose de carreaux neufs, jointoiement compris.',
                'included' => $base, 'excluded' => ['Carreaux (fournis par le client)']],
            ['name' => 'Pose de carrelage au sol', 'price' => 350_000, 'duration' => 360, 'type' => ServiceType::INSTALLATION->value,
                'description' => 'Pose de carrelage sur une pièce : préparation du support, pose et joints.',
                'included' => $base, 'excluded' => $materiaux],
            ['name' => 'Pose de faïence murale', 'price' => 300_000, 'duration' => 300, 'type' => ServiceType::INSTALLATION->value,
                'description' => 'Pose de faïence sur mur de cuisine ou salle de bain, découpes comprises.',
                'included' => $base, 'excluded' => $materiaux],
        ];
    }
}
