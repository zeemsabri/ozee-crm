/**
 * The app's primary navigation, as data.
 *
 * The Vue app never had this — every nav item is hand-written twice, in
 * Components/Layout/TopNavigation.vue and again in MobileNavigation.vue, and the two
 * lists have already drifted apart. React pages read this file instead, so desktop rail,
 * overflow menu and any future mobile drawer can never disagree.
 *
 * `permission` is a slug checked with usePermissions(); items without one are visible to
 * every signed-in user. `match` decides which rail item lights up: the first entry whose
 * prefix the current path starts with wins, so order matters for nested paths.
 */

/**
 * Resolve a Ziggy route name to a URL, falling back to a literal path.
 *
 * Ziggy is injected globally by the @routes Blade directive, but a route name can be
 * removed server-side without anyone touching this file. Rather than let that throw and
 * blank the whole shell, an unknown name degrades to `fallback`.
 */
export function url(name, fallback) {
    try {
        if (typeof window !== 'undefined' && typeof window.route === 'function') {
            return window.route(name);
        }
    } catch {
        /* Unknown route name — fall through. */
    }
    return fallback;
}

/**
 * Sign out, and actually leave the page.
 *
 * NOT `router.post('/logout')`, which is what this used to be and why signing out of a
 * React page appeared to do nothing until you refreshed.
 *
 * `AuthenticatedSessionController::destroy` answers 302 → `/`, which renders the Vue
 * `Welcome` component. Inertia follows the redirect inside the SPA and asks the React
 * runtime to resolve `Welcome` — there is no `ReactPages/Welcome.jsx`, the resolve
 * rejects, and because Inertia resolves the component BEFORE it touches history, nothing
 * moves: no navigation, no error, the signed-out inbox just sits there until a refresh
 * re-runs the app.js dispatcher and boots Vue. This is the cross-framework rule in
 * resources/js/app.js, hit by a redirect rather than by a link.
 *
 * So: a plain axios POST (which carries the XSRF cookie header like every other request
 * in the app — see bootstrap.js, and note the deliberate decision there NOT to rely on a
 * static csrf-token meta tag), then a real navigation. `Api\Portal\PortalController`
 * solves the same problem from the other end with `Inertia::location`; that is not used
 * here because the legacy Vue layouts post to this same route and clear their own local
 * state in Inertia's `@success` callback, which a 409 location visit never fires.
 *
 * Local state is cleared BEFORE the request, not after: the user has already decided to
 * leave, and a callback racing a page teardown is how keys get left behind.
 */
export async function signOut() {
    ['authToken', 'userRole', 'userId', 'userEmail', 'remembered'].forEach((key) => {
        try {
            localStorage.removeItem(key);
        } catch {
            /* Private mode, or storage disabled. Signing out still has to work. */
        }
    });

    try {
        if (window.axios?.defaults?.headers?.common) {
            delete window.axios.defaults.headers.common.Authorization;
        }
    } catch {
        /* ignore */
    }

    try {
        await window.axios.post(url('logout', '/logout'));
    } catch {
        // A 419 means the session had already expired, which is the state we were trying
        // to reach anyway. Either way the next line is what the person asked for.
    } finally {
        window.location.assign('/');
    }
}

/** The 64px icon rail down the left edge. Icons are Vibe glyph names. */
export const RAIL_ITEMS = [
    { key: 'work', icon: 'Home', label: 'My work', route: 'workspace.index', href: '/workspace', match: ['/workspace'] },
    { key: 'projects', icon: 'Board', label: 'Projects', route: 'projects.index', href: '/projects', match: ['/projects'] },
    { key: 'tasks', icon: 'CheckList', label: 'Tasks', route: 'dashboard', href: '/dashboard', match: ['/dashboard', '/tasks'] },
    { key: 'inbox', icon: 'Email', label: 'Inbox', route: 'inbox', href: '/inbox', match: ['/inbox'], permission: 'view_emails' },
    { key: 'reports', icon: 'Chart', label: 'Reports', route: 'admin.productivity.index', href: '/admin/productivity', match: ['/admin/productivity', '/admin/reports'], permission: 'manage_projects' },
    { key: 'team', icon: 'Team', label: 'Team', route: 'users.page', href: '/users', match: ['/users', '/leaderboard'], permission: 'create_users' },
    { key: 'admin', icon: 'Settings', label: 'Admin', route: 'admin.roles.index', href: '/admin/roles', match: ['/admin'], permission: 'view_admin_dropdown' },
];

/** Items in the avatar dropdown. `danger` renders in the negative tone. */
export const USER_MENU_ITEMS = [
    { value: 'attendance', label: 'Attendance', icon: 'Calendar', route: 'attendance.index', href: '/attendance' },
    { value: 'profile', label: 'Profile', icon: 'Person', route: 'profile.edit', href: '/profile' },
    { divider: true },
    { value: 'logout', label: 'Log out', icon: 'Item', destructive: true },
];

/** Footer link columns. Kept short on purpose — this is a chrome footer, not a sitemap. */
export const FOOTER_LINKS = [
    { label: 'Dashboard', route: 'dashboard', href: '/dashboard' },
    { label: 'My workspace', route: 'workspace.index', href: '/workspace' },
    { label: 'Leaderboard', route: 'leaderboard.index', href: '/leaderboard' },
];

/**
 * Which rail item is current, given a pathname.
 * Longest matching prefix wins so /admin/productivity picks Reports over Admin.
 */
export function activeRailKey(pathname = '/') {
    let best = null;
    let bestLength = -1;

    RAIL_ITEMS.forEach((item) => {
        (item.match || []).forEach((prefix) => {
            if (pathname === prefix || pathname.startsWith(`${prefix}/`)) {
                if (prefix.length > bestLength) {
                    best = item.key;
                    bestLength = prefix.length;
                }
            }
        });
    });

    return best;
}
