<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\Address;
use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use App\Domain\Tickets\Actions\TransitionTicket;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Geo;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| API mobile — catalogue, adresses et demandes (§7.2, §8.1, §8.2)
|--------------------------------------------------------------------------
|
| Ces tests attaquent l'API par ses garanties plutôt que par ses chemins
| heureux : ce qu'un client ne doit pas voir, ce qu'il ne doit pas pouvoir
| faire deux fois, et ce qui doit rester figé une fois annoncé.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);
});

/** Client authentifié avec une adresse dans la zone RAT-CENTRE. */
function clientAvecAdresse(float $lat = 9.595, float $lng = -13.640): array
{
    $client = User::factory()->create();

    /** @var Address $adresse */
    $adresse = Address::query()->create([
        'user_id' => $client->id,
        'label' => 'Maison',
        'formatted_address' => 'Kipé, Ratoma, Conakry',
        'location' => Geo::point($lat, $lng),
        'is_default' => true,
    ]);

    Sanctum::actingAs($client);

    return [$client, $adresse];
}

// ----------------------------------------------------------------- catalogue --

it('sert le catalogue sans authentification', function (): void {
    $reponse = $this->getJson('/api/v1/catalogue');

    $reponse->assertOk()
        ->assertJsonStructure(['categories' => [['id', 'code', 'nom', 'prestations' => [['id', 'nom', 'prix_gnf', 'prix_formate']]]]]);
});

it('n\'expose que les prestations actives', function (): void {
    $service = Service::query()->firstOrFail();
    $service->forceFill(['is_active' => false])->save();

    $ids = collect($this->getJson('/api/v1/catalogue')->json('categories'))
        ->flatMap(fn (array $c): array => array_column($c['prestations'], 'id'));

    expect($ids)->not->toContain($service->id);
});

it('annonce les zones desservies sans livrer leur contour', function (): void {
    $reponse = $this->getJson('/api/v1/zones');

    $reponse->assertOk()->assertJsonCount(3, 'zones');

    expect($reponse->json('zones.0'))->not->toHaveKey('boundary');
});

it('sert les textes légaux depuis les paramètres plutôt que depuis le code', function (): void {
    $this->getJson('/api/v1/reglages')
        ->assertOk()
        ->assertJsonStructure(['cgu', 'politique_confidentialite', 'frais_annulation_gnf']);
});

// ------------------------------------------------------------------ adresses --

it('marque la première adresse comme adresse par défaut', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/adresses', [
        'label' => 'Maison',
        'formatted_address' => 'Kipé, Ratoma',
        'latitude' => 9.595,
        'longitude' => -13.640,
    ])->assertCreated()->assertJsonPath('adresse.par_defaut', true);
});

it('ne garde qu\'une seule adresse par défaut', function (): void {
    [$client] = clientAvecAdresse();

    $this->postJson('/api/v1/adresses', [
        'label' => 'Bureau',
        'formatted_address' => 'Nongo, Ratoma',
        'latitude' => 9.600,
        'longitude' => -13.635,
        'is_default' => true,
    ])->assertCreated();

    expect(Address::query()->where('user_id', $client->id)->where('is_default', true)->count())->toBe(1);
});

it('prévient qu\'une adresse est hors zone sans refuser de l\'enregistrer', function (): void {
    Sanctum::actingAs(User::factory()->create());

    // Kaloum : bien en Guinée, mais hors des trois zones du pilote.
    $this->postJson('/api/v1/adresses', [
        'label' => 'Bureau',
        'formatted_address' => 'Kaloum, Conakry',
        'latitude' => 9.510,
        'longitude' => -13.710,
    ])->assertCreated()->assertJsonPath('adresse.couverte', false);
});

it('rejette une coordonnée hors de Guinée', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/adresses', [
        'label' => 'Ailleurs',
        'formatted_address' => 'Paris',
        'latitude' => 48.85,
        'longitude' => 2.35,
    ])->assertStatus(422)->assertJsonValidationErrors(['latitude', 'longitude']);
});

it('traite l\'adresse d\'un autre comme inexistante', function (): void {
    [, $adresse] = clientAvecAdresse();

    Sanctum::actingAs(User::factory()->create());

    $this->deleteJson('/api/v1/adresses/'.$adresse->id)->assertNotFound();
});

// --------------------------------------------------------------------- devis --

it('donne un devis détaillé avant toute publication', function (): void {
    [, $adresse] = clientAvecAdresse();
    $service = Service::query()->firstOrFail();

    $reponse = $this->postJson('/api/v1/devis', [
        'service_id' => $service->id,
        'address_id' => $adresse->id,
    ]);

    $reponse->assertOk()
        ->assertJsonStructure(['devis' => ['total_gnf', 'total_formate', 'lignes'], 'zone']);

    expect($reponse->json('devis.total_gnf'))->toBeInt()->toBeGreaterThan($service->base_price_gnf);
});

it('ne crée aucun ticket en produisant un devis', function (): void {
    [, $adresse] = clientAvecAdresse();

    $this->postJson('/api/v1/devis', [
        'service_id' => Service::query()->value('id'),
        'address_id' => $adresse->id,
    ])->assertOk();

    expect(Ticket::query()->count())->toBe(0);
});

it('refuse un devis pour une adresse hors zone', function (): void {
    [, $adresse] = clientAvecAdresse(9.510, -13.710);

    $this->postJson('/api/v1/devis', [
        'service_id' => Service::query()->value('id'),
        'address_id' => $adresse->id,
    ])->assertStatus(422)->assertJsonPath('hors_zone', true);
});

it('ne chiffre pas un devis sur l\'adresse de quelqu\'un d\'autre', function (): void {
    [, $adresse] = clientAvecAdresse();

    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/devis', [
        'service_id' => Service::query()->value('id'),
        'address_id' => $adresse->id,
    ])->assertNotFound();
});

// ------------------------------------------------------------------- tickets --

it('publie une demande au prix exact annoncé par le devis', function (): void {
    [, $adresse] = clientAvecAdresse();
    $service = Service::query()->firstOrFail();

    $devis = $this->postJson('/api/v1/devis', [
        'service_id' => $service->id,
        'address_id' => $adresse->id,
    ])->json('devis.total_gnf');

    $this->postJson('/api/v1/tickets', [
        'service_id' => $service->id,
        'address_id' => $adresse->id,
        'problem_description' => 'Fuite sous l\'évier depuis ce matin.',
    ])
        ->assertCreated()
        ->assertJsonPath('ticket.etat', 'PUBLIEE')
        ->assertJsonPath('ticket.prix.total_gnf', $devis);
});

it('horodate la publication et journalise la transition d\'origine', function (): void {
    [, $adresse] = clientAvecAdresse();

    $id = $this->postJson('/api/v1/tickets', [
        'service_id' => Service::query()->value('id'),
        'address_id' => $adresse->id,
    ])->json('ticket.id');

    $ticket = Ticket::query()->findOrFail($id);

    expect($ticket->published_at)->not->toBeNull()
        ->and($ticket->events()->count())->toBe(1)
        ->and($ticket->events()->first()->from_state)->toBe(TicketState::BROUILLON);
});

it('fige l\'adresse sur le ticket pour que l\'historique survive à sa suppression', function (): void {
    [, $adresse] = clientAvecAdresse();

    $id = $this->postJson('/api/v1/tickets', [
        'service_id' => Service::query()->value('id'),
        'address_id' => $adresse->id,
    ])->json('ticket.id');

    $adresse->delete();

    $this->getJson('/api/v1/tickets/'.$id)
        ->assertOk()
        ->assertJsonPath('ticket.adresse.formatted_address', 'Kipé, Ratoma, Conakry');
});

it('refuse une seconde demande tant que la première est ouverte', function (): void {
    [, $adresse] = clientAvecAdresse();
    $service = Service::query()->value('id');

    $this->postJson('/api/v1/tickets', ['service_id' => $service, 'address_id' => $adresse->id])
        ->assertCreated();

    $this->postJson('/api/v1/tickets', ['service_id' => $service, 'address_id' => $adresse->id])
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'encore en cours'));
});

it('refuse de publier depuis une adresse hors zone', function (): void {
    [, $adresse] = clientAvecAdresse(9.510, -13.710);

    $this->postJson('/api/v1/tickets', [
        'service_id' => Service::query()->value('id'),
        'address_id' => $adresse->id,
    ])->assertStatus(422);
});

it('n\'accepte que trois photos', function (): void {
    [, $adresse] = clientAvecAdresse();

    $this->postJson('/api/v1/tickets', [
        'service_id' => Service::query()->value('id'),
        'address_id' => $adresse->id,
        'photos' => ['a.jpg', 'b.jpg', 'c.jpg', 'd.jpg'],
    ])->assertStatus(422)->assertJsonValidationErrors('photos');
});

it('ne montre pas le ticket d\'un autre', function (): void {
    [, $adresse] = clientAvecAdresse();

    $id = $this->postJson('/api/v1/tickets', [
        'service_id' => Service::query()->value('id'),
        'address_id' => $adresse->id,
    ])->json('ticket.id');

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/tickets/'.$id)->assertNotFound();
});

// ------------------------------------------------------- prix non saisissable --

it('ignore un montant de déplacement glissé dans la requête', function (): void {
    [, $adresse] = clientAvecAdresse();
    $service = Service::query()->firstOrFail();

    // Le §8.2 exige que le kilométrage soit calculé, jamais saisi. Cette
    // requête tente de le poser directement, comme le ferait un client
    // modifié ou un technicien qui rejouerait l'appel.
    $id = $this->postJson('/api/v1/tickets', [
        'service_id' => $service->id,
        'address_id' => $adresse->id,
        'travel_fee_gnf' => 1,
        'short_trip_uplift_gnf' => 999_999,
        'total_gnf' => 1,
        'commission_gnf' => 0,
        'distance_km' => 0.1,
    ])->json('ticket.id');

    $ticket = Ticket::query()->findOrFail($id);

    expect($ticket->total_gnf)->toBeGreaterThanOrEqual($service->base_price_gnf)
        ->and($ticket->total_gnf)->not->toBe(1)
        ->and($ticket->short_trip_uplift_gnf)->not->toBe(999_999)
        ->and($ticket->priceIsCoherent())->toBeTrue();
});

it('n’expose aucune route permettant de modifier le prix d’un ticket', function (): void {
    $modifiantes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r): bool => str_starts_with((string) $r->uri(), 'api/v1/tickets'))
        ->reject(fn ($r): bool => $r->methods() === ['GET', 'HEAD'])
        ->map(fn ($r): string => implode('|', $r->methods()).' '.$r->uri())
        ->values();

    // Publier et annuler : rien d'autre ne touche à un ticket existant, et
    // aucune des deux ne prend de montant en entrée.
    expect($modifiantes->all())->toBe(['POST api/v1/tickets', 'DELETE api/v1/tickets/{ticket}']);
});

// ---------------------------------------------------------------- annulation --

it('annule sans frais une demande que personne n\'a encore prise', function (): void {
    [, $adresse] = clientAvecAdresse();

    $id = $this->postJson('/api/v1/tickets', [
        'service_id' => Service::query()->value('id'),
        'address_id' => $adresse->id,
    ])->json('ticket.id');

    $this->deleteJson('/api/v1/tickets/'.$id, ['motif' => 'Plus besoin'])
        ->assertOk()
        ->assertJsonPath('frais_gnf', 0)
        ->assertJsonPath('ticket.etat', 'ANNULEE_CLIENT');
});

it('facture l\'annulation dès que le technicien est en route', function (): void {
    [$client, $adresse] = clientAvecAdresse();
    $technicien = User::factory()->technician()->create();

    $id = $this->postJson('/api/v1/tickets', [
        'service_id' => Service::query()->value('id'),
        'address_id' => $adresse->id,
    ])->json('ticket.id');

    $ticket = Ticket::query()->findOrFail($id);
    $ticket->forceFill(['technician_id' => $technicien->id])->save();

    $transition = app(TransitionTicket::class);
    $transition->execute($ticket, TicketState::ACCEPTEE);
    $transition->execute($ticket, TicketState::EN_ROUTE);

    Sanctum::actingAs($client);

    $this->deleteJson('/api/v1/tickets/'.$id)
        ->assertOk()
        ->assertJsonPath('frais_gnf', 20_000);
});

it('refuse d\'annuler une intervention déjà terminée', function (): void {
    [$client, $adresse] = clientAvecAdresse();
    $technicien = User::factory()->technician()->create();

    $id = $this->postJson('/api/v1/tickets', [
        'service_id' => Service::query()->value('id'),
        'address_id' => $adresse->id,
    ])->json('ticket.id');

    $ticket = Ticket::query()->findOrFail($id);
    $ticket->forceFill(['technician_id' => $technicien->id])->save();

    $transition = app(TransitionTicket::class);

    foreach ([TicketState::ACCEPTEE, TicketState::EN_ROUTE, TicketState::SUR_PLACE,
        TicketState::EN_COURS, TicketState::TERMINEE] as $etape) {
        $transition->execute($ticket, $etape);
    }

    Sanctum::actingAs($client);

    $this->deleteJson('/api/v1/tickets/'.$id)->assertStatus(422);
});

// ---------------------------------------------------------- confidentialité --

it('masque le numéro du technicien tant que l\'intervention n\'est pas engagée', function (): void {
    [$client, $adresse] = clientAvecAdresse();
    $technicien = User::factory()->technician()->create(['phone' => '+224620999888']);

    $id = $this->postJson('/api/v1/tickets', [
        'service_id' => Service::query()->value('id'),
        'address_id' => $adresse->id,
    ])->json('ticket.id');

    $ticket = Ticket::query()->findOrFail($id);
    $ticket->forceFill(['technician_id' => $technicien->id])->save();

    Sanctum::actingAs($client);

    // PUBLIEE : le ticket n'est pas encore actif, le numéro reste masqué.
    $masque = $this->getJson('/api/v1/tickets/'.$id)->json('ticket.technicien.telephone');

    expect($masque)->not->toBe('+224620999888');

    app(TransitionTicket::class)->execute($ticket, TicketState::ACCEPTEE);

    $this->getJson('/api/v1/tickets/'.$id)
        ->assertJsonPath('ticket.technicien.telephone', '+224620999888');
});

it('ne révèle pas la part technicien au client', function (): void {
    [, $adresse] = clientAvecAdresse();

    $reponse = $this->postJson('/api/v1/tickets', [
        'service_id' => Service::query()->value('id'),
        'address_id' => $adresse->id,
    ]);

    expect($reponse->json('ticket.prix'))->not->toHaveKey('net_technicien_gnf');
});
