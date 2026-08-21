/**
 * The redesigned inbox.
 *
 * Route:  GET /inbox/beta  →  App\Http\Controllers\InboxBetaController  →  'React/Inbox/Index'
 * Design: Redesign/Multi-proposal milestone submission page/Inbox.dc.html  (desktop)
 *         Redesign/inbox-mobile/Inbox Mobile.dc.html                       (phone)
 *
 * Runs ALONGSIDE the original Vue inbox at /inbox, which is unchanged and stays the
 * default. Both read the same tables and write the same read markers, so someone can move
 * between them mid-session; the link at the top right goes back. Because only one of Vue
 * and React boots per page load, that link is a plain <a>, never an Inertia <Link>.
 *
 * This file is a dispatcher and nothing else. One controller hook runs whatever the
 * viewport, and only the arrangement changes:
 *
 *   useInboxPage  — every rule: permissions, endpoints, polling, approvals, bulk actions.
 *   DesktopInbox  — app shell, filter rail, list or thread side by side.
 *   MobileInbox   — the phone: bottom tabs, push navigation, sheets.
 *
 * Both layouts are mounted from the same hook on purpose. The phone design was drawn
 * without the approval queue, screening, template fields or the permission gates, and the
 * only reliable defence against shipping that gap — now or on the next change — is that
 * neither layout is allowed to answer those questions for itself.
 *
 * The switch is live, not a one-time read: rotating a tablet or resizing a window moves
 * between the two without a reload. Both trees are mounted through the same hook, so state
 * survives the swap.
 */

import { useIsMobile } from '../../ReactComponents/app/useIsMobile';
import { useInboxPage } from '../../ReactComponents/inbox/useInboxPage';
import { DesktopInbox } from '../../ReactComponents/inbox/DesktopInbox';
import { MobileInbox } from '../../ReactComponents/inbox/mobile/MobileInbox';

export default function InboxIndex({ settings, initialThreadId }) {
    const isMobile = useIsMobile();
    const page = useInboxPage({ settings, initialThreadId });

    return isMobile ? <MobileInbox page={page} /> : <DesktopInbox page={page} />;
}
