/**
 * "New email" — starting a fresh thread, template or custom.
 *
 * Design source: Inbox.dc.html, the composer Modal at the end of the markup.
 *
 * Posts to the SAME endpoints the legacy composer uses, so an email started here is
 * indistinguishable from one started there:
 *   template → POST /api/emails/templated  { project_id, client_ids[], subject,
 *                                            template_id, template_data, status }
 *   custom   → POST /api/emails            { project_id, client_ids[{id}], subject, body }
 * Note the two take `client_ids` in DIFFERENT shapes — raw ids for the templated route,
 * objects for the custom one. That is the existing API's inconsistency, not a typo here.
 *
 * Both create the email as a draft, and in this system creating a draft IS submitting it:
 * `Email::created` fires the automation workflow, which runs the AI approval analysis and
 * then either sends the email or parks it at pending_approval for a human. That is the
 * same thing the classic composer does with the same endpoints — nothing here sends
 * directly. "Save for later" exists, but it is deliberately NOT one of these rows: it is
 * status 'saved' via /api/inbox/saved, outside the automation entirely, and submitting a
 * saved email comes back through the create endpoints above. See useComposeDrafts.
 *
 * A third mode, "Project update", is the block builder from EmailBlocks.dc.html. It posts
 * to the same custom-email endpoint with a `blocks` array instead of a `body`; the server
 * renders it. See App\Services\Inbox\BlockComposition.
 */

import { useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import {
    Button,
    ButtonGroup,
    Chips,
    Dropdown,
    Icon,
    Modal,
    Popover,
    TextField,
    Toggle,
    useAnchoredPopover,
} from '../ds';
import { BlockBuilder } from './BlockBuilder';
import { LetterEditor, useSnippets } from './MarkdownEditor';
import { GreetingLine, SignOffPreview, greetingTextFor } from './Salutation';
import { TemplateFields } from './TemplateFields';
import { useBlocks } from './useBlocks';
import { useComposeDrafts, draftAgeLabel } from './useComposeDrafts';
import { emptyValueFor, inputPlaceholders, useTemplatePreview } from './useTemplates';

/** 20 MB, matching the attachment cap the classic inbox enforces server-side. */
const MAX_ATTACHMENT_BYTES = 20 * 1024 * 1024;

function prettySize(bytes) {
    if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${bytes} B`;
}

export function ComposeModal({
    open,
    onClose,
    onCreated,
    compose,
    projects,
    onError,
    classicUrl,
    // The page's ONE useSavedEmails instance (list + refresh + remove) — shared with the
    // rail badge and the Saved view. May be absent on older callers; the feature hides.
    saved,
    // A saved email the page wants opened (SavedList's Resume). Applied once, on open.
    initialDraft,
    style,
    dense,
}) {
    const canTemplate = compose?.canTemplate ?? false;
    const canCustom = compose?.canCustom ?? false;
    const canBlocks = compose?.canBlocks ?? canCustom;
    /*
     * Send-as-private. The flag and its permission already exist — "Make private" next to
     * a sent message — but only after the fact, so every private message was visible to
     * the project team until someone remembered to flip it. Setting it here writes it at
     * creation. See InboxAccess::canMarkPrivate.
     */
    const canMarkPrivate = compose?.canMarkPrivate ?? false;
    // See ReplyBox: marking private is `delete_emails`, reading one is
    // `view_private_emails`, and on a NEW thread every email is this one — so someone
    // holding the first without the second loses the whole conversation from their list.
    const canSeePrivate = compose?.canSeePrivate ?? false;
    const [isPrivate, setIsPrivate] = useState(false);

    const [projectId, setProjectId] = useState(null);
    const [clients, setClients] = useState([]);
    const [clientIds, setClientIds] = useState([]);
    const [loadingClients, setLoadingClients] = useState(false);

    // Open on a mode this person actually has. Template first when they have it;
    // otherwise custom, and failing that the block builder — a template-less user who can
    // only build project updates must not open on an empty 'template' tab.
    const initialKind = canTemplate ? 'template' : canCustom ? 'custom' : 'blocks';
    const [kind, setKind] = useState(initialKind);
    const [templateId, setTemplateId] = useState(null);
    const [templateData, setTemplateData] = useState({});
    const [previewedTemplateId, setPreviewedTemplateId] = useState(null);

    const [subject, setSubject] = useState('');
    const [body, setBody] = useState(''); // markdown — see MarkdownEditor.jsx
    const [busy, setBusy] = useState(false);

    /*
     * Attachments — the UI half. Files are validated and shown as chips, but the two
     * create endpoints have nowhere to put them yet, so nothing is uploaded and the
     * row says so. The send-path (upload + Gmail parts, like the block builder's CID
     * images) is the backend follow-up; this ships first so the flow can be felt.
     */
    const [attachments, setAttachments] = useState([]);
    const fileInputRef = useRef(null);
    const imageInputRef = useRef(null);

    const addFiles = (fileList, kind) => {
        const next = Array.from(fileList || []).map((file) => ({
            id: `at_${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`,
            name: file.name,
            size: file.size,
            kind,
            file,
            error: file.size > MAX_ATTACHMENT_BYTES ? `over the ${prettySize(MAX_ATTACHMENT_BYTES)} limit` : null,
        }));
        if (next.length) setAttachments((current) => [...current, ...next]);
    };

    const snippetsApi = useSnippets();

    /*
     * The greeting was here all along, hardcoded to the first client's first name and
     * invisible: `greeting_name` was posted and HandlesEmailCreation prepended it, so
     * anyone who did not already know that typed their own "Hi Sarah," and sent two.
     * Now it is a control. Default matches the Vue composer's — full name.
     */
    const [greetingMode, setGreetingMode] = useState('full_name');
    const [greetingName, setGreetingName] = useState('');

    const isTemplate = kind === 'template';
    const isBlocks = kind === 'blocks';

    // Names of the people actually selected, in the order they were picked, so a greeting
    // reading "Hi Sarah & Tom," names the two clients this email is going to and nobody
    // else on the project.
    const recipientNames = clientIds
        .map((id) => clients.find((c) => c.id === id)?.name)
        .filter(Boolean);

    const greeting = greetingTextFor({
        mode: greetingMode,
        customName: greetingName,
        names: recipientNames,
    });

    // Gated on its own permission, not on free-form: the builder emits typed blocks the
    // server renders, so it is offered to anyone who may compose. The server applies the
    // same rule — see InboxAccess::canComposeBlocks.
    const blocks = useBlocks({ projectId, onError });

    /*
     * Saved (unfinished) emails. A real emails row with status 'saved' — a status the
     * workflow automation never sees (the controller writes with model events off).
     * It could never be status 'draft': in this system draft means SUBMITTED, and
     * Email::created starts the automation. Submitting a saved email posts to the
     * normal create endpoint (fresh row, automation fires as always) and deletes the
     * saved row. See useComposeDrafts + InboxSavedController.
     */
    const drafts = useComposeDrafts({
        active: open && kind === 'custom' && !busy,
        onSaved: saved?.refresh,
        onError,
        getSnapshot: () => {
            if (kind !== 'custom') return null;
            if (!body.trim() && !subject.trim()) return null;
            // The server payload, verbatim — snake_case is the API's shape.
            return {
                project_id: projectId,
                client_ids: clientIds,
                subject,
                body,
                greeting_mode: greetingMode,
                greeting_name: greetingName,
                is_private: canMarkPrivate && isPrivate,
            };
        },
    });

    /*
     * A resumed draft's recipients can only be applied once the right project's clients
     * are in — and "in" is not knowable from loadingClients alone, because effects read
     * the values of the commit they run in: on the commit that changes the project this
     * effect would still see the PREVIOUS project's clients with loadingClients false.
     * clientsForProjectRef records which project the clients state actually belongs to
     * (set inside the load's .then), which refs report live rather than per-commit.
     */
    const [pendingResume, setPendingResume] = useState(null); // { projectId, clientIds }
    const clientsForProjectRef = useRef(null);

    useEffect(() => {
        if (!pendingResume) return;
        if ((pendingResume.projectId ?? null) !== (projectId ?? null)) return;
        if (pendingResume.projectId === null) {
            setClientIds([]);
            setPendingResume(null);
            return;
        }
        if (loadingClients || clientsForProjectRef.current !== pendingResume.projectId) return;
        // People leave projects between saves — keep only recipients that still exist.
        setClientIds(pendingResume.clientIds.filter((id) => clients.some((c) => c.id === id)));
        setPendingResume(null);
    }, [pendingResume, projectId, clients, loadingClients]);

    const draftsPop = useAnchoredPopover({ preferredHeight: 320, align: 'end', minWidth: 340 });

    const resumeDraft = (draft) => {
        const meta = draft.meta || {};
        draftsPop.setOpen(false);
        setKind('custom');
        setSubject(draft.subject || '');
        setBody(draft.body || '');
        setGreetingMode(meta.greeting_mode || 'full_name');
        setGreetingName(meta.greeting_name || '');
        setIsPrivate(canMarkPrivate ? !!draft.is_private : false);
        setPendingResume({
            projectId: meta.project_id ?? null,
            clientIds: Array.isArray(meta.client_ids) ? meta.client_ids : [],
        });
        setProjectId(meta.project_id ?? null);
        drafts.adopt(draft.id);
    };

    // The preview endpoint takes one client id. It CAN combine several names, but only
    // when `client_ids` is also posted — which this does not do, matching the real send,
    // where renderEmailContent resolves the single conversable client. Previewing against
    // the first recipient therefore shows what will actually go out.
    const preview = useTemplatePreview({
        projectId,
        clientId: clientIds[0] ?? null,
        onError,
    });

    const KIND_OPTIONS = useMemo(
        () =>
            [
                canTemplate ? { value: 'template', text: 'Template' } : null,
                canCustom ? { value: 'custom', text: 'Custom message' } : null,
                canBlocks ? { value: 'blocks', text: 'Project update' } : null,
            ].filter(Boolean),
        [canTemplate, canCustom, canBlocks]
    );

    // Reset everything each time it opens — a half-filled composer from last time is
    // worse than an empty one.
    useEffect(() => {
        if (!open) return;
        setProjectId(null);
        setClients([]);
        setClientIds([]);
        setTemplateId(null);
        setTemplateData({});
        setPreviewedTemplateId(null);
        setSubject('');
        setBody('');
        setGreetingMode('full_name');
        setGreetingName('');
        blocks.reset();
        setIsPrivate(false);
        setKind(initialKind);
        setAttachments([]);
        setPendingResume(null);
        // Fresh composer, fresh draft identity. The PREVIOUS session's autosaved draft
        // stays in the list — closing the modal is how a draft survives, not how it dies.
        drafts.detach();
        preview.reset();
        // Opened from the Saved view (or a stale gallery card): pick the draft up
        // exactly as the in-modal Resume button would.
        if (initialDraft) resumeDraft(initialDraft);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    // Recipients belong to a project, so changing the project invalidates them.
    useEffect(() => {
        if (!projectId) {
            setClients([]);
            setClientIds([]);
            clientsForProjectRef.current = null;
            return;
        }

        setLoadingClients(true);
        setClientIds([]);
        preview.reset();
        setPreviewedTemplateId(null);

        axios
            .get(`/api/projects/${projectId}/sections/clients`, { params: { type: 'clients' } })
            .then(({ data }) => {
                // The endpoint returns { clients: [...] } when scoped by type.
                // Bare array when `type` is set; the wrapped shape is the untyped call.
                const rows = Array.isArray(data) ? data : data?.clients || [];
                setClients(rows);
                // Which project these clients belong to — the resume effect keys on it.
                clientsForProjectRef.current = projectId;
            })
            .catch(() => onError?.('Could not load the clients on that project.'))
            .finally(() => setLoadingClients(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [projectId]);

    const selectTemplate = (id) => {
        setTemplateId(id);
        const next = compose?.templates?.find((t) => t.id === id);
        const seeded = {};
        inputPlaceholders(next).forEach((p) => {
            seeded[p.name] = emptyValueFor(p);
        });
        setTemplateData(seeded);
        setPreviewedTemplateId(null);
        preview.reset();
    };

    const toggleClient = (id) => {
        setClientIds((current) =>
            current.includes(id) ? current.filter((c) => c !== id) : [...current, id]
        );
        // The rendered subject depends on the recipient, and the stored subject is also
        // the key Conversation::firstOrCreate groups on — a stale one files the draft
        // under the previous client's thread. Force a re-preview.
        setPreviewedTemplateId(null);
        preview.reset();
    };

    /*
     * A templated email must be previewed before it can be created.
     *
     * The subject that reaches the client is the TEMPLATE's, rendered server-side —
     * pressing Preview is what puts that real subject in the field. Creating without it
     * would store a subject nobody ever sees, and storeTemplatedEmail requires one.
     */
    const previewed = !isTemplate || previewedTemplateId === templateId;

    const canCreate = (() => {
        if (busy) return false;
        if (!projectId || clientIds.length === 0 || !subject.trim()) return false;
        if (isTemplate) return !!templateId && previewed;
        if (isBlocks) return blocks.meaningfulCount > 0 && !blocks.uploading;
        return !!body.trim();
    })();

    const create = async () => {
        setBusy(true);

        try {
            if (isTemplate) {
                await axios.post('/api/emails/templated', {
                    project_id: projectId,
                    client_ids: clientIds, // raw ids for this route
                    subject: subject.trim(),
                    template_id: templateId,
                    template_data: templateData,
                    status: 'draft',
                    // Only when it is on AND allowed, so a stale toggle cannot post a flag
                    // the server would refuse. The server re-checks the permission anyway.
                    ...(canMarkPrivate && isPrivate ? { is_private: true } : {}),
                });
            } else {
                /*
                 * greeting_name matters: HandlesEmailCreation prepends it to the body and
                 * falls back to a literal "Hi there" when none is sent, so this key is
                 * never optional in practice — it only decides whether the client reads
                 * their own name or a generic line.
                 *
                 * `custom_greeting_name` is deliberately NOT posted, even though the Vue
                 * composer posts it: the server reads it with `??`, so the empty string
                 * that composer sends whenever the mode is not "custom" wins over
                 * greeting_name and the email goes out with no greeting at all. Sending
                 * one fully-built string avoids the whole question.
                 */
                await axios.post('/api/emails', {
                    project_id: projectId,
                    client_ids: clientIds.map((id) => ({ id })), // objects for this one
                    subject: subject.trim(),
                    composition_type: isBlocks ? 'blocks' : 'custom',
                    // One or the other, never both — the server requires a body only when
                    // there are no blocks. `body_format` tells the send path the custom
                    // body is the letter editor's markdown, to be rendered to HTML at
                    // send time — see App\Services\Inbox\MarkdownBody.
                    ...(isBlocks
                        ? { blocks: blocks.payload() }
                        : { body: body.trim(), body_format: 'markdown' }),
                    greeting_name: greeting,
                    ...(canMarkPrivate && isPrivate ? { is_private: true } : {}),
                });
            }

            // Not "saved to drafts". The row has been created, which is what starts the
            // automation — saying otherwise would suggest a second step that does not
            // exist and that nobody would go looking for.
            drafts.discardCurrent(); // submitted — the local draft is spent
            onCreated?.('Submitted for review — it goes out once it is approved.');
            onClose();
        } catch (e) {
            // These controllers answer 422 with message: "Validation failed" and the real
            // reason under `errors`, so reading `message` first told the user nothing.
            const data = e?.response?.data;
            const firstError = data?.errors ? Object.values(data.errors).flat()[0] : null;

            onError?.(firstError || data?.message || 'Could not create the email.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="New email"
            description="Templates keep the wording consistent, custom is for one-offs, and Project update builds a structured summary."
            size="large"
            style={style}
            dense={dense}
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                {KIND_OPTIONS.length === 0 ? (
                    <span
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            font: '400 13px/20px Figtree, sans-serif',
                            color: 'var(--negative-color)',
                        }}
                    >
                        <Icon name="Security" size={16} color="currentColor" />
                        <span>
                            You do not have permission to compose emails. Ask an admin for the
                            &ldquo;compose_emails&rdquo; permission.
                        </span>
                    </span>
                ) : null}

                {KIND_OPTIONS.length > 1 || (saved?.drafts?.length || 0) > 0 ? (
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                        {KIND_OPTIONS.length > 1 ? (
                            <ButtonGroup options={KIND_OPTIONS} value={kind} onChange={setKind} size="small" />
                        ) : null}
                        {(saved?.drafts?.length || 0) > 0 ? (
                            <span ref={draftsPop.anchorRef} style={{ marginInlineStart: 'auto' }}>
                                <Button
                                    kind="tertiary"
                                    size="small"
                                    active={draftsPop.open}
                                    onClick={() => draftsPop.setOpen(!draftsPop.open)}
                                >
                                    <Icon name="Doc" size={14} color="currentColor" />
                                    Drafts
                                    <span
                                        style={{
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            minWidth: 16,
                                            height: 16,
                                            padding: '0 4px',
                                            borderRadius: 8,
                                            background: 'var(--primary-color)',
                                            color: 'var(--text-color-on-primary)',
                                            font: 'var(--font-text3-medium)',
                                        }}
                                    >
                                        {saved.drafts.length}
                                    </span>
                                </Button>
                            </span>
                        ) : null}
                    </div>
                ) : null}

                {draftsPop.open && saved ? (
                    <Popover
                        position={draftsPop.position}
                        panelRef={draftsPop.panelRef}
                        style={{
                            background: 'var(--dialog-background-color)',
                            border: '1px solid var(--layout-border-color)',
                            borderRadius: 'var(--border-radius-medium)',
                            boxShadow: 'var(--box-shadow-medium)',
                            padding: 0,
                        }}
                    >
                        <div style={{ width: 360, display: 'flex', flexDirection: 'column' }}>
                            <div
                                style={{
                                    padding: '10px 14px',
                                    borderBottom: '1px solid var(--om-hairline)',
                                    font: 'var(--font-text3-medium)',
                                    color: 'var(--secondary-text-color)',
                                }}
                            >
                                Saved emails — yours, and your projects'
                            </div>
                            <div style={{ maxHeight: 260, overflowY: 'auto', padding: '4px 0' }}>
                                {saved.drafts.map((d) => (
                                    <div
                                        key={d.id}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 10,
                                            padding: '8px 14px',
                                        }}
                                    >
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div
                                                style={{
                                                    font: 'var(--font-text2-medium)',
                                                    color: 'var(--primary-text-color)',
                                                    whiteSpace: 'nowrap',
                                                    overflow: 'hidden',
                                                    textOverflow: 'ellipsis',
                                                }}
                                            >
                                                {d.subject?.trim() || '(no subject)'}
                                            </div>
                                            <div
                                                style={{
                                                    font: 'var(--font-text3-normal)',
                                                    color: 'var(--secondary-text-color)',
                                                }}
                                            >
                                                {(projects || []).find((p) => p.value === d.meta?.project_id)
                                                    ?.label || 'No project yet'}
                                                {d.sender?.name ? ` · ${d.sender.name}` : ''}
                                                {' · saved '}
                                                {d.updated_at
                                                    ? draftAgeLabel(Date.parse(d.updated_at))
                                                    : 'recently'}
                                            </div>
                                        </div>
                                        <Button size="small" kind="secondary" onClick={() => resumeDraft(d)}>
                                            Resume
                                        </Button>
                                        <button
                                            type="button"
                                            title="Delete draft"
                                            aria-label="Delete draft"
                                            onClick={() => saved.remove(d.id)}
                                            style={{
                                                border: 'none',
                                                background: 'transparent',
                                                color: 'var(--icon-color)',
                                                cursor: 'pointer',
                                                padding: 2,
                                            }}
                                        >
                                            <Icon name="CloseSmall" size={14} color="currentColor" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                            <div
                                style={{
                                    padding: '8px 14px',
                                    borderTop: '1px solid var(--om-hairline)',
                                    font: 'var(--font-text3-normal)',
                                    color: 'var(--secondary-text-color)',
                                }}
                            >
                                Nothing here reaches the AI checker or the client until you
                                submit it. Attachments are not kept with a saved email.
                            </div>
                        </div>
                    </Popover>
                ) : null}

                {KIND_OPTIONS.length === 1 && isTemplate ? (
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
                        <span>Free-form emails are admin-only — build yours from a template.</span>
                    </span>
                ) : null}

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 12 }}>
                    {/*
                      `compose_projects` from /api/inbox/filters, NOT the full project
                      list: storeTemplatedEmail refuses a project the user is not a member
                      of, even for a super admin, so offering the rest here would only
                      produce a 403 after the whole form was filled in.
                    */}
                    <Dropdown
                        label="Project"
                        options={projects || []}
                        value={projectId}
                        onChange={setProjectId}
                        size="small"
                        searchable
                        placeholder={projects?.length ? 'Choose a project' : 'No projects you can email on'}
                        disabled={!projects?.length}
                    />
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                        <span style={{ font: '600 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                            To
                        </span>
                        {!projectId ? (
                            <span style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                                Pick a project first.
                            </span>
                        ) : loadingClients ? (
                            <span style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                                Loading clients…
                            </span>
                        ) : clients.length ? (
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                {clients.map((client) => {
                                    const on = clientIds.includes(client.id);
                                    return (
                                        <Chips
                                            key={client.id}
                                            label={client.name}
                                            size="small"
                                            color={on ? 'primary' : 'neutral'}
                                            onClick={() => toggleClient(client.id)}
                                            title={on ? 'Remove recipient' : 'Add recipient'}
                                        />
                                    );
                                })}
                            </div>
                        ) : (
                            <span style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--negative-color)' }}>
                                That project has no clients attached.
                            </span>
                        )}
                    </div>
                </div>

                {isTemplate && canTemplate ? (
                    <TemplateFields
                        templates={compose?.templates || []}
                        templateOptions={compose?.templateOptions || []}
                        templateId={templateId}
                        templateData={templateData}
                        sourceData={compose?.sourceData || {}}
                        loadingTemplates={compose?.loading}
                        onTemplate={{ select: selectTemplate, loadSourceData: compose?.loadSourceData }}
                        onData={setTemplateData}
                        preview={preview}
                        onRefreshPreview={async () => {
                            const result = await preview.refresh(templateId, templateData);
                            if (!result) return;

                            // Mark it previewed on ANY successful render. Gating on a
                            // truthy subject meant a template that renders an empty one
                            // left Save disabled with nothing explaining why.
                            setSubject(result.subject || '');
                            setPreviewedTemplateId(templateId);
                        }}
                    />
                ) : null}

                {!isTemplate ? (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                        <TextField
                            label="Subject"
                            size="small"
                            placeholder="What is this about?"
                            value={subject}
                            onChange={(e) => setSubject(e.target.value)}
                        />
                        {isBlocks ? (
                            <BlockBuilder
                                blocks={blocks}
                                disabled={busy}
                                // The builder puts the greeting in as its first block, so
                                // the preview has to show the same string the send will —
                                // see HandlesEmailCreation, which unshifts it.
                                greeting={greeting || 'Hi there,'}
                                signOff={compose?.signOff}
                            />
                        ) : (
                            <>
                                {/*
                                  The letter: greeting, body and signature in ONE surface
                                  styled like the email the client receives, with the
                                  Gmail-style toolbar at the bottom. What it stores (and
                                  what `body` holds here) is markdown — see
                                  MarkdownEditor.jsx for why that matters to the AI check.
                                */}
                                <LetterEditor
                                    value={body}
                                    onChange={setBody}
                                    disabled={busy}
                                    // The "</>" markdown peek confused non-admins into
                                    // thinking it was a mode to choose — super admins only.
                                    canViewSource={compose?.isSuperAdmin ?? false}
                                    snippetsApi={snippetsApi}
                                    onPickFiles={() => fileInputRef.current?.click()}
                                    onPickImage={() => imageInputRef.current?.click()}
                                    greeting={
                                        <GreetingLine
                                            // No "No greeting" here — see GreetingLine.
                                            mode={greetingMode}
                                            customName={greetingName}
                                            names={recipientNames}
                                            disabled={busy}
                                            onMode={setGreetingMode}
                                            onCustomName={setGreetingName}
                                        />
                                    }
                                    signOff={<SignOffPreview signOff={compose?.signOff} />}
                                />

                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    multiple
                                    style={{ display: 'none' }}
                                    onChange={(e) => {
                                        addFiles(e.target.files, 'file');
                                        e.target.value = '';
                                    }}
                                />
                                <input
                                    ref={imageInputRef}
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    style={{ display: 'none' }}
                                    onChange={(e) => {
                                        addFiles(e.target.files, 'image');
                                        e.target.value = '';
                                    }}
                                />

                                {attachments.length ? (
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                                        {attachments.map((a) => (
                                            <span
                                                key={a.id}
                                                style={{
                                                    display: 'inline-flex',
                                                    alignItems: 'center',
                                                    gap: 8,
                                                    height: 32,
                                                    padding: '0 10px',
                                                    border: `1px solid ${a.error ? 'var(--negative-color)' : 'var(--layout-border-color)'}`,
                                                    borderRadius: 'var(--border-radius-small)',
                                                    background: a.error
                                                        ? 'color-mix(in srgb, var(--negative-color) 8%, transparent)'
                                                        : 'var(--allgrey-background-color)',
                                                }}
                                            >
                                                <Icon
                                                    name={a.kind === 'image' ? 'Image' : 'File'}
                                                    size={14}
                                                    color={a.error ? 'var(--negative-color)' : 'var(--icon-color)'}
                                                />
                                                <span
                                                    style={{
                                                        font: 'var(--font-text3-normal)',
                                                        color: a.error
                                                            ? 'var(--negative-color)'
                                                            : 'var(--primary-text-color)',
                                                        maxWidth: 220,
                                                        whiteSpace: 'nowrap',
                                                        overflow: 'hidden',
                                                        textOverflow: 'ellipsis',
                                                    }}
                                                >
                                                    {a.name}
                                                    {a.error ? ` — ${a.error}` : ''}
                                                </span>
                                                {!a.error ? (
                                                    <span
                                                        style={{
                                                            font: 'var(--font-text3-normal)',
                                                            color: 'var(--secondary-text-color)',
                                                        }}
                                                    >
                                                        {prettySize(a.size)}
                                                    </span>
                                                ) : null}
                                                <button
                                                    type="button"
                                                    title="Remove"
                                                    aria-label={`Remove ${a.name}`}
                                                    disabled={busy}
                                                    onClick={() =>
                                                        setAttachments((current) =>
                                                            current.filter((x) => x.id !== a.id)
                                                        )
                                                    }
                                                    style={{
                                                        border: 'none',
                                                        background: 'transparent',
                                                        color: 'var(--icon-color)',
                                                        cursor: 'pointer',
                                                        padding: 0,
                                                        display: 'inline-flex',
                                                    }}
                                                >
                                                    <Icon name="CloseSmall" size={14} color="currentColor" />
                                                </button>
                                            </span>
                                        ))}
                                        <span
                                            style={{
                                                font: 'var(--font-text3-normal)',
                                                color: 'var(--secondary-text-color)',
                                            }}
                                        >
                                            Attachments are not sent yet — that part of the backend is
                                            still being wired up.
                                        </span>
                                    </div>
                                ) : null}
                            </>
                        )}
                    </div>
                ) : (
                    <TextField
                        label="Subject"
                        size="small"
                        readOnly
                        value={subject}
                        placeholder="Set by the template — press Preview to fill it in"
                        onChange={() => {}}
                    />
                )}

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        paddingTop: 12,
                        borderTop: '1px solid var(--om-hairline)',
                        flexWrap: 'wrap',
                    }}
                >
                    <Button size="small" disabled={!canCreate} loading={busy} onClick={create}>
                        Submit for review
                    </Button>
                    {kind === 'custom' ? (
                        <Button
                            kind="secondary"
                            size="small"
                            disabled={busy || (!body.trim() && !subject.trim())}
                            title="Keep this to finish later — nothing is submitted"
                            onClick={async () => {
                                if (await drafts.saveNow()) {
                                    onCreated?.('Saved — finish it any time from the Saved view.');
                                    onClose();
                                }
                            }}
                        >
                            Save
                        </Button>
                    ) : null}
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
                                    color: isPrivate
                                        ? 'var(--primary-text-color)'
                                        : 'var(--secondary-text-color)',
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
                            You cannot read private messages, so this thread will vanish from
                            your inbox as soon as it is created.
                        </span>
                    ) : null}
                    <span style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                        {/*
                          This used to read "Save to drafts / Creates a draft. Submitting it
                          for approval happens from Drafts." That described a step that does
                          not exist: creating the row is what starts the automation, and
                          nobody has to go back to Drafts to push it along. Worse, it implied
                          the email was parked and safe when it was already on its way.
                        */}
                        {canMarkPrivate && isPrivate
                            ? 'The client still receives this — it is hidden from the project team.'
                            : 'Goes to the AI checker first. If it passes, it is sent; if not, someone approves it by hand.'}
                    </span>
                    <span style={{ marginInlineStart: 'auto', display: 'flex', alignItems: 'center', gap: 8 }}>
                        {kind === 'custom' && drafts.savedState !== 'idle' ? (
                            <span
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 5,
                                    font: 'var(--font-text3-normal)',
                                    color: 'var(--secondary-text-color)',
                                }}
                            >
                                <Icon
                                    name={
                                        drafts.savedState === 'saving'
                                            ? 'Recurring'
                                            : drafts.savedState === 'error'
                                              ? 'Alert'
                                              : 'Check'
                                    }
                                    size={12}
                                    color={
                                        drafts.savedState === 'saving'
                                            ? 'currentColor'
                                            : drafts.savedState === 'error'
                                              ? 'var(--negative-color)'
                                              : 'var(--positive-color)'
                                    }
                                />
                                {drafts.savedState === 'saving'
                                    ? 'Saving…'
                                    : drafts.savedState === 'error'
                                      ? 'Not saved yet'
                                      : `Saved ${draftAgeLabel(drafts.savedState)}`}
                            </span>
                        ) : null}
                        {classicUrl ? (
                            <a href={classicUrl} style={{ textDecoration: 'none' }}>
                                <Button kind="tertiary" size="small">
                                    Classic composer
                                </Button>
                            </a>
                        ) : null}
                        <Button
                            kind="tertiary"
                            size="small"
                            color="negative"
                            title="Close and delete the draft of this email"
                            onClick={() => {
                                // Discard means discard: the autosaved copy goes too.
                                // Closing with X or Escape is the way to KEEP the draft.
                                drafts.discardCurrent();
                                onClose();
                            }}
                        >
                            Discard
                        </Button>
                    </span>
                </div>
            </div>
        </Modal>
    );
}
