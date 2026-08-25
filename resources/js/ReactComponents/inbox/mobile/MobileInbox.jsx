/**
 * The inbox on a phone.
 *
 * Design source: Redesign/inbox-mobile/Inbox Mobile.dc.html
 *
 * Layout only. Every rule — permissions, endpoints, polling, approvals — comes from
 * useInboxPage, the same hook DesktopInbox.jsx renders from, so the two can differ in
 * shape and cannot differ in behaviour. See ReactPages/Inbox/Index.jsx.
 *
 * Where this deliberately departs from the mock, and why:
 *
 *  - The tab bar keeps the mock's five slots, but the mock's five destinations cover three
 *    of the eight views the desktop rail lists. The missing ones include the manager's
 *    approval queue, so all eight live in the filter sheet's Status section with their
 *    server-side counts. Nothing is unreachable.
 *  - "Portal" is a link out to /portal, the signed-in client portal, not a screen. The mock
 *    draws a staff-side "what each client can see" list; no such page exists, and inventing
 *    one from the inbox's data would show a client-facing state we do not actually hold.
 *  - There is no Cc or Bcc, on the phone or anywhere else. The mock does not draw one and
 *    that is correct — see the note in ReplyBox.jsx.
 *  - The mock's role switcher ("Viewing as a contractor") is a design device for showing
 *    two states in one file. Real permissions come from the session.
 */

import { useMemo, useState } from 'react';

import '../../../../css/ozee-ds/index.css';
import '../../../../css/ozee-ds/inbox-mobile.css';

import { url } from '../../app/navigation';
import { InboxDialogs } from '../InboxDialogs';
import { VIEW_DEFS, plural } from '../format';
import { MobileShell } from './MobileShell';
import { MobileThreadList } from './MobileThreadList';
import { MobileThreadView } from './MobileThreadView';
import { MobileComposer } from './MobileComposer';
import { MobileFilterSheet } from './MobileFilterSheet';

/**
 * Which tab a set of filters corresponds to, or null.
 *
 * Null is a real answer and worth keeping: someone who has jumped to "Waiting approval"
 * from the filter sheet is in none of the four tabs, and lighting one up anyway would say
 * they are somewhere they are not.
 */
function activeTab(filters) {
    if (filters.view === 'sent') return 'sent';
    if (filters.view === 'needsReply') return filters.overdue_only ? 'alerts' : 'inbox';
    return null;
}

export function MobileInbox({ page }) {
    const { inbox, thread, settings } = page;

    // Purely presentational, so it stays here rather than in the shared controller — the
    // desktop has no filter sheet to open.
    const [filtersOpen, setFiltersOpen] = useState(false);

    const tab = activeTab(inbox.filters);
    const threadOpen = !!thread.thread || thread.loading;

    const title = useMemo(() => {
        if (tab === 'alerts') return 'Alerts';
        if (tab === 'sent') return 'Sent';
        if (tab === 'inbox') return 'Inbox';
        return VIEW_DEFS.find((v) => v.key === inbox.filters.view)?.label || 'Inbox';
    }, [tab, inbox.filters.view]);

    /**
     * The chips under the search box.
     *
     * Only refinements that are not otherwise visible on screen. The view is in the list
     * heading and the search term is in the box it was typed into, so neither earns a chip
     * — a row of chips restating what is already legible is how a header stops being read.
     */
    const activeFilters = useMemo(() => {
        const chips = [];

        if (inbox.filters.project_id && inbox.filters.project_id !== 'all') {
            const label = (inbox.options.projects || []).find(
                (p) => String(p.value) === String(inbox.filters.project_id)
            )?.label;
            chips.push({
                key: 'project',
                label: label || 'Project',
                onRemove: () => inbox.setFilters({ project_id: 'all' }),
            });
        }

        (inbox.filters.category_ids || []).forEach((id) => {
            const category = (inbox.options.categories || []).find((c) => Number(c.id) === Number(id));
            chips.push({
                key: `category-${id}`,
                label: category?.name || 'Category',
                onRemove: () =>
                    inbox.setFilters({
                        category_ids: inbox.filters.category_ids.filter((c) => Number(c) !== Number(id)),
                    }),
            });
        });

        if (inbox.filters.unread_only) {
            chips.push({
                key: 'unread',
                label: 'Unread only',
                onRemove: () => inbox.setFilters({ unread_only: false }),
            });
        }

        // Not shown while the Alerts tab is the thing that switched it on — the tab is
        // already lit, and a chip offering to remove it would look like a second control.
        if (inbox.filters.overdue_only && tab !== 'alerts') {
            chips.push({
                key: 'overdue',
                label: 'Past the reply rule',
                onRemove: () => inbox.setFilters({ overdue_only: false }),
            });
        }

        if (inbox.filters.from || inbox.filters.to) {
            chips.push({
                key: 'dates',
                label: `${inbox.filters.from || 'Any'} → ${inbox.filters.to || 'Any'}`,
                onRemove: () => inbox.setFilters({ from: '', to: '' }),
            });
        }

        return chips;
    }, [inbox.filters, inbox.options, tab]);

    const goToTab = (key) => {
        thread.close();
        page.clearSelection();

        if (key === 'alerts') {
            // Same jump the breach banner makes, and for the same reason it names the sort
            // explicitly: longest-waiting first is the point of the screen.
            page.showOverdue();
            return;
        }

        if (key === 'sent') {
            inbox.setFilters({ view: 'sent', overdue_only: false, sort: 'date' });
            return;
        }

        inbox.setFilters({ view: 'needsReply', overdue_only: false, sort: 'breach' });
    };

    const tabs = [
        {
            key: 'inbox',
            label: 'Inbox',
            icon: 'Inbox',
            active: tab === 'inbox',
            count: inbox.counts?.needsReply || 0,
            onClick: () => goToTab('inbox'),
        },
        {
            key: 'alerts',
            label: 'Alerts',
            icon: 'Alert',
            active: tab === 'alerts',
            count: inbox.overdueCount || 0,
            countTone: 'negative',
            onClick: () => goToTab('alerts'),
        },
        {
            key: 'sent',
            label: 'Sent',
            icon: 'Send',
            active: tab === 'sent',
            onClick: () => goToTab('sent'),
        },
        {
            key: 'portal',
            label: 'Portal',
            icon: 'Globe',
            // Leaves React for a Laravel page, so a plain href and a full reload.
            href: url('portal.projects.index', '/portal'),
        },
    ];

    return (
        <MobileShell
            title={title}
            showChrome={!threadOpen}
            classicUrl={settings.classic_url}
            search={{
                value: inbox.filters.search,
                placeholder: 'Search mail, clients, projects',
                onChange: (e) => inbox.setFilters({ search: e.target.value }),
                onClear: () => inbox.setFilters({ search: '' }),
            }}
            onOpenFilters={() => setFiltersOpen(true)}
            filterCount={activeFilters.length}
            activeFilters={activeFilters}
            breach={
                !threadOpen && inbox.overdueCount > 0 && !inbox.filters.overdue_only
                    ? {
                          text: `${plural(inbox.overdueCount, 'client thread')} ${
                              inbox.overdueCount === 1 ? 'has' : 'have'
                          } passed the reply rule`,
                          onClick: page.showOverdue,
                      }
                    : null
            }
            tabs={tabs}
            canCompose={page.canCompose}
            onCompose={() => page.setComposeOpen(true)}
            toasts={page.toasts}
            onDismissToast={page.dismissToast}
        >
            {threadOpen ? (
                <MobileThreadView
                    // Stepping with the chevrons does not unmount this — `thread.thread`
                    // holds the previous thread while the next loads — so without a key
                    // the expanded-message set carries across every thread visited, and
                    // "newest message open" quietly stops applying.
                    key={thread.thread?.id}
                    thread={thread.thread}
                    loading={thread.loading}
                    recipients={page.recipients}
                    settings={settings}
                    noteOpen={page.noteOpen}
                    noteText={page.noteText}
                    summarising={page.summarising}
                    refreshing={page.refreshing}
                    onBack={page.closeThread}
                    onStep={page.stepThread}
                    onRefresh={page.refreshThread}
                    onSummarise={page.summariseThread}
                    onOpenReply={page.openReply}
                    onReplyToMessage={page.replyToMessage}
                    onOpenNote={() => page.setNoteOpen(true)}
                    onNoteText={page.setNoteText}
                    onSaveNote={page.saveNote}
                    onCancelNote={page.cancelNote}
                    onApprove={page.approveAndSend}
                    onEditApprove={page.openEditApprove}
                    onReject={() => page.setRejectFor(thread.thread?.approval?.email_id)}
                    onResendAi={() => page.resendToAi(thread.thread)}
                    onDelete={page.askDeleteThread}
                    onMore={page.onMore}
                    onTogglePrivacy={page.togglePrivacy}
                    onDeleteMessage={page.askDeleteMessage}
                    onCreateTask={(suggestion) => page.setTaskFor(suggestion || {})}
                    onOpenClientView={page.openClientView}
                />
            ) : (
                <MobileThreadList
                    threads={inbox.threads}
                    meta={inbox.meta}
                    loading={inbox.loading}
                    filters={inbox.filters}
                    counts={inbox.counts}
                    overdueCount={inbox.overdueCount}
                    slaMinutes={page.slaMinutes}
                    selectedIds={page.selectedIds}
                    isManager={page.isManager}
                    onOpen={page.openThread}
                    onReply={async (id) => {
                        await page.openThread(id);
                        page.openReply();
                    }}
                    onRelease={page.releaseThread}
                    onResendAi={page.resendToAi}
                    onToggleRead={page.toggleRead}
                    onToggleSelect={page.toggleSelect}
                    onClearSelection={page.clearSelection}
                    onBulk={page.runBulk}
                    onPage={inbox.setPage}
                    onRefresh={inbox.refresh}
                />
            )}

            <MobileComposer
                /*
                 * The lock wins over `replyOpen`, as it does on the desktop.
                 *
                 * `can.reply` on a list row does not cover every lock: a contractor on a
                 * thread carrying a private message passes it, so Reply is offered, the
                 * composer opened, the reply written — and InboxReplyController::store
                 * then 403s it. The server holds either way; this is about not taking
                 * somebody's typing somewhere it can never be sent from.
                 */
                open={page.replyOpen && !!thread.thread && !thread.thread.reply_lock}
                thread={thread.thread}
                recipients={page.recipients}
                editing={page.replyEditing}
                replyTo={page.replyTarget}
                compose={page.compose}
                busy={page.replyBusy}
                onSend={page.sendReply}
                onClose={page.closeReply}
                onRegenerateDraft={page.regenerateDraft}
                onError={page.warn}
            />

            <MobileFilterSheet
                open={filtersOpen}
                onClose={() => setFiltersOpen(false)}
                filters={inbox.filters}
                counts={inbox.counts}
                overdueCount={inbox.overdueCount}
                options={inbox.options}
                hasFilters={inbox.hasFilters}
                resultCount={inbox.meta?.total}
                onView={(view) => {
                    thread.close();
                    page.clearSelection();
                    inbox.setView(view);
                }}
                onChange={(patch) => {
                    page.clearSelection();
                    inbox.setFilters(patch);
                }}
                onClear={inbox.clearFilters}
            />

            {/* Full screen on a phone: a 580px dialog centred in a 390px viewport is a
                dialog with 16px of margin and a scrollbar. */}
            <InboxDialogs page={page} fullScreen />
        </MobileShell>
    );
}
