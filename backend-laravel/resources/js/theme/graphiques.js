import Highcharts from 'highcharts';
import 'highcharts/highcharts-more';
import 'highcharts/modules/heatmap';
import 'highcharts/modules/solid-gauge';
import 'highcharts/modules/no-data-to-display';
import 'highcharts/modules/accessibility';

import { applyHighchartsTheme, formatGnf } from './highcharts-theme';

/*
 * Rendu déclaratif des graphiques du back-office.
 *
 * Blade pose un conteneur :
 *   <div data-graphique="ligne" data-serie="{…}" data-titre="…"></div>
 * et ce module s'occupe du reste. Aucune donnée n'est calculée ici : tout
 * arrive déjà agrégé du domaine (DashboardService).
 */

const instances = new Map();

function couleursTheme() {
    const sombre = document.documentElement.dataset.bsTheme === 'dark';

    return {
        sombre,
        encre: sombre ? '#E9EFF8' : '#0F172A',
        estompe: sombre ? '#8FA1BA' : '#64748B',
        grille: sombre ? 'rgba(148,163,184,.16)' : 'rgba(15,23,42,.08)',
        vide: sombre ? '#1E293B' : '#EEF3FA',
    };
}

function lisDonnees(element) {
    try {
        return JSON.parse(element.dataset.serie ?? '{}');
    } catch {
        console.warn('Données de graphique illisibles sur', element);
        return {};
    }
}

// --- Constructeurs par type --------------------------------------------------

function optionsLigne(donnees) {
    return {
        chart: { type: 'areaspline', height: 320 },
        xAxis: { categories: donnees.categories, tickInterval: Math.ceil(donnees.categories.length / 12) || 1 },
        yAxis: { title: { text: null }, allowDecimals: false, min: 0 },
        legend: { align: 'left', verticalAlign: 'top', margin: 18 },
        tooltip: { shared: true },
        plotOptions: {
            areaspline: {
                fillOpacity: 0.08,
                marker: { enabled: false, symbol: 'circle', radius: 3, states: { hover: { enabled: true } } },
                lineWidth: 2,
            },
        },
        series: donnees.series,
    };
}

function optionsCamembert(donnees, couleurs) {
    return {
        chart: { type: 'pie', height: 320 },
        tooltip: {
            pointFormat: '<b>{point.y}</b> demandes — {point.percentage:.1f} %',
        },
        plotOptions: {
            pie: {
                innerSize: '62%',
                borderWidth: 0,
                dataLabels: {
                    enabled: true,
                    distance: 12,
                    format: '{point.name}<br><span style="opacity:.65">{point.percentage:.0f} %</span>',
                    style: { color: couleurs.encre, textOutline: 'none', fontWeight: '500' },
                },
            },
        },
        series: [{ name: 'Demandes', data: donnees }],
    };
}

function optionsColonnesEmpilees(donnees) {
    return {
        chart: { type: 'column', height: 320 },
        xAxis: { categories: donnees.categories },
        yAxis: {
            title: { text: null },
            min: 0,
            labels: {
                formatter() {
                    // Les montants en GNF atteignent vite le million :
                    // l'axe reste lisible en milliers.
                    return `${Highcharts.numberFormat(this.value / 1000, 0, ',', ' ')} k`;
                },
            },
            stackLabels: { enabled: false },
        },
        legend: { align: 'left', verticalAlign: 'top', margin: 18 },
        tooltip: {
            shared: true,
            formatter() {
                const lignes = this.points.map(
                    (p) => `<span style="color:${p.color}">●</span> ${p.series.name} : <b>${formatGnf(p.y)}</b>`,
                );
                const total = this.points.reduce((somme, p) => somme + p.y, 0);

                return `<b>${this.x}</b><br>${lignes.join('<br>')}<br>Total : <b>${formatGnf(total)}</b>`;
            },
        },
        plotOptions: { column: { stacking: 'normal', borderWidth: 0, borderRadius: 3, pointPadding: 0.08 } },
        series: donnees.series,
    };
}

function optionsHeatmap(donnees, couleurs) {
    return {
        chart: { type: 'heatmap', height: 320, marginTop: 24, marginBottom: 60 },
        xAxis: {
            categories: Array.from({ length: 24 }, (_, h) => String(h).padStart(2, '0')),
            title: { text: 'Heure de la journée' },
            labels: { step: 2 },
        },
        yAxis: { categories: donnees.jours, title: null, reversed: true },
        colorAxis: {
            min: 0,
            max: Math.max(donnees.max, 1),
            stops: [
                [0, couleurs.vide],
                [0.4, '#93C5FD'],
                [1, '#1B6FF3'],
            ],
            labels: { style: { color: couleurs.estompe } },
        },
        legend: { align: 'right', layout: 'horizontal', verticalAlign: 'bottom', margin: 4, symbolHeight: 10 },
        tooltip: {
            formatter() {
                const jour = donnees.jours[this.point.y];
                const heure = String(this.point.x).padStart(2, '0');

                return `<b>${jour}</b> à ${heure} h<br>${this.point.value} demande${this.point.value > 1 ? 's' : ''}`;
            },
        },
        series: [{
            name: 'Demandes',
            borderWidth: 1,
            borderColor: couleurs.sombre ? 'rgba(11,18,32,.9)' : '#FFFFFF',
            data: donnees.data,
            turboThreshold: 0,
        }],
    };
}

function optionsJauge(valeur, couleurs) {
    // Vert au-delà de 70 %, orange entre 45 et 70, rouge en dessous : ce sont
    // les seuils à partir desquels le matching commence à peiner.
    const teinte = valeur >= 70 ? '#16A34A' : valeur >= 45 ? '#F59E0B' : '#DC2626';

    return {
        chart: { type: 'solidgauge', height: 240, marginTop: 8 },
        pane: {
            center: ['50%', '72%'],
            size: '150%',
            startAngle: -90,
            endAngle: 90,
            background: {
                backgroundColor: couleurs.vide,
                innerRadius: '65%',
                outerRadius: '100%',
                shape: 'arc',
                borderWidth: 0,
            },
        },
        yAxis: {
            min: 0,
            max: 100,
            lineWidth: 0,
            tickLength: 0,
            tickPositions: [0, 50, 100],
            labels: { distance: 14, style: { color: couleurs.estompe, fontSize: '11px' } },
        },
        tooltip: { enabled: false },
        plotOptions: {
            solidgauge: {
                innerRadius: '65%',
                dataLabels: {
                    y: -18,
                    borderWidth: 0,
                    useHTML: true,
                    format:
                        '<div style="text-align:center">' +
                        `<div style="font-size:1.9rem;font-weight:700;font-family:Poppins,sans-serif;color:${couleurs.encre}">{y} %</div>` +
                        `<div style="font-size:.75rem;color:${couleurs.estompe}">des sollicitations acceptées</div>` +
                        '</div>',
                },
            },
        },
        series: [{ name: "Taux d'acceptation", data: [{ y: valeur, color: teinte }] }],
    };
}

// --- Orchestration -----------------------------------------------------------

function construis(element) {
    const couleurs = couleursTheme();
    const donnees = lisDonnees(element);

    const options = {
        ligne: () => optionsLigne(donnees),
        camembert: () => optionsCamembert(donnees, couleurs),
        colonnes: () => optionsColonnesEmpilees(donnees),
        heatmap: () => optionsHeatmap(donnees, couleurs),
        jauge: () => optionsJauge(Number(element.dataset.valeur ?? 0), couleurs),
    }[element.dataset.graphique];

    if (!options) {
        console.warn(`Type de graphique inconnu : « ${element.dataset.graphique} »`);
        return;
    }

    instances.get(element)?.destroy();
    instances.set(element, Highcharts.chart(element, options()));
}

/** Rend tous les graphiques de la page. Rappelé au changement de thème. */
export function rendGraphiques() {
    applyHighchartsTheme();
    document.querySelectorAll('[data-graphique]').forEach(construis);
}
