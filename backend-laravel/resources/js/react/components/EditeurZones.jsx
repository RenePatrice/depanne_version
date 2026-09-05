import { useCallback, useEffect, useRef, useState } from 'react';
import L from 'leaflet';

// La feuille de style de Leaflet arrive avec l'ilot, pas avec le bundle
// principal : seules les pages qui affichent une carte la telechargent.
import 'leaflet/dist/leaflet.css';

/**
 * Éditeur d'emprises de zones (§6).
 *
 * Le fond de carte est OpenStreetMap : le back-office n'a besoin que d'un
 * support pour tracer, pas du routage de Google — lequel reste réservé au
 * calcul de distance et à l'application mobile, où il est indispensable
 * (ADR-0017). L'éditeur fonctionne donc sans aucune clé d'API.
 *
 * Clic sur la carte = sommet ajouté. Sommet déplaçable, retirable au clic
 * droit. Le polygone est renvoyé au serveur sous forme de sommets ; c'est le
 * domaine qui le valide et le ferme.
 */

const CENTRE_RATOMA = [9.595, -13.635];

const COULEURS = {
    active: { color: '#1B6FF3', fillColor: '#1B6FF3' },
    inactive: { color: '#64748B', fillColor: '#64748B' },
    edition: { color: '#FF7A1A', fillColor: '#FF7A1A' },
};

function formatGnf(montant) {
    return `${new Intl.NumberFormat('fr-FR').format(montant)} GNF`;
}

export default function EditeurZones({ zones, urlCreer, urlModifier, urlBasculer, jeton, peutModifier }) {
    const conteneur = useRef(null);
    const carte = useRef(null);
    const couches = useRef({ existantes: null, edition: null, sommets: null });

    const [selection, setSelection] = useState(null);
    const [sommets, setSommets] = useState([]);
    const [formulaire, setFormulaire] = useState({
        name: '', commune: 'Ratoma',
        base_travel_fee_gnf: 15000, price_per_km_gnf: 3000, included_km: 3,
    });

    // --- Rendu du tracé en cours ---------------------------------------------

    const redessine = useCallback((points) => {
        const { edition, sommets: groupeSommets } = couches.current;
        if (!edition) {
            return;
        }

        edition.clearLayers();
        groupeSommets.clearLayers();

        if (points.length >= 2) {
            const trace = points.length >= 3
                ? L.polygon(points, { ...COULEURS.edition, weight: 2, fillOpacity: 0.15, dashArray: '6 4' })
                : L.polyline(points, { ...COULEURS.edition, weight: 2, dashArray: '6 4' });
            trace.addTo(edition);
        }

        points.forEach((point, index) => {
            const marqueur = L.circleMarker(point, {
                radius: 6, color: '#FF7A1A', fillColor: '#fff', fillOpacity: 1, weight: 2,
            });

            marqueur.bindTooltip(`Sommet ${index + 1} — clic droit pour retirer`, { direction: 'top' });
            marqueur.on('contextmenu', (evenement) => {
                L.DomEvent.stop(evenement);
                setSommets((actuels) => actuels.filter((_, i) => i !== index));
            });

            marqueur.addTo(groupeSommets);
        });
    }, []);

    // --- Initialisation de la carte ------------------------------------------

    useEffect(() => {
        if (carte.current || !conteneur.current) {
            return undefined;
        }

        const instance = L.map(conteneur.current, { center: CENTRE_RATOMA, zoom: 13 });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap',
        }).addTo(instance);

        couches.current.existantes = L.layerGroup().addTo(instance);
        couches.current.edition = L.layerGroup().addTo(instance);
        couches.current.sommets = L.layerGroup().addTo(instance);

        carte.current = instance;

        // Leaflet mesure le conteneur au montage : dans un onglet ou une carte
        // repliée, la taille est fausse tant qu'on ne l'invalide pas.
        setTimeout(() => instance.invalidateSize(), 120);

        return () => {
            instance.remove();
            carte.current = null;
        };
    }, []);

    // --- Zones existantes -----------------------------------------------------

    useEffect(() => {
        const groupe = couches.current.existantes;
        if (!groupe) {
            return;
        }

        groupe.clearLayers();

        zones.forEach((zone) => {
            if (zone.sommets.length < 3 || zone.id === selection?.id) {
                return;
            }

            const points = zone.sommets.map((s) => [s.lat, s.lng]);
            const style = zone.active ? COULEURS.active : COULEURS.inactive;

            L.polygon(points, { ...style, weight: 2, fillOpacity: zone.active ? 0.12 : 0.06 })
                .bindTooltip(
                    `<strong>${zone.nom}</strong><br>${formatGnf(zone.tarifBase)} + ${formatGnf(zone.prixKm)}/km` +
                    `<br>${zone.tickets} ticket${zone.tickets > 1 ? 's' : ''}`,
                    { direction: 'top' },
                )
                .addTo(groupe);
        });
    }, [zones, selection]);

    // --- Saisie des sommets ---------------------------------------------------

    useEffect(() => {
        const instance = carte.current;
        if (!instance || !peutModifier) {
            return undefined;
        }

        const auClic = (evenement) => {
            setSommets((actuels) => [...actuels, [evenement.latlng.lat, evenement.latlng.lng]]);
        };

        instance.on('click', auClic);

        return () => instance.off('click', auClic);
    }, [peutModifier]);

    useEffect(() => redessine(sommets), [sommets, redessine]);

    // --- Sélection d'une zone -------------------------------------------------

    const editer = (zone) => {
        setSelection(zone);
        setFormulaire({
            name: zone.nom,
            commune: zone.commune,
            base_travel_fee_gnf: zone.tarifBase,
            price_per_km_gnf: zone.prixKm,
            included_km: zone.kmInclus,
        });

        const points = zone.sommets.map((s) => [s.lat, s.lng]);
        // Le dernier point ferme l'anneau : il ne se saisit pas à la main.
        setSommets(points.length > 3 ? points.slice(0, -1) : points);

        if (points.length >= 3 && carte.current) {
            carte.current.fitBounds(L.polygon(points).getBounds(), { padding: [40, 40] });
        }
    };

    const nouvelle = () => {
        setSelection(null);
        setSommets([]);
        setFormulaire({ name: '', commune: 'Ratoma', base_travel_fee_gnf: 15000, price_per_km_gnf: 3000, included_km: 3 });
    };

    const champ = (nom) => ({
        value: formulaire[nom],
        onChange: (e) => setFormulaire({ ...formulaire, [nom]: e.target.value }),
    });

    const action = selection ? urlModifier.replace('__ID__', String(selection.id)) : urlCreer;
    const tracéValide = sommets.length >= 3;

    return (
        <div className="row g-3">
            <div className="col-12 col-xl-8">
                <div className="dm-card p-2">
                    <div ref={conteneur} className="dm-carte" />
                    <p className="text-body-secondary small mb-0 px-2 py-2">
                        {peutModifier
                            ? 'Clique sur la carte pour ajouter un sommet. Clic droit sur un sommet pour le retirer.'
                            : 'Ton rôle permet de consulter les zones, pas de les modifier.'}
                    </p>
                </div>
            </div>

            <div className="col-12 col-xl-4">
                <div className="dm-card p-4 mb-3">
                    <div className="d-flex align-items-center gap-2 mb-3">
                        <h3 className="h6 text-uppercase text-body-secondary mb-0" style={{ letterSpacing: '.08em' }}>
                            {selection ? `Modifier « ${selection.nom} »` : 'Nouvelle zone'}
                        </h3>
                        {selection && (
                            <button type="button" className="btn btn-sm btn-outline-secondary ms-auto" onClick={nouvelle}>
                                Annuler
                            </button>
                        )}
                    </div>

                    <form method="POST" action={action}>
                        <input type="hidden" name="_token" value={jeton} />
                        {sommets.map((point, index) => (
                            <span key={`${point[0]}-${point[1]}-${index}`}>
                                <input type="hidden" name={`sommets[${index}][lat]`} value={point[0]} />
                                <input type="hidden" name={`sommets[${index}][lng]`} value={point[1]} />
                            </span>
                        ))}

                        <div className="mb-2">
                            <label className="form-label" htmlFor="zone-nom">Nom</label>
                            <input id="zone-nom" name="name" className="form-control" required maxLength={100}
                                   placeholder="Kipé — Nongo — Taouyah" {...champ('name')} />
                        </div>

                        <div className="mb-2">
                            <label className="form-label" htmlFor="zone-commune">Commune</label>
                            <input id="zone-commune" name="commune" className="form-control" maxLength={80} {...champ('commune')} />
                        </div>

                        <div className="row g-2">
                            <div className="col-6">
                                <label className="form-label" htmlFor="zone-base">Tarif de base</label>
                                <input id="zone-base" name="base_travel_fee_gnf" type="number" min="0" step="1000"
                                       className="form-control" required {...champ('base_travel_fee_gnf')} />
                            </div>
                            <div className="col-6">
                                <label className="form-label" htmlFor="zone-km">Prix au km</label>
                                <input id="zone-km" name="price_per_km_gnf" type="number" min="0" step="500"
                                       className="form-control" required {...champ('price_per_km_gnf')} />
                            </div>
                            <div className="col-6">
                                <label className="form-label" htmlFor="zone-inclus">Km inclus</label>
                                <input id="zone-inclus" name="included_km" type="number" min="0" max="100"
                                       className="form-control" {...champ('included_km')} />
                            </div>
                        </div>

                        <div className="d-flex align-items-center gap-2 mt-3">
                            <span className={`dm-badge ${tracéValide ? 'dm-badge--success' : 'dm-badge--warning'}`}>
                                {sommets.length} sommet{sommets.length > 1 ? 's' : ''}
                            </span>
                            {sommets.length > 0 && (
                                <button type="button" className="btn btn-sm btn-outline-secondary"
                                        onClick={() => setSommets([])}>
                                    Effacer le tracé
                                </button>
                            )}
                        </div>

                        <button type="submit" className="btn btn-primary w-100 mt-3"
                                disabled={!peutModifier || !tracéValide}>
                            {selection ? 'Enregistrer la zone' : 'Créer la zone'}
                        </button>

                        {!tracéValide && (
                            <p className="text-body-secondary small mt-2 mb-0">
                                Une zone a besoin d'au moins trois sommets.
                            </p>
                        )}
                    </form>
                </div>

                <div className="dm-card p-4">
                    <h3 className="h6 text-uppercase text-body-secondary mb-3" style={{ letterSpacing: '.08em' }}>
                        Zones existantes
                    </h3>

                    {zones.length === 0 && (
                        <p className="text-body-secondary small mb-0">Aucune zone pour l'instant.</p>
                    )}

                    {zones.map((zone) => (
                        <div key={zone.id} className="d-flex align-items-center gap-2 py-2 border-bottom">
                            <div className="flex-grow-1 min-w-0">
                                <div className="fw-medium text-truncate">{zone.nom}</div>
                                <div className="small text-body-secondary">
                                    {formatGnf(zone.tarifBase)} + {formatGnf(zone.prixKm)}/km ·
                                    {' '}{zone.kmInclus} km inclus · {zone.tickets} ticket{zone.tickets > 1 ? 's' : ''}
                                </div>
                            </div>

                            <span className={`dm-badge ${zone.active ? 'dm-badge--success' : 'dm-badge--secondary'}`}>
                                {zone.active ? 'Active' : 'Inactive'}
                            </span>

                            {peutModifier && (
                                <>
                                    <button type="button" className="btn btn-sm btn-outline-secondary"
                                            onClick={() => editer(zone)} title="Modifier">
                                        <i className="bi bi-pencil" aria-hidden="true"></i>
                                    </button>
                                    <form method="POST" action={urlBasculer.replace('__ID__', String(zone.id))}>
                                        <input type="hidden" name="_token" value={jeton} />
                                        <button type="submit" className="btn btn-sm btn-outline-secondary"
                                                title={zone.active ? 'Désactiver' : 'Réactiver'}>
                                            <i className={`bi ${zone.active ? 'bi-slash-circle' : 'bi-arrow-counterclockwise'}`}
                                               aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
