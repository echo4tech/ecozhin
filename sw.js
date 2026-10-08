// EcoZîn service worker. Static assets: stale-while-revalidate. Catalog API: network-first with cache fallback.
// Pages and authenticated API responses are never cached (they contain personal data).
const VERSION = 'v2';
const STATIC = 'static-' + VERSION, DATA = 'data-' + VERSION;
// The worker lives in the app root, so its own URL tells us the sub-folder ("" or "/ecozhin").
const BASE = new URL('./', self.location).pathname.replace(/\/$/, '');
const SHELL = ['/assets/css/app.css', '/assets/js/api.js', '/assets/js/ui.js', '/assets/js/views.js', '/assets/js/app.js', '/assets/js/auth.js', '/assets/images/icon.svg', '/offline.html'].map(p => BASE + p);
const CATALOG = /^\/api\/(units|products|waste-types|geo\/|settings\/public)/;

self.addEventListener('install', e => { e.waitUntil(caches.open(STATIC).then(c => c.addAll(SHELL)).then(() => self.skipWaiting())); });
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => ![STATIC, DATA].includes(k)).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', e => {
  const req = e.request, url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== location.origin) return;
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match(BASE + '/offline.html')));
  } else if (CATALOG.test(url.pathname.slice(BASE.length))) {
    e.respondWith(fetch(req).then(r => { if (r.ok) { const c = r.clone(); caches.open(DATA).then(d => d.put(req, c)); } return r; }).catch(() => caches.match(req)));
  } else if (url.pathname.startsWith(BASE + '/assets/') && !url.pathname.startsWith(BASE + '/assets/uploads/')) {
    e.respondWith(caches.open(STATIC).then(async c => {
      const hit = await c.match(req, { ignoreSearch: true });
      const net = fetch(req).then(r => { if (r.ok) c.put(req, r.clone()); return r; }).catch(() => hit);
      return hit || net;
    }));
  }
});
