// Service Worker para PWA ShapeFIT
const CACHE_NAME = 'shapefit-v6'; // Versão do cache atualizada para forçar a atualização
const urlsToCache = [
  '/',
  '/assets/css/style.css',
  '/assets/js/script.js',
  '/assets/js/banner-carousel.js',
  '/assets/images/icon-192x192.png', // Caminho corrigido
  '/assets/images/icon-512x512.png'  // Caminho corrigido
];

// Install event - cache resources
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Cache aberto');
        return cache.addAll(urlsToCache);
      })
  );
});

// Fetch event - serve cached content
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Return cached version or fetch from network
        return response || fetch(event.request);
      }
    )
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('Deletando cache antigo:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});

