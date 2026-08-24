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
    resolve: (name) => {
        /*
         * A Vue page arriving inside the React runtime.
         *
         * Only one framework is mounted per page load (see app.js), so any Inertia
         * navigation that lands on a non-React component is unresolvable here. Without
         * this branch that failure is SILENT and looks like a frozen page: @inertiajs/core
         * resolves the component BEFORE it pushes history, so a rejected resolve leaves
         * the URL, the history entry and the mounted page exactly as they were, with no
         * error on screen. That is what made signing out of /inbox/beta look like nothing
         * had happened until you refreshed.
         *
         * Crossing frameworks is supposed to be a plain <a>, or Inertia::location from the
         * server — but a REDIRECT can cross the boundary without any link being involved,
         * which is not something a code review of the links can catch. So: hand it to the
         * browser. Reloading the current URL re-runs the app.js dispatcher against
         * whatever the server now says this session may see, which for the case that
         * actually happens — the session ended underneath us — lands on the login page.
         *
         * The intended destination is not recoverable at this point (resolve is given the
         * component name and nothing else), so this fixes the frozen page rather than
         * completing the navigation. A cross-framework link still needs to be an <a>.
         */
        if (!name.startsWith('React/')) {
            window.location.reload();

            // Never settles, so Inertia does not continue into swapComponent with a
            // component it cannot render while the reload is in flight.
            return new Promise(() => {});
        }

        return resolvePageComponent(
            `./ReactPages/${name.replace(/^React\//, '')}.jsx`,
            import.meta.glob('./ReactPages/**/*.jsx'),
        );
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#1a73e8',
    },
});
