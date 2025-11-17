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
            // Tenta buscar offline.html da rede primeiro
            return fetch(OFFLINE_URL).then((response) => {
                if (response && response.ok) {
                    return cache.put(OFFLINE_URL, response);
                }
                throw new Error('Failed to fetch offline.html');
            }).catch((error) => {
                // Se falhar (usuário offline na primeira instalação), cria uma versão inline
                console.warn('[SW] Não conseguiu buscar offline.html, criando versão inline:', error);
                const offlineHTML = `<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShapeFit - Offline</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #101010;
            color: #F5F5F5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 24px;
        }
        .offline-popup-overlay {
            width: 100%;
            max-width: 380px;
        }
        .offline-popup-content {
            background: linear-gradient(165deg, rgba(60, 60, 60, 0.3) 0%, rgba(45, 45, 45, 0.2) 100%);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 30px 24px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        .offline-popup-title {
            font-size: 22px;
            font-weight: 700;
            color: #F5F5F5;
            margin-bottom: 12px;
        }
        .offline-popup-message {
            font-size: 15px;
            color: #A3A3A3;
            margin-bottom: 10px;
            line-height: 1.55;
        }
        .offline-popup-button {
            background: linear-gradient(45deg, #FFAE00, #F83600);
            color: #F5F5F5 !important;
            border: none;
            border-radius: 16px;
            padding: 14px 24px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: 18px;
            -webkit-tap-highlight-color: transparent;
        }
        .offline-popup-button:active {
            transform: scale(0.98);
        }
    </style>
</head>
<body>
    <div class="offline-popup-overlay">
        <div class="offline-popup-content">
            <h2 class="offline-popup-title">Sem Conexão</h2>
            <p class="offline-popup-message">Parece que você está sem internet no momento.</p>
            <p class="offline-popup-message">Verifique sua conexão e tente novamente.</p>
            <button class="offline-popup-button" onclick="window.location.reload()">Tentar Novamente</button>
        </div>
    </div>
</body>
</html>`;
                return cache.put(OFFLINE_URL, new Response(offlineHTML, {
                    headers: { 'Content-Type': 'text/html' }
                }));
            });
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
    if (request.mode === 'navigate' || (request.method === 'GET' && request.headers.get('accept') && request.headers.get('accept').includes('text/html'))) {
        event.respondWith(
            (async () => {
                try {
                    // Tenta buscar a página da internet com timeout curto
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 3000); // 3 segundos de timeout
                    
                    const networkResponse = await fetch(request, {
                        signal: controller.signal,
                        cache: 'no-store'
                    });
                    
                    clearTimeout(timeoutId);
                    
                    // Se a resposta for válida, retorna ela
                    if (networkResponse && networkResponse.status === 200) {
                        return networkResponse;
                    }
                    // Se a resposta não for válida, cai no catch
                    throw new Error('Network response not ok');
                } catch (error) {
                    // Se a busca falhar (está offline ou timeout), serve a página offline do cache.
                    console.log('[SW] Falha na busca de navegação. Servindo página offline.', error.name || error);
                    const cache = await caches.open(CACHE_NAME);
                    let cachedResponse = await cache.match(OFFLINE_URL);
                    
                    // Se não estiver no cache, tenta buscar novamente (pode estar online mas a página específica falhou)
                    if (!cachedResponse) {
                        try {
                            const offlineFetch = await fetch(OFFLINE_URL, { cache: 'no-store' });
                            if (offlineFetch && offlineFetch.ok) {
                                cachedResponse = offlineFetch;
                                cache.put(OFFLINE_URL, offlineFetch.clone());
                            }
                        } catch (e) {
                            console.warn('[SW] Erro ao buscar offline.html da rede:', e.name);
                        }
                    }
                    
                    // Se ainda não tiver, cria uma resposta básica inline
                    if (!cachedResponse) {
                        console.warn('[SW] Criando resposta offline inline como último recurso');
                        const offlineHTML = `<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShapeFit - Offline</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #101010;
            color: #F5F5F5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 24px;
        }
        .offline-popup-overlay {
            width: 100%;
            max-width: 380px;
        }
        .offline-popup-content {
            background: linear-gradient(165deg, rgba(60, 60, 60, 0.3) 0%, rgba(45, 45, 45, 0.2) 100%);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 30px 24px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        .offline-popup-title {
            font-size: 22px;
            font-weight: 700;
            color: #F5F5F5;
            margin-bottom: 12px;
        }
        .offline-popup-message {
            font-size: 15px;
            color: #A3A3A3;
            margin-bottom: 10px;
            line-height: 1.55;
        }
        .offline-popup-button {
            background: linear-gradient(45deg, #FFAE00, #F83600);
            color: #F5F5F5 !important;
            border: none;
            border-radius: 16px;
            padding: 14px 24px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: 18px;
            -webkit-tap-highlight-color: transparent;
        }
        .offline-popup-button:active {
            transform: scale(0.98);
        }
    </style>
</head>
<body>
    <div class="offline-popup-overlay">
        <div class="offline-popup-content">
            <h2 class="offline-popup-title">Sem Conexão</h2>
            <p class="offline-popup-message">Parece que você está sem internet no momento.</p>
            <p class="offline-popup-message">Verifique sua conexão e tente novamente.</p>
            <button class="offline-popup-button" onclick="window.location.reload()">Tentar Novamente</button>
        </div>
    </div>
</body>
</html>`;
                        return new Response(offlineHTML, {
                            headers: { 'Content-Type': 'text/html' }
                        });
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

