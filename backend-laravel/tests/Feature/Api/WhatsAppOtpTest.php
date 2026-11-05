<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\PasswordResetCode;

/**
 * Inscription minimale, autonome : ne depend d'aucun helper d'un autre fichier
 * de test (l'ordre de chargement n'est pas garanti).
 *
 * @return array<string, string>
 */
function inscriptionOtp(): array
{
    return [
        'full_name' => 'Mariama Diallo',
        'phone' => '620123456',
        'password' => 'Depanne2026',
        'password_confirmation' => 'Depanne2026',
    ];
}

/*
|--------------------------------------------------------------------------
| OTP par WhatsApp (#8) — canal et mode test
|--------------------------------------------------------------------------
|
| Pas d'API SMS : le code part par WhatsApp. En mode test explicite (flag, hors
| production), le code est renvoye pour tester sur l'appareil sans passerelle ;
| ce flag affaiblit la garde anti-enumeration et reste a false ailleurs.
|
*/

it('annonce le canal WhatsApp et le lien support', function (): void {
    config([
        'depanne.sms.provider' => 'whatsapp',
        'depanne.whatsapp.support_number' => '224620000000',
        'depanne.sms.expose_test_code' => false,
    ]);

    $this->postJson(route('api.inscription'), inscriptionOtp())->assertCreated();

    $reponse = $this->postJson(route('api.mot-de-passe.demander'), ['phone' => '620123456'])
        ->assertOk()
        ->assertJsonPath('canal', 'whatsapp')
        ->assertJsonPath('wa_support_url', 'https://wa.me/224620000000');

    // Flag eteint : le code ne sort jamais dans la reponse.
    expect($reponse->json('code_test'))->toBeNull();
});

it('renvoie le code en mode test, et seulement pour un numero inscrit', function (): void {
    config(['depanne.sms.expose_test_code' => true]);

    $this->postJson(route('api.inscription'), inscriptionOtp())->assertCreated();

    $connu = $this->postJson(route('api.mot-de-passe.demander'), ['phone' => '620123456'])->assertOk();
    $inconnu = $this->postJson(route('api.mot-de-passe.demander'), ['phone' => '628999999'])->assertOk();

    // Le message reste identique (anti-enumeration pour l'affichage), mais en
    // mode test le code n'accompagne que le numero reellement inscrit.
    expect($connu->json('message'))->toBe($inconnu->json('message'))
        ->and($connu->json('code_test'))->toMatch('/^\d{6}$/')
        ->and($inconnu->json('code_test'))->toBeNull()
        ->and(PasswordResetCode::query()->count())->toBe(1);
});
