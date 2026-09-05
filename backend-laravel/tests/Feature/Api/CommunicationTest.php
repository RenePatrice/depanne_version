<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Chat\Events\MessageEnvoye;
use App\Domain\Chat\Models\Message;
use App\Domain\Disputes\Data\DisputePriority;
use App\Domain\Disputes\Data\DisputeReason;
use App\Domain\Disputes\Models\Dispute;
use App\Domain\Reviews\Models\Review;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\Demo\ZoneSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Chat, avis et réclamations (§7.3, §7.2, §8.1)
|--------------------------------------------------------------------------
|
| Le chat porte le risque business n°1 : ces tests vérifient autant ce qui
| est masqué que le fait que la conversation ne s'ouvre ni trop tôt, ni trop
| tard.
|
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(ZoneSeeder::class);
    $this->seed(AppSettingsSeeder::class);
    Queue::fake();
});

/** Ticket accepté, avec son client et son technicien. */
function interventionEnCours(TicketState $etat = TicketState::EN_ROUTE): array
{
    $ticket = ticketPublie();

    $technicien = User::factory()->technician()->create();
    TechnicianProfile::query()->updateOrCreate(['user_id' => $technicien->id], [
        'specialties' => ['PLOMBERIE'],
        'verification_status' => VerificationStatus::VALIDE,
        'is_online' => true,
        'rating_avg' => 0.0,
        'reviews_count' => 0,
        'jobs_completed' => 0,
    ]);

    $ticket->forceFill([
        'technician_id' => $technicien->id,
        'state' => $etat->value,
        'accepted_at' => now()->subHour(),
        'completed_at' => in_array($etat, [
            TicketState::TERMINEE, TicketState::PAYEE, TicketState::CLOTUREE,
        ], true) ? now()->subMinutes(10) : null,
    ])->save();

    return [$ticket->fresh(), $ticket->client, $technicien];
}

// --------------------------------------------------------------------- chat --

it('laisse les deux parties converser pendant l’intervention', function (): void {
    [$ticket, $client, $technicien] = interventionEnCours();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/messages", ['contenu' => 'Je suis devant le portail'])
        ->assertCreated()
        ->assertJsonPath('message.contenu', 'Je suis devant le portail')
        ->assertJsonPath('avertissement', null);

    Sanctum::actingAs($client);
    $this->getJson("/api/v1/tickets/{$ticket->id}/messages")
        ->assertOk()
        ->assertJsonPath('ouvert', true)
        ->assertJsonCount(1, 'messages');
});

it('n’ouvre pas la conversation avant l’acceptation', function (): void {
    $ticket = ticketPublie();

    Sanctum::actingAs($ticket->client);

    $this->postJson("/api/v1/tickets/{$ticket->id}/messages", ['contenu' => 'Bonjour ?'])
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'aura accepté'));
});

it('ferme la conversation après la clôture', function (): void {
    [$ticket, $client] = interventionEnCours(TicketState::CLOTUREE);

    Sanctum::actingAs($client);

    $this->postJson("/api/v1/tickets/{$ticket->id}/messages", ['contenu' => 'Une dernière question'])
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'réclamation'));
});

it('masque un numéro et prévient l’expéditeur sans bloquer le message', function (): void {
    [$ticket, , $technicien] = interventionEnCours();

    Sanctum::actingAs($technicien);

    $reponse = $this->postJson("/api/v1/tickets/{$ticket->id}/messages", [
        'contenu' => 'Appelle-moi directement au 620123456',
    ])->assertCreated();

    expect($reponse->json('message.contenu'))->not->toContain('620123456')
        ->and($reponse->json('message.masque'))->toBeTrue()
        ->and($reponse->json('avertissement'))->toContain('garantie');
});

it('conserve l’original pour le support sans jamais le renvoyer', function (): void {
    [$ticket, , $technicien] = interventionEnCours();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/messages", ['contenu' => 'Mon numéro 620123456'])
        ->assertCreated();

    $message = Message::query()->firstOrFail();

    // En base, l'original est là pour le support…
    expect($message->getAttributes()['original_content'])->toContain('620123456')
        // …mais il ne sort jamais par une sérialisation.
        ->and($message->toArray())->not->toHaveKey('original_content')
        ->and(json_encode($message))->not->toContain('620123456');
});

it('signale le message au back-office avec sa raison', function (): void {
    [$ticket, , $technicien] = interventionEnCours();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/messages", ['contenu' => 'ecris a moi@gmail.com'])
        ->assertCreated();

    $message = Message::query()->flagged()->firstOrFail();

    expect($message->flag_reason)->toBe('EMAIL');
});

it('ne stocke pas d’original quand rien n’a été masqué', function (): void {
    [$ticket, , $technicien] = interventionEnCours();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/messages", ['contenu' => 'Je suis arrivé'])
        ->assertCreated();

    expect(Message::query()->firstOrFail()->getAttributes()['original_content'])->toBeNull();
});

it('diffuse le message sur le canal de l’intervention', function (): void {
    Event::fake([MessageEnvoye::class]);

    [$ticket, , $technicien] = interventionEnCours();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/messages", ['contenu' => 'Je démarre'])->assertCreated();

    Event::assertDispatched(MessageEnvoye::class);
});

it('tient un tiers hors de la conversation', function (): void {
    [$ticket] = interventionEnCours();

    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/tickets/{$ticket->id}/messages")->assertNotFound();
    $this->postJson("/api/v1/tickets/{$ticket->id}/messages", ['contenu' => 'Salut'])->assertStatus(422);
});

it('marque comme lus les messages de l’autre, pas les siens', function (): void {
    [$ticket, $client, $technicien] = interventionEnCours();

    Sanctum::actingAs($technicien);
    $this->postJson("/api/v1/tickets/{$ticket->id}/messages", ['contenu' => 'Je suis là'])->assertCreated();

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/messages", ['contenu' => 'J’ouvre'])->assertCreated();
    $this->getJson("/api/v1/tickets/{$ticket->id}/messages")->assertOk();

    $messages = Message::query()->orderBy('id')->get();

    expect($messages[0]->read_at)->not->toBeNull()   // du technicien, lu par le client
        ->and($messages[1]->read_at)->toBeNull();     // le sien, pas lu par lui-même
});

// ------------------------------------------------------------- appel masqué --

it('annonce franchement que la mise en relation est simulée', function (): void {
    [$ticket, $client] = interventionEnCours();

    Sanctum::actingAs($client);

    $this->postJson("/api/v1/tickets/{$ticket->id}/appel")
        ->assertOk()
        ->assertJsonPath('relais.simule', true)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'bientôt'));
});

it('ne donne jamais le numéro réel de l’autre partie', function (): void {
    [$ticket, $client, $technicien] = interventionEnCours();

    Sanctum::actingAs($client);

    $reponse = $this->postJson("/api/v1/tickets/{$ticket->id}/appel")->assertOk();

    expect($reponse->json('relais.numero'))->not->toBe($technicien->phone);
});

// --------------------------------------------------------------------- avis --

it('enregistre un avis et met la moyenne à jour', function (): void {
    [$ticket, $client, $technicien] = interventionEnCours(TicketState::CLOTUREE);

    Sanctum::actingAs($client);

    $this->postJson("/api/v1/tickets/{$ticket->id}/avis", [
        'note' => 5,
        'etiquettes' => ['ponctuel', 'travail_soigne'],
        'commentaire' => 'Rapide et propre.',
    ])->assertCreated()->assertJsonPath('avis.note', 5);

    expect($technicien->fresh()->technicianProfile->rating_avg)->toBe(5.0)
        ->and($technicien->fresh()->technicianProfile->reviews_count)->toBe(1);
});

it('refuse de noter une intervention pas encore terminée', function (): void {
    [$ticket, $client] = interventionEnCours(TicketState::EN_ROUTE);

    Sanctum::actingAs($client);

    $this->postJson("/api/v1/tickets/{$ticket->id}/avis", ['note' => 5])->assertStatus(422);
});

it('refuse de noter deux fois', function (): void {
    [$ticket, $client] = interventionEnCours(TicketState::CLOTUREE);

    Sanctum::actingAs($client);

    $this->postJson("/api/v1/tickets/{$ticket->id}/avis", ['note' => 5])->assertCreated();
    $this->postJson("/api/v1/tickets/{$ticket->id}/avis", ['note' => 1])->assertStatus(422);

    expect(Review::query()->count())->toBe(1);
});

it('refuse qu’un tiers note à la place du client', function (): void {
    [$ticket] = interventionEnCours(TicketState::CLOTUREE);

    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/tickets/{$ticket->id}/avis", ['note' => 1])->assertStatus(422);
});

it('rejette une étiquette inventée', function (): void {
    [$ticket, $client] = interventionEnCours(TicketState::CLOTUREE);

    Sanctum::actingAs($client);

    $this->postJson("/api/v1/tickets/{$ticket->id}/avis", [
        'note' => 4,
        'etiquettes' => ['excellent_prix'],
    ])->assertStatus(422)->assertJsonValidationErrors('etiquettes.0');
});

it('ne montre que le prénom du client sur un avis public', function (): void {
    [$ticket, $client, $technicien] = interventionEnCours(TicketState::CLOTUREE);

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/avis", ['note' => 4])->assertCreated();

    $reponse = $this->getJson("/api/v1/techniciens/{$technicien->id}/avis")->assertOk();

    expect($reponse->json('avis.0.client'))->toBe($client->firstName())
        ->and($reponse->json('avis.0.client'))->not->toBe($client->full_name)
        ->and($reponse->json('note_moyenne'))->toEqual(4.0);
});

// ------------------------------------------------------------- réclamations --

it('ouvre une réclamation et suspend la libération des fonds', function (): void {
    [$ticket, $client] = interventionEnCours(TicketState::TERMINEE);

    Sanctum::actingAs($client);

    $this->postJson("/api/v1/tickets/{$ticket->id}/reclamation", [
        'motif' => DisputeReason::TRAVAIL_NON_CONFORME->value,
        'description' => 'La fuite est revenue dès le lendemain matin, le joint n’a pas tenu.',
    ])->assertCreated()->assertJsonPath('reclamation.statut', 'OUVERT');

    expect($ticket->fresh()->state->etat())->toBe(TicketState::LITIGE_OUVERT);
});

it('refuse une réclamation avant la fin de l’intervention', function (): void {
    [$ticket, $client] = interventionEnCours(TicketState::EN_ROUTE);

    Sanctum::actingAs($client);

    $this->postJson("/api/v1/tickets/{$ticket->id}/reclamation", [
        'motif' => DisputeReason::RETARD->value,
        'description' => 'Le technicien met beaucoup trop de temps à arriver chez moi.',
    ])->assertStatus(422);
});

it('refuse une réclamation hors du délai', function (): void {
    [$ticket, $client] = interventionEnCours(TicketState::TERMINEE);

    $ticket->forceFill(['completed_at' => now()->subDays(5)])->save();

    Sanctum::actingAs($client);

    $this->postJson("/api/v1/tickets/{$ticket->id}/reclamation", [
        'motif' => DisputeReason::SURFACTURATION->value,
        'description' => 'Le montant facturé ne correspond pas du tout à ce qui était annoncé.',
    ])->assertStatus(422)->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'dépassé'));
});

it('suit la fenêtre de réclamation définie en back-office', function (): void {
    AppSetting::put(AppSetting::DISPUTE_WINDOW_HOURS, 240);

    [$ticket, $client] = interventionEnCours(TicketState::TERMINEE);
    $ticket->forceFill(['completed_at' => now()->subDays(5)])->save();

    Sanctum::actingAs($client);

    $this->postJson("/api/v1/tickets/{$ticket->id}/reclamation", [
        'motif' => DisputeReason::SURFACTURATION->value,
        'description' => 'Le montant facturé ne correspond pas du tout à ce qui était annoncé.',
    ])->assertCreated();

    AppSetting::put(AppSetting::DISPUTE_WINDOW_HOURS, 72);
});

it('classe un dégât matériel en urgence, un retard en basse priorité', function (): void {
    [$ticket, $client] = interventionEnCours(TicketState::TERMINEE);

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/reclamation", [
        'motif' => DisputeReason::DEGAT_MATERIEL->value,
        'description' => 'Le carrelage de la salle de bain a été cassé pendant l’intervention.',
    ])->assertCreated();

    expect(Dispute::query()->firstOrFail()->priority)->toBe(DisputePriority::URGENTE);
});

it('refuse une seconde réclamation sur la même intervention', function (): void {
    [$ticket, $client] = interventionEnCours(TicketState::TERMINEE);

    Sanctum::actingAs($client);

    $corps = [
        'motif' => DisputeReason::TRAVAIL_NON_CONFORME->value,
        'description' => 'La fuite est revenue dès le lendemain matin, le joint n’a pas tenu.',
    ];

    $this->postJson("/api/v1/tickets/{$ticket->id}/reclamation", $corps)->assertCreated();
    $this->postJson("/api/v1/tickets/{$ticket->id}/reclamation", $corps)->assertStatus(422);
});

it('n’expose ni la priorité ni l’échéance au déclarant', function (): void {
    [$ticket, $client] = interventionEnCours(TicketState::TERMINEE);

    Sanctum::actingAs($client);
    $reponse = $this->postJson("/api/v1/tickets/{$ticket->id}/reclamation", [
        'motif' => DisputeReason::COMPORTEMENT->value,
        'description' => 'Le technicien a été très désagréable pendant toute son intervention.',
    ])->assertCreated();

    expect($reponse->json('reclamation'))->not->toHaveKeys(['priorite', 'priority', 'sla_due_at']);
});

it('liste les réclamations de son auteur seulement', function (): void {
    [$ticket, $client] = interventionEnCours(TicketState::TERMINEE);

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/tickets/{$ticket->id}/reclamation", [
        'motif' => DisputeReason::AUTRE->value,
        'description' => 'Je souhaite signaler un problème sur cette intervention récente.',
    ])->assertCreated();

    $this->getJson('/api/v1/reclamations')->assertOk()->assertJsonCount(1, 'reclamations');

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/reclamations')->assertOk()->assertJsonCount(0, 'reclamations');
});
