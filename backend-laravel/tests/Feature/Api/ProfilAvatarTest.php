<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Photo de profil et version de l'application
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    config(['filesystems.media' => 'public']);
    Storage::fake('public');
});

/** Une vraie image JPEG, avec des métadonnées EXIF dans un segment APP1. */
function jpegAvecExif(): UploadedFile
{
    $image = imagecreatetruecolor(800, 600);
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();

    // Segment APP1 « Exif » glissé juste après le marqueur SOI.
    $exif = "Exif\0\0".'GPS-LATITUDE-9.6412';
    $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;
    $contenu = substr($jpeg, 0, 2).$app1.substr($jpeg, 2);

    return UploadedFile::fake()->createWithContent('photo.jpg', $contenu);
}

it('ré-encode la photo en carré de 512 px et efface les métadonnées', function (): void {
    $utilisateur = User::factory()->create();
    Sanctum::actingAs($utilisateur);

    $reponse = $this->post('/api/v1/moi/avatar', ['photo' => jpegAvecExif()], ['Accept' => 'application/json'])
        ->assertOk();

    $url = (string) $reponse->json('utilisateur.avatar_url');
    expect($url)->toContain('/avatars/');

    $fichiers = Storage::disk('public')->files('avatars');
    expect($fichiers)->toHaveCount(1);

    $contenu = Storage::disk('public')->get($fichiers[0]);
    expect($contenu)->not->toContain('GPS-LATITUDE');

    $taille = getimagesizefromstring((string) $contenu);
    expect($taille[0])->toBe(512)->and($taille[1])->toBe(512);
});

it('remplace l’ancienne photo au lieu de l’accumuler', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->post('/api/v1/moi/avatar', ['photo' => jpegAvecExif()], ['Accept' => 'application/json'])->assertOk();
    $this->post('/api/v1/moi/avatar', ['photo' => jpegAvecExif()], ['Accept' => 'application/json'])->assertOk();

    expect(Storage::disk('public')->files('avatars'))->toHaveCount(1);
});

it('refuse un SVG, qui peut porter du script', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $svg = UploadedFile::fake()->createWithContent(
        'photo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    );

    $this->post('/api/v1/moi/avatar', ['photo' => $svg], ['Accept' => 'application/json'])
        ->assertUnprocessable();

    expect(Storage::disk('public')->files('avatars'))->toBeEmpty();
});

it('refuse un faux JPEG qui ne se décode pas', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $faux = UploadedFile::fake()->createWithContent('photo.jpg', "\xFF\xD8\xFF".str_repeat('x', 200));

    $this->post('/api/v1/moi/avatar', ['photo' => $faux], ['Accept' => 'application/json'])
        ->assertUnprocessable();
});

it('retire la photo et efface le fichier', function (): void {
    $utilisateur = User::factory()->create();
    Sanctum::actingAs($utilisateur);

    $this->post('/api/v1/moi/avatar', ['photo' => jpegAvecExif()], ['Accept' => 'application/json'])->assertOk();
    $this->deleteJson('/api/v1/moi/avatar')->assertOk()->assertJsonPath('utilisateur.avatar_url', null);

    expect(Storage::disk('public')->files('avatars'))->toBeEmpty();
});

it('exige d’être connecté pour déposer une photo', function (): void {
    $this->post('/api/v1/moi/avatar', ['photo' => jpegAvecExif()], ['Accept' => 'application/json'])
        ->assertUnauthorized();
});

it('publie la version de l’application sans authentification', function (): void {
    config(['depanne.mobile.derniere_version' => '1.4.0', 'depanne.mobile.version_minimale' => '1.2.0']);

    $this->getJson('/api/v1/application/version')
        ->assertOk()
        ->assertJsonPath('derniere_version', '1.4.0')
        ->assertJsonPath('version_minimale', '1.2.0')
        ->assertJsonPath('url_telechargement', route('site.telecharger'));
});

it('ne prétend aucune version quand rien n’est configuré', function (): void {
    config(['depanne.mobile.derniere_version' => '', 'depanne.mobile.version_minimale' => '']);

    $this->getJson('/api/v1/application/version')
        ->assertOk()
        ->assertJsonPath('derniere_version', null)
        ->assertJsonPath('version_minimale', null);
});
