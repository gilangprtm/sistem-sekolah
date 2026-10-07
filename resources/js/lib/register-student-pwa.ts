export function registerStudentPwa(): void {
    if (
        typeof window === 'undefined' ||
        !window.location.pathname.startsWith('/student') ||
        !('serviceWorker' in navigator)
    ) {
        return;
    }

    window.addEventListener('load', () => {
        void navigator.serviceWorker.register('/sw.js', { scope: '/student' });
    });
}
