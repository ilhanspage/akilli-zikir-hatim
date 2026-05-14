const CACHE_NAME = 'akilli-zikir-hatim-v20260512027200';
const APP_SHELL = [
  '/',
  '/index.php',
  '/manifest.json?v=20260512027200',
  '/app/index.html',
  '/app/assets/css/app.css?v=1.2.72',
  '/app/assets/js/app.js?v=1.2.72',
  '/app/assets/splash/splash-720x1280.png',
  '/app/assets/splash/splash-1080x1920.png',
  '/app/assets/icons/apple-touch-icon.png?v=20260512027200',
  '/app/assets/icons/favicon-16.png?v=20260512027200',
  '/app/assets/icons/favicon-32.png?v=20260512027200',
  '/app/assets/icons/icon-192.png?v=20260512027200',
  '/app/assets/icons/icon-384.png?v=20260512027200',
  '/app/assets/icons/icon-512.png?v=20260512027200',
  '/app/assets/icons/maskable-512.png?v=20260512027200',
  '/app/assets/splash/splash-1080x1920.png?v=20260512027200',
  '/app/assets/splash/splash-720x1280.png?v=20260512027200',
  '/app/assets/img/kuran-hatim-v1_2_8.svg?v=20260512027200'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(APP_SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(event.request).catch(() => new Response(JSON.stringify({ ok: false, offline: true, message: 'Çevrimdışı mod aktif.' }), {
        headers: { 'Content-Type': 'application/json' }
      }))
    );
    return;
  }

  if (event.request.mode === 'navigate') {
    event.respondWith(fetch(event.request).catch(() => caches.match('/')));
    return;
  }

  event.respondWith(
    caches.match(event.request).then(cached => cached || fetch(event.request).then(response => {
      const copy = response.clone();
      caches.open(CACHE_NAME).then(cache => cache.put(event.request, copy));
      return response;
    }).catch(() => cached))
  );
});
