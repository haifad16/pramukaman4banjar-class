const CACHE_NAME = 'absen-pramuka-v1';

self.addEventListener('install', event => {
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.map(key => caches.delete(key))
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;
  // Always fetch dynamic PHP & HTML directly from network
  event.respondWith(
    fetch(event.request).catch(() => caches.match(event.request))
  );
});
