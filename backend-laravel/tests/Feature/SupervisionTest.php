<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\AdminUser;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use Database\Seeders\DatabaseSeeder;

/*
 * Les trois tables de supervision tournent en mode serveur : ce qui compte,
 * c'est que le filtrage et la recherche soient calculés en base, et que
 * l'export reprenne exactement la sélection affichée.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->admin = AdminUser::query()->whereHas('roles', fn ($q) => $q->where('name', 'ADMIN'))->firstOrFail();
});

it('affiche les trois écrans de supervision', function (): void {
    foreach (['tickets', 'clients', 'techniciens'] as $route) {
        $this->actingAs($this->admin, 'admin')
            ->get(route($route))
            ->assertOk()
            ->assertSee('data-table-serveur', escape: false);
    }
});

it('sert les tickets en mode serveur, sans charger toute la table', function (): void {
    $reponse = $this->actingAs($this->admin, 'admin')
        ->getJson(route('tickets.donnees', parametresTable(
            ['reference', 'created_at', 'statut', 'client', 'technicien', 'prestation', 'zone', 'total', 'actions'],
        )));

    $reponse->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

    // 150 tickets en base, 25 renvoyés : la pagination est bien côté serveur.
    expect($reponse->json('recordsTotal'))->toBe(150)
        ->and($reponse->json('data'))->toHaveCount(25);
});

it('filtre les tickets par statut en base', function (): void {
    $attendu = Ticket::query()->where('state', TicketState::CLOTUREE)->count();

    $reponse = $this->actingAs($this->admin, 'admin')
        ->getJson(route('tickets.donnees', parametresTable(
            ['reference', 'created_at', 'statut', 'client', 'technicien', 'prestation', 'zone', 'total', 'actions'],
            ['etat' => TicketState::CLOTUREE->value],
        )));

    expect($reponse->json('recordsFiltered'))->toBe($attendu)
        ->and($attendu)->toBeLessThan(150);
});

it('cherche un client par son numéro de téléphone', function (): void {
    $client = User::query()->where('is_client', true)->firstOrFail();

    // Le support tape le numéro sans l'indicatif : c'est ce qu'il a sous les yeux.
    $saisie = substr($client->phone, 4);

    $reponse = $this->actingAs($this->admin, 'admin')
        ->getJson(route('clients.donnees', parametresTable(
            ['full_name', 'phone', 'statut', 'casquettes', 'interventions', 'depense_formatee', 'created_at', 'actions'],
            ['search' => ['value' => $saisie, 'regex' => 'false']],
        )));

    expect($reponse->json('recordsFiltered'))->toBe(1)
        ->and($reponse->json('data.0.full_name'))->toBe($client->full_name);
});

it('filtre les techniciens sur le statut de vérification', function (): void {
    $attendu = TechnicianProfile::query()
        ->where('verification_status', VerificationStatus::EN_ATTENTE_VALIDATION)
        ->count();

    $reponse = $this->actingAs($this->admin, 'admin')
        ->getJson(route('techniciens.donnees', parametresTable(
            ['full_name', 'phone', 'specialites', 'verification', 'presence', 'jobs_completed', 'note', 'acceptation', 'solde_formate', 'actions'],
            ['verification' => VerificationStatus::EN_ATTENTE_VALIDATION->value],
        )));

    expect($reponse->json('recordsFiltered'))->toBe($attendu)
        ->and($attendu)->toBeGreaterThan(0);
});

it('exporte en CSV la sélection filtrée, pas la page affichée', function (): void {
    $attendu = Ticket::query()->where('state', TicketState::CLOTUREE)->count();

    $reponse = $this->actingAs($this->admin, 'admin')
        ->get(route('tickets.export', ['format' => 'csv', 'etat' => TicketState::CLOTUREE->value]));

    $reponse->assertOk()->assertDownload();

    $contenu = $reponse->streamedContent();
    // Une ligne d'en-tête plus une ligne par ticket clôturé.
    $lignes = substr_count(trim($contenu), "\n") + 1;

    expect($lignes)->toBe($attendu + 1)
        ->and($contenu)->toContain('Référence')
        ->and($attendu)->toBeGreaterThan(25);
});

it('refuse un format d\'export inconnu', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(route('tickets.export', ['format' => 'docx']))
        ->assertStatus(422);
});

it('affiche le détail complet d\'un ticket', function (): void {
    $ticket = Ticket::query()->whereNotNull('commission_gnf')->firstOrFail();

    $this->actingAs($this->admin, 'admin')
        ->get(route('tickets.detail', $ticket))
        ->assertOk()
        ->assertSee($ticket->reference)
        ->assertSee('Déroulé de l\'intervention', escape: false)
        ->assertSee('Décomposition du prix', escape: false)
        ->assertSee('Sollicitations de matching')
        ->assertSee('Actions support');
});

it('affiche la fiche d\'un client et celle d\'un technicien', function (): void {
    $client = User::query()->where('is_client', true)->firstOrFail();
    $technicien = User::query()->where('is_technician', true)->firstOrFail();

    $this->actingAs($this->admin, 'admin')
        ->get(route('clients.detail', $client))
        ->assertOk()
        ->assertSee($client->full_name);

    $this->actingAs($this->admin, 'admin')
        ->get(route('techniciens.detail', $technicien))
        ->assertOk()
        ->assertSee($technicien->full_name)
        ->assertSee('Taux d\'acceptation', escape: false);
});

it('sert la file de validation et ses dossiers en attente', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(route('techniciens.validation'))
        ->assertOk()
        ->assertSee('data-react-component="FileValidation"', escape: false);

    $reponse = $this->actingAs($this->admin, 'admin')
        ->getJson(route('techniciens.dossiers'))
        ->assertOk()
        ->assertJsonStructure(['dossiers' => [['id', 'nom', 'telephone', 'specialites', 'documents']], 'total']);

    expect($reponse->json('total'))->toBe(
        TechnicianProfile::query()
            ->where('verification_status', VerificationStatus::EN_ATTENTE_VALIDATION)
            ->count()
    );
});

it('approuve un technicien depuis le back-office', function (): void {
    $profil = TechnicianProfile::query()
        ->where('verification_status', VerificationStatus::EN_ATTENTE_VALIDATION)
        ->firstOrFail();

    $this->actingAs($this->admin, 'admin')
        ->post(route('techniciens.approuver', $profil->user_id))
        ->assertRedirect();

    expect($profil->fresh()->verification_status)->toBe(VerificationStatus::VALIDE);
});

it('interdit à FINANCE d\'approuver un dossier ou d\'agir sur un ticket', function (): void {
    $finance = AdminUser::query()->whereHas('roles', fn ($q) => $q->where('name', 'FINANCE'))->firstOrFail();

    $profil = TechnicianProfile::query()
        ->where('verification_status', VerificationStatus::EN_ATTENTE_VALIDATION)
        ->firstOrFail();

    $this->actingAs($finance, 'admin')
        ->post(route('techniciens.approuver', $profil->user_id))
        ->assertForbidden();

    $ticket = Ticket::query()->firstOrFail();

    $this->actingAs($finance, 'admin')
        ->post(route('tickets.cloturer', $ticket), ['motif' => 'Un motif suffisamment long.'])
        ->assertForbidden();
});
