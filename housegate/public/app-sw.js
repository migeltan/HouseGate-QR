/*
 * HouseGate app-wide service worker. Caches static assets ONLY (css, js, vendor, fonts, images).
 * Never touches HTML pages, /storage photos, /live/* or any non-GET request: those always hit the network.
 * The Directory has its own worker (scope /directory); the more specific scope wins there, so the two never clash.
 */
const CACHE = "hg-app-static-v1";
const STATIC_PREFIXES = ["/css/", "/js/", "/vendor/", "/fonts/", "/images/"];

self.addEventListener("install", () => self.skipWaiting());

self.addEventListener("activate", (event) => {
    event.waitUntil(
        (async () => {
            for (const name of await caches.keys()) {
                if (name.startsWith("hg-app-static-") && name !== CACHE)
                    await caches.delete(name);
            }
            await self.clients.claim();
        })(),
    );
});

self.addEventListener("fetch", (event) => {
    const request = event.request;
    if (request.method !== "GET" || request.headers.has("range")) return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    if (!STATIC_PREFIXES.some((p) => url.pathname.startsWith(p))) return;

    // ?v=<filemtime> changes whenever the file does, so a hit can never be stale: cache first.
    // Anything unversioned (fonts, icons, images) is served from cache and refreshed in the background.
    if (url.searchParams.has("v")) event.respondWith(cacheFirst(event));
    else event.respondWith(staleWhileRevalidate(event));
});

function cacheable(res) {
    return res.status === 200 && res.type === "basic";
}

async function cacheFirst(event) {
    const cache = await caches.open(CACHE);
    const hit = await cache.match(event.request);
    if (hit) return hit;
    const res = await fetch(event.request);
    if (cacheable(res))
        event.waitUntil(store(cache, event.request, res.clone()));
    return res;
}

async function staleWhileRevalidate(event) {
    const cache = await caches.open(CACHE);
    const hit = await cache.match(event.request);
    const network = fetch(event.request).then((res) => {
        if (cacheable(res))
            event.waitUntil(store(cache, event.request, res.clone()));
        return res;
    });
    if (hit) {
        event.waitUntil(network.catch(() => {}));
        return hit;
    }
    return network;
}

// Keep one copy per file: a new ?v= replaces the old entry for the same path.
async function store(cache, request, response) {
    await cache.put(request, response);
    const now = new URL(request.url);
    for (const key of await cache.keys()) {
        const old = new URL(key.url);
        if (old.pathname === now.pathname && old.search !== now.search)
            await cache.delete(key);
    }
}
