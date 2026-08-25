/**
 * The inbox's dialogs: send-back, create-task, bulk-categorise, and delete.
 *
 * Design source: Inbox.dc.html, the three <Modal> blocks at the end of the markup.
 *
 * The "New email" composer lives in its own file, ComposeModal.jsx. The mock's
 * drag-and-drop block builder is a separate design file (EmailBlocks.dc.html) and is not
 * implemented — template and custom composing are.
 */

import { useEffect, useState } from 'react';
import { AlertBanner, Button, Checkbox, Chips, Dropdown, Modal, TextArea, TextField } from '../ds';
import { categoryColour } from './format';

export function RejectModal({ open, onClose, onConfirm, busy, style, dense }) {
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
            style={style}
            dense={dense}
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
 * Delete — one message, or a whole thread.
 *
 * There are two copies of every delivered email: ours, and Gmail's. Deleting used to mean
 * only ours, silently and with no confirmation — press the button, the thread is gone.
 * This is the classic inbox's dialog (EmailDetailsContent.vue) rebuilt, with one
 * deliberate difference: **the Gmail box starts unticked.** The classic one pre-ticks
 * both, so the fastest path through it trashes the client's side of the conversation too,
 * and nothing in the CRM can bring that back.
 *
 * `gmailReachable` is false when there is nothing on Gmail's side to reach — a draft that
 * never sent. The box is disabled rather than hidden, because "why can't I tick this" is a
 * question worth answering on the spot.
 *
 * Deleting is a SOFT delete on our side. That is worth saying in the dialog: people treat
 * "delete" as irreversible and hesitate over it, and here only half of it is.
 */
const SUB_TEXT = {
    marginInlineStart: 28,
    font: '400 12px/16px Figtree, sans-serif',
    color: 'var(--secondary-text-color)',
};

export function DeleteModal({
    open,
    onClose,
    onConfirm,
    busy,
    scope = 'thread',
    count = 1,
    gmailReachable = true,
    style,
    dense,
}) {
    const [local, setLocal] = useState(true);
    const [gmail, setGmail] = useState(false);

    useEffect(() => {
        if (!open) return;
        setLocal(true);
        setGmail(false);
    }, [open]);

    const isThread = scope === 'thread';
    const subject = isThread
        ? count > 1
            ? `these ${count} threads`
            : 'this thread'
        : 'this message';

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={isThread ? (count > 1 ? `Delete ${count} threads` : 'Delete this thread') : 'Delete this message'}
            description={`Choose which copies of ${subject} to delete.`}
            size="small"
            style={style}
            dense={dense}
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                {/*
                  ds/Checkbox takes a label and nothing else — no subText, unlike the
                  Fields.jsx inputs. The explanation is rendered beside it rather than
                  added to the component, because every other Checkbox in the app is a
                  one-liner and widening the primitive for one dialog is the wrong trade.
                */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                    <div>
                        <Checkbox checked={local} disabled={busy} onChange={setLocal} label="Delete our copy" />
                        <div style={SUB_TEXT}>
                            Removed from this inbox. A soft delete — the row is kept, and someone
                            who can delete emails can restore it.
                        </div>
                    </div>
                    <div>
                        <Checkbox
                            checked={gmail && gmailReachable}
                            disabled={busy || !gmailReachable}
                            onChange={setGmail}
                            label="Delete the Gmail copy"
                        />
                        <div style={SUB_TEXT}>
                            {gmailReachable
                                ? 'Moved to the Gmail bin. The client keeps their own copy either way — this only affects our mailbox.'
                                : 'Nothing to delete — this was never sent, so it has no Gmail copy.'}
                        </div>
                    </div>
                </div>

                {gmail && gmailReachable ? (
                    <AlertBanner type="warning">
                        The Gmail copy is not soft-deleted. Once it is in the bin, nothing here
                        can bring it back.
                    </AlertBanner>
                ) : null}

                <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                    <Button kind="tertiary" size="small" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        size="small"
                        color="negative"
                        // At least one copy, or there is nothing to do. The server refuses
                        // the same request with a 422; this just saves the round trip.
                        disabled={!local && !(gmail && gmailReachable)}
                        loading={busy}
                        onClick={() => onConfirm({ local, gmail: gmail && gmailReachable })}
                    >
                        Delete
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
export function TaskModal({ open, onClose, onCreate, busy, suggestion, users = [], style, dense }) {
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
        <Modal open={open} onClose={onClose} title="Create task from this thread" size="medium" style={style} dense={dense}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                <TextField label="Task title" value={title} onChange={(e) => setTitle(e.target.value)} />
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: 12 }}>
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

export function CategoriseModal({ open, onClose, onApply, busy, categories = [], count, style, dense }) {
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
            style={style}
            dense={dense}
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
                                selected={on}
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
