<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\UserStatus;
use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\PasswordResetCode;
use App\Domain\Accounts\Models\RefreshToken;
use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Contracts\SmsProvider;
use Illuminate\Support\Facades\Hash;

/*
 * L'authentification mobile est la seule porte ouverte sur l'extérieur.
 * Ces tests fixent ce qui s'y joue : normalisation du numéro, robustesse du mot
 * de passe, rotation des jetons, et surtout ce que l'API accepte de révéler à
 * un inconnu.
 */

const MOT_DE_PASSE = 'Depanne2026';

function inscrire(array $surcharges = []): array
{
    return array_merge([
        'full_name' => 'Mariama Diallo',
        'phone' => '620123456',
        'password' => MOT_DE_PASSE,
        'password_confirmation' => MOT_DE_PASSE,
    ], $surcharges);
}

// ------------------------------------------------------------- inscription --

it('inscrit un client et le connecte immédiatement', function (): void {
    $reponse = $this->postJson(route('api.inscription'), inscrire())
        ->assertCreated()
        ->assertJsonStructure([
            'message',
            'utilisateur' => ['id', 'nom_complet', 'telephone', 'casquettes'],
            'jetons' => ['access_token', 'refresh_token', 'expire_dans', 'expire_le'],
            'etape_suivante',
        ]);

    $utilisateur = User::query()->firstOrFail();

    expect($reponse->json('utilisateur.casquettes.client'))->toBeTrue()
        ->and($reponse->json('utilisateur.casquettes.technicien'))->toBeFalse()
        ->and($reponse->json('etape_suivante'))->toBe('accueil')
        ->and($utilisateur->clientProfile)->not->toBeNull()
        // L'access token vaut 15 minutes, comme le veut l'ADR-0003.
        ->and($reponse->json('jetons.expire_dans'))->toBe(900);
});

it('normalise le numéro saisi en E.164', function (): void {
    // Le même numéro, sous trois formes que les gens tapent réellement.
    foreach (['620123456', '+224 620 12 34 56', '00224620123456'] as $index => $saisie) {
        User::query()->forceDelete();

        $this->postJson(route('api.inscription'), inscrire(['phone' => $saisie]))
            ->assertCreated()
            ->assertJsonPath('utilisateur.telephone', '+224620123456');
    }
});

it('refuse un second compte sur le même numéro, quelle que soit la forme saisie', function (): void {
    $this->postJson(route('api.inscription'), inscrire())->assertCreated();

    $this->postJson(route('api.inscription'), inscrire(['phone' => '+224 620 12 34 56']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');

    expect(User::query()->count())->toBe(1);
});

it('impose un mot de passe d\'au moins huit caractères, avec majuscule et chiffre', function (): void {
    foreach (['court1A', 'minuscules123', 'SANSCHIFFRE'] as $faible) {
        $this->postJson(route('api.inscription'), inscrire([
            'password' => $faible,
            'password_confirmation' => $faible,
        ]))->assertStatus(422)->assertJsonValidationErrors('password');
    }

    expect(User::query()->count())->toBe(0);
});

it('inscrit un technicien en attente de validation', function (): void {
    $reponse = $this->postJson(route('api.inscription'), inscrire(['is_technician' => true]))
        ->assertCreated();

    $technicien = User::query()->firstOrFail();
    $technicien->load('technicianProfile');

    expect($reponse->json('etape_suivante'))->toBe('dossier_technicien')
        ->and($technicien->is_technician)->toBeTrue()
        ->and($technicien->is_client)->toBeFalse()
        ->and($technicien->technicianProfile->verification_status)
        ->toBe(VerificationStatus::EN_ATTENTE_VALIDATION);
});

it('enregistre le dossier technicien et le remet en attente de validation', function (): void {
    $jetons = $this->postJson(route('api.inscription'), inscrire(['is_technician' => true]))->json('jetons');

    $reponse = $this->withToken($jetons['access_token'])
        ->postJson(route('api.dossier-technicien'), [
            'specialties' => ['PLOMBERIE'],
            'latitude' => 9.598,
            'longitude' => -13.643,
            'service_radius_km' => 7,
            'id_doc_front_url' => 'identity-docs/recto.jpg',
            'id_doc_back_url' => 'identity-docs/verso.jpg',
            'selfie_url' => 'identity-docs/selfie.jpg',
        ])
        ->assertOk();

    expect($reponse->json('profil_technicien.specialites.0.code'))->toBe('PLOMBERIE')
        ->and($reponse->json('profil_technicien.zone.rayon_km'))->toBe(7)
        ->and($reponse->json('profil_technicien.verification.peut_travailler'))->toBeFalse();
});

it('exige les trois pièces justificatives du dossier technicien', function (): void {
    $jetons = $this->postJson(route('api.inscription'), inscrire(['is_technician' => true]))->json('jetons');

    $this->withToken($jetons['access_token'])
        ->postJson(route('api.dossier-technicien'), [
            'specialties' => ['PLOMBERIE'],
            'latitude' => 9.598,
            'longitude' => -13.643,
            'service_radius_km' => 7,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['id_doc_front_url', 'id_doc_back_url', 'selfie_url']);
});

// --------------------------------------------------------------- connexion --

it('connecte avec le numéro et le mot de passe', function (): void {
    $this->postJson(route('api.inscription'), inscrire())->assertCreated();

    $this->postJson(route('api.connexion'), ['phone' => '620123456', 'password' => MOT_DE_PASSE])
        ->assertOk()
        ->assertJsonPath('utilisateur.telephone', '+224620123456')
        ->assertJsonStructure(['jetons' => ['access_token', 'refresh_token']]);

    expect(User::query()->firstOrFail()->last_login_at)->not->toBeNull();
});

it('refuse un mauvais mot de passe sans dire si le compte existe', function (): void {
    $this->postJson(route('api.inscription'), inscrire())->assertCreated();

    $existant = $this->postJson(route('api.connexion'), ['phone' => '620123456', 'password' => 'Mauvais123'])
        ->assertStatus(401);

    $inconnu = $this->postJson(route('api.connexion'), ['phone' => '628999999', 'password' => 'Mauvais123'])
        ->assertStatus(401);

    // Le message est identique : l'API ne sert pas à découvrir qui est inscrit.
    expect($existant->json('message'))->toBe($inconnu->json('message'));
});

it('refuse la connexion d\'un compte suspendu', function (): void {
    $this->postJson(route('api.inscription'), inscrire())->assertCreated();

    User::query()->firstOrFail()->forceFill(['status' => UserStatus::SUSPENDU])->save();

    $this->postJson(route('api.connexion'), ['phone' => '620123456', 'password' => MOT_DE_PASSE])
        ->assertStatus(401)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'suspendu'));
});

it('verrouille après cinq tentatives infructueuses', function (): void {
    $this->postJson(route('api.inscription'), inscrire())->assertCreated();

    for ($i = 0; $i < 5; $i++) {
        $this->postJson(route('api.connexion'), ['phone' => '620123456', 'password' => 'Mauvais123'])
            ->assertStatus(401);
    }

    // Même avec le bon mot de passe, la sixième tentative est refusée.
    $this->postJson(route('api.connexion'), ['phone' => '620123456', 'password' => MOT_DE_PASSE])
        ->assertStatus(401)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'Trop de tentatives'));
});

// ------------------------------------------------------------------ jetons --

it('fait tourner le jeton de rafraîchissement à chaque usage', function (): void {
    $jetons = $this->postJson(route('api.inscription'), inscrire())->json('jetons');

    $nouveaux = $this->postJson(route('api.refresh'), ['refresh_token' => $jetons['refresh_token']])
        ->assertOk()
        ->json('jetons');

    expect($nouveaux['refresh_token'])->not->toBe($jetons['refresh_token'])
        ->and($nouveaux['access_token'])->not->toBe($jetons['access_token']);

    // Le nouveau jeton d'accès fonctionne.
    $this->withToken($nouveaux['access_token'])->getJson(route('api.ping'))->assertOk();
});

it('révoque toutes les sessions quand un jeton déjà utilisé est rejoué', function (): void {
    $jetons = $this->postJson(route('api.inscription'), inscrire())->json('jetons');

    $nouveaux = $this->postJson(route('api.refresh'), ['refresh_token' => $jetons['refresh_token']])->json('jetons');

    // Rejeu de l'ancien : signe qu'une copie circule.
    $this->postJson(route('api.refresh'), ['refresh_token' => $jetons['refresh_token']])
        ->assertStatus(401)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'sécurité'));

    // Le jeton légitime tombe aussi : mieux vaut une reconnexion qu'une
    // session volée qui perdure.
    $this->postJson(route('api.refresh'), ['refresh_token' => $nouveaux['refresh_token']])
        ->assertStatus(401);

    expect(RefreshToken::query()->whereNull('revoked_at')->count())->toBe(0);
});

it('ne stocke jamais le jeton de rafraîchissement en clair', function (): void {
    $jetons = $this->postJson(route('api.inscription'), inscrire())->json('jetons');

    expect(RefreshToken::query()->where('token_hash', $jetons['refresh_token'])->exists())->toBeFalse()
        ->and(RefreshToken::query()->where('token_hash', RefreshToken::hash($jetons['refresh_token']))->exists())
        ->toBeTrue();
});

it('renvoie du JSON, jamais du HTML, quand le jeton manque', function (): void {
    $this->getJson(route('api.moi'))
        ->assertStatus(401)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'Session'));
});

it('déconnecte et invalide les jetons', function (): void {
    $jetons = $this->postJson(route('api.inscription'), inscrire())->json('jetons');

    $this->withToken($jetons['access_token'])
        ->postJson(route('api.deconnexion'), ['refresh_token' => $jetons['refresh_token']])
        ->assertOk();

    $this->postJson(route('api.refresh'), ['refresh_token' => $jetons['refresh_token']])->assertStatus(401);
});

// -------------------------------------------------------- mot de passe oublié --

it('répond la même chose que le numéro existe ou non', function (): void {
    $this->postJson(route('api.inscription'), inscrire())->assertCreated();

    $connu = $this->postJson(route('api.mot-de-passe.demander'), ['phone' => '620123456'])->assertOk();
    $inconnu = $this->postJson(route('api.mot-de-passe.demander'), ['phone' => '628999999'])->assertOk();

    expect($connu->json('message'))->toBe($inconnu->json('message'));

    // Un code n'est créé que pour le numéro réellement inscrit.
    expect(PasswordResetCode::query()->count())->toBe(1);
});

it('réinitialise le mot de passe avec le code reçu et coupe les sessions', function (): void {
    $codeEnvoye = null;

    // On intercepte le SMS pour lire le code : en développement il part dans
    // laravel.log, ce qui n'est pas exploitable depuis un test.
    $boite = new stdClass;
    $boite->code = null;

    $this->app->bind(SmsProvider::class, fn (): SmsProvider => new class($boite) implements SmsProvider
    {
        public function __construct(private readonly stdClass $boite) {}

        public function envoyer(string $telephoneE164, string $message): bool
        {
            preg_match('/\b(\d{6})\b/', $message, $trouve);
            $this->boite->code = $trouve[1] ?? null;

            return true;
        }
    });

    $jetons = $this->postJson(route('api.inscription'), inscrire())->json('jetons');

    $this->postJson(route('api.mot-de-passe.demander'), ['phone' => '620123456'])->assertOk();

    expect($boite->code)->not->toBeNull();

    $this->postJson(route('api.mot-de-passe.reinitialiser'), [
        'phone' => '620123456',
        'code' => $boite->code,
        'password' => 'NouveauPass1',
        'password_confirmation' => 'NouveauPass1',
    ])->assertOk();

    expect(Hash::check('NouveauPass1', User::query()->firstOrFail()->password))->toBeTrue();

    // Toutes les sessions tombent : si le compte était compromis, l'intrus sort.
    $this->withToken($jetons['access_token'])->getJson(route('api.ping'))->assertStatus(401);
    $this->postJson(route('api.refresh'), ['refresh_token' => $jetons['refresh_token']])->assertStatus(401);
});

it('refuse un code erroné et décompte les essais', function (): void {
    $this->postJson(route('api.inscription'), inscrire())->assertCreated();
    $this->postJson(route('api.mot-de-passe.demander'), ['phone' => '620123456'])->assertOk();

    $this->postJson(route('api.mot-de-passe.reinitialiser'), [
        'phone' => '620123456',
        'code' => '000000',
        'password' => 'NouveauPass1',
        'password_confirmation' => 'NouveauPass1',
    ])->assertStatus(422);

    expect(PasswordResetCode::query()->firstOrFail()->attempts)->toBe(1);
});

// ------------------------------------------------------------------ profil --

it('renvoie mon compte avec les profils de mes casquettes', function (): void {
    $jetons = $this->postJson(route('api.inscription'), inscrire())->json('jetons');

    $this->withToken($jetons['access_token'])
        ->getJson(route('api.moi'))
        ->assertOk()
        ->assertJsonPath('utilisateur.telephone', '+224620123456')
        ->assertJsonStructure(['utilisateur' => ['profil_client' => ['points_fidelite']]]);
});

it('active la seconde casquette depuis les paramètres', function (): void {
    $jetons = $this->postJson(route('api.inscription'), inscrire())->json('jetons');

    $reponse = $this->withToken($jetons['access_token'])
        ->postJson(route('api.moi.seconde-casquette'))
        ->assertOk();

    expect($reponse->json('utilisateur.casquettes.technicien'))->toBeTrue()
        ->and($reponse->json('etape_suivante'))->toBe('dossier_technicien');

    // Une seconde activation n'a plus de sens.
    $this->withToken($jetons['access_token'])
        ->postJson(route('api.moi.seconde-casquette'))
        ->assertStatus(422);
});

it('exige le mot de passe actuel pour en changer', function (): void {
    $jetons = $this->postJson(route('api.inscription'), inscrire())->json('jetons');

    $this->withToken($jetons['access_token'])
        ->postJson(route('api.moi.mot-de-passe'), [
            'ancien_mot_de_passe' => 'PasLeBon1',
            'password' => 'NouveauPass1',
            'password_confirmation' => 'NouveauPass1',
        ])
        ->assertStatus(422);

    $this->withToken($jetons['access_token'])
        ->postJson(route('api.moi.mot-de-passe'), [
            'ancien_mot_de_passe' => MOT_DE_PASSE,
            'password' => 'NouveauPass1',
            'password_confirmation' => 'NouveauPass1',
        ])
        ->assertOk();

    // L'appareil courant garde sa session : on ne se déconnecte pas soi-même.
    $this->withToken($jetons['access_token'])->getJson(route('api.ping'))->assertOk();
});
