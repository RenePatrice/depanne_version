/**
 * Apparence du back-office : thème clair/sombre et barre latérale rétractable.
 *
 * Le thème est appliqué avant le premier rendu par un script en ligne dans le
 * gabarit ; ce module ne gère que les bascules et leur mémorisation.
 */

const CLE_THEME = 'depanne.theme';
const CLE_SIDEBAR = 'depanne.sidebar';

function litPreference(cle, defaut) {
    try {
        return localStorage.getItem(cle) ?? defaut;
    } catch {
        // Navigation privée ou stockage bloqué : on retombe sur le défaut.
        return defaut;
    }
}

function ecritPreference(cle, valeur) {
    try {
        localStorage.setItem(cle, valeur);
    } catch {
        // Sans mémorisation, la bascule reste valable pour la session en cours.
    }
}

export function themeCourant() {
    return document.documentElement.dataset.bsTheme === 'dark' ? 'dark' : 'light';
}

export function appliqueTheme(theme) {
    document.documentElement.dataset.bsTheme = theme;
    ecritPreference(CLE_THEME, theme);

    document.querySelectorAll('[data-bascule-theme]').forEach((bouton) => {
        const sombre = theme === 'dark';
        bouton.setAttribute('aria-pressed', String(sombre));
        bouton.setAttribute('title', sombre ? 'Passer en thème clair' : 'Passer en thème sombre');

        const icone = bouton.querySelector('i');
        if (icone) {
            icone.className = sombre ? 'bi bi-sun' : 'bi bi-moon-stars';
        }
    });

    // Les graphiques déjà rendus doivent suivre le thème.
    document.dispatchEvent(new CustomEvent('depanne:theme', { detail: { theme } }));
}

function initTheme() {
    appliqueTheme(themeCourant());

    document.querySelectorAll('[data-bascule-theme]').forEach((bouton) => {
        bouton.addEventListener('click', () => {
            appliqueTheme(themeCourant() === 'dark' ? 'light' : 'dark');
        });
    });
}

function initSidebar() {
    const layout = document.querySelector('[data-layout]');
    if (!layout) {
        return;
    }

    const grandEcran = () => window.matchMedia('(min-width: 992px)').matches;

    if (grandEcran() && litPreference(CLE_SIDEBAR, 'ouverte') === 'repliee') {
        layout.classList.add('is-collapsed');
    }

    document.querySelectorAll('[data-bascule-sidebar]').forEach((bouton) => {
        bouton.addEventListener('click', () => {
            if (grandEcran()) {
                layout.classList.toggle('is-collapsed');
                ecritPreference(CLE_SIDEBAR, layout.classList.contains('is-collapsed') ? 'repliee' : 'ouverte');
            } else {
                layout.classList.toggle('is-open');
            }
        });
    });

    layout.querySelector('[data-fermer-sidebar]')?.addEventListener('click', () => {
        layout.classList.remove('is-open');
    });

    // Échap referme le tiroir : sur mobile il masque toute la page.
    document.addEventListener('keydown', (evenement) => {
        if (evenement.key === 'Escape') {
            layout.classList.remove('is-open');
        }
    });
}

export function initApparence() {
    initTheme();
    initSidebar();
}
