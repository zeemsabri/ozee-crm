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
import { Button, ButtonGroup, Chips, Icon, TextArea, TextField, Toggle } from '../ds';
import { BlockBuilder } from './BlockBuilder';
import { GreetingPicker, SignOffPreview, greetingTextFor } from './Salutation';
import { TemplateFields } from './TemplateFields';
import { useBlocks } from './useBlocks';
import { emptyValueFor, inputPlaceholders } from './useTemplates';

/*
 * No "Reply all" — one Reply, and a recipient picker.
 *
 * "Reply" and "Reply all" both resolved to every client on the project, so the choice was
 * decoration. The answer is not to revive the distinction but to remove the guess: the To
 * row is a set of chips, pre-ticked with whoever wrote the message being answered, and
 * everyone else on the project is one click away. That covers what both buttons meant and
 * the cases neither did.
 *
 * Forward is still its own mode — it is the only one that reaches an address that is not
 * on the thread, and it only appears for someone holding `email_custom_recipients`.
 */
const MODES = [{ value: 'reply', text: 'Reply' }];
const FORWARD_MODE = { value: 'forward', text: 'Forward' };

export function ReplyBox({
    thread,
    recipients,
    aiDraft,
    aiEnabled,
    // Where a "Draft for me" request has got to, straight from the server. The composer
    // does not track this itself: the job outlives any local state, and a page refresh
    // mid-request has to pick the truth back up.
    aiDrafting,
    aiDraftFailed,
    aiDraftStalled,
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
     * button, except the server now enforces it too. "Project update" is NOT part of that
     * rule any more: it carries canComposeBlocks, which anyone who may compose clears, so
     * a template-only user sees Template and Project update and opens on the former.
     */
    const canTemplate = compose?.canTemplate ?? false;
    const canCustom = compose?.canCustom ?? false;
    const canBlocks = compose?.canBlocks ?? canCustom;
    const [kind, setKind] = useState(canCustom ? 'custom' : 'template');

    /*
     * Send-as-private.
     *
     * The flag already exists and already has a control — "Make private" next to a sent
     * message — but only AFTER the fact, which means every private message is visible to
     * the whole project team for however long it takes someone to remember. Setting it
     * here writes it at creation instead. `delete_emails`, the same permission as that
     * toggle; see InboxAccess::canMarkPrivate.
     *
     * Not offered when editing an existing draft: that row already exists, and the
     * approve endpoint does not carry the flag — the per-message toggle is the control
     * for one of those.
     */
    const canMarkPrivate = (compose?.canMarkPrivate ?? false) && !isEditing;
    // Marking private is `delete_emails`; READING a private message is
    // `view_private_emails`. Someone can hold the first without the second, and the reply
    // endpoint refuses every later reply on a thread holding a message they cannot read —
    // so they would lock themselves out of their own thread. Warned, not blocked: it is a
    // legitimate thing for a manager to do on someone else's behalf.
    const canSeePrivate = compose?.canSeePrivate ?? false;
    const [isPrivate, setIsPrivate] = useState(false);

    /*
     * The greeting.
     *
     * A reply used to have none at all — InboxReplyController stored the body verbatim —
     * so a custom reply opened with whatever you typed, while a new email opened with a
     * greeting the server added invisibly. Two composers, two rules, neither on screen.
     * The endpoint now takes `greeting_name` and this picks it.
     *
     * Default is "No greeting", NOT the new-email default, and deliberately so: replies
     * have never carried one, an AI draft writes its own opening, and mid-thread a fresh
     * "Hi Sarah," on every message reads like a form letter. Choosing one is a decision,
     * not the default.
     */
    const [greetingMode, setGreetingMode] = useState('none');
    const [greetingName, setGreetingName] = useState('');

    /*
     * Who this reply goes to.
     *
     * It used to go to every client on the project, always, with no say in it — answering
     * one person's question in front of four others. `candidates` is the people on this
     * thread and this project; `suggested_keys` is whoever wrote the message being
     * answered. Both come from the recipients endpoint; see Correspondent::selectableFor.
     *
     * KEYS travel, never addresses. The server resolves them against the same candidate
     * list, so a reply can be narrowed or widened among the people on the thread and still
     * cannot be redirected to somebody who is not.
     *
     * null means "not initialised yet" — distinct from [], which is a person having
     * unticked everyone and is refused rather than quietly widened back to all.
     */
    const candidates = recipients?.candidates || [];
    const [recipientKeys, setRecipientKeys] = useState(null);

    // The ticked rows. Declared here rather than beside `to` below because the greeting,
    // which is computed further up, names exactly these people.
    const chosen = candidates.filter((c) => (recipientKeys || []).includes(c.key));
    const [templateId, setTemplateId] = useState(null);
    const [templateData, setTemplateData] = useState({});
    // Which template the currently-shown subject came from. Gates sending — see canSend.
    const [previewedTemplateId, setPreviewedTemplateId] = useState(null);

    const isTemplateKind = kind === 'template';
    const isBlocksKind = kind === 'blocks';

    /*
     * Only the custom composer greets. A template renders its own opening, and on the
     * block builder a greeting has to BE a block or the send-time re-render drops it (see
     * BlockComposition::renderForSend) — the server applies exactly the same rule.
     * Editing an existing draft is showing someone's actual words, so nothing is prepended
     * to those either.
     */
    const canGreet = !isTemplateKind && !isBlocksKind && !isEditing;

    // Display names for the thread's clients, from the recipients endpoint. Names, never
    // addresses — see Correspondent::namesFor.
    const greeting = canGreet
        ? greetingTextFor({
              mode: greetingMode,
              customName: greetingName,
              // The people actually ticked, not everyone on the thread — otherwise a reply
              // narrowed to one person would still open "Hi Sarah & Tom,".
              names: chosen.length
                  ? chosen.map((c) => c.name)
                  : recipients?.recipient_names || [],
          })
        : '';

    /*
     * The block builder has its own gate, and anyone who may compose clears it.
     *
     * It was gated as free-form on the reasoning that its content is whatever the author
     * typed. It is not the same door: it emits a fixed set of typed blocks — text,
     * bullets, a link, an image — that BlockRenderer turns into our own markup, with no
     * HTML passthrough and no free-text recipient. The server applies the same rule; this
     * only decides whether the tab is drawn. See InboxAccess::canComposeBlocks.
     */
    const blocks = useBlocks({ projectId: thread?.project?.id ?? null, onError });

    const KIND_OPTIONS = useMemo(
        () =>
            [
                canTemplate ? { value: 'template', text: 'Template' } : null,
                canCustom ? { value: 'custom', text: 'Custom message' } : null,
                canBlocks ? { value: 'blocks', text: 'Project update' } : null,
            ].filter(Boolean),
        [canTemplate, canCustom, canBlocks]
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

    /**
     * Why "Draft for me" is unavailable right now, or null when it is available.
     *
     * Each of these used to hide the control outright. Naming the reason costs one line of
     * text and saves someone concluding the feature is broken — which is exactly what
     * happened.
     */
    const draftBlockedReason = (() => {
        if (isTemplateKind) return 'Drafts are for free-form replies, not templates';
        if (isBlocksKind) return 'Drafts are for free-form replies, not block updates';
        if (!recipients?.last_inbound_email_id) return 'Nothing from the client to answer yet';
        if (!draftMatchesTarget) return 'Drafts answer the client\u2019s latest message';
        return null;
    })();

    // Load the AI draft once, and only into an untouched box — refetching must never
    // overwrite something the person has started typing. Never when editing an existing
    // draft: that box is showing someone's actual words.
    useEffect(() => {
        if (!isEditing && draftMatchesTarget && aiDraft && !body.trim()) {
            setBody(aiDraft);
            setUsedAi(true);
            // InboxAiService::draftReply is prompted to open with "Hi <first name>,", so a
            // picked greeting on top of it would send two. Forced back to none rather than
            // disabled: the person can still choose one after clearing the draft.
            setGreetingMode('none');
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

    /*
     * Reset the selection to the suggestion whenever the message being answered changes.
     *
     * Deliberately NOT sticky per thread. Carrying a selection forward would mean somebody
     * added once for one reason stays on every later reply with nothing on screen saying
     * why — and the person who added them is not necessarily the one sending next. Every
     * reply starts from who wrote the message you clicked Reply on.
     *
     * Keyed on the target message id so an ordinary re-render cannot undo a change made
     * by hand.
     */
    const replyTargetId = replyTo?.emailId ?? recipients?.last_inbound_email_id ?? null;
    const suggestedKeys = recipients?.suggested_keys;

    useEffect(() => {
        setRecipientKeys(suggestedKeys ? [...suggestedKeys] : null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [replyTargetId, (suggestedKeys || []).join(',')]);

    /*
     * An existing draft already has its recipients on the record server-side, and forward
     * types its own. Otherwise this is the selection.
     *
     * Falls back to the flat `reply` list when the endpoint sent no candidates — an older
     * payload, or a thread shape selectableFor found nobody on — so the box degrades to
     * what it did before rather than looking empty.
     */
    const to = isEditing
        ? editing.to || recipients?.reply || []
        : mode === 'forward'
          ? forwardTo.split(',').map((s) => s.trim()).filter(Boolean)
          : candidates.length
            ? chosen.map((c) => c.address)
            : recipients?.reply || [];

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
        // Every branch needs somebody to send to. Only worth stating now that the
        // recipients are a choice — before this they could not be empty, so the template
        // branch never checked and would have offered Send on a reply the server refuses.
        if (!isEditing && to.length === 0) return false;
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
        /*
         * The chosen recipients, as keys. Only for a reply — forward addresses are typed,
         * and editing a draft does not re-address it. Omitted entirely when the endpoint
         * offered no candidates, which is what makes the server fall back to its old
         * everyone-on-the-thread behaviour instead of refusing an empty selection.
         */
        recipient_keys:
            mode === 'forward' || isEditing || !candidates.length ? undefined : recipientKeys || [],
        // The message this answers, and so what In-Reply-To/References will point at.
        // The message the person clicked Reply on wins; the bottom composer falls back to
        // the newest inbound one. The server re-checks that it belongs to this thread and
        // applies the same fallback if it is null.
        in_reply_to_email_id: replyTo?.emailId ?? recipients?.last_inbound_email_id ?? null,
        // Only sent when it is on AND allowed, so a stale toggle can never post a flag the
        // server would 403 on. The server re-checks the permission regardless.
        is_private: canMarkPrivate && isPrivate ? true : undefined,
        // '' is meaningful — it is "no greeting", which is the default here — so this is
        // sent rather than omitted. The server treats empty as none and prepends nothing.
        greeting_name: canGreet ? greeting : '',
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
                    ) : candidates.length ? (
                        /*
                          A picker, not a display.

                          Every candidate is drawn; the ticked ones are solid, the rest are
                          outlined and one click away. Showing the untick-able people
                          alongside the unselected ones is the whole point — the previous
                          version listed only the recipients and gave no hint that the
                          project had three more contacts, or that you could drop one.

                          The chip carries the NAME. Addresses are masked for anyone without
                          edit_clients and are only ever a title here; what the server acts
                          on is the key.
                        */
                        <>
                            {candidates.map((candidate) => {
                                const on = (recipientKeys || []).includes(candidate.key);

                                return (
                                    <Chips
                                        key={candidate.key}
                                        label={candidate.name}
                                        // 'neutral' is the unselected tone; ds/Chips
                                        // defaults `color` to primary, so undefined would
                                        // make every chip look ticked.
                                        color={on ? 'primary' : 'neutral'}
                                        // Drives aria-pressed — these are toggles, and the
                                        // opacity below is not something a screen reader
                                        // can report.
                                        selected={on}
                                        size="small"
                                        title={`${candidate.address} — ${candidate.reason}`}
                                        onClick={() =>
                                            setRecipientKeys((current) => {
                                                const keys = current || [];

                                                return keys.includes(candidate.key)
                                                    ? keys.filter((k) => k !== candidate.key)
                                                    : [...keys, candidate.key];
                                            })
                                        }
                                        style={{
                                            cursor: 'pointer',
                                            opacity: on ? 1 : 0.55,
                                        }}
                                    />
                                );
                            })}
                            {(recipientKeys || []).length === 0 ? (
                                <span
                                    style={{
                                        font: '400 12px/16px Figtree, sans-serif',
                                        color: 'var(--negative-color)',
                                    }}
                                >
                                    Pick at least one person.
                                </span>
                            ) : null}
                        </>
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
                  No Cc / Bcc. The To row above IS the picker this note used to promise.

                  There was an "Add Cc / Bcc" button here with two text fields behind it.
                  They did nothing: `emails` has no cc or bcc column, so the endpoint wrote
                  the addresses into a team note and sent to `to` only — a control that
                  looks like it copies somebody and does not.

                  They are not coming back as free text. Client mail goes from our mailbox
                  to the project's clients and nowhere else; that is what keeps a client
                  from corresponding with an individual staff member directly, and only a
                  handful of people have Gmail access at all. That is exactly why the To row
                  is a picker over the people on the thread rather than a box you type into
                  — the choice is real, the set it chooses from is not negotiable.
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
                            {/* Not "on this project" any more — it is who you picked. */}
                            {to.length} people on this reply — each receives their own copy.
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

                {/*
                  Shown whenever AI is on and this is a new reply.

                  It used to also require !isTemplateKind, !isBlocksKind and
                  draftMatchesTarget — three conditions that each removed the whole strip
                  without saying anything, so "Draft for me" simply vanished depending on
                  which composer tab you were on and which message you pressed Reply from.
                  Reported, reasonably, as the feature disappearing.

                  The conditions were right; hiding the control was not. Each one now
                  disables the button and says why.
                */}
                {aiEnabled && !isEditing ? (
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
                        {/*
                          Three states, because the request is asynchronous and pretending
                          otherwise is what made this look broken. The button used to stay
                          "Draft for me" the entire time the job ran, so pressing it
                          appeared to do nothing at all.
                        */}
                        <Icon
                            name={aiDrafting ? 'Update' : 'Wand'}
                            size={14}
                            color="var(--primary-color)"
                            style={aiDrafting ? { animation: 'ozeeSpin 900ms linear infinite' } : undefined}
                        />
                        <span style={{ font: '600 12px/16px Figtree, sans-serif', color: 'var(--primary-color)' }}>
                            {draftBlockedReason
                                ? draftBlockedReason
                                : aiDrafting
                                  ? 'Writing a draft…'
                                  : aiDraftStalled
                                    ? 'That draft never came back'
                                    : aiDraftFailed
                                      ? 'The AI could not write one'
                                      : usedAi
                                        ? 'AI draft — edit before it goes out'
                                        : 'Your own words'}
                        </span>
                        <span style={{ marginInlineStart: 'auto', display: 'flex', gap: 4 }}>
                            <Button
                                kind="tertiary"
                                size="small"
                                // Disabled while genuinely working, or when a draft would
                                // not apply here. A stalled request re-enables it, because
                                // the whole point of noticing a stall is to let someone
                                // try again.
                                disabled={(aiDrafting && !aiDraftStalled) || !!draftBlockedReason}
                                title={draftBlockedReason || undefined}
                                onClick={onRegenerateDraft}
                            >
                                {aiDrafting && !aiDraftStalled
                                    ? 'Working…'
                                    : aiDraftFailed || aiDraftStalled
                                      ? 'Try again'
                                      : usedAi
                                        ? 'Try another'
                                        : 'Draft for me'}
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

                {canGreet ? (
                    <GreetingPicker
                        allowNone
                        mode={greetingMode}
                        customName={greetingName}
                        names={recipients?.recipient_names || []}
                        disabled={busy}
                        onMode={setGreetingMode}
                        onCustomName={setGreetingName}
                    />
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

                {/*
                  Shown for a custom reply and for editing a draft, because the branded
                  block goes on the way out either way — it is the layout, not the
                  composer, that adds it. Not under the block builder, which draws its own
                  sign-off inside the preview.
                */}
                {(!isTemplateKind && !isBlocksKind) || isEditing ? (
                    <SignOffPreview signOff={compose?.signOff} />
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
                {canMarkPrivate ? (
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                        <Toggle
                            size="small"
                            checked={isPrivate}
                            disabled={busy}
                            ariaLabel="Keep this message private"
                            onChange={setIsPrivate}
                        />
                        <span
                            style={{
                                font: '400 12px/16px Figtree, sans-serif',
                                color: isPrivate ? 'var(--primary-text-color)' : 'var(--secondary-text-color)',
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 4,
                            }}
                        >
                            <Icon name="Hide" size={14} color="currentColor" />
                            Private
                        </span>
                    </span>
                ) : null}
                {isPrivate && !canSeePrivate ? (
                    <span
                        style={{
                            flexBasis: '100%',
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            font: '400 12px/16px Figtree, sans-serif',
                            color: 'var(--negative-color)',
                        }}
                    >
                        <Icon name="Alert" size={14} color="currentColor" />
                        You cannot read private messages, so you will not be able to open this
                        one or reply on this thread afterwards.
                    </span>
                ) : null}
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
                        {isPrivate && canMarkPrivate
                            ? 'The client still receives this — it is hidden from the project team'
                            : isEditing
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
