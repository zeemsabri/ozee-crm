// Boots the new React 19 / Inertia app. Split out of app.js so it is only
// downloaded when the current page is actually a React page — see app.js.
//
// Convention: React pages live in resources/js/ReactPages and are rendered
// server-side as Inertia::render('React/SomePage', [...]). The 'React/'
// prefix is what app.js uses to decide whether to boot Vue or React, and
// what we strip here to find the matching file under ReactPages/.
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./ReactPages/${name.replace(/^React\//, '')}.jsx`,
            import.meta.glob('./ReactPages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#1a73e8',
    },
});
