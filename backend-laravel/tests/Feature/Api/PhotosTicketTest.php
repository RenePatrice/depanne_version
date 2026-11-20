<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\Address;
use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Data\ServiceType;
use App\Domain\Catalog\Models\Service;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Services\PhotosTicket;
use App\Support\Geo;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
| Photos de panne (lot 3) : déposées sur le disque privé, ré-encodées, jointes
| par chemin, servies par lien signé de quinze minutes.
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);
    Storage::fake((string) config('filesystems.default'));
});

/** @return array{0: User, 1: Address} */
function clientPhotos(): array
{
    $client = User::factory()->create();
    $adresse = Address::query()->create([
        'user_id' => $client->id,
        'label' => 'Maison',
        'formatted_address' => 'Kipé, Ratoma, Conakry',
        'location' => Geo::point(9.595, -13.640),
        'is_default' => true,
    ]);
    Sanctum::actingAs($client);

    return [$client, $adresse];
}

function prestationDepannage(): int
{
    return (int) Service::query()->where('type', ServiceType::DEPANNAGE->value)->orderBy('id')->value('id');
}

function deposerPhotoPanne(): string
{
    return test()->post('/api/v1/tickets/photos', [
        'photo' => UploadedFile::fake()->image('fuite.png', 2400, 1800),
    ], ['Accept' => 'application/json'])->assertCreated()->json('chemin');
}

it('dépose une photo ré-encodée en JPEG sur le disque privé', function (): void {
    [$client] = clientPhotos();

    $chemin = deposerPhotoPanne();
    $disque = Storage::disk((string) config('filesystems.default'));

    expect($chemin)->toStartWith('ticket-photos/'.$client->id.'/')
        ->and($chemin)->toEndWith('.jpg')
        ->and($disque->exists($chemin))->toBeTrue();

    $infos = getimagesizefromstring((string) $disque->get($chemin));
    expect($infos['mime'])->toBe('image/jpeg')
        ->and(max($infos[0], $infos[1]))->toBe(1600);
});

it('refuse ce qui n\'est pas une image', function (): void {
    clientPhotos();

    $this->post('/api/v1/tickets/photos', [
        'photo' => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('exige d\'être connecté pour déposer', function (): void {
    $this->post('/api/v1/tickets/photos', [
        'photo' => UploadedFile::fake()->image('p.png'),
    ], ['Accept' => 'application/json'])->assertUnauthorized();
});

it('ne retient que les photos du client, jamais une adresse externe ni celle d\'un autre', function (): void {
    [$autre] = clientPhotos();
    $cheminAutre = deposerPhotoPanne();

    [, $adresse] = clientPhotos();
    $mienne = deposerPhotoPanne();

    $id = $this->postJson('/api/v1/tickets', [
        'service_id' => prestationDepannage(),
        'address_id' => $adresse->id,
        'photos' => [$mienne, $cheminAutre, 'https://exemple.com/piege.jpg'],
    ])->assertCreated()->json('ticket.id');

    expect(Ticket::query()->findOrFail($id)->photos)->toBe([$mienne])
        ->and(app(PhotosTicket::class)->retenir($autre, ['../identity-docs/1/recto.jpg', 'ticket-photos/'.$autre->id.'/../x.jpg']))
        ->toBe([]);
});

it('sert les photos par un lien signé, et rien sans signature valide', function (): void {
    [, $adresse] = clientPhotos();
    $chemin = deposerPhotoPanne();

    $reponse = $this->postJson('/api/v1/tickets', [
        'service_id' => prestationDepannage(),
        'address_id' => $adresse->id,
        'photos' => [$chemin],
    ])->assertCreated();

    $lien = (string) $reponse->json('ticket.photos.0');
    expect($lien)->toContain('signature=')->not->toContain('ticket-photos/'.'..');

    $this->get($lien)->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    // Signature valide pour une photo, réutilisée pour une autre : refusée.
    $autreChemin = PhotosTicket::dossierDe(999).'00000000-0000-0000-0000-000000000000.jpg';
    $this->get(str_replace(urlencode($chemin), urlencode($autreChemin), $lien))->assertForbidden();

    // Sans signature : refusée.
    $this->get('/api/v1/photos-ticket?chemin='.urlencode($chemin))->assertForbidden();
});

it('sert le même lien d\'une lecture à l\'autre, pour ne pas retélécharger', function (): void {
    clientPhotos();
    $chemin = deposerPhotoPanne();
    $photos = app(PhotosTicket::class);

    $premier = $photos->lien($chemin);
    $this->travel(5)->minutes();

    expect($photos->lien($chemin))->toBe($premier);
});

it('rend telle quelle l\'adresse d\'une photo d\'un ancien ticket', function (): void {
    expect(app(PhotosTicket::class)->liens(['https://exemple.com/ancienne.jpg']))
        ->toBe(['https://exemple.com/ancienne.jpg']);
});
