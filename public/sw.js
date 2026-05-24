const CACHE_NAME = 'infraguard-static-v3';
const STATIC_ASSETS = [
    '/favicon.ico',
    '/favicon.png',
    '/icon-192.png',
    '/icon-512.png',
    '/manifest.json',
    '/offline.html'
];

const LIVE_API_PREFIXES = [
    '/api/'
];

const SESSION_PATH_PREFIXES = [
    '/login',
    '/register',
    '/logout',
    '/dashboard',
    '/forgot-password',
    '/reset-password',
    '/confirm-password',
    '/verify-email',
    '/email',
    '/two-factor',
    '/profile',
    '/admin'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys
                .filter((key) => key !== CACHE_NAME)
                .map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    const isSessionPath = SESSION_PATH_PREFIXES.some((prefix) => url.pathname === prefix || url.pathname.startsWith(`${prefix}/`));
    const hasCredentialSignals = request.credentials === 'include'
        || request.headers.has('Authorization')
        || request.headers.has('Cookie');

    if (LIVE_API_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) {
        return;
    }

    if (isSessionPath || hasCredentialSignals) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html'))
        );
        return;
    }

    const isStaticAsset = request.destination === 'style'
        || request.destination === 'script'
        || request.destination === 'image'
        || request.destination === 'font'
        || url.pathname.startsWith('/build/')
        || STATIC_ASSETS.includes(url.pathname);

    if (!isStaticAsset) {
        return;
    }

    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(request).then((networkResponse) => {
                if (networkResponse && networkResponse.ok && networkResponse.type === 'basic') {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, responseClone));
                }

                return networkResponse;
            });
        })
    );
});
