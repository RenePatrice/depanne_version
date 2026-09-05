<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Events\TechnicianPositionUpdated;
use App\Domain\Accounts\Models\AdminUser;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Tickets\Actions\TransitionTicket;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Events\TicketTransitioned;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Navigation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

/*
 * Une position GPS est une donnée personnelle : le canal qui la transporte doit
 * être fermé aussi soigneusement qu'une route. Ces tests vérifient qui peut s'y
 * abonner, ce que les événements transportent, et que la carte reste utilisable
 * quand le WebSocket n'est pas là.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->admin = adminAvecRole('ADMIN');
});

/** Rejoue la règle d'autorisation du canal, comme le ferait /broadcasting/auth. */
function autoriseCanal(mixed $utilisateur): bool
{
    $canal = collect(Broadcast::getChannels())->first(
        fn ($_, string $nom): bool => $nom === 'back-office'
    );

    expect($canal)->not->toBeNull();

    return (bool) $canal($utilisateur);
}

it('n\'ouvre le canal de supervision qu\'aux administrateurs habilités', function (): void {
    expect(autoriseCanal(adminAvecRole('ADMIN')))->toBeTrue()
        ->and(autoriseCanal(adminAvecRole('SUPPORT')))->toBeTrue();

    // FINANCE n'a pas la permission carte-live.voir : pas de positions pour lui.
    expect(autoriseCanal(adminAvecRole('FINANCE')))->toBeFalse();
});

it('ferme le canal à un compte désactivé', function (): void {
    $admin = adminAvecRole('ADMIN');
    $admin->forceFill(['is_active' => false])->save();

    expect(autoriseCanal($admin->fresh()))->toBeFalse();
});

it('ferme le canal à un utilisateur de l\'application mobile', function (): void {
    $client = User::query()->where('is_client', true)->firstOrFail();

    expect(autoriseCanal($client))->toBeFalse()
        ->and(autoriseCanal(null))->toBeFalse();
});

it('diffuse chaque transition de ticket sur le canal privé', function (): void {
    Event::fake([TicketTransitioned::class]);

    $ticket = Ticket::query()->where('state', TicketState::ACCEPTEE)->firstOrFail();

    app(TransitionTicket::class)->execute($ticket, TicketState::EN_ROUTE);

    Event::assertDispatched(TicketTransitioned::class, function (TicketTransitioned $evenement) use ($ticket): bool {
        $charge = $evenement->broadcastWith();

        return $evenement->broadcastAs() === 'ticket.transition'
            && $evenement->broadcastOn()[0]->name === 'private-back-office'
            && $charge['reference'] === $ticket->reference
            && $charge['etat'] === TicketState::EN_ROUTE->value
            && $charge['active'] === true
            && $charge['latitude'] !== null;
    });
});

it('transporte une position sans sérialiser de modèle', function (): void {
    $evenement = new TechnicianPositionUpdated(42, 'Mamadou Diallo', 9.598, -13.643, true, 'DM-2026-000001');

    $charge = $evenement->broadcastWith();

    expect($evenement->broadcastAs())->toBe('technicien.position')
        ->and($evenement->broadcastOn()[0]->name)->toBe('private-back-office')
        ->and($charge['id'])->toBe(42)
        ->and($charge['latitude'])->toBe(9.598)
        ->and($charge['enIntervention'])->toBeTrue()
        ->and($charge['ticket'])->toBe('DM-2026-000001');

    // Aucune propriété n'est un modèle Eloquent : l'événement part jusqu'à
    // toutes les huit secondes, il doit rester léger.
    foreach (get_object_vars($evenement) as $valeur) {
        expect($valeur)->not->toBeInstanceOf(Model::class);
    }
});

it('sert un instantané complet de la carte', function (): void {
    $reponse = $this->actingAs($this->admin, 'admin')
        ->getJson(route('carte-live.donnees'))
        ->assertOk()
        ->assertJsonStructure([
            'techniciens' => [['id', 'nom', 'latitude', 'longitude', 'enIntervention']],
            'interventions' => [['id', 'reference', 'etat', 'categorieId', 'latitude', 'longitude']],
            'compteurs' => ['techniciens_en_ligne', 'interventions_en_cours', 'demandes_en_attente'],
        ]);

    // Seuls les techniciens en ligne et validés apparaissent sur la carte.
    $attendu = TechnicianProfile::query()
        ->where('is_online', true)
        ->where('verification_status', VerificationStatus::VALIDE)
        ->whereNotNull('last_known_location')
        ->count();

    expect($reponse->json('techniciens'))->toHaveCount($attendu)
        ->and($attendu)->toBeGreaterThan(0);
});

it('ne place sur la carte que des interventions réellement en cours', function (): void {
    $reponse = $this->actingAs($this->admin, 'admin')->getJson(route('carte-live.donnees'));

    $etatsActifs = array_map(
        static fn (TicketState $e): string => $e->value,
        array_filter(TicketState::cases(), static fn (TicketState $e): bool => $e->isActive()),
    );

    foreach ($reponse->json('interventions') as $intervention) {
        expect($etatsActifs)->toContain($intervention['etat']);
    }
});

it('expose des compteurs séparément, pour éviter de recharger toute la carte', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->getJson(route('carte-live.compteurs'))
        ->assertOk()
        ->assertJsonStructure(['compteurs' => ['techniciens_en_ligne', 'interventions_en_cours'], 'horodatage'])
        ->assertJsonMissingPath('techniciens');
});

it('réserve la carte live à qui a la permission', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(route('carte-live'))
        ->assertOk()
        ->assertSee('data-react-component="CarteLive"', escape: false);

    $this->actingAs(adminAvecRole('FINANCE'), 'admin')
        ->get(route('carte-live'))
        ->assertForbidden();

    $this->actingAs(adminAvecRole('FINANCE'), 'admin')
        ->getJson(route('carte-live.donnees'))
        ->assertForbidden();
});

it('simule des positions et les diffuse sur le même événement que le mobile', function (): void {
    Event::fake([TechnicianPositionUpdated::class]);

    $this->artisan('demo:positions', ['--duree' => 1, '--intervalle' => 1])
        ->assertSuccessful();

    Event::assertDispatched(TechnicianPositionUpdated::class);
});

it('propose la carte live dans la navigation des rôles habilités', function (): void {
    $liens = static function (AdminUser $admin): array {
        $cles = [];

        foreach (Navigation::forAdmin($admin) as $groupe) {
            foreach ($groupe['items'] as $item) {
                $cles[] = $item['cle'];
            }
        }

        return $cles;
    };

    expect($liens(adminAvecRole('ADMIN')))->toContain('carte-live')
        ->and($liens(adminAvecRole('SUPPORT')))->toContain('carte-live')
        ->and($liens(adminAvecRole('FINANCE')))->not->toContain('carte-live');
});
