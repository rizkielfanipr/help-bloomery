const CACHE = 'bloomery-v3';

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

    const isStaticAsset = /\.(png|jpe?g|webp|gif|svg|ico|woff2?|css|js)(\?|$)/i.test(e.request.url);

    // Let the browser handle application pages and Livewire navigation directly.
    if (!isStaticAsset) return;

    // For static assets: cache-first with a non-rejecting network fallback.
    e.respondWith(
        caches.match(e.request).then(async (cached) => {
            if (cached) return cached;

            try {
                const res = await fetch(e.request);
                if (res.ok) {
                    const clone = res.clone();
                    caches.open(CACHE).then((c) => c.put(e.request, clone));
                }

                return res;
            } catch {
                return new Response('', { status: 504, statusText: 'Asset unavailable' });
            }
        })
    );
});
