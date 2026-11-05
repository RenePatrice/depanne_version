<?php

declare(strict_types=1);

use App\Domain\Catalog\Data\ServiceType;
use App\Domain\Catalog\Data\Specialty;
use App\Domain\Catalog\Models\Service;
use App\Domain\Catalog\Models\ServiceCategory;
use App\Http\Requests\Api\DossierTechnicienRequest;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| Ouverture du catalogue aux métiers du BTP (#5, décision du 5 novembre 2026)
|--------------------------------------------------------------------------
|
| Les corps d'état du bâtiment rejoignent le catalogue par la même structure
| que la plomberie et l'électricité : une spécialité = une catégorie, et le
| matching, la validation du dossier et le back-office suivent sans code
| spécifique.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
});

it('ajoute les quatre métiers du BTP au catalogue, actifs', function (): void {
    foreach ([
        Specialty::MACONNERIE,
        Specialty::PEINTURE,
        Specialty::MENUISERIE,
        Specialty::CARRELAGE,
    ] as $specialite) {
        $categorie = ServiceCategory::query()->where('code', $specialite->value)->first();

        expect($categorie)->not->toBeNull()
            ->and($categorie->is_active)->toBeTrue()
            ->and($specialite->estBtp())->toBeTrue()
            ->and($categorie->services()->count())->toBeGreaterThan(0);
    }
});

it('sert les catégories BTP par l’API publique du catalogue', function (): void {
    $this->getJson('/api/v1/catalogue')
        ->assertOk()
        ->assertJsonFragment(['nom' => 'Maçonnerie'])
        ->assertJsonFragment(['nom' => 'Carrelage']);
});

it('type les grosses prestations BTP en installation (zéro photo)', function (): void {
    $pose = Service::query()->where('slug', 'pose-de-carrelage-au-sol')->firstOrFail();
    $fissure = Service::query()->where('slug', 'rebouchage-de-fissure')->firstOrFail();

    // Une pose est une installation : rien à photographier d'avance.
    expect($pose->type)->toBe(ServiceType::INSTALLATION)
        ->and($pose->type->photosAutorisees())->toBe(0)
        // Un rebouchage de fissure est un dépannage : la photo aide à estimer.
        ->and($fissure->type)->toBe(ServiceType::DEPANNAGE)
        ->and($fissure->type->photosAutorisees())->toBe(3);
});

it('accepte une spécialité BTP à la validation du dossier technicien', function (): void {
    // La règle du dossier liste blanche toutes les spécialités de l'énumération
    // (Rule::in) : une spécialité BTP y entre sans code dédié, un intrus non.
    $regle = (new DossierTechnicienRequest)->rules()['specialties.*'];

    $ok = Validator::make(
        ['valeur' => Specialty::CARRELAGE->value],
        ['valeur' => $regle],
    );
    $ko = Validator::make(
        ['valeur' => 'ASTROLOGIE'],
        ['valeur' => $regle],
    );

    expect($ok->passes())->toBeTrue()
        ->and($ko->passes())->toBeFalse();
});
