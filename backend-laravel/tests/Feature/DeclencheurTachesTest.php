<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/*
 * Sur un hébergeur sans processus permanent, un planificateur externe vide la
 * file d'attente en appelant /internes/taches. L'adresse ne doit ni exister
 * sans jeton configuré, ni répondre à qui ne le connaît pas.
 */

it('n\'existe pas tant qu\'aucun jeton n\'est configuré', function (): void {
    config(['depanne.taches.jeton' => '']);

    $this->postJson(route('internes.taches'), [], ['X-Taches-Jeton' => ''])->assertNotFound();
});

it('répond 404 à un jeton absent ou faux, sans rien exécuter', function (): void {
    config(['depanne.taches.jeton' => 'secret-de-test']);
    Artisan::spy();

    $this->postJson(route('internes.taches'))->assertNotFound();
    $this->postJson(route('internes.taches'), [], ['X-Taches-Jeton' => 'mauvais'])->assertNotFound();

    Artisan::shouldNotHaveReceived('call');
});

it('vide la file d\'attente avec le bon jeton, sans jeton CSRF', function (): void {
    config(['depanne.taches.jeton' => 'secret-de-test']);

    $this->post(route('internes.taches'), [], ['X-Taches-Jeton' => 'secret-de-test'])
        ->assertOk()
        ->assertJson(['etat' => 'ok']);
});
