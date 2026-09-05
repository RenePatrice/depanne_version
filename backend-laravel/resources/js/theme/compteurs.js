/**
 * Animation des cartes KPI : le chiffre monte de zéro à sa valeur.
 *
 * Purement décoratif — la valeur finale est déjà dans le DOM, si bien qu'un
 * lecteur d'écran, un moteur d'impression ou un navigateur sans JavaScript
 * lisent le bon nombre. L'animation est désactivée si l'utilisateur a demandé
 * moins de mouvement.
 */

const DUREE = 700;

function formate(valeur, format) {
    switch (format) {
        case 'gnf':
            return `${new Intl.NumberFormat('fr-FR').format(Math.round(valeur))}`;
        case 'pourcentage':
            return `${valeur.toFixed(1).replace('.', ',')}`;
        case 'note':
            return valeur.toFixed(2).replace('.', ',');
        case 'duree': {
            const secondes = Math.round(valeur);
            if (secondes < 60) return `${secondes}`;
            return `${Math.floor(secondes / 60)}`;
        }
        default:
            return new Intl.NumberFormat('fr-FR').format(Math.round(valeur));
    }
}

export function animeCompteurs() {
    const reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.querySelectorAll('[data-compteur]').forEach((element) => {
        const cible = Number(element.dataset.compteur);
        const format = element.dataset.format ?? 'entier';

        if (reduit || !Number.isFinite(cible) || cible === 0) {
            return;
        }

        const depart = performance.now();

        const pas = (instant) => {
            const avancement = Math.min((instant - depart) / DUREE, 1);
            // Sortie amortie : le chiffre ralentit en approchant de sa valeur.
            const eased = 1 - (1 - avancement) ** 3;

            element.textContent = formate(cible * eased, format);

            if (avancement < 1) {
                requestAnimationFrame(pas);
            }
        };

        requestAnimationFrame(pas);
    });
}
