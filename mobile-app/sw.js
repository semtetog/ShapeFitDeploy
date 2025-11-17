// Service Worker para ShapeFit Mobile App
// Versão 3 - Otimizado para Android/iOS com Capacitor
const CACHE_NAME = 'shapefit-v4-online-first'; // Nova versão para forçar atualização
const OFFLINE_URL = 'offline.html';

// No momento da instalação, pré-cache apenas a página offline.
// O resto será cacheado sob demanda.
self.addEventListener('install', (event) => {
    console.log('[SW] Instalando Service Worker v4...');
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[SW] Pré-cache da página offline.');
            return cache.add(OFFLINE_URL);
        })
    );
    // Força o novo Service Worker a se ativar imediatamente.
    self.skipWaiting();
});

// Na ativação, limpa caches antigos.
self.addEventListener('activate', (event) => {
    console.log('[SW] Ativando Service Worker v4...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[SW] Removendo cache antigo:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            console.log('[SW] Ativado e controlando clientes.');
            return self.clients.claim();
        })
    );
});

// Intercepta as requisições de rede.
self.addEventListener('fetch', (event) => {
    const { request } = event;

    // ESTRATÉGIA PARA NAVEGAÇÃO DE PÁGINAS
    // Tenta a rede primeiro. Se falhar (offline), mostra a página offline.
    if (request.mode === 'navigate') {
        event.respondWith(
            (async () => {
                try {
                    // Tenta buscar a página da internet.
                    const networkResponse = await fetch(request);
                    return networkResponse;
                } catch (error) {
                    // Se a busca falhar (está offline), serve a página offline do cache.
                    console.log('[SW] Falha na busca de navegação. Servindo página offline.', error);
                    const cache = await caches.open(CACHE_NAME);
                    const cachedResponse = await cache.match(OFFLINE_URL);
                    return cachedResponse;
                }
            })()
        );
        return;
    }

    // ESTRATÉGIA PARA ASSETS (CSS, JS, Imagens)
    // Cache first: serve do cache se disponível para velocidade, senão busca na rede.
    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            // Se o asset estiver no cache, retorna ele.
            if (cachedResponse) {
                return cachedResponse;
            }

            // Se não, busca da rede.
            return fetch(request).then((networkResponse) => {
                // Se a resposta for válida, clona, cacheia e retorna.
                if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
                    const responseToCache = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseToCache);
                    });
                }
                return networkResponse;
            });
        })
    );
});





