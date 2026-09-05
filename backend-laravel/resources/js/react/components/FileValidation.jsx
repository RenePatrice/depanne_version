import { useCallback, useEffect, useState } from 'react';

/**
 * File de validation des techniciens (§6).
 *
 * Un dossier à la fois, avec ses trois pièces côte à côte : le support compare
 * le recto, le verso et le selfie sans changer d'écran. Les décisions partent
 * en formulaire classique — le contrôleur redirige et affiche son message — ce
 * qui garde la trace d'audit et le message d'erreur du domaine intacts.
 */

const dateFr = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' });

function Piece({ titre, url, onZoom }) {
    return (
        <figure className="dm-piece">
            <figcaption className="dm-piece__titre">{titre}</figcaption>

            {url ? (
                <button type="button" className="dm-piece__cadre" onClick={() => onZoom({ titre, url })}
                        title={`Agrandir ${titre.toLowerCase()}`}>
                    <img src={url} alt={titre} loading="lazy" />
                    <span className="dm-piece__loupe" aria-hidden="true"><i className="bi bi-zoom-in"></i></span>
                </button>
            ) : (
                <div className="dm-piece__cadre dm-piece__cadre--vide">
                    <i className="bi bi-file-earmark-lock" aria-hidden="true"></i>
                    <span>Pièce non consultable</span>
                    <small>Le bucket privé Supabase n'est pas encore branché.</small>
                </div>
            )}
        </figure>
    );
}

export default function FileValidation({ urlDossiers, urlApprouver, urlRejeter, urlFiche, jeton }) {
    const [dossiers, setDossiers] = useState(null);
    const [index, setIndex] = useState(0);
    const [zoom, setZoom] = useState(null);
    const [motif, setMotif] = useState('');
    const [rejetOuvert, setRejetOuvert] = useState(false);

    const charger = useCallback(async () => {
        try {
            const reponse = await fetch(urlDossiers, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const charge = await reponse.json();
            setDossiers(charge.dossiers ?? []);
            setIndex(0);
        } catch {
            setDossiers([]);
        }
    }, [urlDossiers]);

    useEffect(() => {
        void charger();
    }, [charger]);

    useEffect(() => {
        const fermer = (e) => e.key === 'Escape' && setZoom(null);
        document.addEventListener('keydown', fermer);

        return () => document.removeEventListener('keydown', fermer);
    }, []);

    if (dossiers === null) {
        return (
            <div className="dm-empty">
                <span className="dm-empty__icon" aria-hidden="true"><i className="bi bi-hourglass-split"></i></span>
                <p className="dm-empty__text">Chargement des dossiers en attente…</p>
            </div>
        );
    }

    if (dossiers.length === 0) {
        return (
            <div className="dm-empty">
                <span className="dm-empty__icon" aria-hidden="true"><i className="bi bi-check2-circle"></i></span>
                <h2 className="dm-empty__title">Aucun dossier en attente</h2>
                <p className="dm-empty__text">
                    Tous les dossiers de techniciens ont été examinés. Les nouvelles inscriptions
                    apparaîtront ici automatiquement.
                </p>
            </div>
        );
    }

    const dossier = dossiers[Math.min(index, dossiers.length - 1)];
    const lien = (modele) => modele.replace('__ID__', String(dossier.id));

    return (
        <div className="dm-validation">

            <div className="dm-validation__barre">
                <span className="dm-badge dm-badge--warning">
                    {dossiers.length} dossier{dossiers.length > 1 ? 's' : ''} en attente
                </span>

                <div className="d-flex align-items-center gap-2 ms-auto">
                    <button type="button" className="btn btn-sm btn-outline-secondary"
                            disabled={index === 0} onClick={() => { setIndex(index - 1); setRejetOuvert(false); }}>
                        <i className="bi bi-chevron-left" aria-hidden="true"></i> Précédent
                    </button>
                    <span className="text-body-secondary small">{index + 1} / {dossiers.length}</span>
                    <button type="button" className="btn btn-sm btn-outline-secondary"
                            disabled={index >= dossiers.length - 1} onClick={() => { setIndex(index + 1); setRejetOuvert(false); }}>
                        Suivant <i className="bi bi-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <div className="dm-card p-4">
                <div className="d-flex flex-wrap align-items-start gap-3 mb-4">
                    <div className="flex-grow-1">
                        <h2 className="h5 mb-1">{dossier.nom}</h2>
                        <div className="text-body-secondary small">
                            <span className="font-monospace">{dossier.telephone}</span>
                            {dossier.email ? ` · ${dossier.email}` : ''}
                            {dossier.inscritLe ? ` · inscrit le ${dateFr.format(new Date(dossier.inscritLe))}` : ''}
                        </div>
                        <div className="d-flex flex-wrap gap-2 mt-2">
                            {dossier.specialites.map((specialite) => (
                                <span key={specialite} className="dm-badge dm-badge--secondary">{specialite}</span>
                            ))}
                            <span className="dm-badge dm-badge--primary">Rayon {dossier.rayonKm} km</span>
                        </div>
                    </div>

                    <a href={lien(urlFiche)} className="btn btn-sm btn-outline-secondary">
                        Voir la fiche complète
                    </a>
                </div>

                <div className="dm-pieces">
                    <Piece titre="Pièce d'identité — recto" url={dossier.documents.recto} onZoom={setZoom} />
                    <Piece titre="Pièce d'identité — verso" url={dossier.documents.verso} onZoom={setZoom} />
                    <Piece titre="Selfie" url={dossier.documents.selfie} onZoom={setZoom} />
                </div>

                <hr className="my-4" />

                <div className="d-flex flex-wrap gap-2">
                    <form method="POST" action={lien(urlApprouver)}>
                        <input type="hidden" name="_token" value={jeton} />
                        <button type="submit" className="btn btn-success">
                            <i className="bi bi-check-lg me-1" aria-hidden="true"></i>Approuver le dossier
                        </button>
                    </form>

                    <button type="button" className="btn btn-outline-danger"
                            onClick={() => setRejetOuvert(!rejetOuvert)}
                            aria-expanded={rejetOuvert}>
                        <i className="bi bi-x-lg me-1" aria-hidden="true"></i>Rejeter…
                    </button>
                </div>

                {rejetOuvert && (
                    <form method="POST" action={lien(urlRejeter)} className="mt-3">
                        <input type="hidden" name="_token" value={jeton} />

                        <label htmlFor="motif-rejet" className="form-label">
                            Motif du rejet — il sera transmis au technicien
                        </label>
                        <textarea id="motif-rejet" name="motif" className="form-control" rows="3"
                                  minLength={10} maxLength={500} required
                                  value={motif} onChange={(e) => setMotif(e.target.value)}
                                  placeholder="Exemple : la date d'expiration de la pièce d'identité est masquée sur le recto."></textarea>

                        <div className="d-flex align-items-center gap-2 mt-2">
                            <button type="submit" className="btn btn-danger" disabled={motif.trim().length < 10}>
                                Confirmer le rejet
                            </button>
                            <span className="text-body-secondary small">
                                Le technicien pourra corriger et représenter son dossier.
                            </span>
                        </div>
                    </form>
                )}
            </div>

            {zoom && (
                <div className="dm-zoom" role="dialog" aria-modal="true" aria-label={zoom.titre}
                     onClick={() => setZoom(null)}>
                    <button type="button" className="dm-zoom__fermer" aria-label="Fermer">
                        <i className="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                    <img src={zoom.url} alt={zoom.titre} />
                </div>
            )}
        </div>
    );
}
