/**
 * Boot script that reads user preference values from cookies
 * and sets data-* attributes on <html> before paint to prevent
 * flash of wrong theme/layout.
 */
import { useLayoutEffect } from 'react';

import { PREFERENCE_REGISTRY } from '@/lib/preferences/preferences-config';

function readCookie(name: string): string | null {
    const cookie = document.cookie
        .split('; ')
        .find((value) => value.startsWith(`${name}=`));

    return cookie ? decodeURIComponent(cookie.slice(name.length + 1)) : null;
}

function applyThemeBoot(): void {
    try {
        const root = document.documentElement;
        const preferences: Record<string, string> = {};

        for (const [key, definition] of Object.entries(PREFERENCE_REGISTRY)) {
            const cookieValue =
                definition.persistence === 'client-cookie' ||
                definition.persistence === 'server-cookie'
                    ? readCookie(key)
                    : null;
            const value =
                cookieValue !== null &&
                definition.values.includes(cookieValue as never)
                    ? cookieValue
                    : definition.defaultValue;

            preferences[key] = value;
            root.setAttribute(definition.attribute, value);
        }

        const mode = preferences.theme_mode;
        const resolvedMode =
            mode === 'system' && window.matchMedia
                ? window.matchMedia('(prefers-color-scheme: dark)').matches
                    ? 'dark'
                    : 'light'
                : mode === 'dark'
                  ? 'dark'
                  : 'light';

        root.classList.toggle('dark', resolvedMode === 'dark');
        root.style.colorScheme = resolvedMode;
    } catch (error) {
        console.warn('ThemeBootScript error:', error);
    }
}

export function ThemeBootScript() {
    useLayoutEffect(() => {
        applyThemeBoot();
    }, []);

    return null;
}
