// Service Worker para PWA ShapeFIT
// Versão atualizada com estratégia Online-First para proteção offline robusta
const CACHE_NAME = 'shapefit-v7-online-first'; // Nova versão para forçar atualização
const OFFLINE_URL = '/offline.html';

// No momento da instalação, pré-cache apenas a página offline.
// O resto será cacheado sob demanda.
self.addEventListener('install', (event) => {
    console.log('[SW] Instalando Service Worker v7 (Online-First)...');
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
    console.log('[SW] Ativando Service Worker v7...');
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
                    // Se a resposta for válida, retorna ela
                    if (networkResponse && networkResponse.status === 200) {
                        return networkResponse;
                    }
                    // Se a resposta não for válida, cai no catch
                    throw new Error('Network response not ok');
                } catch (error) {
                    // Se a busca falhar (está offline), serve a página offline do cache.
                    console.log('[SW] Falha na busca de navegação. Servindo página offline.', error);
                    const cache = await caches.open(CACHE_NAME);
                    let cachedResponse = await cache.match(OFFLINE_URL);
                    
                    // Se não estiver no cache, tenta buscar novamente
                    if (!cachedResponse) {
                        try {
                            cachedResponse = await fetch(OFFLINE_URL);
                            if (cachedResponse) {
                                cache.put(OFFLINE_URL, cachedResponse.clone());
                            }
                        } catch (e) {
                            console.error('[SW] Erro ao buscar offline.html:', e);
                        }
                    }
                    
                    // Se ainda não tiver, cria uma resposta básica
                    if (!cachedResponse) {
                        return new Response(
                            '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Offline</title></head><body style="background:#101010;color:#fff;display:flex;justify-content:center;align-items:center;min-height:100vh;font-family:sans-serif"><h1>Sem Conexão</h1></body></html>',
                            { headers: { 'Content-Type': 'text/html' } }
                        );
                    }
                    
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

