// Gerenciador Offline - Intercepta requisições e gerencia estado offline
import offlineDB from './db.js';
import syncManager from './sync.js';

class OfflineManager {
  constructor() {
    this.isOnline = navigator.onLine;
    this.apiBaseUrl = window.API_BASE_URL || 'https://seudominio.com/api';
    
    this.init();
  }

  init() {
    // Detectar mudanças de conexão
    window.addEventListener('online', () => this.handleOnline());
    window.addEventListener('offline', () => this.handleOffline());
    
    // Mostrar status inicial
    this.updateOfflineUI();
    
    // Interceptar fetch requests
    this.interceptFetch();
  }

  handleOnline() {
    console.log('[Offline] Conexão restaurada');
    this.isOnline = true;
    this.updateOfflineUI();
    
    // Notificar app
    window.dispatchEvent(new CustomEvent('connection-restored'));
  }

  handleOffline() {
    console.log('[Offline] Sem conexão');
    this.isOnline = false;
    this.updateOfflineUI();
    
    // Notificar app
    window.dispatchEvent(new CustomEvent('connection-lost'));
  }

  updateOfflineUI() {
    const offlineBanner = document.getElementById('offline-banner');
    if (offlineBanner) {
      if (this.isOnline) {
        offlineBanner.style.display = 'none';
      } else {
        offlineBanner.style.display = 'block';
        offlineBanner.textContent = 'Você está offline. Algumas funcionalidades podem estar limitadas.';
      }
    }
  }

  interceptFetch() {
    const originalFetch = window.fetch;
    
    window.fetch = async (url, options = {}) => {
      // Se for requisição para API
      if (typeof url === 'string' && url.includes('/api/')) {
        return this.handleAPIRequest(url, options, originalFetch);
      }
      
      // Outras requisições passam direto
      return originalFetch(url, options);
    };
  }

  async handleAPIRequest(url, options, originalFetch) {
    const method = options.method || 'GET';
    const isMutation = ['POST', 'PUT', 'DELETE', 'PATCH'].includes(method);
    
    // Se está offline
    if (!this.isOnline) {
      // Para GET, tentar cache
      if (method === 'GET') {
        const cached = await offlineDB.getCachedAPIResponse(url);
        if (cached) {
          return new Response(JSON.stringify(cached), {
            status: 200,
            headers: { 'Content-Type': 'application/json' }
          });
        }
      }
      
      // Para mutations, enfileirar
      if (isMutation) {
        await this.queueAction(url, options);
        return new Response(JSON.stringify({
          success: true,
          offline: true,
          queued: true,
          message: 'Ação enfileirada para execução quando online'
        }), {
          status: 202,
          headers: { 'Content-Type': 'application/json' }
        });
      }
      
      // Se não tem cache e é GET, retornar erro
      return new Response(JSON.stringify({
        success: false,
        offline: true,
        message: 'Sem conexão e sem dados em cache'
      }), {
        status: 503,
        headers: { 'Content-Type': 'application/json' }
      });
    }
    
    // Se está online, fazer requisição normal
    try {
      const response = await originalFetch(url, options);
      
      // Cachear resposta de GET
      if (method === 'GET' && response.ok) {
        const data = await response.clone().json();
        await offlineDB.cacheAPIResponse(url, data);
      }
      
      return response;
    } catch (error) {
      // Se falhar e for GET, tentar cache
      if (method === 'GET') {
        const cached = await offlineDB.getCachedAPIResponse(url);
        if (cached) {
          return new Response(JSON.stringify(cached), {
            status: 200,
            headers: { 'Content-Type': 'application/json' }
          });
        }
      }
      
      throw error;
    }
  }

  async queueAction(url, options) {
    const action = {
      type: 'api_request',
      url: url.replace(this.apiBaseUrl, ''),
      method: options.method || 'POST',
      body: options.body ? JSON.parse(options.body) : null,
      timestamp: Date.now()
    };
    
    await offlineDB.addToQueue(action);
    console.log('[Offline] Ação enfileirada:', action);
  }

  // Método helper para fazer requisições com fallback offline
  async request(url, options = {}) {
    return fetch(url, options);
  }

  // Verificar se está online
  getOnlineStatus() {
    return this.isOnline;
  }
}

// Export singleton
const offlineManager = new OfflineManager();
export default offlineManager;

