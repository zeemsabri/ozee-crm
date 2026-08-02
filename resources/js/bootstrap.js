import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true; // IMPORTANT for Sanctum SPA authentication with cookies/sessions

// Axios will automatically send the X-XSRF-TOKEN header using the value of the XSRF-TOKEN cookie.
// We do not need to manually set the X-CSRF-TOKEN header from the meta tag, as doing so uses a static token
// that becomes stale when sessions/tokens are regenerated dynamically (e.g. on failed logins or bypass).

// Initialize Laravel Echo with Reverb
window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    disableStats: true,
    enabledTransports: ['ws'],
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.head.querySelector('meta[name="csrf-token"]')?.content,
        },
        withCredentials: true,
    },
});
