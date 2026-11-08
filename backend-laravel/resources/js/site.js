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

/* --- Barre de progression de lecture -------------------------------------- */
function initProgress() {
    const barre = document.querySelector('.pc-progress');
    if (!barre) return;

    const maj = () => {
        const h = document.documentElement;
        const dispo = h.scrollHeight - h.clientHeight;
        const ratio = dispo > 0 ? Math.min(h.scrollTop / dispo, 1) : 0;
        barre.style.setProperty('--pc-progress', ratio.toFixed(4));
    };
    maj();
    window.addEventListener('scroll', maj, { passive: true });
    window.addEventListener('resize', maj, { passive: true });
}

/* --- Lien de navigation actif selon la section visible --------------------- */
function initScrollspy() {
    const liens = Array.from(document.querySelectorAll('.pc-nav__links a[href^="#"]'));
    if (!liens.length || !('IntersectionObserver' in window)) return;

    const parId = new Map();
    liens.forEach((a) => {
        const id = a.getAttribute('href').slice(1);
        const section = id && document.getElementById(id);
        if (section) parId.set(section, a);
    });
    if (!parId.size) return;

    const obs = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    liens.forEach((a) => a.classList.remove('is-current'));
                    parId.get(entry.target)?.classList.add('is-current');
                }
            });
        },
        { rootMargin: '-45% 0px -50% 0px', threshold: 0 },
    );
    parId.forEach((_, section) => obs.observe(section));
}

/* --- Inclinaison 3D de la carte, qui suit la souris ----------------------- */
function initTilt() {
    if (prefersReduced) return;
    const wrap = document.querySelector('.pc-zones__visual');
    const carte = wrap && wrap.querySelector('.pc-zones__map');
    if (!carte) return;

    const maxDeg = 8;
    let frame = 0;

    wrap.addEventListener('mousemove', (e) => {
        if (frame) return;
        frame = requestAnimationFrame(() => {
            frame = 0;
            const r = carte.getBoundingClientRect();
            const px = (e.clientX - r.left) / r.width - 0.5;
            const py = (e.clientY - r.top) / r.height - 0.5;
            carte.style.transform =
                `rotateY(${(px * maxDeg).toFixed(2)}deg) rotateX(${(-py * maxDeg).toFixed(2)}deg)`;
        });
    });
    wrap.addEventListener('mouseleave', () => {
        carte.style.transform = '';
    });
}

/* --- Écran de chargement : logo animé, retiré une fois la page prête -------- */
function initLoader() {
    const loader = document.getElementById('pc-loader');
    if (!loader) return;

    const minVisible = prefersReduced ? 0 : 650; // laisse voir l'animation
    const debut = performance.now();
    const masquer = () => {
        const reste = Math.max(0, minVisible - (performance.now() - debut));
        window.setTimeout(() => loader.classList.add('is-done'), reste);
    };

    if (document.readyState === 'complete') masquer();
    else window.addEventListener('load', masquer, { once: true });

    // Filet de sécurité : ne jamais rester bloqué sur l'écran de chargement.
    window.setTimeout(() => loader.classList.add('is-done'), 4000);
}

document.addEventListener('DOMContentLoaded', () => {
    initLoader();
    initNav();
    initReveal();
    initCompteurs();
    initProgress();
    initScrollspy();
    initTilt();
});
