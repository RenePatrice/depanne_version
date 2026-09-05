import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Flux d'activité du tableau de bord.
 *
 * Il démarre avec les événements rendus par Blade — la liste n'est jamais vide
 * au premier affichage — puis suit les transitions poussées par Reverb. Quand le
 * WebSocket n'est pas joignable, il retombe sur une interrogation périodique :
 * le réseau coupe, la supervision continue.
 */

const INTERVALLE_MS = 20000;

const relatif = new Intl.RelativeTimeFormat('fr', { numeric: 'auto' });
const heure = new Intl.DateTimeFormat('fr-FR', {
    timeZone: 'Africa/Conakry',
    hour: '2-digit',
    minute: '2-digit',
});

function ilYA(horodatage) {
    const secondes = Math.round((new Date(horodatage) - Date.now()) / 1000);
    const paliers = [
        [60, 'second'],
        [3600, 'minute'],
        [86400, 'hour'],
        [604800, 'day'],
    ];

    let unite = 'second';
    let diviseur = 1;

    for (const [limite, nom] of paliers) {
        if (Math.abs(secondes) < limite) {
            break;
        }
        unite = nom;
        diviseur = limite;
    }

    if (unite === 'second') {
        return relatif.format(Math.round(secondes), 'second');
    }

    const facteur = { minute: 60, hour: 3600, day: 86400 }[unite] ?? 1;

    return relatif.format(Math.round(secondes / facteur), unite);
}

function formatGnf(montant) {
    return `${new Intl.NumberFormat('fr-FR').format(montant)} GNF`;
}

export default function FluxActivite({ url, initial = [] }) {
    const [evenements, setEvenements] = useState(initial);
    const [enErreur, setEnErreur] = useState(false);
    const [tick, setTick] = useState(0);
    const [tempsReel, setTempsReel] = useState(false);
    const monte = useRef(true);

    const rafraichis = useCallback(async () => {
        try {
            const reponse = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!reponse.ok) {
                throw new Error(String(reponse.status));
            }

            const charge = await reponse.json();

            if (monte.current) {
                setEvenements(charge.evenements ?? []);
                setEnErreur(false);
            }
        } catch {
            // Réseau instable : on garde la dernière liste connue et on le dit,
            // plutôt que de vider l'écran.
            if (monte.current) {
                setEnErreur(true);
            }
        }
    }, [url]);

    useEffect(() => {
        monte.current = true;

        const echo = window.Echo;
        const canal = echo?.private('back-office');

        // Chaque transition de ticket est déjà un événement du flux : on
        // recharge la liste plutôt que de l'insérer à la main, pour rester
        // exactement aligné sur ce que le serveur renverrait.
        canal?.listen('.ticket.transition', () => {
            void rafraichis();
        });

        const connecteur = echo?.connector?.pusher?.connection;
        const surEtat = ({ current }) => setTempsReel(current === 'connected');
        connecteur?.bind('state_change', surEtat);
        setTempsReel(connecteur?.state === 'connected');

        // Les libellés « il y a 3 minutes » doivent vieillir tout seuls.
        const horloge = setInterval(() => setTick((t) => t + 1), 30000);

        return () => {
            monte.current = false;
            connecteur?.unbind('state_change', surEtat);
            echo?.leave('back-office');
            clearInterval(horloge);
        };
    }, [rafraichis]);

    // Repli : sans WebSocket, on interroge le serveur périodiquement.
    useEffect(() => {
        if (tempsReel) {
            return undefined;
        }

        const identifiant = setInterval(rafraichis, INTERVALLE_MS);

        return () => clearInterval(identifiant);
    }, [tempsReel, rafraichis]);

    if (evenements.length === 0) {
        return (
            <div className="text-center text-body-secondary py-5">
                <i className="bi bi-inbox fs-3 d-block mb-2" aria-hidden="true"></i>
                Aucune activité sur la plateforme pour l'instant.
            </div>
        );
    }

    return (
        <div>
            {enErreur && (
                <div className="alert alert-warning py-2 px-3 small d-flex align-items-center gap-2 mb-3" role="status">
                    <i className="bi bi-wifi-off" aria-hidden="true"></i>
                    <span>Flux interrompu — dernière mise à jour conservée.</span>
                </div>
            )}

            {!tempsReel && !enErreur && (
                <div className="text-body-secondary small mb-2">
                    <i className="bi bi-arrow-repeat me-1" aria-hidden="true"></i>
                    Mode dégradé — actualisation toutes les 20 secondes.
                </div>
            )}

            <ul className="dm-flux" data-tick={tick}>
                {evenements.map((evenement) => (
                    <li key={evenement.id} className="dm-flux__item">
                        <span className={`dm-flux__puce dm-flux__puce--${evenement.ton}`} aria-hidden="true"></span>

                        <div className="min-w-0 flex-grow-1">
                            <div className="d-flex align-items-center gap-2 flex-wrap">
                                <span className={`dm-badge dm-badge--${evenement.ton}`}>{evenement.etatLibelle}</span>
                                <span className="font-monospace small text-body-secondary">{evenement.reference}</span>
                            </div>
                            <div className="small text-body-secondary mt-1 text-truncate">
                                {evenement.client}
                                {evenement.technicien ? ` · ${evenement.technicien}` : ''}
                                {evenement.montant > 0 ? ` · ${formatGnf(evenement.montant)}` : ''}
                            </div>
                        </div>

                        <time className="dm-flux__heure" dateTime={evenement.horodatage}
                              title={heure.format(new Date(evenement.horodatage))}>
                            {ilYA(evenement.horodatage)}
                        </time>
                    </li>
                ))}
            </ul>
        </div>
    );
}
