const CACHE_NAME = 'karigor-cache-v1';
const ASSETS_TO_CACHE = [
  './index.php',
  './db.php',
  './header.php',
  './footer.php',
  './offline.php',
  './assets/images/karigor-icon.png',
  './assets/images/icon.png',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// Install Service Worker and cache essential assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('PWA Service Worker: Caching critical assets');
      return cache.addAll(ASSETS_TO_CACHE);
    }).then(() => self.skipWaiting())
  );
});

// Activate event (cleaning old caches if any)
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            console.log('PWA Service Worker: Clearing old cache', cache);
            return caches.delete(cache);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch events: Network-first, fallback to cache, then fallback to offline.php
self.addEventListener('fetch', (event) => {
  // Only handle HTTP/HTTPS (ignore browser extensions)
  if (!event.request.url.startsWith(self.location.origin) && !event.request.url.startsWith('https://')) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        // Cache the newly retrieved resource if successful
        if (response.status === 200 && event.request.method === 'GET') {
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseClone);
          });
        }
        return response;
      })
      .catch(() => {
        // Fallback to cache
        return caches.match(event.request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }
          // If a page/document request fails (HTML), show offline fallback
          if (event.request.headers.get('accept').includes('text/html')) {
            return caches.match('./offline.php');
          }
        });
      })
  );
});
