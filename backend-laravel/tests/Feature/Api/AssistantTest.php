<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Models\Service;
use Database\Seeders\Demo\CatalogSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/*
| Assistant de diagnostic (lot 4) : modèle Ollama auto-hébergé, simulé ici.
| Le modèle choisit parmi le catalogue ; tout le reste est relu et filtré.
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    Cache::forget('assistant:catalogue');
    Sanctum::actingAs(User::factory()->create());
});

function brancherOllama(): void
{
    config([
        'depanne.assistant.driver' => 'ollama',
        'depanne.assistant.ollama.url' => 'http://ollama.interne:11434',
        'depanne.assistant.ollama.jeton' => 'secret-proxy',
    ]);
}

/** @param  array<string, mixed>  $contenu */
function reponseOllama(array $contenu): array
{
    return ['message' => ['role' => 'assistant', 'content' => json_encode($contenu)]];
}

it('ne tente aucun appel sans modèle configuré', function (): void {
    Http::fake();

    $this->postJson('/api/v1/assistant/diagnostic', ['description' => 'Mon évier fuit'])
        ->assertOk()
        ->assertExactJson(['source' => null]);

    Http::assertNothingSent();
});

it('rend les prestations choisies par le modèle', function (): void {
    brancherOllama();
    $id = (int) Service::query()->orderBy('id')->value('id');
    Http::fake(['ollama.interne:11434/*' => Http::response(reponseOllama([
        'prestations' => [$id],
        'explication' => 'Il s’agit probablement d’une fuite.',
        'conseil' => 'Fermez le robinet d’arrivée d’eau.',
    ]))]);

    $this->postJson('/api/v1/assistant/diagnostic', ['description' => 'L’eau coule sous mon évier'])
        ->assertOk()
        ->assertJsonPath('source', 'ia')
        ->assertJsonPath('prestations', [$id])
        ->assertJsonPath('conseil', 'Fermez le robinet d’arrivée d’eau.');

    Http::assertSent(fn (Request $r): bool => $r->url() === 'http://ollama.interne:11434/api/chat'
        && $r->hasHeader('Authorization', 'Bearer secret-proxy')
        && $r['options']['temperature'] === 0
        && str_contains($r['messages'][0]['content'], (string) $id));
});

it('écarte les identifiants inventés ou désactivés', function (): void {
    brancherOllama();
    $actif = (int) Service::query()->orderBy('id')->value('id');
    $inactif = Service::query()->orderByDesc('id')->firstOrFail();
    $inactif->forceFill(['is_active' => false])->save();

    Http::fake(['*' => Http::response(reponseOllama([
        'prestations' => [999999, $inactif->id, $actif],
        'explication' => 'x',
    ]))]);

    $this->postJson('/api/v1/assistant/diagnostic', ['description' => 'Problème'])
        ->assertJsonPath('prestations', [$actif]);
});

it('retombe sur le moteur local si le modèle ne propose rien d’exploitable', function (): void {
    brancherOllama();
    Http::fake(['*' => Http::response(reponseOllama(['prestations' => [999999], 'explication' => 'x']))]);

    $this->postJson('/api/v1/assistant/diagnostic', ['description' => 'Bonjour'])
        ->assertExactJson(['source' => null]);
});

it('retombe sur le moteur local si le modèle est en panne ou répond n’importe quoi', function (): void {
    brancherOllama();

    Http::fake(['*' => Http::response('erreur', 500)]);
    $this->postJson('/api/v1/assistant/diagnostic', ['description' => 'Ma prise grésille'])
        ->assertExactJson(['source' => null]);

    Http::fake(['*' => Http::response(['message' => ['content' => 'pas du json']])]);
    $this->postJson('/api/v1/assistant/diagnostic', ['description' => 'Ma prise grésille'])
        ->assertExactJson(['source' => null]);

    Http::fake(fn () => throw new ConnectionException('délai dépassé'));
    $this->postJson('/api/v1/assistant/diagnostic', ['description' => 'Ma prise grésille'])
        ->assertExactJson(['source' => null]);
});

it('valide la description et exige une session', function (): void {
    $this->postJson('/api/v1/assistant/diagnostic', ['description' => str_repeat('a', 501)])
        ->assertStatus(422);

    app('auth')->forgetGuards();
    $this->withHeaders(['Authorization' => ''])
        ->postJson('/api/v1/assistant/diagnostic', ['description' => 'Fuite'])
        ->assertUnauthorized();
});
