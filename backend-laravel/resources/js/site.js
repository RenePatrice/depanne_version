// =============================================================================
// PAREMA CONNECT — Site vitrine : interactions (vanilla, sans dépendance).
// =============================================================================

const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* --- Navigation : ombre au défilement + menu mobile ----------------------- */
function initNav() {
    const nav = document.querySelector('[data-nav]');
    if (!nav) return;

    const onScroll = () => nav.classList.toggle('is-scrolled', window.scrollY > 8);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    const toggle = nav.querySelector('[data-nav-toggle]');
    const links = nav.querySelector('[data-nav-links]');
    if (toggle && links) {
        toggle.addEventListener('click', () => {
            const ouvert = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-label', ouvert ? 'Fermer le menu' : 'Ouvrir le menu');
        });
        // Un clic sur un lien d'ancre referme le menu.
        links.querySelectorAll('a[href^="#"]').forEach((a) =>
            a.addEventListener('click', () => nav.classList.remove('is-open')),
        );
    }
}

/* --- Révélation au défilement --------------------------------------------- */
function initReveal() {
    const cibles = document.querySelectorAll('[data-reveal]');
    if (!cibles.length) return;

    if (prefersReduced || !('IntersectionObserver' in window)) {
        cibles.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    const obs = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    obs.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.12, rootMargin: '0px 0px -8% 0px' },
    );

    cibles.forEach((el) => obs.observe(el));
}

/* --- Compteurs animés ------------------------------------------------------ */
function animerCompteur(el) {
    const cible = parseFloat(el.dataset.counter || '0');
    const suffixe = el.dataset.suffix || '';
    const decimales = parseInt(el.dataset.decimals || '0', 10);
    const format = (v) =>
        v.toLocaleString('fr-FR', { minimumFractionDigits: decimales, maximumFractionDigits: decimales }) + suffixe;

    if (prefersReduced) {
        el.textContent = format(cible);
        return;
    }

    const duree = 1400;
    const depart = performance.now();
    const tick = (maintenant) => {
        const p = Math.min((maintenant - depart) / duree, 1);
        const eased = 1 - Math.pow(1 - p, 3); // easeOutCubic
        el.textContent = format(cible * eased);
        if (p < 1) requestAnimationFrame(tick);
        else el.textContent = format(cible);
    };
    requestAnimationFrame(tick);
}

function initCompteurs() {
    const compteurs = document.querySelectorAll('[data-counter]');
    if (!compteurs.length) return;

    if (!('IntersectionObserver' in window)) {
        compteurs.forEach(animerCompteur);
        return;
    }

    const obs = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    animerCompteur(entry.target);
                    obs.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.5 },
    );

    compteurs.forEach((el) => obs.observe(el));
}

document.addEventListener('DOMContentLoaded', () => {
    initNav();
    initReveal();
    initCompteurs();
});
