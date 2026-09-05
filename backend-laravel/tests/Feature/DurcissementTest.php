<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Http\Middleware\IdentifiantDeCorrelation;
use Database\Seeders\Demo\AdminUserSeeder;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Durcissement (§10)
|--------------------------------------------------------------------------
|
| Ce que ces tests protègent n'a pas de fonctionnalité visible : personne ne
| remarquera qu'ils passent. On remarquera le jour où ils ne passent plus.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);
});

// ------------------------------------------------------------------- CORS --

it('n’autorise pas n’importe quelle origine à appeler l’API', function (): void {
    $origines = config('cors.allowed_origins');

    expect($origines)->toBeArray()
        ->and($origines)->not->toContain('*')
        ->and(config('cors.supports_credentials'))->toBeFalse();
});

// ---------------------------------------------------------- corrélation --

it('renvoie un identifiant de corrélation sur chaque réponse', function (): void {
    $reponse = $this->getJson('/api/v1/catalogue')->assertOk();

    expect($reponse->headers->get(IdentifiantDeCorrelation::ENTETE))->toBeString()->not->toBeEmpty();
});

it('reprend l’identifiant fourni par le client', function (): void {
    $reponse = $this->withHeaders([IdentifiantDeCorrelation::ENTETE => 'flutter-abc-123'])
        ->getJson('/api/v1/catalogue')->assertOk();

    expect($reponse->headers->get(IdentifiantDeCorrelation::ENTETE))->toBe('flutter-abc-123');
});

it('régénère un identifiant mal formé plutôt que de l’écrire dans les logs', function (): void {
    // Un saut de ligne injecté forgerait de fausses entrées de journal.
    $reponse = $this->withHeaders([IdentifiantDeCorrelation::ENTETE => "abc\ndef ERROR faux"])
        ->getJson('/api/v1/catalogue')->assertOk();

    expect($reponse->headers->get(IdentifiantDeCorrelation::ENTETE))->not->toContain("\n")
        ->and($reponse->headers->get(IdentifiantDeCorrelation::ENTETE))->not->toContain('faux');
});

// -------------------------------------------------- pièces justificatives --

it('refuse une pièce justificative sans URL signée', function (): void {
    $this->seed(AdminUserSeeder::class);

    $profil = profilAvecPieces();

    $this->actingAs(adminAvecRole('ADMIN'), 'admin')
        ->get('/documents/'.$profil->user_id.'/recto')
        ->assertStatus(403);   // signature absente
});

it('refuse une pièce justificative à un visiteur non connecté', function (): void {
    $profil = profilAvecPieces();

    $this->get(URL::temporarySignedRoute('documents.piece', now()->addMinutes(5), [
        'profil' => $profil->user_id,
        'piece' => 'recto',
    ]))->assertRedirect();   // renvoyé vers l'écran de connexion
});

it('sert la pièce à un administrateur muni d’un lien signé', function (): void {
    $this->seed(AdminUserSeeder::class);

    $profil = profilAvecPieces();

    $this->actingAs(adminAvecRole('ADMIN'), 'admin')
        ->get(URL::temporarySignedRoute('documents.piece', now()->addMinutes(5), [
            'profil' => $profil->user_id,
            'piece' => 'recto',
        ]))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('refuse une pièce dont le lien a expiré', function (): void {
    $this->seed(AdminUserSeeder::class);

    $profil = profilAvecPieces();

    $lien = URL::temporarySignedRoute('documents.piece', now()->addMinutes(5), [
        'profil' => $profil->user_id,
        'piece' => 'recto',
    ]);

    $this->travel(10)->minutes();

    $this->actingAs(adminAvecRole('ADMIN'), 'admin')->get($lien)->assertStatus(403);
});

it('n’expose aucune pièce en dehors des trois prévues', function (): void {
    $this->seed(AdminUserSeeder::class);

    $profil = profilAvecPieces();

    $this->actingAs(adminAvecRole('ADMIN'), 'admin')
        ->get(URL::temporarySignedRoute('documents.piece', now()->addMinutes(5), [
            'profil' => $profil->user_id,
            'piece' => 'service_area',
        ]))
        ->assertNotFound();
});

// --------------------------------------------------------------- Policies --

it('applique la Policy même si un contrôleur oublie de la vérifier', function (): void {
    $moi = User::factory()->create();
    $autre = User::factory()->create();

    $ticket = ticketPublie();

    expect($ticket->estPartiePrenante($ticket->client))->toBeTrue()
        ->and($ticket->estPartiePrenante($autre))->toBeFalse()
        ->and($ticket->estLeClient($ticket->client))->toBeTrue()
        ->and($ticket->estLeTechnicien($ticket->client))->toBeFalse()
        ->and($ticket->estPartiePrenante(null))->toBeFalse()
        ->and($moi->id)->not->toBe($autre->id);
});

it('refuse au technicien de libérer son propre séquestre', function (): void {
    $ticket = ticketPublie();
    $technicien = User::factory()->technician()->create();
    $ticket->forceFill(['technician_id' => $technicien->id])->save();

    Sanctum::actingAs($technicien);

    // Valider libère l'argent : c'est un geste de client, jamais de technicien.
    $this->postJson("/api/v1/tickets/{$ticket->id}/valider")->assertNotFound();
});

// ---------------------------------------------------------- en-têtes web --

it('interdit l’affichage du back-office dans une iframe', function (): void {
    $this->get('/connexion')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

/** Profil technicien avec ses trois pièces posées sur le disque de test. */
function profilAvecPieces(): TechnicianProfile
{
    Storage::fake('local');
    Storage::disk('local')->put('documents/cni-recto.jpg', 'image-factice');

    $technicien = User::factory()->technician()->create();

    /** @var TechnicianProfile */
    return TechnicianProfile::query()->updateOrCreate(['user_id' => $technicien->id], [
        'specialties' => ['PLOMBERIE'],
        'verification_status' => VerificationStatus::EN_ATTENTE_VALIDATION,
        'id_doc_front_url' => 'documents/cni-recto.jpg',
        'id_doc_back_url' => 'documents/cni-verso.jpg',
        'selfie_url' => 'documents/selfie.jpg',
    ]);
}
