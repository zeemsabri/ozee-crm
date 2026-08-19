/**
 * A tiny toast queue for the app shell.
 *
 * The portal pages each own a single <Toast>; that stops working once a page can fire
 * several notices in a row (approve, then mark read, then delete). This keeps a queue and
 * auto-dismisses, and AppShell renders the stack so pages only ever call `push()`.
 */

import { useCallback, useEffect, useRef, useState } from 'react';

const DEFAULT_TTL = 4000;

export function useToasts() {
    const [toasts, setToasts] = useState([]);
    const timers = useRef(new Map());
    const nextId = useRef(1);

    const dismiss = useCallback((id) => {
        setToasts((list) => list.filter((t) => t.id !== id));
        const timer = timers.current.get(id);
        if (timer) {
            clearTimeout(timer);
            timers.current.delete(id);
        }
    }, []);

    const push = useCallback(
        (message, { type = 'positive', ttl = DEFAULT_TTL } = {}) => {
            if (!message) return null;
            const id = nextId.current++;
            setToasts((list) => [...list.slice(-2), { id, message, type }]);
            if (ttl > 0) {
                timers.current.set(
                    id,
                    setTimeout(() => dismiss(id), ttl)
                );
            }
            return id;
        },
        [dismiss]
    );

    // Clear pending timers on unmount so a dismissed page can't setState afterwards.
    useEffect(() => {
        const pending = timers.current;
        return () => {
            pending.forEach((t) => clearTimeout(t));
            pending.clear();
        };
    }, []);

    return { toasts, push, dismiss };
}
