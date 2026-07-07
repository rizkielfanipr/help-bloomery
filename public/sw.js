const CACHE = 'bloomery-v1';

const PRECACHE = [
    '/icons/pwa-192.png',
    '/icons/pwa-512.png',
];

self.addEventListener('install', (e) => {
    e.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(PRECACHE))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (e) => {
    // Only handle GET requests, skip cross-origin and non-http
    if (e.request.method !== 'GET') return;
    if (!e.request.url.startsWith(self.location.origin)) return;

    // For navigation requests: network-first so Livewire always gets fresh HTML
    if (e.request.mode === 'navigate') {
        e.respondWith(
            fetch(e.request).catch(() => caches.match(e.request))
        );
        return;
    }

    // For static assets: cache-first
    e.respondWith(
        caches.match(e.request).then(
            (cached) => cached || fetch(e.request).then((res) => {
                if (res.ok && e.request.url.match(/\.(png|jpg|svg|woff2?|css|js)(\?|$)/)) {
                    const clone = res.clone();
                    caches.open(CACHE).then((c) => c.put(e.request, clone));
                }
                return res;
            })
        )
    );
});
