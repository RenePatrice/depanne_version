<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Navigation du back-office
|--------------------------------------------------------------------------
|
| Source unique de la barre latérale et du fil d'Ariane. Chaque module déclare
| la permission qui le protège : `Navigation::forAdmin()` filtre dessus, si bien
| qu'un lien affiché est toujours un lien ouvrable.
|
| Jusqu'en phase B5, ce fichier portait aussi les écrans « module en
| préparation ». Tous les modules étant livrés, ce mécanisme a été retiré — il
| reste décrit dans l'ADR-0015 si un module devait à nouveau arriver plus tard.
|
*/

return [

    'navigation' => [

        [
            'groupe' => 'Pilotage',
            'items' => [
                [
                    'cle' => 'tableau-de-bord',
                    'libelle' => 'Tableau de bord',
                    'icone' => 'bi-speedometer2',
                    'permission' => 'tableau-de-bord.voir',
                ],
                [
                    'cle' => 'carte-live',
                    'libelle' => 'Carte live',
                    'icone' => 'bi-geo-alt',
                    'permission' => 'carte-live.voir',
                ],
            ],
        ],

        [
            'groupe' => 'Opérations',
            'items' => [
                [
                    'cle' => 'tickets',
                    'libelle' => 'Tickets',
                    'icone' => 'bi-ticket-detailed',
                    'permission' => 'tickets.voir',
                ],
                [
                    'cle' => 'clients',
                    'libelle' => 'Clients',
                    'icone' => 'bi-people',
                    'permission' => 'clients.voir',
                ],
                [
                    'cle' => 'techniciens',
                    'libelle' => 'Techniciens',
                    'icone' => 'bi-person-badge',
                    'permission' => 'techniciens.voir',
                ],
            ],
        ],

        [
            'groupe' => 'Finances',
            'items' => [
                [
                    'cle' => 'finances',
                    'libelle' => 'Commissions',
                    'icone' => 'bi-graph-up-arrow',
                    'permission' => 'finances.voir',
                ],
                [
                    'cle' => 'retraits',
                    'libelle' => 'Retraits',
                    'icone' => 'bi-cash-stack',
                    'permission' => 'retraits.approuver',
                ],
                [
                    'cle' => 'litiges',
                    'libelle' => 'Litiges',
                    'icone' => 'bi-exclamation-diamond',
                    'permission' => 'litiges.voir',
                ],
            ],
        ],

        [
            'groupe' => 'Administration',
            'items' => [
                [
                    'cle' => 'catalogue',
                    'libelle' => 'Catalogue & tarifs',
                    'icone' => 'bi-tags',
                    'permission' => 'catalogue.voir',
                ],
                [
                    'cle' => 'zones',
                    'libelle' => 'Zones',
                    'icone' => 'bi-map',
                    'permission' => 'zones.voir',
                ],
                [
                    'cle' => 'configuration',
                    'libelle' => 'Configuration',
                    'icone' => 'bi-sliders',
                    'permission' => 'configuration.voir',
                ],
                [
                    'cle' => 'journal-audit',
                    'libelle' => 'Journal d\'audit',
                    'icone' => 'bi-clock-history',
                    'permission' => 'journal-audit.voir',
                ],
            ],
        ],

    ],

];
