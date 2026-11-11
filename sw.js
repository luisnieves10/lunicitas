// LuniCitas — service worker. Licencia AGPL-3.0
// Las páginas y los datos siempre se piden a la red (nunca se muestra una agenda vieja).
// Solo se guardan los archivos fijos (logo, letra, iconos) y una página para cuando no hay conexión.
const CACHE = 'lunicitas-v1';
const FIJOS = ['offline.html', 'logo.svg?v=3', 'assets/app/icono-192.png',
  'assets/fonts/poppins-latin-400-normal.woff2', 'assets/fonts/poppins-latin-600-normal.woff2', 'assets/fonts/poppins-latin-800-normal.woff2'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(FIJOS)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', (e) => {
  const r = e.request;
  if (r.method !== 'GET' || new URL(r.url).origin !== location.origin) return;
  if (r.mode === 'navigate') {
    e.respondWith(fetch(r).catch(() => caches.match('offline.html')));
    return;
  }
  const u = new URL(r.url);
  if (/\.(woff2|png|svg)$/.test(u.pathname)) {
    e.respondWith(caches.match(r).then((hit) => hit || fetch(r)));
  }
});
