/**
 * The reply composer inside a thread.
 *
 * Design source: Inbox.dc.html, the sc-if="replyOpen" block, plus EmailBlocks.dc.html for
 * the block builder.
 *
 * Two jobs, one component:
 *
 *  - NEW REPLY (default). ONE request: POST /api/inbox/threads/{id}/reply. That creates
 *    the email as a draft, which is the trigger the automation workflow watches: it runs
 *    the AI approval analysis and then either sends the email or parks it at
 *    pending_approval for a human. Nobody — manager or not — sends directly from here.
 *
 *    This used to be two requests, the second being edit-and-approve, with managers
 *    seeing "Send now". That skipped the AI review the classic inbox puts every outgoing
 *    email through, which made the redesign the one way to get unreviewed mail to a
 *    client. The button now says "Submit for review" for everyone, because that is what
 *    it does.
 *
 *  - EDITING AN EXISTING DRAFT (`editing` prop set, from "Edit & approve"). This is the
 *    other end of the same pipeline: the AI has already refused something and a human with
 *    approval rights is deciding. One request to edit-and-approve, against the email that
 *    is already there. It must NOT create a second email — the original would stay pending
 *    and approving both later would send the client the same thing twice.
 *
 * Three ways to write a reply: a template, free prose, or the block builder. All three
 * submit identically; they differ only in what ends up in the row. See
 * InboxReplyController.
 *
 * The AI draft is a starting point that is always editable, and nothing generated is ever
 * sent without someone pressing the button.
 */

import { useEffect, useMemo, useState } from 'react';
import { Button, ButtonGroup, Chips, Icon, TextArea, TextField } from '../ds';
import { BlockBuilder } from './BlockBuilder';
import { TemplateFields } from './TemplateFields';
import { useBlocks } from './useBlocks';
import { emptyValueFor, inputPlaceholders } from './useTemplates';

/*
 * No "Reply all".
 *
 * Recipients are the project's clients whichever you pick, so "Reply" and "Reply all"
 * resolved to the same list and the choice was decoration. Forward is the only mode that
 * changes who receives it, and it only appears for someone who may type an address.
 */
const MODES = [{ value: 'reply', text: 'Reply' }];
const FORWARD_MODE = { value: 'forward', text: 'Forward' };

export function ReplyBox({
    thread,
    recipients,
    aiDraft,
    aiEnabled,
    busy,
    editing,
    replyTo,
    // Template composing. `compose` carries the gates and the template data source; see
    // useTemplates.js and InboxAccess::canComposeCustom for what decides them.
    compose,
    onSend,
    onDiscard,
    onRegenerateDraft,
    onError,
}) {
    const isEditing = !!editing;

    const [mode, setMode] = useState(replyTo?.mode || 'reply');
    const [subject, setSubject] = useState(editing?.subject || recipients?.subject || '');
    const [body, setBody] = useState(editing?.body || '');
    const [usedAi, setUsedAi] = useState(false);
    const [forwardTo, setForwardTo] = useState('');

    /*
     * Whether this person may type an address at all.
     *
     * Client mail goes from our mailbox to the project's clients and nowhere else — that
     * is what stops a client corresponding with an individual staff member directly, and
     * only a handful of people have access to the mailbox. So there is no Cc field, no Bcc
     * field, and no editable To. The server resolves recipients from the project and
     * discards anything posted, so this only decides what is worth drawing.
     */
    const canAddressManually = recipients?.can_address_manually ?? false;
    const modeOptions = canAddressManually ? [...MODES, FORWARD_MODE] : MODES;

    /*
     * Template vs custom.
     *
     * Custom is admin-only — the same rule the legacy inbox applies to its "Custom Email"
     * button, except the server now enforces it too. Someone who can only use templates
     * never sees the switch, and the box opens straight into the template picker.
     */
    const canTemplate = compose?.canTemplate ?? false;
    const canCustom = compose?.canCustom ?? false;
    const [kind, setKind] = useState(canCustom ? 'custom' : 'template');
    const [templateId, setTemplateId] = useState(null);
    const [templateData, setTemplateData] = useState({});
    // Which template the currently-shown subject came from. Gates sending — see canSend.
    const [previewedTemplateId, setPreviewedTemplateId] = useState(null);

    const isTemplateKind = kind === 'template';
    const isBlocksKind = kind === 'blocks';

    /*
     * The block builder is gated as free-form, not as template.
     *
     * Its content is whatever the author typed — prose, links, screenshots — with no
     * template constraining it, so offering it on the template permission would hand
     * every non-admin the free-form composer through a different door. The server applies
     * the same rule; this only decides whether the tab is drawn.
     */
    const blocks = useBlocks({ projectId: thread?.project?.id ?? null, onError });

    const KIND_OPTIONS = useMemo(
        () =>
            [
                canTemplate ? { value: 'template', text: 'Template' } : null,
                canCustom ? { value: 'custom', text: 'Custom message' } : null,
                canCustom ? { value: 'blocks', text: 'Project update' } : null,
            ].filter(Boolean),
        [canTemplate, canCustom]
    );

    // Seed the placeholder keys when a template is picked, so every field is controlled
    // from the first render rather than flipping from uncontrolled on first keystroke.
    const selectTemplate = (id) => {
        setTemplateId(id);
        const next = compose?.templates?.find((t) => t.id === id);
        const seeded = {};
        inputPlaceholders(next).forEach((p) => {
            seeded[p.name] = emptyValueFor(p);
        });
        setTemplateData(seeded);
        setPreviewedTemplateId(null);
        compose?.preview?.reset?.();
    };

    // The AI draft answers the thread's NEWEST inbound message and nothing else
    // (ThreadPresenter generates it from $latestInbound). Seeding it into a reply aimed at
    // an older message would put an answer to question B under a header saying "Answering
    // question A", and send it In-Reply-To A — so it is only offered when the target is
    // that same newest message, or when there is no explicit target at all.
    const draftMatchesTarget =
        !replyTo?.emailId || replyTo.emailId === recipients?.last_inbound_email_id;

    // Load the AI draft once, and only into an untouched box — refetching must never
    // overwrite something the person has started typing. Never when editing an existing
    // draft: that box is showing someone's actual words.
    useEffect(() => {
        if (!isEditing && draftMatchesTarget && aiDraft && !body.trim()) {
            setBody(aiDraft);
            setUsedAi(true);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [aiDraft, isEditing, draftMatchesTarget]);

    // Follow the message the person clicked Reply on.
    //
    // Keyed on `token`, not on emailId+mode: clicking Reply again on the message you are
    // already answering produces identical primitives, so those deps would skip the effect
    // and leave the composer in whatever mode you had switched it to. Pressing Reply is
    // the natural "start over" gesture and has to actually reset it — otherwise you press
    // Reply and silently send a Forward.
    useEffect(() => {
        if (!isEditing && replyTo?.mode) setMode(replyTo.mode);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [replyTo?.token, isEditing]);

    // Seed from whichever source applies. Editing wins — the draft's own subject is the
    // one being approved, not a generated "Re: …".
    useEffect(() => {
        if (isEditing) {
            setSubject(editing.subject || '');
            setBody(editing.body || '');
            return;
        }
        if (recipients?.subject) setSubject(recipients.subject);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isEditing, editing?.emailId, recipients?.subject]);

    // An existing draft already has its recipients; the reply modes do not apply to it.
    const to = isEditing
        ? editing.to || recipients?.reply || []
        : mode === 'forward'
          ? forwardTo.split(',').map((s) => s.trim()).filter(Boolean)
          : recipients?.[mode] || recipients?.reply || [];

    // When editing, the draft's recipients are already on the record server-side, so an
    // empty display list must not block approving it.
    /*
     * A templated reply has no body of its own — the text is the template — and its
     * subject is produced by the preview, exactly as the legacy composer does it. So the
     * gate is "a template is chosen and it has been previewed at least once", which is
     * also what guarantees a subject exists (the server requires one).
     */
    /*
     * A templated reply must be previewed before it can be sent.
     *
     * Not busywork: the subject that actually reaches the client is the TEMPLATE's,
     * re-rendered server-side at send time. The subject field is otherwise pre-seeded with
     * the thread's "Re: …", so without this you would approve a reply reading
     * "Re: Website update" and the client would receive "Your monthly report". Pressing
     * Preview is what replaces that field with the real one.
     */
    const previewed = !isTemplateKind || previewedTemplateId === templateId;

    const canSend = (() => {
        if (busy) return false;
        if (isTemplateKind) return !!templateId && previewed && !!subject.trim();
        if (!isEditing && isBlocksKind) {
            return to.length > 0 && !!subject.trim() && blocks.meaningfulCount > 0 && !blocks.uploading;
        }
        return (isEditing || to.length > 0) && !!subject.trim() && !!body.trim();
    })();

    // Editing an existing draft always posts as the thing it already is; the builder and
    // the template picker are not offered in that mode.
    const compositionType = isEditing ? 'custom' : isTemplateKind ? 'template' : isBlocksKind ? 'blocks' : 'custom';

    const payload = () => ({
        emailId: editing?.emailId,
        mode,
        subject: subject.trim(),
        body: isTemplateKind || isBlocksKind ? null : body.trim(),
        // Only the blocks the renderer would actually emit something for — an empty row
        // someone added and never filled in is dropped here rather than posted and
        // silently discarded server-side.
        blocks: isBlocksKind && !isEditing ? blocks.payload() : undefined,
        // Only forwarding sends addresses. For reply and reply-all the server resolves
        // the recipients from the conversation and ignores anything posted — the chips
        // above may be masked labels rather than real addresses, and a thread reply must
        // not be redirectable from the client. See InboxReplyController::resolveRecipients.
        composition_type: compositionType,
        template_id: isTemplateKind ? templateId : null,
        template_data: isTemplateKind ? templateData : null,
        // Only forwarding sends addresses, and only from someone permitted to type one.
        // For a reply the server resolves the project's clients and ignores this.
        to: mode === 'forward' ? to : undefined,
        // The message this answers, and so what In-Reply-To/References will point at.
        // The message the person clicked Reply on wins; the bottom composer falls back to
        // the newest inbound one. The server re-checks that it belongs to this thread and
        // applies the same fallback if it is null.
        in_reply_to_email_id: replyTo?.emailId ?? recipients?.last_inbound_email_id ?? null,
    });

    return (
        <div
            style={{
                border: '1px solid var(--primary-color)',
                borderRadius: 8,
                background: 'var(--primary-background-color)',
                overflow: 'hidden',
                animation: 'dcRise 200ms cubic-bezier(0,0,.35,1) both',
            }}
        >
            <div
                style={{
                    padding: '10px 14px',
                    borderBottom: '1px solid var(--om-hairline)',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 8,
                    flexWrap: 'wrap',
                }}
            >
                {isEditing ? (
                    <span style={{ font: '600 12px/16px Figtree, sans-serif', color: 'var(--primary-color)' }}>
                        Editing the draft before it goes out
                    </span>
                ) : replyTo?.author ? (
                    <span
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            font: '400 12px/16px Figtree, sans-serif',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        <Icon name="Reply" size={14} color="currentColor" />
                        <span>
                            Answering{' '}
                            <strong style={{ color: 'var(--primary-text-color)' }}>{replyTo.author}</strong>
                            {replyTo.when ? ` — ${replyTo.when}` : ''}
                        </span>
                        {modeOptions.length > 1 ? (
                            <ButtonGroup
                                options={modeOptions}
                                value={mode}
                                onChange={setMode}
                                size="small"
                                style={{ marginInlineStart: 8 }}
                            />
                        ) : null}
                    </span>
                ) : (
                    modeOptions.length > 1 ? (
                        <ButtonGroup options={modeOptions} value={mode} onChange={setMode} size="small" />
                    ) : null
                )}
                {thread.reply?.needs_reply ? (
                    <span
                        style={{
                            marginInlineStart: 'auto',
                            font: '400 12px/16px Figtree, sans-serif',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        Sending stops the reply clock
                    </span>
                ) : null}
            </div>

            <div style={{ padding: '12px 14px', display: 'flex', flexDirection: 'column', gap: 10 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                    <span style={{ width: 32, font: '600 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                        To
                    </span>
                    {isEditing && !to.length ? (
                        <span style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                            Already addressed on the draft
                        </span>
                    ) : mode === 'forward' && canAddressManually && !isEditing ? (
                        <div style={{ flex: 1, minWidth: 220 }}>
                            <TextField
                                size="small"
                                placeholder="Comma-separated addresses to forward to"
                                value={forwardTo}
                                onChange={(e) => setForwardTo(e.target.value)}
                            />
                        </div>
                    ) : to.length ? (
                        to.map((address) => (
                            <Chips
                                key={address}
                                label={address}
                                color="primary"
                                size="small"
                                readOnly
                                title={
                                    recipients?.masked
                                        ? 'Partly hidden because you cannot view client contact details — the reply still reaches them.'
                                        : address
                                }
                            />
                        ))
                    ) : (
                        <span style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--negative-color)' }}>
                            {recipients?.reason || 'No client on this thread to reply to.'}
                        </span>
                    )}
                </div>

                {/*
                  No Cc / Bcc, and no editable To.

                  There was an "Add Cc / Bcc" button here with two text fields behind it.
                  They did nothing: `emails` has no cc or bcc column, so the endpoint wrote
                  the addresses into a team note and sent to `to` only — a control that
                  looks like it copies somebody and does not.

                  They are not coming back as free text. Client mail goes from our mailbox
                  to the project's clients and nowhere else; that is what keeps a client
                  from corresponding with an individual staff member directly, and only a
                  handful of people have Gmail access at all. When a real Cc arrives it will
                  be a picker over the project's clients, not a box you type into.
                */}
                {!isEditing && to.length > 1 ? (
                    <span
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            font: '400 12px/16px Figtree, sans-serif',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        <Icon name="Team" size={14} color="currentColor" />
                        <span>
                            {to.length} clients on this project — each receives their own copy.
                        </span>
                    </span>
                ) : null}

                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <span style={{ width: 32, font: '600 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                        Subj
                    </span>
                    <div style={{ flex: 1 }}>
                        <TextField
                            size="small"
                            value={subject}
                            readOnly={isTemplateKind && !isEditing}
                            placeholder={
                                isTemplateKind && !isEditing
                                    ? 'Set by the template — press Preview to fill it in'
                                    : isBlocksKind
                                      ? 'What this update is about'
                                      : undefined
                            }
                            onChange={(e) => setSubject(e.target.value)}
                        />
                    </div>
                </div>

                {/*
                  Template vs custom. Hidden entirely when only one is available — someone
                  who cannot compose free-form should not be shown a disabled control
                  advertising a mode they will never get.
                */}
                {!isEditing && KIND_OPTIONS.length === 0 ? (
                    <span
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            font: '400 12px/16px Figtree, sans-serif',
                            color: 'var(--negative-color)',
                        }}
                    >
                        <Icon name="Security" size={14} color="currentColor" />
                        <span>
                            You do not have permission to compose emails. Ask an admin for the
                            &ldquo;compose_emails&rdquo; permission.
                        </span>
                    </span>
                ) : null}

                {!isEditing && KIND_OPTIONS.length > 1 ? (
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                        <ButtonGroup options={KIND_OPTIONS} value={kind} onChange={setKind} size="small" />
                    </div>
                ) : null}

                {!isEditing && KIND_OPTIONS.length === 1 && isTemplateKind ? (
                    <span
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            font: '400 12px/16px Figtree, sans-serif',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        <Icon name="Security" size={14} color="currentColor" />
                        <span>Free-form replies are admin-only — build yours from a template.</span>
                    </span>
                ) : null}

                {!isEditing && isTemplateKind && canTemplate ? (
                    <TemplateFields
                        templates={compose?.templates || []}
                        templateOptions={compose?.templateOptions || []}
                        templateId={templateId}
                        templateData={templateData}
                        sourceData={compose?.sourceData || {}}
                        loadingTemplates={compose?.loading}
                        onTemplate={{ select: selectTemplate, loadSourceData: compose?.loadSourceData }}
                        onData={setTemplateData}
                        preview={compose?.preview || { canPreview: false, html: '', loading: false }}
                        onRefreshPreview={async () => {
                            const result = await compose?.preview?.refresh(templateId, templateData);
                            // The subject is a product of the template, never typed — the
                            // legacy composer sets it from this same response.
                            if (result?.subject) {
                                setSubject(result.subject);
                                setPreviewedTemplateId(templateId);
                            }
                        }}
                    />
                ) : null}

                {!isEditing && isBlocksKind ? (
                    <BlockBuilder
                        blocks={blocks}
                        disabled={busy}
                        /*
                          No greeting on a reply. handleCustomClientEmail prepends one for
                          a NEW email; InboxReplyController deliberately does not, because
                          a reply lands inside a running conversation. Showing one here
                          would preview something the client never receives.
                        */
                        greeting={null}
                        signOff={compose?.signOff}
                    />
                ) : null}

                {aiEnabled && !isEditing && !isTemplateKind && !isBlocksKind && draftMatchesTarget ? (
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            padding: '6px 10px',
                            borderRadius: 4,
                            background: 'var(--primary-highlighted-color)',
                            flexWrap: 'wrap',
                        }}
                    >
                        <Icon name="Wand" size={14} color="var(--primary-color)" />
                        <span style={{ font: '600 12px/16px Figtree, sans-serif', color: 'var(--primary-color)' }}>
                            {usedAi ? 'AI draft — edit before it goes out' : 'Your own words'}
                        </span>
                        <span style={{ marginInlineStart: 'auto', display: 'flex', gap: 4 }}>
                            <Button kind="tertiary" size="small" onClick={onRegenerateDraft}>
                                {usedAi ? 'Try another' : 'Draft for me'}
                            </Button>
                            {usedAi ? (
                                <Button
                                    kind="tertiary"
                                    size="small"
                                    onClick={() => {
                                        setBody('');
                                        setUsedAi(false);
                                    }}
                                >
                                    Write my own
                                </Button>
                            ) : null}
                        </span>
                    </div>
                ) : null}

                {(!isTemplateKind && !isBlocksKind) || isEditing ? (
                    <TextArea
                        rows={8}
                        placeholder="Write your reply"
                        value={body}
                        onChange={(e) => {
                            setBody(e.target.value);
                            if (usedAi && e.target.value !== aiDraft) setUsedAi(false);
                        }}
                    />
                ) : null}
            </div>

            <div
                style={{
                    padding: '10px 14px',
                    borderTop: '1px solid var(--om-hairline)',
                    background: 'var(--allgrey-background-color)',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 8,
                    flexWrap: 'wrap',
                }}
            >
                {/*
                  One button, one meaning. "Send now" used to appear here for managers and
                  it was a lie by omission — it created the email and immediately approved
                  it, walking past the AI review. Submitting is now the only thing this
                  does, for everyone; whether the email goes straight out or waits for a
                  human is the automation's call, not this button's.

                  There is deliberately no "Save draft" beside it either. In this schema
                  `draft` is the status the workflow picks up, so a Save-draft button would
                  be a button labelled "save" that mails a client. See
                  InboxReplyController.
                */}
                <Button
                    size="small"
                    disabled={!canSend}
                    loading={busy}
                    leftIcon={<Icon name="Send" size={16} />}
                    onClick={() => onSend(payload())}
                >
                    {isEditing ? 'Approve & send' : 'Submit for review'}
                </Button>
                <Button kind="tertiary" size="small" color="negative" disabled={busy} onClick={onDiscard}>
                    Discard
                </Button>
                <span
                    style={{
                        marginInlineStart: 'auto',
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 6,
                        font: '400 12px/16px Figtree, sans-serif',
                        color: 'var(--secondary-text-color)',
                    }}
                >
                    <Icon name="Note" size={14} color="currentColor" />
                    {/*
                      Worth saying out loud, because the box only shows what you typed:
                      the earlier messages are attached on the way out, not stored on the
                      reply. That is what keeps the thread out of the AI checker's prompt.
                    */}
                    <span>
                        {isEditing
                            ? 'Approving this sends it to the client now'
                            : isTemplateKind
                              ? 'Checked by AI, then sent — the template is rendered on the way out'
                              : isBlocksKind
                                ? 'Checked by AI, then sent — images are embedded in the message itself'
                                : 'Checked by AI, then sent — earlier messages are quoted automatically'}
                    </span>
                </span>
            </div>
        </div>
    );
}
