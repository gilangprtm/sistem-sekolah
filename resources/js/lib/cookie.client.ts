// Client-side cookie utilities.
export function setClientCookie(name: string, value: string) {
    document.cookie = `${name}=${encodeURIComponent(value)};path=/;max-age=${60 * 60 * 24 * 365}`;
}

export function writeClientCookie(serializedCookie: string) {
    document.cookie = serializedCookie;
}

export function getClientCookie(name: string): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : null;
}
