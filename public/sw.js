const CACHE = 'bloomery-v2';

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

    const acceptsHtml = (e.request.headers.get('accept') || '').includes('text/html');
    const isPageRequest = e.request.mode === 'navigate' || acceptsHtml;
    const isStaticAsset = /\.(png|jpe?g|webp|gif|svg|ico|woff2?|css|js)(\?|$)/i.test(e.request.url);

    // Handle regular navigation and wire:navigate page requests network-first.
    if (isPageRequest) {
        e.respondWith(
            fetch(e.request).catch(async () => {
                const cached = await caches.match(e.request);

                return cached || new Response('Koneksi ke server terputus. Silakan muat ulang halaman.', {
                    status: 503,
                    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
                });
            })
        );
        return;
    }

    // Do not intercept ordinary application GET requests.
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
