// Service Worker para ShapeFit Mobile App
// Versão 3 - Otimizado para Android/iOS com Capacitor
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
    './offline.html', // Página offline (CRÍTICA - sempre disponível)
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
                            // Não logar erro para páginas opcionais que podem não existir
                            const optionalPages = ['register.php', 'onboarding.php', 'index.php'];
                            const isOptional = optionalPages.some(page => url.includes(page));
                            if (!isOptional) {
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
// IMPORTANTE: Interceptar TODAS as requisições para evitar erros do navegador
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Só aplicar cache para assets estáticos (CSS, JS, Imagens, Fontes)
    if (url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|webp|woff|woff2|ttf|eot|ico)$/i)) {
        event.respondWith(
            caches.match(event.request).then(cachedResponse => {
                // Se tiver no cache, retorna do cache
                if (cachedResponse) {
                    return cachedResponse;
                }
                // Se não, busca na rede, cacheia e retorna
                return fetch(event.request).then(networkResponse => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseToCache = networkResponse.clone();
                        caches.open(CACHE_NAME).then(cache => {
                            cache.put(event.request, responseToCache);
                        });
                    }
                    return networkResponse;
                });
            })
        );
        return;
    }

    // Para todas as outras requisições (ex: páginas .php),
    // apenas tenta buscar da rede. Se falhar (offline), o Capacitor
    // vai mostrar a 'offline.html' definida no errorPath.
    // O Service Worker não precisa mais retornar uma página offline.
    return;
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





