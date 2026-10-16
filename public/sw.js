const PREVIOUS_CACHE_NAME = 'student-app-shell-v2';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.delete(PREVIOUS_CACHE_NAME));
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);

    if (
        url.origin !== self.location.origin ||
        (url.pathname !== '/' && !url.pathname.startsWith('/student'))
    ) {
        return;
    }

    event.respondWith(fetch(event.request));
});