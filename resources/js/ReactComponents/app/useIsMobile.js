/**
 * Is this a phone-shaped viewport?
 *
 * One media query, one source of truth. The redesigned pages are built from inline styles
 * rather than a stylesheet with breakpoints (see the design-system note), so "narrow"
 * cannot be expressed in CSS where the components live — a page that needs a different
 * LAYOUT, not just different padding, has to know the answer in JavaScript.
 *
 * 860px, not 768: the inbox's desktop layout is a 240px rail plus a thread pane, and the
 * pane stops being usable well before a tablet's portrait width. Erring wide means a small
 * tablet gets the phone layout, which works; erring narrow would give a phone the rail.
 *
 * Coarse pointer is deliberately NOT part of the test. A touchscreen laptop is not a
 * phone, and the layout question here is about width.
 */

import { useEffect, useState } from 'react';

export const MOBILE_MAX_WIDTH = 860;

const QUERY = `(max-width: ${MOBILE_MAX_WIDTH}px)`;

function matches() {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return false;
    return window.matchMedia(QUERY).matches;
}

export function useIsMobile() {
    const [isMobile, setIsMobile] = useState(matches);

    useEffect(() => {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return undefined;

        const mql = window.matchMedia(QUERY);
        const onChange = (event) => setIsMobile(event.matches);

        // addListener is the pre-2021 Safari spelling; still worth carrying for iOS 13.
        if (mql.addEventListener) mql.addEventListener('change', onChange);
        else mql.addListener(onChange);

        // Re-read once on mount: the first render may have happened before layout settled,
        // and a stale `false` here means a phone renders the desktop rail.
        setIsMobile(mql.matches);

        return () => {
            if (mql.removeEventListener) mql.removeEventListener('change', onChange);
            else mql.removeListener(onChange);
        };
    }, []);

    return isMobile;
}
