import { getPreferencePersistence } from './preferences-config';
import type { PreferenceKey, PreferenceValueMap } from './preferences-config';

export function setClientCookie(name: string, value: string): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.cookie = `${name}=${encodeURIComponent(value)};path=/;max-age=31536000;SameSite=Lax`;
}

export function setLocalStorageValue(key: string, value: string): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(key, value);
}

export function persistPreference<K extends PreferenceKey>(
    key: K,
    value: PreferenceValueMap[K],
): void {
    const mode = getPreferencePersistence(key);

    switch (mode) {
        case 'none':
            return;
        case 'client-cookie':
            setClientCookie(key, value);

            return;
        case 'localStorage':
            setLocalStorageValue(key, value);

            return;
    }
}
