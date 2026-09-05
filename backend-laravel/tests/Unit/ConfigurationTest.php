<?php

declare(strict_types=1);

/*
 * Les tests unitaires ne démarrent pas l'application : ils ne portent que sur
 * de la logique pure. Tout ce qui touche à la configuration ou à la base vit
 * dans tests/Feature.
 */

it('formate les montants en GNF sans décimale', function (): void {
    $formate = number_format(100_000, 0, ',', ' ').' GNF';

    expect($formate)->toBe('100 000 GNF');
});

it('arrondit les frais de déplacement au millier de GNF supérieur', function (): void {
    // Règle §8.2 — l'arrondi se fait toujours vers le haut, sur des entiers.
    $arrondi = static fn (int $montant): int => (int) (ceil($montant / 1000) * 1000);

    expect($arrondi(12_300))->toBe(13_000)
        ->and($arrondi(12_000))->toBe(12_000)
        ->and($arrondi(1))->toBe(1_000);
});
