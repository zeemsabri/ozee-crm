import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createPinia } from 'pinia';
import { registerPermissionDirective, fetchGlobalPermissions, usePermissionStore } from './Directives/permissions';
import NotificationContainer from '@/Components/NotificationContainer.vue'; // Correctly imported
import { setNotificationContainer, success, error } from '@/Utils/notification'; // Import notification utilities

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

        // Initialize the main Inertia app
        const mountedApp = app.mount(el);
        console.log('Main Inertia app mounted.');

        // Create a separate Vue app instance for the NotificationContainer
        // and mount it directly to the body. This ensures it's outside
        // the main Inertia app's DOM hierarchy and can have a higher z-index.
        const notificationAppInstance = createApp({
            render: () => h(NotificationContainer)
        });
        const notificationMountPoint = document.createElement('div');
        notificationMountPoint.id = 'notification-mount-point';
        document.body.appendChild(notificationMountPoint);
        notificationAppInstance.mount(notificationMountPoint);
        console.log('NotificationContainer app instance mounted to body.');

        // It's crucial that setNotificationContainer is called *after* NotificationContainer is mounted.
        // The NotificationContainer's onMounted hook should handle calling setNotificationContainer.
        // We can add a simple test notification here to see if the system is ready.
        setTimeout(() => {
            console.log('Attempting to show test notification...');
            success('Test notification: System is ready!');
            // error('Test error notification: Something went wrong!');
        }, 1000); // Give it a moment to ensure everything is mounted

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
