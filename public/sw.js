/* ══════════════════════════════════════════════════════════════
   KabuTech — Service Worker (offline support)

   When Wi-Fi / the network is lost:
   • The dashboard still opens from cache (app shell + last page)
   • API GET requests fall back to the last successful response
     (marked with the "X-SW-Offline: 1" header so the UI knows
      the data is stale)
   • Any page never visited before shows /offline.html

   Bump CACHE_VERSION whenever you change cached static files.
   ══════════════════════════════════════════════════════════════ */

const CACHE_VERSION = 'v25';
const STATIC_CACHE  = `kabutech-static-${CACHE_VERSION}`;
const PAGE_CACHE    = `kabutech-pages-${CACHE_VERSION}`;
const API_CACHE     = `kabutech-api-${CACHE_VERSION}`;
const ALL_CACHES    = [STATIC_CACHE, PAGE_CACHE, API_CACHE];

// Base path of the app (works on "/" and on XAMPP sub-folders like "/thesis-ui/public/")
const BASE = new URL(self.registration.scope).pathname.replace(/\/$/, '');
const u = (path) => `${BASE}${path}`;

const OFFLINE_URL = u('/offline.html');

// App shell — downloaded once at install time
const PRECACHE_URLS = [
    OFFLINE_URL,
    u('/css/dashboard.css'),
    u('/js/dashboard.js'),
    u('/images/mushroom-logo.jpg'),
    u('/images/icon-192.png'),
    u('/images/icon-512.png'),
    u('/favicon.ico'),
    u('/vendor/bootstrap/5.3.2/css/bootstrap.min.css'),
    u('/vendor/bootstrap/5.3.2/js/bootstrap.bundle.min.js'),
    u('/vendor/chart.js/4.4.1/chart.umd.min.js'),
    u('/vendor/fontawesome/6.4.0/css/all.min.css'),
    u('/vendor/fontawesome/6.4.0/webfonts/fa-solid-900.woff2'),
    u('/vendor/fontawesome/6.4.0/webfonts/fa-regular-400.woff2'),
    u('/vendor/fontawesome/6.4.0/webfonts/fa-brands-400.woff2'),
];

// API endpoints that must never be cached (live video, file downloads, etc.)
const API_NO_CACHE = [
    '/api/camera-stream',
    '/api/camera/live-frame',
    '/api/camera/control',
    '/api/misting/',
    '/api/fan/',
    '/api/csrf-token',
    '/api/export/',
];

// ── Install: pre-cache the app shell + current dashboard HTML ──
self.addEventListener('install', (event) => {
    event.waitUntil(
        Promise.all([
            caches.open(STATIC_CACHE).then((cache) =>
                Promise.all(
                    PRECACHE_URLS.map((url) =>
                        cache.add(new Request(url, { cache: 'reload' }))
                            .catch((err) => console.warn('[SW] Precache failed:', url, err))
                    )
                )
            ),
            // Immediately cache the root dashboard page so offline reload works on first visit
            caches.open(PAGE_CACHE).then((cache) =>
                fetch(u('/'), { credentials: 'same-origin' })
                    .then((res) => {
                        if (res.ok && !res.redirected) {
                            return cache.put(u('/'), res);
                        }
                    })
                    .catch((err) => console.warn('[SW] Initial page cache failed:', err))
            ),
        ]).then(() => self.skipWaiting())
    );
});

// ── Activate: remove caches from older versions ──
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((k) => k.startsWith('kabutech-') && !ALL_CACHES.includes(k))
                    .map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

// ── Messages from the page ──
self.addEventListener('message', (event) => {
    const data = event.data || {};
    // Called on logout so the next person can't see cached private data offline
    if (data.type === 'CLEAR_USER_CACHE') {
        event.waitUntil(Promise.all([caches.delete(PAGE_CACHE), caches.delete(API_CACHE)]));
    }
    // Page asks SW to snapshot the current URL right after registration
    if (data.type === 'CACHE_CURRENT_PAGE' && data.url) {
        event.waitUntil(
            caches.open(PAGE_CACHE).then((cache) =>
                fetch(data.url, { credentials: 'same-origin' })
                    .then((res) => {
                        if (res.ok && !res.redirected) {
                            return cache.put(data.url, res);
                        }
                    })
                    .catch(() => {})
            )
        );
    }
});

// ── Fetch routing ──
self.addEventListener('fetch', (event) => {
    const req = event.request;

    // Only GET requests are cached. POST/PUT/DELETE go straight to the network.
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // Google Fonts — cache-first (cross-origin)
    if (url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com') {
        event.respondWith(cacheFirst(req, STATIC_CACHE));
        return;
    }

    // Ignore every other cross-origin request (e.g. the ESP32-CAM stream)
    if (url.origin !== self.location.origin) return;

    // Page navigations (opening / reloading the dashboard)
    if (req.mode === 'navigate') {
        const path = url.pathname.slice(BASE.length);
        if (/^\/(login|register|forgot-password|reset-password)/.test(path)) {
            return; // Never cache auth pages with CSRF tokens
        }
        event.respondWith(networkFirstPage(req));
        return;
    }

    // API calls
    if (url.pathname.startsWith(u('/api/'))) {
        const apiPath = url.pathname.slice(BASE.length);
        if (API_NO_CACHE.some((p) => apiPath.startsWith(p))) return;
        event.respondWith(networkFirstApi(req));
        return;
    }

    // Static assets (css, js, images, fonts)
    if (/\.(css|js|png|jpe?g|gif|webp|svg|ico|woff2?|ttf)$/i.test(url.pathname)) {
        event.respondWith(staleWhileRevalidate(req, STATIC_CACHE));
    }
});

// ── Strategies ──

function isBrowserOffline() {
    return typeof navigator !== 'undefined' && navigator.onLine === false;
}

// Pages: ALWAYS try network first so login & live pages are never blocked
// by a stale navigator.onLine flag. Fall back to cache / offline.html only
// if the network request actually fails.
async function networkFirstPage(req) {
    try {
        const res = await fetch(req);
        if (res.ok && !res.redirected && res.type !== 'opaqueredirect') {
            const cache = await caches.open(PAGE_CACHE);
            cache.put(req, res.clone());
        }
        return res;
    } catch (err) {
        const pageCache = await caches.open(PAGE_CACHE);
        const cached = (await pageCache.match(req, { ignoreSearch: true }))
            || (await pageCache.match(u('/'), { ignoreSearch: true }));
        if (cached) return cached;

        const offline = await caches.match(OFFLINE_URL);
        return offline || new Response('<h1>Offline</h1>', {
            status: 503,
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
        });
    }
}

// API: try network (unless offline), remember last good JSON, fall back to it when offline
async function networkFirstApi(req) {
    if (!isBrowserOffline()) {
        try {
            const res = await fetch(req);
            if (res.ok) {
                const cache = await caches.open(API_CACHE);
                cache.put(req, res.clone());
            }
            return res;
        } catch (err) {
            // Fall through to cache below
        }
    }

    const cache = await caches.open(API_CACHE);
    const cached = (await cache.match(req)) || (await cache.match(req, { ignoreSearch: true }));
    if (cached) return markAsOffline(cached);

    return new Response(JSON.stringify({
        offline: true,
        message: 'You are offline. No cached data is available for this request.',
    }), {
        status: 503,
        headers: { 'Content-Type': 'application/json', 'X-SW-Offline': '1' },
    });
}

// Static: serve from cache instantly, refresh in background when online
async function staleWhileRevalidate(req, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(req, { ignoreSearch: true });
    if (isBrowserOffline() && cached) return cached;

    const network = fetch(req)
        .then((res) => {
            if (res.ok) cache.put(req, res.clone());
            return res;
        })
        .catch(() => null);
    return cached || (await network) || new Response('', { status: 504 });
}

async function cacheFirst(req, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(req);
    if (cached) return cached;
    if (isBrowserOffline()) return new Response('', { status: 504 });
    try {
        const res = await fetch(req);
        if (res.ok || res.type === 'opaque') cache.put(req, res.clone());
        return res;
    } catch (err) {
        return new Response('', { status: 504 });
    }
}

// Copy a cached response and tag it so the dashboard can show "stale data"
async function markAsOffline(res) {
    const headers = new Headers(res.headers);
    headers.set('X-SW-Offline', '1');
    const body = await res.blob();
    return new Response(body, { status: res.status, statusText: res.statusText, headers });
}

// ══════════════════════════════════════════════════════════════
// ── Web Push Notifications ──
// ══════════════════════════════════════════════════════════════
self.addEventListener('push', (event) => {
    let data = {
        title: "Fiona's Mushroom Farm",
        body: 'New mushroom farm alert.',
        type: 'alert',
        url: '/',
    };

    if (event.data) {
        try {
            data = Object.assign(data, event.data.json());
        } catch (e) {
            data.body = event.data.text() || data.body;
        }
    }

    const options = {
        body: data.body,
        icon: u('/images/mushroom-logo.jpg'),
        badge: u('/images/mushroom-logo.jpg'),
        tag: `kabutech-${data.type || 'alert'}`,
        renotify: true,
        vibrate: [200, 100, 200],
        data: { url: u(data.url || '/') },
    };

    event.waitUntil(self.registration.showNotification(data.title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url) || u('/');

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if (client.url.includes(BASE || '/') && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
