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
