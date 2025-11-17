// Service Worker para ShapeFit Mobile App
const CACHE_NAME = 'shapefit-v3';
const API_BASE = 'https://appshapefit.com';

// Páginas críticas que devem estar sempre no cache (precache)
// IMPORTANTE: Não coloque TODAS as páginas aqui! Apenas as essenciais.
// As outras páginas serão cacheadas automaticamente quando o usuário visitá-las.
const CRITICAL_PAGES = [
    './',
    './index.html',
    './index.php',
    './auth/login.php',
    './auth/register.php', // Página de registro (se existir)
    './main_app.php', // Página principal do app
    './onboarding/onboarding.php', // Página de onboarding
    // Páginas principais do app (mais visitadas)
    './diary.php',
    './progress.php',
    './ranking.php',
    './more_options.php',
    './routine.php'
];

// Assets estáticos críticos
const CRITICAL_ASSETS = [
    // CSS principal
    './assets/css/main_app_glass_theme.css',
    './assets/css/main_app_specific.css',
    // JS principal
    './assets/js/config.js',
    './assets/js/capacitor-init.js',
    './assets/js/offline-manager.js',
    // Logo
    './assets/images/SHAPE-FIT-LOGO.png'
];

// Instalar Service Worker
self.addEventListener('install', (event) => {
    console.log('[SW] Instalando Service Worker...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[SW] Cache aberto:', CACHE_NAME);
                
                // Cachear páginas críticas e assets
                const allAssets = [...CRITICAL_PAGES, ...CRITICAL_ASSETS];
                
                // Tentar adicionar todos os assets, mas não falhar se alguns não existirem
                return Promise.allSettled(
                    allAssets.map(url => 
                        cache.add(url).catch(err => {
                            // Não logar erro para páginas que podem não existir (como register.php)
                            if (!url.includes('register.php') && !url.includes('onboarding.php')) {
                                console.warn('[SW] Não foi possível cachear:', url);
                            }
                            return null;
                        })
                    )
                );
            })
            .then(results => {
                const successful = results.filter(r => r.status === 'fulfilled').length;
                const failed = results.filter(r => r.status === 'rejected').length;
                console.log(`[SW] Instalação concluída: ${successful} sucessos, ${failed} falhas`);
            })
            .catch(err => console.error('[SW] Erro ao instalar:', err))
    );
    // Forçar ativação imediata
    self.skipWaiting();
});

// Ativar Service Worker
self.addEventListener('activate', (event) => {
    console.log('[SW] Ativando Service Worker...');
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[SW] Removendo cache antigo:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
        .then(() => {
            console.log('[SW] Ativação concluída');
            // Assumir controle de todas as páginas imediatamente
            return self.clients.claim();
        })
    );
});

// Estratégia de cache: Network First com fallback para cache
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    
    // Ignorar requisições não-GET
    if (event.request.method !== 'GET') {
        return;
    }
    
    // Ignorar requisições que não são do nosso domínio
    if (!url.href.includes('appshapefit.com') && !url.href.includes('localhost')) {
        return;
    }
    
    // Para requisições de API: Network Only (não cachear)
    if (url.pathname.includes('/api/') || url.pathname.includes('/ajax')) {
        event.respondWith(
            fetch(event.request)
                .catch(() => {
                    // Se offline, retornar resposta JSON de erro
                    return new Response(
                        JSON.stringify({ 
                            error: 'Offline', 
                            offline: true,
                            message: 'Você está offline. Conecte-se à internet para continuar.'
                        }),
                        { 
                            status: 503,
                            headers: { 
                                'Content-Type': 'application/json',
                                'Cache-Control': 'no-cache'
                            }
                        }
                    );
                })
        );
        return;
    }
    
    // Para assets estáticos (CSS, JS, imagens, fonts): Cache First
    if (url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|webp|woff|woff2|ttf|eot|ico)$/i)) {
        event.respondWith(
            caches.match(event.request)
                .then(cachedResponse => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    // Se não tiver no cache, buscar da rede e cachear
                    return fetch(event.request).then(response => {
                        // Só cachear se a resposta for válida
                        if (response && response.status === 200) {
                            const responseToCache = response.clone();
                            caches.open(CACHE_NAME).then(cache => {
                                cache.put(event.request, responseToCache);
                            });
                        }
                        return response;
                    }).catch(() => {
                        // Se falhar e não tiver no cache, retornar erro
                        return new Response('Asset não disponível offline', { 
                            status: 503,
                            headers: { 'Content-Type': 'text/plain' }
                        });
                    });
                })
        );
        return;
    }
    
    // Para páginas HTML/PHP: Network First com fallback para cache
    // ESTRATÉGIA HÍBRIDA: Páginas são cacheadas automaticamente quando visitadas
    event.respondWith(
        fetch(event.request, {
            cache: 'no-store', // Sempre buscar da rede primeiro
            credentials: 'include' // Incluir cookies/sessão
        })
            .then(response => {
                // Cachear TODAS as páginas válidas automaticamente quando visitadas
                // Isso cria um cache dinâmico: quanto mais o usuário navega, mais páginas ficam offline
                if (response && response.status === 200 && response.type === 'basic') {
                    const responseToCache = response.clone();
                    // Cachear em background (não bloquear a resposta)
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(event.request, responseToCache);
                        console.log('[SW] Página cacheada automaticamente:', event.request.url);
                    });
                }
                return response;
            })
            .catch(() => {
                // Se offline, tentar buscar do cache
                return caches.match(event.request)
                    .then(cachedResponse => {
                        if (cachedResponse) {
                            console.log('[SW] Servindo do cache:', event.request.url);
                            return cachedResponse;
                        }
                        
                        // Se for uma página de autenticação (login/register) e não estiver no cache,
                        // tentar buscar da rede mesmo offline (pode ter cache do navegador)
                        const url = new URL(event.request.url);
                        if (url.pathname.includes('/auth/login.php') || url.pathname.includes('/auth/register.php')) {
                            console.log('[SW] Tentando buscar página de auth da rede...');
                            return fetch(event.request).catch(() => {
                                // Se falhar, retornar página offline
                                return createOfflinePage('Esta página requer conexão com a internet para funcionar corretamente.');
                            });
                        }
                        
                        // Para outras páginas não cacheadas, mostrar página offline com links úteis
                        console.log('[SW] Página não encontrada no cache:', event.request.url);
                        return createOfflinePage('Esta página não está disponível offline. Conecte-se à internet para acessá-la.');
                    });
            })
    );
});

// Função auxiliar para criar página offline
function createOfflinePage(customMessage = null) {
    return new Response(
                            `<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - ShapeFit</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #101010;
            color: #F5F5F5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            text-align: center;
            padding: 20px;
        }
        .container {
            max-width: 400px;
        }
        .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #FF6B00;
        }
        p {
            font-size: 1rem;
            line-height: 1.6;
            color: #8E8E93;
            margin-bottom: 1.5rem;
        }
        .links {
            margin-top: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .link-btn {
            display: inline-block;
            padding: 12px 24px;
            background: rgba(255, 107, 0, 0.1);
            border: 1px solid rgba(255, 107, 0, 0.3);
            border-radius: 8px;
            color: #FF6B00;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .link-btn:hover {
            background: rgba(255, 107, 0, 0.2);
            border-color: #FF6B00;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">📴</div>
        <h1>Você está offline</h1>
        <p>${customMessage || 'Algumas funcionalidades podem estar limitadas. Verifique sua conexão com a internet.'}</p>
        <p style="font-size: 0.875rem; margin-top: 1rem;">Páginas visitadas anteriormente podem estar disponíveis offline.</p>
        <div class="links">
            <a href="./auth/login.php" class="link-btn">Tentar Login</a>
            <a href="./main_app.php" class="link-btn">Dashboard</a>
            <a href="./" class="link-btn">Voltar ao Início</a>
        </div>
    </div>
    <script>
        // Tentar recarregar quando voltar online
        window.addEventListener('online', function() {
            console.log('Conexão restaurada, recarregando...');
            window.location.reload();
        });
        
        // Verificar conexão periodicamente
        setInterval(function() {
            if (navigator.onLine) {
                window.location.reload();
            }
        }, 5000);
    </script>
</body>
</html>`,
                            { 
                                status: 503,
                                headers: { 
                                    'Content-Type': 'text/html; charset=utf-8',
                                    'Cache-Control': 'no-cache'
                                }
                            }
                        );
                    });
            })
    );
});

// Sincronização em background quando voltar online
self.addEventListener('sync', (event) => {
    console.log('[SW] Background sync:', event.tag);
    if (event.tag === 'sync-data') {
        event.waitUntil(syncData());
    }
});

async function syncData() {
    // Implementar lógica de sincronização aqui
    console.log('[SW] Sincronizando dados...');
    // Por enquanto, apenas log
    return Promise.resolve();
}

// Mensagens do cliente
self.addEventListener('message', (event) => {
    console.log('[SW] Mensagem recebida:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'CLEAR_CACHE') {
        caches.delete(CACHE_NAME).then(() => {
            console.log('[SW] Cache limpo');
        });
    }
});





