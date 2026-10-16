const SERVICE_WORKER_URL = '/sw.js?v=school-logo-3';

export function registerStudentPwa(): void {
    if (
        typeof window === 'undefined' ||
        (window.location.pathname !== '/' &&
            !window.location.pathname.startsWith('/student')) ||
        !('serviceWorker' in navigator)
    ) {
        return;
    }

    window.addEventListener('load', () => {
        void navigator.serviceWorker.register(SERVICE_WORKER_URL, {
            scope: '/',
            updateViaCache: 'none',
        });
    });
}
