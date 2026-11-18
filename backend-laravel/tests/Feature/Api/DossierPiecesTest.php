<?php

declare(strict_types=1);

use App\Domain\Accounts\Actions\ReviewTechnicianApplication;
use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Models\AppNotification;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Pièces du dossier technicien
|--------------------------------------------------------------------------
|
| Déposées depuis le téléphone sur le disque privé, ré-encodées, rangées sous
| le compte qui les envoie. Le dossier n'accepte que ses propres pièces, et la
| décision du support est poussée à l'application.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);
    config(['filesystems.default' => 'local']);
    Storage::fake('local');
    Storage::fake('public');
});

function technicienSansDossier(): User
{
    $technicien = User::factory()->technician()->create();
    TechnicianProfile::query()->updateOrCreate(['user_id' => $technicien->id], [
        'specialties' => ['PLOMBERIE'],
        'verification_status' => VerificationStatus::EN_ATTENTE_VALIDATION,
    ]);

    return $technicien;
}

function deposer(string $type, ?UploadedFile $fichier = null): TestResponse
{
    return test()->post('/api/v1/technicien/pieces', [
        'type' => $type,
        'photo' => $fichier ?? UploadedFile::fake()->image($type.'.jpg', 2400, 1600),
    ], ['Accept' => 'application/json']);
}

it('range la pièce sur le disque privé, sous le compte, réduite', function (): void {
    $technicien = technicienSansDossier();
    Sanctum::actingAs($technicien);

    $chemin = (string) deposer('recto')->assertCreated()->json('chemin');

    expect($chemin)->toStartWith('identity-docs/'.$technicien->id.'/recto-');
    Storage::disk('local')->assertExists($chemin);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();

    $taille = getimagesizefromstring((string) Storage::disk('local')->get($chemin));
    expect(max($taille[0], $taille[1]))->toBe(1600);
});

it('refuse un type de pièce inconnu et un SVG', function (): void {
    Sanctum::actingAs(technicienSansDossier());

    deposer('passeport')->assertUnprocessable();
    deposer('recto', UploadedFile::fake()->createWithContent('x.svg', '<svg><script/></svg>'))
        ->assertUnprocessable();
});

it('refuse le dépôt à un compte sans casquette technicien', function (): void {
    Sanctum::actingAs(User::factory()->create());

    deposer('recto')->assertUnprocessable();
});

it('n’accepte dans le dossier que les pièces du compte', function (): void {
    $technicien = technicienSansDossier();
    $autre = technicienSansDossier();
    Sanctum::actingAs($technicien);

    $base = [
        'specialties' => ['PLOMBERIE'],
        'latitude' => 9.598,
        'longitude' => -13.643,
        'service_radius_km' => 7,
    ];
    $autreDossier = 'identity-docs/'.$autre->id.'/';
    $monDossier = 'identity-docs/'.$technicien->id.'/';

    // La carte d'identité d'un autre technicien.
    $this->postJson('/api/v1/auth/dossier-technicien', $base + [
        'id_doc_front_url' => $autreDossier.'recto.jpg',
        'id_doc_back_url' => $monDossier.'verso.jpg',
        'selfie_url' => $monDossier.'selfie.jpg',
    ])->assertUnprocessable()->assertJsonValidationErrors('id_doc_front_url');

    // Une remontée d'arborescence déguisée derrière le bon préfixe.
    $this->postJson('/api/v1/auth/dossier-technicien', $base + [
        'id_doc_front_url' => $monDossier.'../'.$autre->id.'/recto.jpg',
        'id_doc_back_url' => $monDossier.'verso.jpg',
        'selfie_url' => $monDossier.'selfie.jpg',
    ])->assertUnprocessable()->assertJsonValidationErrors('id_doc_front_url');
});

it('prévient le technicien quand son dossier est validé ou rejeté', function (): void {
    $valide = technicienSansDossier();
    $rejete = technicienSansDossier();

    app(ReviewTechnicianApplication::class)->approuver($valide->technicianProfile, 1);
    app(ReviewTechnicianApplication::class)->rejeter($rejete->technicianProfile, 'Selfie flou, à reprendre.', 1);

    expect(AppNotification::query()->where('notifiable_id', $valide->id)->value('type'))->toBe('DOSSIER_VALIDE');

    $rejet = AppNotification::query()->where('notifiable_id', $rejete->id)->firstOrFail();
    expect($rejet->type)->toBe('DOSSIER_REJETE')
        ->and($rejet->data['corps'])->toContain('Selfie flou');
});
