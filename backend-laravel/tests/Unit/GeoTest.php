<?php

declare(strict_types=1);

use App\Support\Geo;

/*
 * Le repli Haversine sert quand Google Distance Matrix ne répond pas (§8.2) :
 * une erreur ici se traduirait directement par des frais de déplacement faux.
 */

it('mesure la distance entre Ratoma et Kaloum', function (): void {
    // Deux repères de Conakry, distants d'un peu plus de 11 km à vol d'oiseau.
    $km = Geo::haversineKm(9.585, -13.640, 9.509, -13.712);

    expect($km)->toBeGreaterThan(11.0)->toBeLessThan(12.0);
});

it('renvoie zéro pour deux points identiques', function (): void {
    expect(Geo::haversineKm(9.585, -13.640, 9.585, -13.640))->toBe(0.0);
});

it('est symétrique', function (): void {
    $aller = Geo::haversineKm(9.585, -13.640, 9.509, -13.712);
    $retour = Geo::haversineKm(9.509, -13.712, 9.585, -13.640);

    expect(round($aller, 6))->toBe(round($retour, 6));
});

it('majore la distance routière de 30 % par rapport au vol d\'oiseau', function (): void {
    $vol = Geo::haversineKm(9.585, -13.640, 9.509, -13.712);
    $route = Geo::estimatedRoadKm(9.585, -13.640, 9.509, -13.712);

    expect($route)->toBe(round($vol * 1.3, 2))
        ->and($route)->toBeGreaterThan($vol);
});
