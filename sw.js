const CACHE_NAME = 'karigor-cache-v2';
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

// Fetch events: Network-first for dynamic content, Cache-first for static assets
self.addEventListener('fetch', (event) => {
  const url = event.request.url;

  // Only handle HTTP/HTTPS (ignore browser extensions)
  if (!url.startsWith(self.location.origin) && !url.startsWith('https://')) {
    return;
  }

  // Static assets (fonts, images) can use Cache-First
  const isStaticAsset = url.includes('fonts.googleapis.com') || 
                        url.includes('fonts.gstatic.com') || 
                        url.match(/\.(png|jpg|jpeg|svg|css|js)$/i);
  
  if (isStaticAsset) {
    event.respondWith(
      caches.match(event.request).then((cachedResponse) => {
        if (cachedResponse) return cachedResponse;
        return fetch(event.request).then((networkResponse) => {
          if (networkResponse.status === 200) {
            const clone = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
          }
          return networkResponse;
        });
      })
    );
    return;
  }

  // For dynamic PHP pages (HTML content), ALWAYS use Network-First
  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        // If network succeeds, cache it and return
        if (networkResponse.status === 200 && event.request.method === 'GET') {
          const clone = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
        }
        return networkResponse;
      })
      .catch(() => {
        // If network fails (offline), try to return from cache
        return caches.match(event.request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }
          // If not in cache and it's a page request, show offline fallback
          if (event.request.headers.get('accept') && event.request.headers.get('accept').includes('text/html')) {
            return caches.match('./offline.php');
          }
        });
      })
  );
});
