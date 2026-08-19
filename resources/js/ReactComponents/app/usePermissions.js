/**
 * Permission checks for React pages — the React half of the Vue Pinia store in
 * resources/js/Directives/permissions.js. Same endpoints, same semantics, so a page
 * gated one way behaves identically ported to the other.
 *
 * Semantics worth knowing before you use it:
 * - Super admins (auth.user.role_data.slug === 'super-admin') pass every check without
 *   a request ever being made.
 * - Global permissions come from GET /api/user/permissions.
 * - Project permissions come from GET /api/projects/{id}/permissions and REPLACE the
 *   global set rather than merging with it — that is the server's behaviour, not a
 *   simplification here. A user with a project-specific role is judged only on that
 *   role's permissions for that project.
 * - Until the fetch resolves, every check returns false. Gate *rendering* on this, never
 *   authorisation: the server re-checks on every endpoint.
 *
 * The cache is module-level rather than React state so several components on a page
 * share one in-flight request instead of each firing their own.
 */

import axios from 'axios';
import { useCallback, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';

const cache = new Map(); // key -> { permissions: [...], role, project_role }
const inflight = new Map(); // key -> Promise
const subscribers = new Set();

const keyFor = (projectId) => (projectId == null ? 'global' : `project:${projectId}`);

function urlFor(projectId) {
    return projectId == null ? '/api/user/permissions' : `/api/projects/${projectId}/permissions`;
}

function notify() {
    subscribers.forEach((fn) => fn());
}

function load(projectId) {
    const key = keyFor(projectId);
    if (cache.has(key)) return Promise.resolve(cache.get(key));
    if (inflight.has(key)) return inflight.get(key);

    const request = axios
        .get(urlFor(projectId))
        .then(({ data }) => {
            cache.set(key, data || { permissions: [] });
            return cache.get(key);
        })
        .catch(() => {
            // A failed fetch caches an empty set rather than retrying on every render.
            // Everything stays hidden, which is the safe direction to fail in.
            cache.set(key, { permissions: [] });
            return cache.get(key);
        })
        .finally(() => {
            inflight.delete(key);
            notify();
        });

    inflight.set(key, request);
    return request;
}

/** Drop the cache — call after anything that could change the user's role. */
export function invalidatePermissions() {
    cache.clear();
    inflight.clear();
    notify();
}

/**
 * @param {number|string|null} projectId scope the checks to one project
 * @returns {{ can: (slugs: string|string[], opts?: {operator?: 'and'|'or'}) => boolean,
 *            ready: boolean, isSuperAdmin: boolean, role: object|null }}
 */
export function usePermissions(projectId = null) {
    const page = usePage();
    const user = page?.props?.auth?.user || null;
    const isSuperAdmin = user?.role_data?.slug === 'super-admin';

    const key = keyFor(projectId);
    const [, forceRender] = useState(0);

    useEffect(() => {
        const bump = () => forceRender((n) => n + 1);
        subscribers.add(bump);
        return () => subscribers.delete(bump);
    }, []);

    useEffect(() => {
        if (isSuperAdmin) return;
        load(projectId);
    }, [projectId, isSuperAdmin]);

    const entry = cache.get(key);
    const ready = isSuperAdmin || !!entry;

    const can = useCallback(
        (slugs, { operator = 'and' } = {}) => {
            if (isSuperAdmin) return true;
            if (!slugs) return true;

            const wanted = Array.isArray(slugs) ? slugs : [slugs];
            if (!wanted.length) return true;

            const held = new Set((entry?.permissions || []).map((p) => p.slug));
            return operator === 'or'
                ? wanted.some((s) => held.has(s))
                : wanted.every((s) => held.has(s));
        },
        [entry, isSuperAdmin]
    );

    return {
        can,
        ready,
        isSuperAdmin,
        role: entry?.project_role || entry?.role || user?.role_data || null,
    };
}
