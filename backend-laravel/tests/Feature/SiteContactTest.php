<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\AdminUser;
use App\Domain\Site\Models\SiteAbonne;
use App\Domain\Site\Models\SiteMessage;
use Database\Seeders\Demo\AdminUserSeeder;
use Database\Seeders\Demo\AppSettingsSeeder;
use Database\Seeders\Demo\CatalogSeeder;
use Database\Seeders\RolesPermissionsSeeder;

/*
 * Pages intérieures du site vitrine : fiche d'un métier (alimentée par le
 * catalogue), formulaire de contact et lettre d'information. Les deux
 * formulaires sont publics : ils valident, écartent les robots sans le dire,
 * et ne révèlent pas si une adresse est déjà inscrite.
 */

beforeEach(function (): void {
    $this->seed(AppSettingsSeeder::class);
    $this->seed(CatalogSeeder::class);
});

function messageValide(array $surcharge = []): array
{
    return array_merge([
        'nom' => 'Aïssatou Bah',
        'telephone' => '620 12 34 56',
        'email' => 'aissatou@exemple.com',
        'sujet' => 'question',
        'message' => 'Bonjour, intervenez-vous à Matoto le dimanche ?',
    ], $surcharge);
}

// ------------------------------------------------------------------ métiers --

it('explique un métier à partir du catalogue', function (): void {
    $this->get(route('site.metier', ['code' => 'plomberie']))
        ->assertOk()
        ->assertSee('Plomberie', escape: false)
        ->assertSee('Réparation de fuite de robinet', escape: false)
        ->assertSee('Prix indicatif', escape: false)
        ->assertHeader('X-Robots-Tag', 'index, follow');
});

it('répond 404 pour un métier inconnu', function (): void {
    $this->get('/metiers/astrologie')->assertNotFound();
});

it('relie chaque métier de l\'accueil à sa page d\'explication', function (): void {
    $this->get(route('site.accueil'))
        ->assertOk()
        ->assertSee(route('site.metier', ['code' => 'electricite']), escape: false);
});

// ------------------------------------------------------------------ contact --

it('affiche la page contact sans connexion', function (): void {
    $this->get(route('site.contact'))->assertOk()->assertSee('Écrivez-nous', escape: false);
});

it('enregistre un message de contact valide', function (): void {
    $this->post(route('site.contact.envoyer'), messageValide())
        ->assertRedirect()
        ->assertSessionHas('contact_envoye', true);

    $message = SiteMessage::query()->sole();

    expect($message->nom)->toBe('Aïssatou Bah')
        ->and($message->sujet)->toBe('question')
        ->and($message->estTraite())->toBeFalse();
});

it('refuse un message incomplet sans rien enregistrer', function (): void {
    $this->post(route('site.contact.envoyer'), messageValide(['telephone' => '', 'message' => 'court']))
        ->assertSessionHasErrors(['telephone', 'message']);

    expect(SiteMessage::query()->count())->toBe(0);
});

it('écarte un robot en silence grâce au pot de miel', function (): void {
    $this->post(route('site.contact.envoyer'), messageValide(['site_web' => 'http://spam.example']))
        ->assertRedirect()
        ->assertSessionHas('contact_envoye', true);

    expect(SiteMessage::query()->count())->toBe(0);
});

// --------------------------------------------------------------- newsletter --

it('inscrit une adresse à la newsletter, une seule fois', function (): void {
    $this->post(route('site.newsletter'), ['email' => 'Lecteur@Exemple.com'])
        ->assertRedirect()->assertSessionHas('newsletter_inscrit', true);

    // Même réponse pour une réinscription : le formulaire ne dit pas si
    // l'adresse figurait déjà dans la liste.
    $this->post(route('site.newsletter'), ['email' => 'lecteur@exemple.com'])
        ->assertRedirect()->assertSessionHas('newsletter_inscrit', true);

    expect(SiteAbonne::query()->count())->toBe(1)
        ->and(SiteAbonne::query()->sole()->email)->toBe('lecteur@exemple.com');
});

it('refuse une adresse e-mail invalide', function (): void {
    $this->post(route('site.newsletter'), ['email' => 'pas-une-adresse'])
        ->assertSessionHasErrorsIn('newsletter', ['email']);

    expect(SiteAbonne::query()->count())->toBe(0);
});

// --------------------------------------------------------------- back-office --

it('laisse l\'administrateur traiter un message et exporter les abonnés', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->seed(AdminUserSeeder::class);
    $admin = AdminUser::query()->where('email', 'admin@depanne-moi.gn')->firstOrFail();

    $this->post(route('site.contact.envoyer'), messageValide());
    $this->post(route('site.newsletter'), ['email' => 'lecteur@exemple.com']);
    $message = SiteMessage::query()->sole();

    $this->actingAs($admin, 'admin')->get(route('site-admin'))
        ->assertOk()
        ->assertSee('Aïssatou Bah', escape: false)
        ->assertSee('lecteur@exemple.com', escape: false);

    $this->actingAs($admin, 'admin')
        ->post(route('site-admin.messages.basculer', $message))
        ->assertRedirect();

    expect($message->fresh()->estTraite())->toBeTrue();

    $export = $this->actingAs($admin, 'admin')->get(route('site-admin.abonnes.export'));
    $export->assertOk();
    expect($export->streamedContent())->toContain('lecteur@exemple.com');
});

it('ferme les messages et l\'export aux visiteurs non connectés', function (): void {
    $this->get(route('site-admin.abonnes.export'))->assertRedirect(route('connexion'));
});
