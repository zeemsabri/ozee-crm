/**
 * The inbox's three dialogs: send-back, create-task, and bulk-categorise.
 *
 * Design source: Inbox.dc.html, the three <Modal> blocks at the end of the markup.
 *
 * The mock also has a full "New email" composer with a template picker, placeholder grid
 * and a drag-and-drop block builder (that last one is a separate design file,
 * EmailBlocks.dc.html, and is not in scope here). Rather than ship a half-built second
 * composer that could produce a differently-shaped email from the one the rest of the app
 * sends, ComposeRedirect below hands off to the existing, complete composer. Replies —
 * the thing people actually do all day in an inbox — are fully native here.
 */

import { useEffect, useState } from 'react';
import { Button, Chips, Dropdown, Modal, TextArea, TextField } from '../ds';
import { categoryColour } from './format';

export function RejectModal({ open, onClose, onConfirm, busy }) {
    const [reason, setReason] = useState('');

    useEffect(() => {
        if (open) setReason('');
    }, [open]);

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Send this back to the author"
            description="They see your reason and can fix it without starting again."
            size="small"
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                <TextArea
                    label="Reason"
                    rows={4}
                    required
                    placeholder="e.g. Price is right but drop the second paragraph — it promises a date we can't hold."
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                />
                <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                    <Button kind="tertiary" size="small" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        size="small"
                        color="negative"
                        disabled={!reason.trim()}
                        loading={busy}
                        onClick={() => onConfirm(reason.trim())}
                    >
                        Send back
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

/**
 * Capitalised on purpose. POST /api/emails/{id}/tasks/bulk validates
 * `in:Low,Medium,High`, so lowercase values 422 the whole request.
 */
const PRIORITIES = [
    { value: 'Low', label: 'Low' },
    { value: 'Medium', label: 'Medium' },
    { value: 'High', label: 'High' },
];

/** The AI suggests lowercase priorities; the API wants them capitalised. */
function normalisePriority(value) {
    const match = PRIORITIES.find((p) => p.value.toLowerCase() === String(value || '').toLowerCase());
    return match ? match.value : 'Medium';
}

/**
 * Create a task from the thread.
 *
 * Posts to the existing POST /api/emails/{id}/tasks/bulk that the legacy inbox uses, so
 * tasks made here are indistinguishable from tasks made there.
 */
export function TaskModal({ open, onClose, onCreate, busy, suggestion, users = [] }) {
    const [title, setTitle] = useState('');
    const [due, setDue] = useState('');
    const [priority, setPriority] = useState('Medium');
    const [assignee, setAssignee] = useState(null);
    const [description, setDescription] = useState('');

    // Re-seed from the AI suggestion each time it opens, so re-opening after dismissing
    // a suggestion does not resurrect stale text the person already rejected.
    useEffect(() => {
        if (!open) return;
        setTitle(suggestion?.title || '');
        setPriority(normalisePriority(suggestion?.priority));
        setDescription(suggestion?.reason || '');
        setDue('');
        setAssignee(null);
    }, [open, suggestion]);

    return (
        <Modal open={open} onClose={onClose} title="Create task from this thread" size="medium">
            <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                <TextField label="Task title" value={title} onChange={(e) => setTitle(e.target.value)} />
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 12 }}>
                    <TextField
                        label="Due date"
                        type="date"
                        size="small"
                        value={due}
                        onChange={(e) => setDue(e.target.value)}
                    />
                    <Dropdown
                        label="Priority"
                        options={PRIORITIES}
                        value={priority}
                        onChange={setPriority}
                        size="small"
                    />
                    <Dropdown
                        label="Assign to"
                        options={users}
                        value={assignee}
                        onChange={setAssignee}
                        size="small"
                        searchable
                    />
                </div>
                <TextArea
                    label="Description"
                    rows={3}
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                />
                <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                    <Button kind="tertiary" size="small" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        size="small"
                        disabled={!title.trim() || !due}
                        loading={busy}
                        onClick={() =>
                            onCreate({
                                name: title.trim(),
                                dueDate: due,
                                priority,
                                description: description.trim(),
                                assigned_to_user_id: assignee || undefined,
                            })
                        }
                    >
                        Create task
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

export function CategoriseModal({ open, onClose, onApply, busy, categories = [], count }) {
    const [selected, setSelected] = useState([]);

    useEffect(() => {
        if (open) setSelected([]);
    }, [open]);

    const toggle = (id) =>
        setSelected((current) =>
            current.includes(id) ? current.filter((c) => c !== id) : [...current, id]
        );

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={`Categorise ${count} thread${count === 1 ? '' : 's'}`}
            description="Categories are added to every message in the selected threads. Nothing is removed."
            size="small"
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                    {categories.map((category) => {
                        const on = selected.includes(category.id);
                        return (
                            <Chips
                                key={category.id}
                                label={category.name}
                                size="small"
                                color={on ? categoryColour(category.name) : 'neutral'}
                                onClick={() => toggle(category.id)}
                            />
                        );
                    })}
                </div>
                <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                    <Button kind="tertiary" size="small" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button size="small" disabled={!selected.length} loading={busy} onClick={() => onApply(selected)}>
                        Apply
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

/**
 * "New email" — hands off to the existing composer rather than rebuilding it.
 *
 * The legacy composer knows about email templates, placeholder definitions, source
 * models, client-vs-lead recipients, previews and scheduling. Reimplementing that here
 * would mean two composers that must produce identical emails; getting it subtly wrong
 * would send a client the wrong thing. Composing gets ported as its own piece of work.
 */
export function ComposeRedirect({ open, onClose, classicUrl }) {
    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Start a new email"
            description="Composing still happens on the classic page for now."
            size="small"
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <p style={{ margin: 0, font: '400 14px/20px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                    Replying to a thread works fully here. Starting a brand-new email still uses the classic
                    composer, which knows about templates, placeholders and scheduling — so nothing goes out in a
                    different shape while the redesign is in progress.
                </p>
                <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                    <Button kind="tertiary" size="small" onClick={onClose}>
                        Stay here
                    </Button>
                    {/* Plain anchor, not an Inertia Link: crossing to a Vue page needs a
                        full reload so app.js boots Vue instead of React. */}
                    <a href={classicUrl} style={{ textDecoration: 'none' }}>
                        <Button size="small">Open the classic composer</Button>
                    </a>
                </div>
            </div>
        </Modal>
    );
}
