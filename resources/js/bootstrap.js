import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true; // IMPORTANT for Sanctum SPA authentication with cookies/sessions

// Ensure that Laravel's CSRF token from the meta tag is picked up for POST/PUT/DELETE
// This helps with XSRF-TOKEN header and CSRF token cookie exchange.
let token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
} else {
    console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

// Initialize Laravel Echo with Reverb
window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.PROD_VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.PROD_VITE_REVERB_HOST,
    wsPort: import.meta.env.PROD_VITE_REVERB_PORT,
    wssPort: import.meta.env.PROD_VITE_REVERB_PORT,
    forceTLS: (import.meta.env.PROD_VITE_REVERB_SCHEME === 'https'),
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
});
