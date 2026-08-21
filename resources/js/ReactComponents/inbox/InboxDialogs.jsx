/**
 * The four overlays the inbox can open, in one place so the desktop and the phone cannot
 * end up offering different ones.
 *
 * Send back, create task and categorise are in modals.jsx; the composer is
 * ComposeModal.jsx. Everything here is driven by useInboxPage — this file holds no rules
 * of its own, only the wiring and the one piece of layout that differs between the two:
 * on a phone a 580px-wide centred dialog is worse than a full screen, so `fullScreen`
 * flattens them against the viewport.
 */

import { CategoriseModal, RejectModal, TaskModal } from './modals';
import { ComposeModal } from './ComposeModal';
import { ClientViewDialog } from './ClientViewDialog';

/**
 * Passed straight through to Modal's `style`, which is merged last — so this needs no
 * change to the design-system component. `100dvh` rather than `100vh`: on iOS Safari the
 * latter is the height WITHOUT the browser chrome, so a full-height dialog put its footer
 * under the address bar.
 */
export const FULL_SCREEN_MODAL = {
    top: 0,
    left: 0,
    transform: 'none',
    width: '100vw',
    maxWidth: '100vw',
    height: '100dvh',
    maxHeight: '100dvh',
    borderRadius: 0,
};

export function InboxDialogs({ page, fullScreen = false }) {
    const modalStyle = fullScreen ? FULL_SCREEN_MODAL : undefined;

    return (
        <>
            <RejectModal
                open={!!page.rejectFor}
                busy={page.busy}
                style={modalStyle}
                dense={fullScreen}
                onClose={() => page.setRejectFor(null)}
                onConfirm={page.rejectDraft}
            />

            <TaskModal
                open={!!page.taskFor}
                busy={page.busy}
                suggestion={page.taskFor}
                users={page.users}
                style={modalStyle}
                dense={fullScreen}
                onClose={() => page.setTaskFor(null)}
                onCreate={page.createTask}
            />

            <CategoriseModal
                open={page.categoriseOpen}
                busy={page.busy}
                count={page.bulkCount}
                categories={page.inbox.options.categories || []}
                style={modalStyle}
                dense={fullScreen}
                onClose={() => page.setCategoriseOpen(false)}
                onApply={page.applyCategories}
            />

            {/*
              Mounted only while open. Resetting state in an effect instead painted one
              frame of the PREVIOUS email — recipients, template and the rendered preview
              iframe — before blanking, which flashes another client's correspondence.
            */}
            {page.composeOpen ? (
                <ComposeModal
                    open
                    compose={page.compose}
                    projects={page.inbox.options.compose_projects}
                    classicUrl={page.settings.classic_url}
                    style={modalStyle}
                    dense={fullScreen}
                    onError={page.warn}
                    onCreated={(message) => {
                        page.notify(message);
                        // The new draft belongs in the Drafts view; refresh so the badge
                        // and the list agree with what was just created.
                        page.inbox.refresh();
                    }}
                    onClose={() => page.setComposeOpen(false)}
                />
            ) : null}

            <ClientViewDialog
                open={!!page.clientViewFor}
                message={page.clientViewFor}
                fullScreen={fullScreen}
                style={modalStyle}
                classicUrl={page.settings.classic_url}
                onClose={page.closeClientView}
            />
        </>
    );
}
