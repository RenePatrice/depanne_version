<?php

declare(strict_types=1);

use App\Domain\Accounts\Actions\AuthenticateAdmin;
use App\Support\EmpreinteSession;
use Database\Seeders\Demo\AdminUserSeeder;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Activitylog\Models\Activity;

/*
 * Vol de session (session hijacking) : un cookie de session copié ne doit pas
 * fonctionner ailleurs, et aucune session ne doit durer indéfiniment.
 *
 * On simule l'attaque fidèlement : connexion réelle par le formulaire, puis
 * rejeu du MÊME cookie de session depuis un autre navigateur ou une autre IP.
 */

const AGENT_LEGITIME = 'Mozilla/5.0 (Windows NT 10.0) Chrome/130 — poste de l\'administrateur';

beforeEach(function (): void {
    $this->seed(AdminUserSeeder::class);
    RateLimiter::clear(AuthenticateAdmin::throttleKey(request(), 'admin@depanne-moi.gn'));
});

/** Se connecte par le vrai formulaire et rend l'identifiant de session obtenu. */
function seConnecter(string $agent = AGENT_LEGITIME, string $ip = '41.223.10.5'): string
{
    test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->withHeader('User-Agent', $agent)
        ->post(route('connexion'), ['email' => 'admin@depanne-moi.gn', 'password' => 'DepanneMoi2026'])
        ->assertRedirect(route('tableau-de-bord'));

    return session()->getId();
}

/** Rejoue un identifiant de session, comme le ferait un voleur avec le cookie. */
function rejouer(string $idSession, string $agent, string $ip): Illuminate\Testing\TestResponse
{
    // Comme une vraie requête : l'utilisateur n'est connu que par la session.
    // Sans cela, le banc de test garde l'administrateur connecté en mémoire
    // d'une requête à l'autre, ce que la production ne fait jamais.
    app('auth')->forgetGuards();
    test()->flushSession();

    // Le cookie de session tel qu'un voleur le rejoue : seul l'identifiant
    // voyage, le serveur relit la session dans son stockage.
    return test()->withCookie((string) config('session.cookie'), $idSession)
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->withHeader('User-Agent', $agent)
        ->get(route('tableau-de-bord'));
}

it('pose une empreinte et une heure d\'ouverture à la connexion', function (): void {
    seConnecter();

    expect(session(EmpreinteSession::CLE_EMPREINTE))->toBeString()->toHaveLength(64)
        ->and(session(EmpreinteSession::CLE_DEBUT))->toBeInt();
});

it('garde la session sur l\'appareil qui l\'a ouverte', function (): void {
    $id = seConnecter();

    rejouer($id, AGENT_LEGITIME, '41.223.10.5')->assertOk();
});

it('refuse le cookie de session rejoué depuis un autre navigateur', function (): void {
    $id = seConnecter();

    rejouer($id, 'Mozilla/5.0 (X11; Linux) Firefox/131 — navigateur de l\'attaquant', '41.223.10.5')
        ->assertRedirect(route('connexion'));

    // La session volée est détruite côté serveur : même le propriétaire
    // légitime doit se reconnecter, et le cookie ne vaut plus rien.
    rejouer($id, AGENT_LEGITIME, '41.223.10.5')->assertRedirect(route('connexion'));
});

it('refuse le cookie de session rejoué depuis une autre adresse IP', function (): void {
    $id = seConnecter();

    rejouer($id, AGENT_LEGITIME, '197.149.88.12')->assertRedirect(route('connexion'));
});

it('trace la tentative dans le journal de sécurité', function (): void {
    $id = seConnecter();
    rejouer($id, 'curl/8.5 — attaquant', '197.149.88.12');

    $trace = Activity::query()->where('log_name', 'securite')->latest('id')->first();

    expect($trace)->not->toBeNull()
        ->and($trace->description)->toBe('Session du back-office refusée')
        ->and($trace->properties['ip'])->toBe('197.149.88.12');
});

it('ferme la session au-delà de sa durée maximale, même active', function (): void {
    // Inactivité des tests : 120 min (phpunit.xml). La session sert toutes les
    // 110 minutes, donc n'expire jamais par inactivité : seul le plafond joue.
    config(['session.duree_max' => 480]);
    $id = seConnecter();

    foreach ([110, 110, 110, 110] as $minutes) {
        $this->travel($minutes)->minutes();
        rejouer($id, AGENT_LEGITIME, '41.223.10.5')->assertOk();
    }

    // 481 minutes après la connexion : refusée malgré une activité continue.
    $this->travel(41)->minutes();
    rejouer($id, AGENT_LEGITIME, '41.223.10.5')->assertRedirect(route('connexion'));
});

it('ferme la session après l\'inactivité autorisée', function (): void {
    $inactivite = (int) config('session.lifetime');
    $id = seConnecter();

    $this->travel($inactivite - 1)->minutes();
    rejouer($id, AGENT_LEGITIME, '41.223.10.5')->assertOk();

    $this->travel($inactivite + 1)->minutes();
    rejouer($id, AGENT_LEGITIME, '41.223.10.5')->assertRedirect(route('connexion'));
});

it('ne propose plus « rester connecté »', function (): void {
    $this->get(route('connexion'))->assertOk()->assertDontSee('name="remember"', escape: false);

    $this->post(route('connexion'), [
        'email' => 'admin@depanne-moi.gn',
        'password' => 'DepanneMoi2026',
        'remember' => '1',
    ]);

    // Aucun cookie de reconnexion durable, même si le champ est forcé.
    expect(collect($this->app['cookie']->getQueuedCookies())
        ->contains(fn ($c) => str_starts_with($c->getName(), 'remember_')))->toBeFalse();
});
