const CACHE_NAME = 'karigor-cache-v1';
const ASSETS_TO_CACHE = [
  './index.php',
  './db.php',
  './header.php',
  './footer.php',
  './offline.php',
  './assets/images/karigor-icon.png',
  './assets/images/icon.png',
  'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap',
  'https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200'
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
  const url = event.request.url;

  // Only handle HTTP/HTTPS (ignore browser extensions)
  if (!url.startsWith(self.location.origin) && !url.startsWith('https://')) {
    return;
  }

  // Intercept and cache external fonts or CDNs dynamically
  const isGoogleFont = url.includes('fonts.googleapis.com') || url.includes('fonts.gstatic.com');
  
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      // Return cached fonts or files immediately if offline or cached
      if (cachedResponse) {
        // Fetch new version in background to update cache (stale-while-revalidate)
        if (event.request.method === 'GET' && !isGoogleFont) {
          fetch(event.request).then((networkResponse) => {
            if (networkResponse.status === 200) {
              caches.open(CACHE_NAME).then((cache) => cache.put(event.request, networkResponse));
            }
          }).catch(() => {});
        }
        return cachedResponse;
      }

      return fetch(event.request)
        .then((response) => {
          if (response.status === 200 && event.request.method === 'GET') {
            const responseClone = response.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(event.request, responseClone);
            });
          }
          return response;
        })
        .catch(() => {
          // If a page/document request fails (HTML), show offline fallback
          if (event.request.headers.get('accept') && event.request.headers.get('accept').includes('text/html')) {
            return caches.match('./offline.php');
          }
        });
    })
  );
});
