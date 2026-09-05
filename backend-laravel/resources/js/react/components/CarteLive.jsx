import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import L from 'leaflet';

import 'leaflet/dist/leaflet.css';

/**
 * Carte live du back-office (§6).
 *
 * L'écran reçoit d'abord un instantané complet, puis suit les messages Reverb :
 * sans instantané, la carte resterait vide jusqu'au premier événement. Si le
 * WebSocket n'est pas joignable, elle bascule sur une interrogation périodique
 * et le dit — le réseau de Conakry coupe, mais la supervision ne doit pas
 * s'arrêter pour autant.
 */

const CENTRE_RATOMA = [9.595, -13.635];
const REPLI_MS = 25000;

const heure = new Intl.DateTimeFormat('fr-FR', {
    timeZone: 'Africa/Conakry', hour: '2-digit', minute: '2-digit',
});

const COULEUR_TECHNICIEN = {
    disponible: '#16A34A',
    intervention: '#FF7A1A',
};

function pastille(couleur, pulsante = false) {
    return L.divIcon({
        className: 'dm-marqueur',
        html: `<span class="dm-marqueur__point ${pulsante ? 'is-pulse' : ''}" style="--point:${couleur}"></span>`,
        iconSize: [18, 18],
        iconAnchor: [9, 9],
    });
}

export default function CarteLive({ urlDonnees, urlCompteurs, categories, etats }) {
    const conteneur = useRef(null);
    const carte = useRef(null);
    const couches = useRef({ techniciens: null, interventions: null });
    const marqueurs = useRef({ techniciens: new Map(), interventions: new Map() });

    const [techniciens, setTechniciens] = useState([]);
    const [interventions, setInterventions] = useState([]);
    const [compteurs, setCompteurs] = useState(null);
    const [selection, setSelection] = useState(null);
    const [connecte, setConnecte] = useState(false);
    const [derniereMaj, setDerniereMaj] = useState(null);
    const [filtres, setFiltres] = useState({ etat: '', categorie: '', techniciens: true, interventions: true });

    // --- Chargement de l'instantané -------------------------------------------

    const chargerInstantane = useCallback(async () => {
        try {
            const reponse = await fetch(urlDonnees, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const charge = await reponse.json();

            setTechniciens(charge.techniciens ?? []);
            setInterventions(charge.interventions ?? []);
            setCompteurs(charge.compteurs ?? null);
            setDerniereMaj(new Date());
        } catch {
            // On garde ce qui est affiché : une carte figée vaut mieux qu'une
            // carte vide.
        }
    }, [urlDonnees]);

    const rafraichirCompteurs = useCallback(async () => {
        try {
            const reponse = await fetch(urlCompteurs, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const charge = await reponse.json();
            setCompteurs(charge.compteurs ?? null);
        } catch {
            /* silencieux : le compteur reprendra au message suivant */
        }
    }, [urlCompteurs]);

    // --- Carte -----------------------------------------------------------------

    useEffect(() => {
        if (carte.current || !conteneur.current) {
            return undefined;
        }

        const instance = L.map(conteneur.current, { center: CENTRE_RATOMA, zoom: 13 });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap',
        }).addTo(instance);

        couches.current.interventions = L.layerGroup().addTo(instance);
        couches.current.techniciens = L.layerGroup().addTo(instance);
        carte.current = instance;

        setTimeout(() => instance.invalidateSize(), 120);

        return () => {
            instance.remove();
            carte.current = null;
        };
    }, []);

    // --- Abonnement temps réel -------------------------------------------------

    useEffect(() => {
        void chargerInstantane();

        const echo = window.Echo;

        if (!echo) {
            return undefined;
        }

        const canal = echo.private('back-office');

        canal.listen('.technicien.position', (message) => {
            setDerniereMaj(new Date());
            setTechniciens((actuels) => {
                const index = actuels.findIndex((t) => t.id === message.id);

                if (index === -1) {
                    // Un technicien qui se connecte en cours de session : on
                    // recharge l'instantané plutôt que de deviner son profil.
                    void chargerInstantane();

                    return actuels;
                }

                const copie = [...actuels];
                copie[index] = {
                    ...copie[index],
                    latitude: message.latitude,
                    longitude: message.longitude,
                    enIntervention: message.enIntervention,
                    ticket: message.ticket,
                };

                return copie;
            });
        });

        canal.listen('.ticket.transition', () => {
            // Une transition change la liste des interventions actives et les
            // compteurs : l'instantané est le moyen le plus sûr de rester juste.
            void chargerInstantane();
        });

        // Le connecteur Pusher expose l'état de la connexion : on l'affiche
        // plutôt que de laisser croire à un temps réel qui ne fonctionne pas.
        const connecteur = echo.connector?.pusher?.connection;

        const surEtat = ({ current }) => setConnecte(current === 'connected');
        connecteur?.bind('state_change', surEtat);
        setConnecte(connecteur?.state === 'connected');

        return () => {
            connecteur?.unbind('state_change', surEtat);
            echo.leave('back-office');
        };
    }, [chargerInstantane]);

    // Repli : tant que le WebSocket n'est pas connecté, on interroge le serveur.
    useEffect(() => {
        if (connecte) {
            return undefined;
        }

        const identifiant = setInterval(() => {
            void chargerInstantane();
        }, REPLI_MS);

        return () => clearInterval(identifiant);
    }, [connecte, chargerInstantane]);

    useEffect(() => {
        if (connecte) {
            void rafraichirCompteurs();
        }
    }, [connecte, techniciens, interventions, rafraichirCompteurs]);

    // --- Filtrage ---------------------------------------------------------------

    const interventionsVisibles = useMemo(
        () => interventions.filter((i) => (filtres.etat === '' || i.etat === filtres.etat)
            && (filtres.categorie === '' || String(i.categorieId) === filtres.categorie)),
        [interventions, filtres.etat, filtres.categorie],
    );

    // --- Rendu des marqueurs ----------------------------------------------------

    useEffect(() => {
        const groupe = couches.current.techniciens;
        if (!groupe) {
            return;
        }

        groupe.clearLayers();
        marqueurs.current.techniciens.clear();

        if (!filtres.techniciens) {
            return;
        }

        techniciens.forEach((technicien) => {
            const couleur = technicien.enIntervention
                ? COULEUR_TECHNICIEN.intervention
                : COULEUR_TECHNICIEN.disponible;

            const marqueur = L.marker([technicien.latitude, technicien.longitude], {
                icon: pastille(couleur, technicien.enIntervention),
                title: technicien.nom,
            });

            marqueur.on('click', () => setSelection({ type: 'technicien', donnees: technicien }));
            marqueur.addTo(groupe);
            marqueurs.current.techniciens.set(technicien.id, marqueur);
        });
    }, [techniciens, filtres.techniciens]);

    useEffect(() => {
        const groupe = couches.current.interventions;
        if (!groupe) {
            return;
        }

        groupe.clearLayers();

        if (!filtres.interventions) {
            return;
        }

        interventionsVisibles.forEach((intervention) => {
            const marqueur = L.circleMarker([intervention.latitude, intervention.longitude], {
                radius: 9,
                color: intervention.couleur,
                fillColor: intervention.couleur,
                fillOpacity: 0.28,
                weight: 2,
            });

            marqueur.on('click', () => setSelection({ type: 'intervention', donnees: intervention }));
            marqueur.addTo(groupe);
        });
    }, [interventionsVisibles, filtres.interventions]);

    // --- Rendu ------------------------------------------------------------------

    const cartes = [
        ['techniciens_en_ligne', 'Techniciens en ligne', 'bi-broadcast', 'success'],
        ['interventions_en_cours', 'Interventions en cours', 'bi-activity', 'tech'],
        ['demandes_en_attente', 'Demandes en attente', 'bi-hourglass-split', 'warning'],
        ['sans_reponse_24h', 'Sans réponse (24 h)', 'bi-exclamation-triangle', 'danger'],
    ];

    return (
        <div className="dm-live">

            <div className="row g-3 mb-3">
                {cartes.map(([cle, libelle, icone, ton]) => (
                    <div className="col-6 col-xl-3" key={cle}>
                        <div className="dm-stat">
                            <span className={`dm-stat__icon is-${ton}`} aria-hidden="true">
                                <i className={`bi ${icone}`}></i>
                            </span>
                            <div>
                                <div className="dm-stat__value">{compteurs ? compteurs[cle] : '—'}</div>
                                <div className="dm-stat__label">{libelle}</div>
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            <div className="dm-card p-3 mb-3 d-flex flex-wrap align-items-end gap-3">
                <div>
                    <label className="form-label" htmlFor="live-etat">Statut</label>
                    <select className="form-select form-select-sm" id="live-etat" value={filtres.etat}
                            onChange={(e) => setFiltres({ ...filtres, etat: e.target.value })}>
                        <option value="">Tous</option>
                        {etats.map((etat) => (
                            <option key={etat.valeur} value={etat.valeur}>{etat.libelle}</option>
                        ))}
                    </select>
                </div>

                <div>
                    <label className="form-label" htmlFor="live-categorie">Catégorie</label>
                    <select className="form-select form-select-sm" id="live-categorie" value={filtres.categorie}
                            onChange={(e) => setFiltres({ ...filtres, categorie: e.target.value })}>
                        <option value="">Toutes</option>
                        {categories.map((categorie) => (
                            <option key={categorie.id} value={String(categorie.id)}>{categorie.name}</option>
                        ))}
                    </select>
                </div>

                <div className="form-check">
                    <input className="form-check-input" type="checkbox" id="live-tech"
                           checked={filtres.techniciens}
                           onChange={(e) => setFiltres({ ...filtres, techniciens: e.target.checked })} />
                    <label className="form-check-label small" htmlFor="live-tech">Techniciens</label>
                </div>

                <div className="form-check">
                    <input className="form-check-input" type="checkbox" id="live-inter"
                           checked={filtres.interventions}
                           onChange={(e) => setFiltres({ ...filtres, interventions: e.target.checked })} />
                    <label className="form-check-label small" htmlFor="live-inter">Interventions</label>
                </div>

                <div className="ms-auto d-flex align-items-center gap-2">
                    <span className={`dm-badge ${connecte ? 'dm-badge--success' : 'dm-badge--warning'}`}>
                        {connecte ? 'Temps réel' : 'Mode dégradé'}
                    </span>
                    {derniereMaj && (
                        <span className="text-body-secondary small">
                            màj {heure.format(derniereMaj)}
                        </span>
                    )}
                </div>
            </div>

            {!connecte && (
                <div className="alert alert-warning py-2 px-3 small d-flex align-items-center gap-2" role="status">
                    <i className="bi bi-wifi-off" aria-hidden="true"></i>
                    <span>
                        WebSocket indisponible — la carte se rafraîchit toutes les 25 secondes.
                        Vérifie que <code>php artisan reverb:start</code> tourne.
                    </span>
                </div>
            )}

            <div className="row g-3">
                <div className={selection ? 'col-12 col-xl-8' : 'col-12'}>
                    <div className="dm-card p-2">
                        <div ref={conteneur} className="dm-carte dm-carte--live" />
                        <div className="d-flex flex-wrap gap-3 px-2 py-2 small text-body-secondary">
                            <span><span className="dm-legende" style={{ background: COULEUR_TECHNICIEN.disponible }}></span> Technicien disponible</span>
                            <span><span className="dm-legende" style={{ background: COULEUR_TECHNICIEN.intervention }}></span> Technicien en intervention</span>
                            <span><span className="dm-legende dm-legende--cercle"></span> Intervention en cours ({interventionsVisibles.length})</span>
                        </div>
                    </div>
                </div>

                {selection && (
                    <div className="col-12 col-xl-4">
                        <div className="dm-card p-4">
                            <div className="d-flex align-items-start gap-2 mb-3">
                                <h3 className="h6 mb-0 flex-grow-1">
                                    {selection.type === 'technicien' ? 'Technicien' : 'Intervention'}
                                </h3>
                                <button type="button" className="btn-close" aria-label="Fermer"
                                        onClick={() => setSelection(null)}></button>
                            </div>

                            {selection.type === 'technicien' ? (
                                <>
                                    <div className="fw-semibold">{selection.donnees.nom}</div>
                                    <div className="font-monospace small text-body-secondary mb-3">
                                        {selection.donnees.telephone}
                                    </div>

                                    <div className="d-flex justify-content-between py-1 border-bottom">
                                        <span>Note</span><span>{selection.donnees.note || '—'}</span>
                                    </div>
                                    <div className="d-flex justify-content-between py-1 border-bottom">
                                        <span>Interventions</span><span>{selection.donnees.interventions}</span>
                                    </div>
                                    <div className="d-flex justify-content-between py-1 border-bottom">
                                        <span>État</span>
                                        <span className={`dm-badge ${selection.donnees.enIntervention ? 'dm-badge--tech' : 'dm-badge--success'}`}>
                                            {selection.donnees.enIntervention ? 'En intervention' : 'Disponible'}
                                        </span>
                                    </div>
                                    {selection.donnees.ticket && (
                                        <div className="d-flex justify-content-between py-1">
                                            <span>Ticket</span>
                                            <span className="font-monospace">{selection.donnees.ticket}</span>
                                        </div>
                                    )}

                                    <a href={`/techniciens/${selection.donnees.id}`}
                                       className="btn btn-outline-secondary btn-sm w-100 mt-3">
                                        Ouvrir la fiche
                                    </a>
                                </>
                            ) : (
                                <>
                                    <div className="d-flex align-items-center gap-2 mb-1">
                                        <span className="font-monospace">{selection.donnees.reference}</span>
                                        <span className={`dm-badge dm-badge--${selection.donnees.ton}`}>
                                            {selection.donnees.etatLibelle}
                                        </span>
                                    </div>
                                    <div className="small text-body-secondary mb-3">
                                        {selection.donnees.prestation} · {selection.donnees.categorie}
                                    </div>

                                    <div className="d-flex justify-content-between py-1 border-bottom">
                                        <span>Client</span><span>{selection.donnees.client}</span>
                                    </div>
                                    <div className="d-flex justify-content-between py-1 border-bottom">
                                        <span>Technicien</span><span>{selection.donnees.technicien ?? '—'}</span>
                                    </div>
                                    <div className="d-flex justify-content-between py-1">
                                        <span>Montant</span>
                                        <span className="dm-amount">{selection.donnees.montantFormate}</span>
                                    </div>

                                    <a href={`/tickets/${selection.donnees.id}`}
                                       className="btn btn-outline-secondary btn-sm w-100 mt-3">
                                        Ouvrir le ticket
                                    </a>
                                </>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
