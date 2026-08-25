/**
 * The inbox as it looks on a desktop: app shell, 240px filter rail, list or thread.
 *
 * Design source: Redesign/Multi-proposal milestone submission page/Inbox.dc.html
 *
 * Pure layout. Every rule this renders — who may approve, what a reply posts, which
 * endpoint sends — lives in useInboxPage.js and is shared with the phone layout in
 * mobile/MobileInbox.jsx. If something here needs a new behaviour, it goes in the hook,
 * not in this file, or the two layouts start disagreeing.
 */

import { AppShell } from '../app/AppShell';
import { AlertBanner, Button } from '../ds';

import { FilterRail } from './FilterRail';
import { ThreadList } from './ThreadList';
import { DeletedList } from './DeletedList';
import { SavedList } from './SavedList';
import { ThreadView } from './ThreadView';
import { InboxDialogs } from './InboxDialogs';
import { plural } from './format';

const BACK_LABELS = {
    needsReply: 'Back to needs reply',
    new: 'Back to new mail',
    withAi: 'Back to the AI queue',
    approval: 'Back to approvals',
    received: 'Back to received',
    sent: 'Back to sent',
    drafts: 'Back to in review',
    all: 'Back to all mail',
    saved: 'Back to saved',
};

export function DesktopInbox({ page }) {
    const { inbox, thread, settings } = page;

    const railBadges = inbox.overdueCount ? { inbox: String(inbox.overdueCount) } : undefined;

    const headerExtra = (
        <>
            {/* Plain anchor: crossing back to the Vue page needs a full reload. */}
            <a href={settings.classic_url} style={{ textDecoration: 'none' }}>
                <Button kind="tertiary" size="small">
                    Classic inbox
                </Button>
            </a>
        </>
    );

    return (
        <AppShell
            title="Inbox"
            activeKey="inbox"
            railBadges={railBadges}
            headerExtra={headerExtra}
            toasts={page.toasts}
            onDismissToast={page.dismissToast}
            showFooter={false}
            headerSearch={{
                value: inbox.filters.search,
                placeholder: 'Search mail, projects, clients',
                onChange: (e) => inbox.setFilters({ search: e.target.value }),
                onClear: () => inbox.setFilters({ search: '' }),
            }}
        >
            {inbox.overdueCount > 0 && !inbox.filters.overdue_only ? (
                <div style={{ flex: 'none', animation: 'dcFade 150ms cubic-bezier(0,0,.35,1) both' }}>
                    <AlertBanner type="negative" action={{ text: 'Show them', onClick: page.showOverdue }}>
                        {plural(inbox.overdueCount, 'client thread')}{' '}
                        {inbox.overdueCount === 1 ? 'has' : 'have'} passed the reply rule
                    </AlertBanner>
                </div>
            ) : null}

            <div style={{ flex: 1, display: 'flex', minHeight: 0 }}>
                <FilterRail
                    canSeeDeleted={page.canSeeDeleted}
                    canComposeCustom={page.canComposeCustom}
                    filters={inbox.filters}
                    // The Saved badge comes from the saved list itself, not the thread
                    // counts endpoint — one source, and it is already loaded.
                    counts={{ ...inbox.counts, saved: page.saved.drafts.length }}
                    overdueCount={inbox.overdueCount}
                    options={inbox.options}
                    hasFilters={inbox.hasFilters}
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

                <main style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', minHeight: 0 }}>
                    {/*
                      Deleted is a standalone view — it lists EMAILS, not threads, so the
                      thread list cannot render it (see VIEW_DEFS and
                      InboxDeletedController). Checked before the thread pane so that
                      switching to it while a thread is open shows the bin rather than the
                      thread that was already there.
                    */}
                    {inbox.filters.view === 'saved' ? (
                        <SavedList
                            saved={page.saved}
                            projects={inbox.options.compose_projects}
                            currentUserName={page.compose?.signOff?.name}
                            onResume={page.resumeSaved}
                        />
                    ) : inbox.filters.view === 'deleted' ? (
                        <DeletedList
                            onError={page.warn}
                            onRestored={() => {
                                page.notify('Restored. It is back in its thread.');
                                inbox.refresh();
                            }}
                            onOpenThread={(id) => thread.open(id)}
                        />
                    ) : thread.thread || thread.loading ? (
                        <ThreadView
                            thread={thread.thread}
                            loading={thread.loading}
                            recipients={page.recipients}
                            replyOpen={page.replyOpen}
                            replyBusy={page.replyBusy}
                            noteOpen={page.noteOpen}
                            noteText={page.noteText}
                            // Prefer the per-thread answer from /recipients; fall back to
                            // the page settings until it has loaded, so the Forward button
                            // never flashes in for someone who may not use it.
                            canAddressManually={
                                page.recipients?.can_address_manually ??
                                settings?.can_address_manually ??
                                false
                            }
                            backLabel={BACK_LABELS[inbox.filters.view] || 'Back'}
                            onBack={page.closeThread}
                            onRefresh={page.refreshThread}
                            refreshing={page.refreshing}
                            onSummarise={page.summariseThread}
                            summarising={page.summarising}
                            onOpenReply={page.openReply}
                            onReplyToMessage={page.replyToMessage}
                            replyTarget={page.replyTarget}
                            replyEditing={page.replyEditing}
                            compose={page.compose}
                            onCloseReply={page.closeReply}
                            onSendReply={page.sendReply}
                            onError={page.warn}
                            onRegenerateDraft={page.regenerateDraft}
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
                        <ThreadList
                            threads={inbox.threads}
                            meta={inbox.meta}
                            loading={inbox.loading}
                            filters={inbox.filters}
                            overdueCount={inbox.overdueCount}
                            slaMinutes={page.slaMinutes}
                            selectedIds={page.selectedIds}
                            isManager={page.isManager}
                            onToggleSelect={page.toggleSelect}
                            onSelectAll={page.selectAll}
                            onSort={(sort) => inbox.setFilters({ sort })}
                            onOpen={page.openThread}
                            onReply={async (id) => {
                                await page.openThread(id);
                                page.openReply();
                            }}
                            onRelease={page.releaseThread}
                            onResendAi={page.resendToAi}
                            onBulk={page.runBulk}
                            canCompose={page.canCompose}
                            onCompose={() => page.setComposeOpen(true)}
                            onPage={inbox.setPage}
                        />
                    )}
                </main>
            </div>

            <InboxDialogs page={page} />
        </AppShell>
    );
}
