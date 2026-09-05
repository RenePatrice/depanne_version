<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\Address;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use App\Domain\Matching\Actions\SolicitNextTechnician;
use App\Domain\Matching\Jobs\StartMatchingJob;
use App\Domain\Matching\Models\MatchAttempt;
use App\Domain\Notifications\Models\AppNotification;
use App\Domain\Payments\Data\PaymentMethod;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Providers\MockPaymentProvider;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Wallet\Models\Transaction;
use App\Support\Geo;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Parcours complet, de l'inscription à la clôture (§10)
|--------------------------------------------------------------------------
|
| Les autres suites vérifient chacune une phase. Celle-ci vérifie que les
| phases s'emboîtent : elle traverse l'application par l'API seule, comme le
| fera l'application Flutter, sans jamais appeler une action du domaine
| directement.
|
| C'est le test qui attrape ce que les tests unitaires ne voient pas : un
| champ renommé d'un côté et pas de l'autre, une transition possible en
| théorie mais inaccessible par les routes, une donnée attendue par l'écran
| suivant et jamais renvoyée.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);
    Queue::fake();
});

it('mène une intervention de l’inscription au retrait, par l’API seule', function (): void {
    // ─── 1. Le client s'inscrit ───────────────────────────────────────────

    $inscription = $this->postJson('/api/v1/auth/inscription', [
        'full_name' => 'Aïssatou Barry',
        'phone' => '620 11 22 33',
        'password' => 'MotDePasse1',
        'password_confirmation' => 'MotDePasse1',
    ])->assertCreated();

    $jetonClient = $inscription->json('jetons.access_token');

    expect($inscription->json('utilisateur.telephone'))->toBe('+224620112233')
        ->and($jetonClient)->toBeString();

    $client = User::query()->where('phone', '+224620112233')->firstOrFail();

    // ─── 2. Il consulte le catalogue sans être connecté ───────────────────

    $catalogue = $this->getJson('/api/v1/catalogue')->assertOk();

    // La prestation la plus chère du catalogue : elle porte le parcours
    // jusqu'au retrait, qui a un montant minimum. Une petite intervention
    // s'arrêterait au portefeuille, et la dernière étape ne serait pas jouée.
    $prestation = collect($catalogue->json('categories'))
        ->flatMap(fn (array $c): array => array_map(
            static fn (array $p): array => $p + ['specialite' => $c['code']],
            $c['prestations'],
        ))
        ->sortByDesc('prix_gnf')
        ->first();

    expect($prestation['prix_gnf'])->toBeInt()->toBeGreaterThan(0);

    // ─── 3. Il enregistre son adresse ─────────────────────────────────────

    Sanctum::actingAs($client);

    $adresse = $this->postJson('/api/v1/adresses', [
        'label' => 'Maison',
        'formatted_address' => 'Kipé, Ratoma, Conakry',
        'landmark' => 'Face à la pharmacie',
        'latitude' => 9.595,
        'longitude' => -13.640,
    ])->assertCreated()->assertJsonPath('adresse.couverte', true)->json('adresse.id');

    // ─── 4. Il demande un devis, puis publie ──────────────────────────────

    $devis = $this->postJson('/api/v1/devis', [
        'service_id' => $prestation['id'],
        'address_id' => $adresse,
    ])->assertOk();

    // Le devis est une estimation : le déplacement dépend du technicien.
    expect($devis->json('devis.ferme'))->toBeFalse();

    $ticketId = $this->postJson('/api/v1/tickets', [
        'service_id' => $prestation['id'],
        'address_id' => $adresse,
        'problem_description' => 'Fuite sous l’évier de la cuisine depuis hier soir.',
    ])->assertCreated()->assertJsonPath('ticket.etat', 'PUBLIEE')->json('ticket.id');

    Queue::assertPushed(StartMatchingJob::class);

    // ─── 5. Un technicien s'inscrit et complète son dossier ───────────────

    $inscriptionTech = $this->postJson('/api/v1/auth/inscription', [
        'full_name' => 'Mamadou Diallo',
        'phone' => '622 44 55 66',
        'password' => 'MotDePasse1',
        'password_confirmation' => 'MotDePasse1',
        'is_technician' => true,
    ])->assertCreated();

    expect($inscriptionTech->json('etape_suivante'))->toBe('dossier_technicien');

    $technicien = User::query()->where('phone', '+224622445566')->firstOrFail();
    Sanctum::actingAs($technicien);

    $this->postJson('/api/v1/auth/dossier-technicien', [
        // La spécialité doit couvrir la prestation demandée, sinon la
        // présélection l'écarte — et c'est bien ce qu'on veut d'elle.
        'specialties' => [$prestation['specialite']],
        'id_doc_front_url' => 'documents/cni-recto.jpg',
        'id_doc_back_url' => 'documents/cni-verso.jpg',
        'selfie_url' => 'documents/selfie.jpg',
        'latitude' => 9.597,
        'longitude' => -13.642,
        'service_radius_km' => 10,
    ])->assertOk();

    // Tant que le dossier n'est pas validé, il ne peut pas se mettre en ligne.
    $this->postJson('/api/v1/technicien/disponibilite', ['en_ligne' => true])->assertStatus(422);

    // ─── 6. Le back-office valide le dossier ──────────────────────────────

    TechnicianProfile::query()->whereKey($technicien->id)->update([
        'verification_status' => VerificationStatus::VALIDE,
        'verified_at' => now(),
    ]);

    // Nouvelle instance : chaque requête HTTP reconstruit l'utilisateur depuis
    // la base. Réutiliser celle d'avant la validation garderait la relation
    // telle qu'elle était chargée, et testerait une situation qui n'existe pas.
    Sanctum::actingAs($technicien->fresh());

    $this->postJson('/api/v1/technicien/disponibilite', ['en_ligne' => true])
        ->assertOk()
        ->assertJsonPath('en_ligne', true);

    $this->postJson('/api/v1/technicien/position', ['latitude' => 9.597, 'longitude' => -13.642])
        ->assertOk();

    // ─── 7. Le matching le sollicite ──────────────────────────────────────

    $ticket = Ticket::query()->findOrFail($ticketId);
    app(SolicitNextTechnician::class)->execute($ticket);

    $sollicitations = $this->getJson('/api/v1/technicien/sollicitations')->assertOk();

    expect($sollicitations->json('sollicitations'))->toHaveCount(1)
        // L'adresse exacte reste cachée tant qu'il n'a pas accepté.
        ->and($sollicitations->json('sollicitations.0.adresse'))->toBeNull()
        ->and($sollicitations->json('sollicitations.0.quartier'))->toBe('Face à la pharmacie');

    // ─── 8. Il accepte : le prix devient ferme ────────────────────────────

    $sollicitationId = MatchAttempt::query()->value('id');

    $acceptation = $this->postJson("/api/v1/sollicitations/{$sollicitationId}/accepter")
        ->assertOk()
        ->assertJsonPath('ticket.etat', 'ACCEPTEE')
        ->assertJsonPath('ticket.prix.ferme', true);

    $total = $acceptation->json('ticket.prix.total_gnf');

    expect($total)->toBeInt()->toBeGreaterThan(0)
        ->and(Ticket::query()->findOrFail($ticketId)->priceIsCoherent())->toBeTrue();

    // ─── 9. Ils échangent, et le masquage fait son travail ────────────────

    $message = $this->postJson("/api/v1/tickets/{$ticketId}/messages", [
        'contenu' => 'Bonjour, appelez-moi au 620998877 si vous ne trouvez pas',
    ])->assertCreated();

    expect($message->json('message.contenu'))->not->toContain('620998877')
        ->and($message->json('message.masque'))->toBeTrue();

    Sanctum::actingAs($client);
    $this->getJson("/api/v1/tickets/{$ticketId}/messages")->assertOk()->assertJsonCount(1, 'messages');

    // ─── 10. Le technicien mène l'intervention ────────────────────────────

    Sanctum::actingAs($technicien);

    foreach (['en-route' => 'EN_ROUTE', 'sur-place' => 'SUR_PLACE', 'demarrer' => 'EN_COURS'] as $etape => $etat) {
        $this->postJson("/api/v1/tickets/{$ticketId}/avancer", ['etape' => $etape])
            ->assertOk()
            ->assertJsonPath('ticket.etat', $etat);
    }

    $this->postJson("/api/v1/tickets/{$ticketId}/avancer", [
        'etape' => 'terminer',
        'diagnostic' => 'Joint du siphon remplacé, essai concluant.',
    ])->assertOk()->assertJsonPath('ticket.etat', 'TERMINEE');

    // ─── 11. Le client paie ───────────────────────────────────────────────

    Sanctum::actingAs($client);

    $reference = $this->postJson("/api/v1/tickets/{$ticketId}/paiement")
        ->assertCreated()
        ->assertJsonPath('paiement.montant_gnf', $total)
        ->json('paiement.reference');

    // Le ticket n'a pas bougé : seul l'opérateur peut confirmer.
    expect(Ticket::query()->findOrFail($ticketId)->state->etat())->toBe(TicketState::TERMINEE);

    // ─── 12. L'opérateur confirme ─────────────────────────────────────────

    $corps = json_encode(['reference' => $reference, 'statut' => 'SUCCESS', 'montant_gnf' => $total]);

    $this->call('POST', '/api/v1/paiements/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_DEPANNE_SIGNATURE' => (new MockPaymentProvider)->signer((string) $corps),
    ], (string) $corps)->assertOk()->assertJsonPath('traite', true);

    // Capturé, mais rien n'est encore acquis : c'est le séquestre.
    expect(Ticket::query()->findOrFail($ticketId)->state->etat())->toBe(TicketState::PAYEE)
        ->and(Transaction::balanceFor($technicien->id))->toBe(0);

    // ─── 13. Le client valide et note ─────────────────────────────────────

    $this->postJson("/api/v1/tickets/{$ticketId}/valider")->assertOk()->assertJsonPath('libere', true);

    $this->postJson("/api/v1/tickets/{$ticketId}/avis", [
        'note' => 5,
        'etiquettes' => ['ponctuel', 'travail_soigne'],
        'commentaire' => 'Rapide et propre, je recommande.',
    ])->assertCreated();

    $ticketFinal = Ticket::query()->findOrFail($ticketId);
    $paiement = Payment::query()->firstOrFail();

    expect($ticketFinal->state->etat())->toBe(TicketState::CLOTUREE)
        ->and($paiement->status->value)->toBe('LIBEREE');

    // ─── 14. Le technicien retrouve son argent ────────────────────────────

    Sanctum::actingAs($technicien);

    $net = $ticketFinal->total_gnf - $ticketFinal->commission_gnf;

    $portefeuille = $this->getJson('/api/v1/portefeuille')
        ->assertOk()
        ->assertJsonPath('solde_gnf', $net);

    // Deux mouvements : le chiffre d'affaires et le prélèvement.
    expect($portefeuille->json('mouvements'))->toHaveCount(2)
        ->and(array_column($portefeuille->json('mouvements'), 'type'))
        ->toContain('EARNING')->toContain('COMMISSION');

    // Sa note publique reflète l'avis reçu.
    $this->getJson("/api/v1/techniciens/{$technicien->id}/avis")
        ->assertOk()
        ->assertJsonPath('nombre_avis', 1)
        ->assertJsonPath('avis.0.note', 5);

    // ─── 15. Et il le retire ──────────────────────────────────────────────

    $this->postJson('/api/v1/retraits', [
        'montant_gnf' => 50_000,
        'numero' => '622 44 55 66',
        'operateur' => PaymentMethod::ORANGE_MONEY->value,
    ])->assertCreated()->assertJsonPath('retrait.statut', 'EN_ATTENTE');

    // Demander n'est pas être payé : le solde ne bouge qu'au versement.
    $this->getJson('/api/v1/portefeuille')
        ->assertJsonPath('solde_gnf', $net)
        ->assertJsonPath('engage_gnf', 50_000)
        ->assertJsonPath('retirable_gnf', $net - 50_000);
});

it('mène une demande sans technicien jusqu’à son terme', function (): void {
    // Personne n'est en ligne : la recherche doit conclure proprement plutôt
    // que de laisser le client devant un écran qui tourne.

    $client = User::factory()->create();

    Address::query()->create([
        'user_id' => $client->id,
        'label' => 'Maison',
        'formatted_address' => 'Kipé, Ratoma, Conakry',
        'location' => Geo::point(9.595, -13.640),
        'is_default' => true,
    ]);

    Sanctum::actingAs($client);

    $ticketId = $this->postJson('/api/v1/tickets', [
        'service_id' => Service::query()->value('id'),
        'address_id' => Address::query()->value('id'),
    ])->assertCreated()->json('ticket.id');

    app(SolicitNextTechnician::class)
        ->execute(Ticket::query()->findOrFail($ticketId));

    $this->getJson("/api/v1/tickets/{$ticketId}")
        ->assertOk()
        ->assertJsonPath('ticket.etat', 'SANS_REPONSE')
        ->assertJsonPath('ticket.etat_final', true);

    // Le client a été prévenu, et il peut republier.
    expect(AppNotification::query()->pour($client)->count())->toBe(1);

    $this->postJson('/api/v1/tickets', [
        'service_id' => Service::query()->value('id'),
        'address_id' => Address::query()->value('id'),
    ])->assertCreated();
});
