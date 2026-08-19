/**
 * Light / dark / auto theme for the redesigned pages.
 *
 * The design system's dark theme keys off `<html data-theme="...">`, so this hook owns
 * that attribute. `data-ozds` is set alongside it so resources/css/ozee-ds/tokens/base.css
 * can paint the app background on <html>/<body> only while a redesigned page is mounted —
 * legacy Vue pages are never touched.
 */

import { useCallback, useEffect, useState } from 'react';

const STORAGE_KEY = 'ozds-theme';
export const THEME_OPTIONS = [
    { value: 'system', text: 'Auto' },
    { value: 'light', text: 'Light' },
    { value: 'dark', text: 'Dark' },
];

function readTheme() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        return stored === 'light' || stored === 'dark' || stored === 'system' ? stored : 'system';
    } catch {
        return 'system';
    }
}

export function useTheme() {
    const [theme, setThemeState] = useState(readTheme);

    useEffect(() => {
        const root = document.documentElement;
        root.setAttribute('data-theme', theme);
        root.setAttribute('data-ozds', '1');

        return () => {
            root.removeAttribute('data-theme');
            root.removeAttribute('data-ozds');
        };
    }, [theme]);

    const setTheme = useCallback((value) => {
        try {
            localStorage.setItem(STORAGE_KEY, value);
        } catch {
            /* ignore */
        }
        setThemeState(value);
    }, []);

    return { theme, setTheme };
}
