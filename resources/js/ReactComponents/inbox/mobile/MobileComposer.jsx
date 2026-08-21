/**
 * The reply composer, as a screen rather than a panel.
 *
 * Design source: Redesign/inbox-mobile/Inbox Mobile.dc.html — the `onBuilderScreen` block.
 *
 * The mock draws its own composer: a project/template/subject card, a stack of blocks and
 * a send button. Ours wraps the REAL one. ReplyBox is where the rules live — template
 * versus custom versus blocks and who may use which, the mandatory template preview, the
 * AI draft states, the reply parent that In-Reply-To will point at, and the deliberate
 * absence of Cc, Bcc and an editable To. Redrawing that for a phone would mean maintaining
 * two answers to "may this person send this", and the phone's copy would be the one nobody
 * remembered to update.
 *
 * So this file contributes exactly two things the mock is right about and the desktop
 * panel is not: it takes the whole screen, and it covers the tab bar while you are writing.
 */

import { Icon } from '../../ds';
import { ReplyBox } from '../ReplyBox';
import { PushHeader, PushScreen } from './Sheet';

export function MobileComposer({
    open,
    thread,
    recipients,
    editing,
    replyTo,
    compose,
    busy,
    onSend,
    onClose,
    onRegenerateDraft,
    onError,
}) {
    if (!thread) return null;

    const title = editing ? 'Edit the draft' : replyTo?.mode === 'forward' ? 'Forward' : 'Reply';

    const subtitle = editing
        ? 'Approving this sends it to the client'
        : replyTo?.author
          ? `Answering ${replyTo.author}${replyTo.when ? ` — ${replyTo.when}` : ''}`
          : `${thread.who} · ${thread.project?.short || thread.project?.name || 'Lead'}`;

    return (
        <PushScreen
            open={open}
            onClose={onClose}
            padded
            header={
                <PushHeader
                    onBack={onClose}
                    backIcon="Close"
                    title={title}
                    subtitle={subtitle}
                    actions={
                        thread.reply?.needs_reply ? (
                            <span
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 4,
                                    flex: 'none',
                                    padding: '0 8px',
                                    font: '400 11px/16px Figtree, sans-serif',
                                    color: 'var(--secondary-text-color)',
                                }}
                            >
                                <Icon name="DueDate" size={12} color="currentColor" />
                                Stops the clock
                            </span>
                        ) : null
                    }
                />
            }
        >
            {/*
              ReplyBox brings its own submit/discard row, and that is the point — the
              enabled state of that button is the send gate, and it is computed in one
              place from the template/preview/recipient rules. A phone-specific send button
              in the header would have to re-derive it.
            */}
            <ReplyBox
                thread={thread}
                recipients={recipients}
                editing={editing}
                replyTo={replyTo}
                compose={compose}
                aiDraft={thread.ai?.draft}
                aiEnabled={thread.ai?.enabled}
                aiDrafting={thread.ai?.drafting}
                aiDraftFailed={thread.ai?.draft_failed}
                aiDraftStalled={thread.ai?.draft_stalled}
                busy={busy}
                onSend={onSend}
                onDiscard={onClose}
                onRegenerateDraft={onRegenerateDraft}
                onError={onError}
            />

            <div style={{ height: 'calc(16px + var(--om-safe-bottom, 0px))' }} />
        </PushScreen>
    );
}
