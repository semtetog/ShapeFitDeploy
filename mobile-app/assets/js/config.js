// Configuração global do app
window.APP_CONFIG = {
    // URL da API (sempre apontar para Hostinger no app mobile)
    API_BASE_URL: (() => {
        // Se estiver rodando no Capacitor, sempre usar Hostinger
        if (window.isCapacitor || window.Capacitor?.isNativePlatform()) {
            return 'https://appshapefit.com';
        }
        // Caso contrário, usar a URL atual
        return window.location.origin;
    })(),
    
    // Versão do app
    VERSION: '1.0.0',
    
    // Configurações offline
    OFFLINE: {
        ENABLED: true,
        CACHE_DURATION: 24 * 60 * 60 * 1000, // 24 horas em ms
        SYNC_INTERVAL: 30 * 1000 // 30 segundos
    }
};

// Helper para fazer requisições com fallback offline
window.apiRequest = async function(url, options = {}) {
    const fullUrl = url.startsWith('http') ? url : `${window.APP_CONFIG.API_BASE_URL}${url}`;
    
    try {
        const response = await fetch(fullUrl, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error('Erro na requisição:', error);
        
        // Se tiver offline manager, tentar buscar do cache
        if (window.offlineManager && !navigator.onLine) {
            return window.offlineManager.fetchFromCache(fullUrl);
        }
        
        throw error;
    }
};





