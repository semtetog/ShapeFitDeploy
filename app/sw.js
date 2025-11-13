// Service Worker Avançado para App Offline-First
const CACHE_VERSION = 'shapefit-v1.0.0';
const STATIC_CACHE = `shapefit-static-${CACHE_VERSION}`;
const DYNAMIC_CACHE = `shapefit-dynamic-${CACHE_VERSION}`;
const IMAGE_CACHE = `shapefit-images-${CACHE_VERSION}`;
const API_CACHE = `shapefit-api-${CACHE_VERSION}`;

// Assets estáticos para cache permanente
const STATIC_ASSETS = [
  '/',
  '/index.html',
  '/pages/login.html',
  '/pages/dashboard.html',
  '/pages/diary.html',
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/assets/js/offline.js',
  '/assets/js/sync.js',
  '/assets/js/db.js',
  '/manifest.json'
];

// Estratégias de cache
const CACHE_STRATEGIES = {
  // Cache First: Para assets estáticos
  CACHE_FIRST: 'cache-first',
  // Network First: Para dados da API
  NETWORK_FIRST: 'network-first',
  // Stale While Revalidate: Para conteúdo que pode ser atualizado
  STALE_WHILE_REVALIDATE: 'stale-while-revalidate',
  // Network Only: Para dados críticos que sempre precisam ser frescos
  NETWORK_ONLY: 'network-only'
};

// Install - Cache assets estáticos
self.addEventListener('install', (event) => {
  console.log('[SW] Installing service worker...');
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => {
        console.log('[SW] Caching static assets');
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => self.skipWaiting())
  );
});

// Activate - Limpar caches antigos
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating service worker...');
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== STATIC_CACHE && 
              cacheName !== DYNAMIC_CACHE && 
              cacheName !== IMAGE_CACHE &&
              cacheName !== API_CACHE) {
            console.log('[SW] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
    .then(() => self.clients.claim())
  );
});

// Fetch - Interceptar requisições
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Ignorar requisições do admin
  if (url.pathname.startsWith('/admin')) {
    return;
  }

  // Assets estáticos - Cache First
  if (isStaticAsset(request)) {
    event.respondWith(cacheFirst(request, STATIC_CACHE));
    return;
  }

  // Imagens - Cache First com limite
  if (isImage(request)) {
    event.respondWith(cacheFirstWithLimit(request, IMAGE_CACHE, 50));
    return;
  }

  // API - Network First com fallback
  if (isAPIRequest(request)) {
    event.respondWith(networkFirstWithQueue(request, API_CACHE));
    return;
  }

  // HTML - Network First
  if (isHTML(request)) {
    event.respondWith(networkFirst(request, DYNAMIC_CACHE));
    return;
  }

  // Default - Network First
  event.respondWith(networkFirst(request, DYNAMIC_CACHE));
});

// Helper Functions
function isStaticAsset(request) {
  return request.url.match(/\.(css|js|woff|woff2|ttf|eot)$/);
}

function isImage(request) {
  return request.url.match(/\.(jpg|jpeg|png|gif|webp|svg|ico)$/);
}

function isAPIRequest(request) {
  return request.url.includes('/api/');
}

function isHTML(request) {
  return request.headers.get('accept').includes('text/html');
}

// Cache First Strategy
async function cacheFirst(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  
  if (cached) {
    return cached;
  }
  
  try {
    const response = await fetch(request);
    if (response.ok) {
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    console.error('[SW] Fetch failed:', error);
    // Retornar página offline se for HTML
    if (isHTML(request)) {
      return caches.match('/pages/offline.html');
    }
    throw error;
  }
}

// Network First Strategy
async function networkFirst(request, cacheName) {
  const cache = await caches.open(cacheName);
  
  try {
    const response = await fetch(request);
    if (response.ok) {
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    console.log('[SW] Network failed, trying cache...');
    const cached = await cache.match(request);
    if (cached) {
      return cached;
    }
    throw error;
  }
}

// Network First com Queue para API
async function networkFirstWithQueue(request, cacheName) {
  const cache = await caches.open(cacheName);
  
  try {
    const response = await fetch(request);
    if (response.ok) {
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    console.log('[SW] API request failed, using cache and queueing...');
    
    // Retornar do cache se disponível
    const cached = await cache.match(request);
    if (cached) {
      // Notificar app para enfileirar requisição
      self.clients.matchAll().then(clients => {
        clients.forEach(client => {
          client.postMessage({
            type: 'API_OFFLINE',
            url: request.url,
            method: request.method,
            body: request.body
          });
        });
      });
      return cached;
    }
    
    // Se não tem cache e é POST/PUT/DELETE, retornar erro controlado
    if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(request.method)) {
      return new Response(JSON.stringify({
        success: false,
        offline: true,
        queued: true,
        message: 'Ação enfileirada para sincronização quando online'
      }), {
        status: 202,
        headers: { 'Content-Type': 'application/json' }
      });
    }
    
    throw error;
  }
}

// Cache First com limite de itens
async function cacheFirstWithLimit(request, cacheName, maxItems) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  
  if (cached) {
    return cached;
  }
  
  try {
    const response = await fetch(request);
    if (response.ok) {
      // Verificar limite
      const keys = await cache.keys();
      if (keys.length >= maxItems) {
        // Remover item mais antigo
        const firstKey = keys[0];
        await cache.delete(firstKey);
      }
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    // Retornar placeholder de imagem se disponível
    return caches.match('/assets/images/placeholder.png');
  }
}

// Background Sync (quando disponível)
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-data') {
    event.waitUntil(syncData());
  }
});

async function syncData() {
  // Implementar lógica de sincronização
  console.log('[SW] Background sync triggered');
  // Notificar app para sincronizar
  self.clients.matchAll().then(clients => {
    clients.forEach(client => {
      client.postMessage({ type: 'SYNC_DATA' });
    });
  });
}

// Push Notifications (opcional)
self.addEventListener('push', (event) => {
  const data = event.data.json();
  const options = {
    body: data.body,
    icon: '/assets/images/icon-192x192.png',
    badge: '/assets/images/badge.png',
    vibrate: [200, 100, 200],
    data: data
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

