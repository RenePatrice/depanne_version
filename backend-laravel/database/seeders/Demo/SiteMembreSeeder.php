<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Domain\Site\Models\SiteMembre;
use Illuminate\Database\Seeder;

/**
 * L'équipe présentée sur le site vitrine. Les photos se déposent ensuite en
 * back-office (`site.gerer`) ; sans photo, une pastille d'initiales s'affiche.
 */
final class SiteMembreSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::membres() as $membre) {
            SiteMembre::query()->updateOrCreate(
                ['nom' => $membre['nom']],
                $membre,
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    private static function membres(): array
    {
        return [
            [
                'nom' => 'René Patrice MILLIMOUNO',
                'role' => 'Fondateur & CEO',
                'bio' => 'Porte la vision de Parema Connect : rendre le dépannage et les travaux '
                    .'à Conakry simples, sûrs et transparents pour tous.',
                'ordre' => 1,
                'actif' => true,
            ],
            [
                'nom' => 'Pascal Millimouno',
                'role' => 'Co-fondateur · Directeur des opérations',
                'bio' => 'Veille au terrain : qualité des interventions, réseau de techniciens '
                    .'vérifiés et satisfaction des clients.',
                'ordre' => 2,
                'actif' => true,
            ],
            [
                'nom' => 'Marcelin Sandouno',
                'role' => 'Co-fondateur · Directeur technique',
                'bio' => 'Conçoit la plateforme et l\'application mobile, avec la sécurité et '
                    .'la fiabilité comme première exigence.',
                'ordre' => 3,
                'actif' => true,
            ],
        ];
    }
}
