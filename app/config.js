// Configuração do App
window.APP_CONFIG = {
    // URL base da API (ajustar para seu domínio)
    API_BASE_URL: 'https://seudominio.com/api',
    
    // URL base do app (para assets)
    APP_BASE_URL: '/',
    
    // Versão do app (para cache busting)
    APP_VERSION: '1.0.0',
    
    // Configurações de cache
    CACHE_VERSION: 'v1.0.0',
    CACHE_MAX_AGE: 3600000, // 1 hora em ms
    
    // Configurações de sincronização
    SYNC_INTERVAL: 30000, // 30 segundos
    SYNC_ON_START: true,
    
    // Configurações offline
    OFFLINE_ENABLED: true,
    QUEUE_MAX_RETRIES: 3
};

