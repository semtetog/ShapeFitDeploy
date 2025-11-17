// Service Worker para PWA ShapeFIT
// Estratégia Híbrida: Network-First com Precache de Páginas Críticas
// Versão: 8.0.0 - Data: 2025-11-17
const CACHE_VERSION = '8.0.0';
const CACHE_NAME = `shapefit-v${CACHE_VERSION}`;
const OFFLINE_URL = '/offline.html';

// Páginas críticas para precache (sempre disponíveis offline)
const CRITICAL_PAGES = [
    '/',
    '/index.php',
    '/auth/login.php',
    '/main_app.php',
    '/offline.html'
];

// URLs que NUNCA devem ser cacheadas (APIs)
const API_PATTERNS = [
    '/api/',
    '/actions/',
    'ajax_',
    '.php?action=',
    'calculate_nutrition',
    'sync.php',
    'checkin.php'
];

// Verifica se uma URL é uma API
function isAPIRequest(url) {
    return API_PATTERNS.some(pattern => url.includes(pattern));
}

// Instalação: Precache páginas críticas
self.addEventListener('install', (event) => {
    console.log(`[SW] Instalando Service Worker v${CACHE_VERSION}...`);
    
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[SW] Pré-cacheando páginas críticas...');
            
            // Tenta buscar todas as páginas críticas
            const cachePromises = CRITICAL_PAGES.map(url => {
                return fetch(url).then((response) => {
                    if (response && response.ok) {
                        return cache.put(url, response);
                    }
                    console.warn(`[SW] Falha ao buscar ${url}, continuando...`);
                }).catch((error) => {
                    console.warn(`[SW] Erro ao buscar ${url}:`, error.name);
                    // Se falhar, cria versão inline para offline.html
                    if (url === OFFLINE_URL) {
                        const offlineHTML = createOfflineHTML();
                        return cache.put(OFFLINE_URL, new Response(offlineHTML, {
                            headers: { 'Content-Type': 'text/html' }
                        }));
                    }
                });
            });
            
            return Promise.allSettled(cachePromises).then(() => {
                console.log('[SW] Pré-cache concluído!');
            });
        })
    );
    
    // Força ativação imediata
    self.skipWaiting();
});

// Ativação: Limpa caches antigos e assume controle imediato
self.addEventListener('activate', (event) => {
    console.log(`[SW] Ativando Service Worker v${CACHE_VERSION}...`);
    
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    // Remove todos os caches que não são da versão atual
                    if (cacheName !== CACHE_NAME && cacheName.startsWith('shapefit-')) {
                        console.log('[SW] Removendo cache antigo:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            console.log('[SW] Ativado! Assumindo controle de todos os clientes...');
            // Assume controle IMEDIATAMENTE de todas as páginas abertas
            return self.clients.claim();
        }).then(() => {
            console.log('[SW] Controle assumido com sucesso!');
        })
    );
    
    // Força ativação imediata sem esperar outras páginas fecharem
    self.skipWaiting();
});

// Intercepta requisições
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // APIs: NUNCA cachear, sempre buscar da rede
    if (isAPIRequest(url.pathname) || isAPIRequest(url.search)) {
        event.respondWith(
            fetch(request).catch(() => {
                // Se falhar, retorna erro JSON
                return new Response(JSON.stringify({
                    success: false,
                    error: 'offline',
                    message: 'Você está offline. Conecte-se à internet para continuar.'
                }), {
                    headers: { 'Content-Type': 'application/json' }
                });
            })
        );
        return;
    }
    
    // NAVEGAÇÃO DE PÁGINAS: Network-First (tenta rede, fallback para cache)
    if (request.mode === 'navigate' || 
        (request.method === 'GET' && request.headers.get('accept') && 
         request.headers.get('accept').includes('text/html'))) {
        
        event.respondWith(
            (async () => {
                const cache = await caches.open(CACHE_NAME);
                
                try {
                    // Tenta buscar da rede primeiro (com timeout)
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 3000);
                    
                    const networkResponse = await fetch(request, {
                        signal: controller.signal,
                        cache: 'no-store' // Sempre busca versão fresca
                    });
                    
                    clearTimeout(timeoutId);
                    
                    // Se a resposta for válida, cacheia e retorna
                    if (networkResponse && networkResponse.status === 200) {
                        // Cacheia em background (não bloqueia a resposta)
                        const responseClone = networkResponse.clone();
                        cache.put(request, responseClone).catch(err => {
                            console.warn('[SW] Erro ao cachear resposta:', err);
                        });
                        return networkResponse;
                    }
                    
                    throw new Error('Network response not ok');
                } catch (error) {
                    // Rede falhou: tenta servir do cache
                    console.log('[SW] Rede falhou, tentando cache:', error.name || error);
                    
                    const cachedResponse = await cache.match(request);
                    
                    if (cachedResponse) {
                        console.log('[SW] Servindo do cache:', request.url);
                        return cachedResponse;
                    }
                    
                    // Se não estiver no cache, serve página offline
                    console.log('[SW] Página não está no cache, servindo offline.html');
                    const offlineResponse = await cache.match(OFFLINE_URL);
                    
                    if (offlineResponse) {
                        return offlineResponse;
                    }
                    
                    // Último recurso: cria offline.html inline
                    return new Response(createOfflineHTML(), {
                        headers: { 'Content-Type': 'text/html' }
                    });
                }
            })()
        );
        return;
    }
    
    // ASSETS (CSS, JS, Imagens): Cache-First para performance
    event.respondWith(
        (async () => {
            const cache = await caches.open(CACHE_NAME);
            const cachedResponse = await cache.match(request);
            
            if (cachedResponse) {
                // Serve do cache, mas atualiza em background
                fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        cache.put(request, networkResponse.clone());
                    }
                }).catch(() => {
                    // Ignora erros de atualização em background
                });
                return cachedResponse;
            }
            
            // Se não estiver no cache, busca da rede
            try {
                const networkResponse = await fetch(request);
                if (networkResponse && networkResponse.status === 200) {
                    cache.put(request, networkResponse.clone());
                }
                return networkResponse;
            } catch (error) {
                console.warn('[SW] Erro ao buscar asset:', request.url);
                throw error;
            }
        })()
    );
});

// Função auxiliar para criar HTML offline inline
function createOfflineHTML() {
    return `<!DOCTYPE html>
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
}
