import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createPinia } from 'pinia';
import { registerPermissionDirective, fetchGlobalPermissions, usePermissionStore } from './Directives/permissions';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        // Create and use Pinia store
        const pinia = createPinia();
        app.use(pinia);

        // Use Inertia plugin and Ziggy
        app.use(plugin);
        app.use(ZiggyVue);

        // Register the permission directive
        registerPermissionDirective(app);

        // Register the v-click-outside directive
        app.directive('click-outside', {
            mounted(el, binding) {
                el.__ClickOutsideHandler__ = (event) => {
                    // Check if the clicked element is outside the directive's element
                    if (!(el === event.target || el.contains(event.target))) {
                        binding.value(event);
                    }
                };
                document.addEventListener('click', el.__ClickOutsideHandler__);
            },
            unmounted(el) {
                // Remove the event listener when the element is unmounted
                document.removeEventListener('click', el.__ClickOutsideHandler__);
            },
        });

        // Initialize the app
        const mountedApp = app.mount(el);

        // Fetch global permissions immediately after app initialization
        // This ensures permissions are loaded as soon as the user logs in
        if (props.initialPage.props.auth && props.initialPage.props.auth.user) {
            fetchGlobalPermissions().catch(error => {
                console.error('Failed to fetch global permissions:', error);
            });
        }

        return mountedApp;
    },
    progress: {
        color: '#4B5563',
    },
});
