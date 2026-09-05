<?php

declare(strict_types=1);

use App\Domain\Chat\Data\ResultatMasquage;
use App\Domain\Chat\Filters\ContactMaskingFilter;

/*
|--------------------------------------------------------------------------
| Masquage des coordonnées (§7.3, §11)
|--------------------------------------------------------------------------
|
| Le contournement de la plateforme est le risque business n°1. Ces tests
| couvrent les deux côtés du compromis : ce qui doit être masqué, et surtout
| ce qui ne doit pas l'être — un filtre qui masque les prix rendrait le chat
| inutilisable, ce qui pousserait justement les gens à en sortir.
|
| Test unitaire : le filtre est de la logique pure, il ne touche à rien.
|
*/

function filtrer(string $texte): ResultatMasquage
{
    return (new ContactMaskingFilter)->appliquer($texte);
}

// -------------------------------------------------------------- téléphones --

it('masque un numéro guinéen écrit normalement', function (string $texte): void {
    $resultat = filtrer($texte);

    expect($resultat->texteMasque)->not->toContain('620')
        ->and($resultat->raisons)->toContain(ContactMaskingFilter::RAISON_TELEPHONE);
})->with([
    'Appelle-moi au 620123456',
    'Mon numéro : 620 12 34 56',
    'Tel 620-12-34-56',
    'Fais le +224620123456',
    '00224 620 12 34 56 stp',
]);

it('masque un numéro dicté en toutes lettres', function (): void {
    $resultat = filtrer('six deux zéro un deux trois quatre cinq six');

    expect($resultat->aEteMasque())->toBeTrue()
        ->and($resultat->raisons)->toContain(ContactMaskingFilter::RAISON_TELEPHONE);
});

it('masque un numéro mélangeant chiffres et lettres', function (): void {
    $resultat = filtrer('six deux zéro 12 34 56');

    expect($resultat->aEteMasque())->toBeTrue();
});

it('masque un numéro dont les chiffres sont espacés un à un', function (): void {
    expect(filtrer('6 2 0 1 2 3 4 5 6')->aEteMasque())->toBeTrue();
});

it('laisse le reste de la phrase lisible', function (): void {
    $resultat = filtrer('Bonjour, appelle-moi au 620123456 avant midi');

    expect($resultat->texteMasque)->toContain('Bonjour')
        ->and($resultat->texteMasque)->toContain('avant midi')
        ->and($resultat->texteMasque)->toContain('[masqué]');
});

// ------------------------------------------------------------------ e-mails --

it('masque une adresse e-mail', function (): void {
    $resultat = filtrer('Écris-moi à mamadou.diallo@gmail.com');

    expect($resultat->texteMasque)->not->toContain('@')
        ->and($resultat->raisons)->toContain(ContactMaskingFilter::RAISON_EMAIL);
});

it('masque une adresse e-mail écrite en toutes lettres', function (): void {
    $resultat = filtrer('mamadou arobase gmail point com');

    expect($resultat->texteMasque)->not->toContain('gmail')
        ->and($resultat->raisons)->toContain(ContactMaskingFilter::RAISON_EMAIL);
});

// -------------------------------------------------------------------- liens --

it('masque un lien', function (string $texte): void {
    expect(filtrer($texte)->raisons)->toContain(ContactMaskingFilter::RAISON_LIEN);
})->with([
    'Va sur https://example.com/contact',
    'www.mon-site.gn',
    'Rejoins-moi sur wa.me/224620123456',
    'Mon site : boutique.com',
]);

// ---------------------------------------------------- ce qu'il ne faut pas --

it('ne masque pas un montant en francs guinéens', function (string $texte): void {
    expect(filtrer($texte)->aEteMasque())->toBeFalse();
})->with([
    'Ça fera 100 000 GNF',
    'Le total est de 1 500 000 francs',
    'Je propose 250000 GNF pour la pièce',
    '85 000 fg',
]);

it('ne masque pas une heure, une date ou une quantité', function (string $texte): void {
    expect(filtrer($texte)->aEteMasque())->toBeFalse();
})->with([
    'J’arrive dans 15 minutes',
    'Je serai là à 14h30',
    'Il me faut 3 joints et 2 raccords',
    'La panne dure depuis 2 jours',
    'Rendez-vous le 12 septembre',
]);

it('ne masque pas une conversation ordinaire', function (string $texte): void {
    expect(filtrer($texte)->aEteMasque())->toBeFalse();
})->with([
    'Bonjour, je suis en route',
    'La fuite vient du joint sous l’évier, je le remplace',
    'Vous êtes au premier ou au deuxième étage ?',
    'C’est bon pour moi, à tout de suite',
    'Il y a deux robinets à changer',
]);

it('ne masque pas une référence de ticket', function (): void {
    expect(filtrer('Ma demande DM-2026-000123')->aEteMasque())->toBeFalse();
});

// ----------------------------------------------------------------- résultat --

it('conserve le texte d’origine à côté du texte masqué', function (): void {
    $resultat = filtrer('Appelle le 620123456');

    expect($resultat->texteOriginal)->toBe('Appelle le 620123456')
        ->and($resultat->texteMasque)->not->toBe($resultat->texteOriginal);
});

it('explique pourquoi plutôt que de se contenter d’interdire', function (): void {
    $avertissement = filtrer('620123456')->avertissement();

    expect($avertissement)->toContain('garantie')
        ->and($avertissement)->toContain('support');
});

it('ne dit rien quand il n’y a rien à masquer', function (): void {
    $resultat = filtrer('Je suis arrivé devant le portail');

    expect($resultat->avertissement())->toBeNull()
        ->and($resultat->raisonCourte())->toBeNull();
});

it('tient la raison dans la colonne prévue', function (): void {
    $resultat = filtrer('620123456 ou mamadou@gmail.com ou https://example.com');

    expect(strlen((string) $resultat->raisonCourte()))->toBeLessThanOrEqual(60)
        ->and($resultat->raisons)->toHaveCount(3);
});
