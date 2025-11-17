// Interceptador Global de Erros de Rede para Capacitor
// Captura erros de rede ANTES do Capacitor mostrar tela de erro nativa

(function() {
    'use strict';
    
    // Verifica se está rodando no Capacitor
    const isCapacitor = window.Capacitor && window.Capacitor.isNativePlatform();
    
    if (!isCapacitor) {
        return; // Só funciona no app nativo
    }
    
    console.log('[NetworkErrorHandler] Inicializando interceptador de erros de rede...');
    
    // Intercepta erros de fetch/XMLHttpRequest
    const originalFetch = window.fetch;
    window.fetch = async function(...args) {
        try {
            const response = await originalFetch.apply(this, args);
            return response;
        } catch (error) {
            // Se for erro de rede e for uma requisição de navegação
            if (error.name === 'TypeError' || error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
                const url = args[0];
                if (typeof url === 'string' && (url.includes('.php') || url.endsWith('/') || !url.includes('/api/'))) {
                    console.warn('[NetworkErrorHandler] Erro de rede detectado, redirecionando para offline.html');
                    window.location.href = '/offline.html';
                    return Promise.reject(error);
                }
            }
            throw error;
        }
    };
    
    // Intercepta erros de navegação
    window.addEventListener('error', (event) => {
        if (event.message && (
            event.message.includes('net::ERR_FAILED') ||
            event.message.includes('Failed to fetch') ||
            event.message.includes('NetworkError')
        )) {
            console.warn('[NetworkErrorHandler] Erro de rede detectado no evento error');
            // Não redireciona aqui para evitar loops, apenas loga
        }
    }, true);
    
    // Intercepta quando a página falha ao carregar
    window.addEventListener('unhandledrejection', (event) => {
        if (event.reason && (
            event.reason.message && (
                event.reason.message.includes('Failed to fetch') ||
                event.reason.message.includes('NetworkError') ||
                event.reason.message.includes('net::ERR_FAILED')
            )
        )) {
            console.warn('[NetworkErrorHandler] Promise rejeitada por erro de rede');
            const url = window.location.href;
            if (url && !url.includes('offline.html')) {
                window.location.href = '/offline.html';
            }
        }
    });
    
    // Monitora mudanças de status de rede do Capacitor
    if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Network) {
        window.Capacitor.Plugins.Network.addListener('networkStatusChange', (status) => {
            if (!status.connected) {
                console.log('[NetworkErrorHandler] Rede desconectada detectada pelo Capacitor');
                // Não redireciona imediatamente, deixa o Service Worker ou errorPath do Capacitor lidar
            }
        });
    }
    
    // Intercepta cliques em links que podem falhar
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (link && link.href) {
            // Adiciona um listener de erro para o link
            link.addEventListener('error', () => {
                if (!navigator.onLine) {
                    event.preventDefault();
                    window.location.href = '/offline.html';
                }
            }, { once: true });
        }
    }, true);
    
    console.log('[NetworkErrorHandler] Interceptador configurado com sucesso');
})();

