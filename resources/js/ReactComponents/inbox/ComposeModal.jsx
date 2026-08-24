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
 * directly, and there is no "save for later" state to put an email in.
 *
 * A third mode, "Project update", is the block builder from EmailBlocks.dc.html. It posts
 * to the same custom-email endpoint with a `blocks` array instead of a `body`; the server
 * renders it. See App\Services\Inbox\BlockComposition.
 */

import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import { Button, ButtonGroup, Chips, Dropdown, Icon, Modal, TextArea, TextField, Toggle } from '../ds';
import { BlockBuilder } from './BlockBuilder';
import { GreetingPicker, SignOffPreview, greetingTextFor } from './Salutation';
import { TemplateFields } from './TemplateFields';
import { useBlocks } from './useBlocks';
import { emptyValueFor, inputPlaceholders, useTemplatePreview } from './useTemplates';

export function ComposeModal({ open, onClose, onCreated, compose, projects, onError, classicUrl, style, dense }) {
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
    const [body, setBody] = useState('');
    const [busy, setBusy] = useState(false);

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
        preview.reset();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    // Recipients belong to a project, so changing the project invalidates them.
    useEffect(() => {
        if (!projectId) {
            setClients([]);
            setClientIds([]);
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
                    // there are no blocks.
                    ...(isBlocks ? { blocks: blocks.payload() } : { body: body.trim() }),
                    greeting_name: greeting,
                    ...(canMarkPrivate && isPrivate ? { is_private: true } : {}),
                });
            }

            // Not "saved to drafts". The row has been created, which is what starts the
            // automation — saying otherwise would suggest a second step that does not
            // exist and that nobody would go looking for.
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

                {KIND_OPTIONS.length > 1 ? (
                    <ButtonGroup options={KIND_OPTIONS} value={kind} onChange={setKind} size="small" />
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
                                <GreetingPicker
                                    // No "No greeting" here — see GreetingPicker.
                                    mode={greetingMode}
                                    customName={greetingName}
                                    names={recipientNames}
                                    disabled={busy}
                                    onMode={setGreetingMode}
                                    onCustomName={setGreetingName}
                                />
                                <TextArea
                                    label="Message"
                                    rows={8}
                                    placeholder="Start with what you need to say — the greeting above and the sign-off below are added for you."
                                    value={body}
                                    onChange={(e) => setBody(e.target.value)}
                                />
                                <SignOffPreview signOff={compose?.signOff} />
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
                    <span style={{ marginInlineStart: 'auto', display: 'flex', gap: 8 }}>
                        {classicUrl ? (
                            <a href={classicUrl} style={{ textDecoration: 'none' }}>
                                <Button kind="tertiary" size="small">
                                    Classic composer
                                </Button>
                            </a>
                        ) : null}
                        <Button kind="tertiary" size="small" color="negative" onClick={onClose}>
                            Discard
                        </Button>
                    </span>
                </div>
            </div>
        </Modal>
    );
}
