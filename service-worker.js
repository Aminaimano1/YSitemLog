const CACHE_NAME = 'itemlog-cache-v1';
const urlsToCache = [
  '/',
  '/ItemLog/',
  '/ItemLog/index.php',
  // Add other important files here (CSS, JS, images)
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => response || fetch(event.request))
  );
}); 