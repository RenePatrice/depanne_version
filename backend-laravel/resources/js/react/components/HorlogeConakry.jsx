import { useEffect, useState } from 'react';

const formateur = new Intl.DateTimeFormat('fr-FR', {
    timeZone: 'Africa/Conakry',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
});

/**
 * Premier îlot React du projet : il sert de référence de montage pour les
 * zones temps réel du back-office (carte live, KPI, file de validation).
 */
export default function HorlogeConakry() {
    const [heure, setHeure] = useState(() => formateur.format(new Date()));

    useEffect(() => {
        const id = setInterval(() => setHeure(formateur.format(new Date())), 1000);

        return () => clearInterval(id);
    }, []);

    return (
        <div className="text-end">
            <div className="text-body-secondary small">Heure de Conakry</div>
            <div className="fs-5 fw-semibold font-monospace">{heure}</div>
        </div>
    );
}
