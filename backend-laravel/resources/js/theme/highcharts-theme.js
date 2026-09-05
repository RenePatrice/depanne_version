import Highcharts from 'highcharts';

// Palette dérivée des jetons de design (resources/scss/_tokens.scss).
const PALETTE = ['#1B6FF3', '#FF7A1A', '#16A34A', '#F59E0B', '#0EA5E9', '#8B5CF6', '#DC2626', '#64748B'];

/**
 * Thème Highcharts global : police Inter, crédit désactivé, tooltips arrondis,
 * animations douces. Les couleurs de texte suivent le mode clair/sombre du
 * back-office (attribut data-bs-theme sur <html>).
 */
export function applyHighchartsTheme() {
    const dark = document.documentElement.dataset.bsTheme === 'dark';
    const ink = dark ? '#E9EFF8' : '#0F172A';
    const muted = dark ? '#8FA1BA' : '#64748B';
    const grid = dark ? 'rgba(148, 163, 184, 0.16)' : 'rgba(15, 23, 42, 0.08)';
    const surface = dark ? '#111B2E' : '#FFFFFF';

    Highcharts.setOptions({
        colors: PALETTE,
        chart: {
            backgroundColor: 'transparent',
            style: { fontFamily: "'Inter', 'Segoe UI', system-ui, sans-serif" },
            animation: { duration: 350 },
        },
        title: { style: { color: ink, fontFamily: "'Poppins', sans-serif", fontWeight: '600', fontSize: '16px' } },
        subtitle: { style: { color: muted, fontSize: '13px' } },
        xAxis: {
            lineColor: grid,
            tickColor: grid,
            gridLineColor: grid,
            labels: { style: { color: muted, fontSize: '12px' } },
            title: { style: { color: muted } },
        },
        yAxis: {
            gridLineColor: grid,
            labels: { style: { color: muted, fontSize: '12px' } },
            title: { style: { color: muted } },
        },
        legend: {
            itemStyle: { color: ink, fontWeight: '500', fontSize: '12px' },
            itemHoverStyle: { color: '#1B6FF3' },
        },
        tooltip: {
            backgroundColor: surface,
            borderWidth: 0,
            borderRadius: 12,
            shadow: { color: 'rgba(15, 23, 42, 0.14)', offsetX: 0, offsetY: 4, opacity: 0.1, width: 12 },
            style: { color: ink, fontSize: '12px' },
            useHTML: true,
        },
        plotOptions: {
            series: { animation: { duration: 350 }, borderRadius: 4 },
            column: { borderWidth: 0, borderRadius: 4 },
            pie: { borderWidth: 0, dataLabels: { style: { color: ink, textOutline: 'none', fontWeight: '500' } } },
        },
        credits: { enabled: false },
        lang: {
            // Highcharts affiche ces libellés dans l'export et les états vides.
            noData: 'Aucune donnée sur cette période',
            loading: 'Chargement…',
            months: ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
            shortMonths: ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'],
            weekdays: ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'],
            thousandsSep: ' ',
            decimalPoint: ',',
        },
    });
}

/** Formate un montant en GNF : 100000 → « 100 000 GNF ». */
export function formatGnf(amount) {
    return `${new Intl.NumberFormat('fr-FR').format(amount)} GNF`;
}
