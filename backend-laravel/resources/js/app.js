import './bootstrap';
import 'bootstrap';

import { initApparence } from './theme/apparence';

/*
 * Réseau lent et instable à Conakry (§11) : Highcharts et React ne sont
 * téléchargés que par les pages qui en ont réellement besoin. Une page de
 * DataTable sans graphique ne paie pas les 400 ko de Highcharts.
 */

async function chargeGraphiques() {
    if (!document.querySelector('[data-graphique]')) {
        return;
    }

    const { rendGraphiques } = await import('./theme/graphiques');
    rendGraphiques();
}

async function chargeIlotsReact() {
    if (!document.querySelector('[data-react-component]')) {
        return;
    }

    const { mountReactIslands } = await import('./react/mount');
    mountReactIslands();
}

async function chargeTables() {
    if (!document.querySelector('[data-table-serveur]')) {
        return;
    }

    const { initTables } = await import('./theme/tables');
    initTables();
}

async function chargeCompteurs() {
    if (!document.querySelector('[data-compteur]')) {
        return;
    }

    const { animeCompteurs } = await import('./theme/compteurs');
    animeCompteurs();
}

document.addEventListener('DOMContentLoaded', () => {
    initApparence();
    void chargeCompteurs();
    void chargeTables();
    void chargeGraphiques();
    void chargeIlotsReact();
});

// Les graphiques lisent les couleurs du thème au moment du rendu : ils sont
// reconstruits quand l'utilisateur bascule clair/sombre.
document.addEventListener('depanne:theme', () => {
    void chargeGraphiques();
});
