import $ from 'jquery';
import DataTable from 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';

/*
 * Toutes les tables du back-office, en mode serveur (§10).
 *
 * Blade décrit la table en HTML :
 *   <table data-table-serveur data-config='{"url":…,"colonnes":[…]}'>
 * et ce module l'anime. Les filtres vivent dans un <form data-filtres="…">
 * voisin : ils sont envoyés à chaque requête et recopiés sur les liens
 * d'export, pour que le fichier téléchargé corresponde exactement à l'écran.
 */

DataTable.use($);

const LANGUE_FR = {
    processing: 'Chargement…',
    search: '',
    searchPlaceholder: 'Rechercher…',
    lengthMenu: 'Afficher _MENU_ lignes',
    info: '_START_ à _END_ sur _TOTAL_ entrées',
    infoEmpty: 'Aucune entrée',
    infoFiltered: '(filtré sur _MAX_ entrées au total)',
    loadingRecords: 'Chargement…',
    zeroRecords: 'Aucun résultat pour cette recherche',
    emptyTable: 'Aucune donnée à afficher',
    paginate: { first: 'Premier', previous: 'Précédent', next: 'Suivant', last: 'Dernier' },
    aria: {
        orderable: ' : trier sur cette colonne',
        orderableReverse: ' : inverser le tri',
    },
};

function valeursFiltres(formulaire) {
    if (!formulaire) {
        return {};
    }

    const valeurs = {};

    new FormData(formulaire).forEach((valeur, cle) => {
        if (valeur !== '' && valeur !== null) {
            valeurs[cle] = valeur;
        }
    });

    return valeurs;
}

function majLiensExport(conteneur, filtres) {
    conteneur?.querySelectorAll('[data-export]').forEach((lien) => {
        const url = new URL(lien.dataset.exportUrl, window.location.origin);
        url.searchParams.set('format', lien.dataset.export);

        Object.entries(filtres).forEach(([cle, valeur]) => url.searchParams.set(cle, valeur));

        lien.href = url.toString();
    });
}

function initTable(element) {
    const config = JSON.parse(element.dataset.config);
    const formulaire = config.filtres ? document.querySelector(`[data-filtres="${config.filtres}"]`) : null;
    const barre = element.closest('[data-table-bloc]');

    const table = new DataTable(element, {
        serverSide: true,
        processing: true,
        deferRender: true,
        responsive: true,
        stateSave: true,
        stateDuration: 60 * 60 * 24 * 7,
        pageLength: config.pageLength ?? 25,
        lengthMenu: [10, 25, 50, 100],
        order: config.order ?? [[0, 'desc']],
        language: LANGUE_FR,
        // Recherche à gauche, longueur à droite ; les exports sont dans la
        // barre d'outils Blade, pas dans DataTables.
        dom: "<'row align-items-center mb-3'<'col-sm-7'f><'col-sm-5 text-sm-end'l>>" +
            "<'row'<'col-12'tr>>" +
            "<'row align-items-center mt-3'<'col-sm-5'i><'col-sm-7'p>>",
        ajax: {
            url: config.url,
            data: (parametres) => Object.assign(parametres, valeursFiltres(formulaire)),
        },
        columns: config.colonnes.map((colonne) => ({
            data: colonne.data,
            name: colonne.name ?? colonne.data,
            orderable: colonne.orderable !== false,
            searchable: colonne.searchable !== false,
            className: colonne.classe ?? null,
        })),
    });

    majLiensExport(barre, valeursFiltres(formulaire));

    formulaire?.addEventListener('change', () => {
        table.ajax.reload();
        majLiensExport(barre, valeursFiltres(formulaire));
    });

    formulaire?.addEventListener('reset', () => {
        // Le reset natif vide les champs après l'événement : on attend un tour.
        setTimeout(() => {
            table.ajax.reload();
            majLiensExport(barre, valeursFiltres(formulaire));
        }, 0);
    });

    return table;
}

export function initTables() {
    document.querySelectorAll('[data-table-serveur]').forEach(initTable);
}
