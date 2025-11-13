// Sistema de Sincronização Offline-First
import offlineDB from '/assets/js/db.js';

class SyncManager {
  constructor() {
    this.isOnline = navigator.onLine;
    this.syncing = false;
    this.syncInterval = null;
    this.apiBaseUrl = window.API_BASE_URL || 'https://seudominio.com/api';
    
    this.init();
  }

  init() {
    // Detectar mudanças de conexão
    window.addEventListener('online', () => this.handleOnline());
    window.addEventListener('offline', () => this.handleOffline());
    
    // Sincronizar periodicamente quando online
    if (this.isOnline) {
      this.startPeriodicSync();
    }
    
    // Sincronizar imediatamente se online
    if (this.isOnline) {
      this.sync();
    }
  }

  handleOnline() {
    console.log('[Sync] Online - Iniciando sincronização...');
    this.isOnline = true;
    this.startPeriodicSync();
    this.sync();
  }

  handleOffline() {
    console.log('[Sync] Offline');
    this.isOnline = false;
    this.stopPeriodicSync();
  }

  startPeriodicSync() {
    // Sincronizar a cada 30 segundos quando online
    this.syncInterval = setInterval(() => {
      if (this.isOnline && !this.syncing) {
        this.sync();
      }
    }, 30000);
  }

  stopPeriodicSync() {
    if (this.syncInterval) {
      clearInterval(this.syncInterval);
      this.syncInterval = null;
    }
  }

  async sync() {
    if (this.syncing || !this.isOnline) {
      return;
    }

    this.syncing = true;
    console.log('[Sync] Iniciando sincronização...');

    try {
      // 1. Sincronizar ações pendentes
      await this.syncActionQueue();
      
      // 2. Sincronizar refeições não sincronizadas
      await this.syncMeals();
      
      // 3. Sincronizar histórico de peso
      await this.syncWeightHistory();
      
      // 4. Atualizar dados do usuário
      await this.syncUserData();
      
      console.log('[Sync] Sincronização concluída');
      
      // Notificar app
      this.notifySyncComplete();
    } catch (error) {
      console.error('[Sync] Erro na sincronização:', error);
      this.notifySyncError(error);
    } finally {
      this.syncing = false;
    }
  }

  async syncActionQueue() {
    const queue = await offlineDB.getQueue();
    if (queue.length === 0) return;

    console.log(`[Sync] Processando ${queue.length} ações na fila...`);

    for (const action of queue) {
      try {
        const response = await this.executeAction(action);
        
        if (response.success) {
          await offlineDB.removeFromQueue(action.id);
          console.log(`[Sync] Ação ${action.id} executada com sucesso`);
        } else {
          // Incrementar tentativas
          action.retries++;
          if (action.retries >= 3) {
            // Remover após 3 tentativas falhadas
            await offlineDB.removeFromQueue(action.id);
            console.warn(`[Sync] Ação ${action.id} removida após 3 tentativas`);
          }
        }
      } catch (error) {
        console.error(`[Sync] Erro ao executar ação ${action.id}:`, error);
        action.retries++;
      }
    }
  }

  async executeAction(action) {
    const { type, url, method, body } = action;
    
    const response = await fetch(`${this.apiBaseUrl}${url}`, {
      method: method || 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${this.getAuthToken()}`
      },
      body: JSON.stringify(body)
    });

    return response.json();
  }

  async syncMeals() {
    const unsyncedMeals = await offlineDB.getUnsyncedMeals();
    if (unsyncedMeals.length === 0) return;

    console.log(`[Sync] Sincronizando ${unsyncedMeals.length} refeições...`);

    for (const meal of unsyncedMeals) {
      try {
        const response = await fetch(`${this.apiBaseUrl}/diary/meals`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${this.getAuthToken()}`
          },
          body: JSON.stringify(meal)
        });

        if (response.ok) {
          const data = await response.json();
          await offlineDB.markMealSynced(meal.local_id);
          console.log(`[Sync] Refeição ${meal.local_id} sincronizada`);
        }
      } catch (error) {
        console.error(`[Sync] Erro ao sincronizar refeição ${meal.local_id}:`, error);
      }
    }
  }

  async syncWeightHistory() {
    // Implementar sincronização de peso
    // Similar ao syncMeals
  }

  async syncUserData() {
    try {
      const response = await fetch(`${this.apiBaseUrl}/user/profile`, {
        headers: {
          'Authorization': `Bearer ${this.getAuthToken()}`
        }
      });

      if (response.ok) {
        const userData = await response.json();
        await offlineDB.saveUser(userData);
        console.log('[Sync] Dados do usuário atualizados');
      }
    } catch (error) {
      console.error('[Sync] Erro ao sincronizar dados do usuário:', error);
    }
  }

  getAuthToken() {
    return localStorage.getItem('auth_token') || '';
  }

  notifySyncComplete() {
    window.dispatchEvent(new CustomEvent('sync-complete'));
  }

  notifySyncError(error) {
    window.dispatchEvent(new CustomEvent('sync-error', { detail: error }));
  }

  // Método público para forçar sincronização
  async forceSync() {
    if (!this.isOnline) {
      throw new Error('Não é possível sincronizar offline');
    }
    await this.sync();
  }
}

// Export singleton
const syncManager = new SyncManager();
export default syncManager;

