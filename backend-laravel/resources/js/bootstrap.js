import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const jeton = document.querySelector('meta[name="csrf-token"]');
if (jeton) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = jeton.content;
}

// Connexion temps réel au serveur Reverb : positions des techniciens, changements
// de statut des tickets, messages de chat. Utilisée par les îlots React du
// back-office (carte live, KPI, file de validation).
window.Pusher = Pusher;

// Sans clé Reverb (hébergement sans WebSocket, comme Vercel), on ne crée PAS
// le client : il lèverait une exception au chargement et casserait toute la
// page. Les îlots testent `window.Echo` et basculent sur l'interrogation
// périodique, avec le bandeau « Mode dégradé ».
const cleReverb = import.meta.env.VITE_REVERB_APP_KEY;

window.Echo = cleReverb
    ? new Echo({
        broadcaster: 'reverb',
        key: cleReverb,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    })
    : null;
