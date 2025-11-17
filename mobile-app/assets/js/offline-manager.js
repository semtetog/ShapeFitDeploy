// Offline Manager para ShapeFit Mobile App
class OfflineManager {
    constructor() {
        this.isOnline = navigator.onLine;
        this.apiBase = 'https://appshapefit.com';
        this.dbName = 'shapefit_offline';
        this.dbVersion = 1;
        this.db = null;
        this.syncQueue = [];
        
        this.init();
    }
    
    async init() {
        // Detectar mudanças de rede
        window.addEventListener('online', () => this.handleOnline());
        window.addEventListener('offline', () => this.handleOffline());
        
        // Inicializar IndexedDB
        await this.initDB();
        
        // Carregar fila de sincronização
        await this.loadSyncQueue();
        
        // Mostrar banner offline se necessário
        this.updateOfflineBanner();
        
        // Tentar sincronizar se estiver online
        if (this.isOnline) {
            this.syncPendingData();
        }
    }
    
    async initDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve();
            };
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Store para dados do usuário
                if (!db.objectStoreNames.contains('userData')) {
                    db.createObjectStore('userData', { keyPath: 'key' });
                }
                
                // Store para refeições offline
                if (!db.objectStoreNames.contains('meals')) {
                    db.createObjectStore('meals', { keyPath: 'id', autoIncrement: true });
                }
                
                // Store para fila de sincronização
                if (!db.objectStoreNames.contains('syncQueue')) {
                    db.createObjectStore('syncQueue', { keyPath: 'id', autoIncrement: true });
                }
                
                // Store para cache de alimentos
                if (!db.objectStoreNames.contains('foods')) {
                    db.createObjectStore('foods', { keyPath: 'id' });
                }
            };
        });
    }
    
    handleOnline() {
        this.isOnline = true;
        this.updateOfflineBanner();
        this.syncPendingData();
        console.log('Voltou online - sincronizando dados...');
    }
    
    handleOffline() {
        this.isOnline = false;
        this.updateOfflineBanner();
        console.log('Ficou offline');
    }
    
    updateOfflineBanner() {
        const banner = document.getElementById('offlineBanner');
        if (banner) {
            if (!this.isOnline) {
                banner.classList.add('show');
            } else {
                banner.classList.remove('show');
            }
        }
    }
    
    async saveToDB(storeName, data) {
        if (!this.db) await this.initDB();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.put(data);
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
    
    async getFromDB(storeName, key) {
        if (!this.db) await this.initDB();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.get(key);
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
    
    async getAllFromDB(storeName) {
        if (!this.db) await this.initDB();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.getAll();
            
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        });
    }
    
    async addToSyncQueue(action, data) {
        const syncItem = {
            action: action,
            data: data,
            timestamp: Date.now(),
            retries: 0
        };
        
        await this.saveToDB('syncQueue', syncItem);
        this.syncQueue.push(syncItem);
    }
    
    async loadSyncQueue() {
        this.syncQueue = await this.getAllFromDB('syncQueue');
    }
    
    async syncPendingData() {
        if (!this.isOnline || this.syncQueue.length === 0) {
            return;
        }
        
        console.log(`Sincronizando ${this.syncQueue.length} itens pendentes...`);
        
        const itemsToSync = [...this.syncQueue];
        
        for (const item of itemsToSync) {
            try {
                await this.syncItem(item);
                // Remover da fila se sincronizado com sucesso
                await this.removeFromSyncQueue(item.id);
            } catch (error) {
                console.error('Erro ao sincronizar item:', error);
                item.retries++;
                if (item.retries < 3) {
                    await this.saveToDB('syncQueue', item);
                } else {
                    // Remover após 3 tentativas
                    await this.removeFromSyncQueue(item.id);
                }
            }
        }
    }
    
    async syncItem(item) {
        const { action, data } = item;
        
        let url = '';
        let method = 'POST';
        
        switch (action) {
            case 'addMeal':
                url = `${this.apiBase}/api/meals`;
                break;
            case 'updateMeal':
                url = `${this.apiBase}/api/meals/${data.id}`;
                method = 'PUT';
                break;
            case 'deleteMeal':
                url = `${this.apiBase}/api/meals/${data.id}`;
                method = 'DELETE';
                break;
            default:
                throw new Error(`Ação desconhecida: ${action}`);
        }
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            throw new Error(`Erro na sincronização: ${response.statusText}`);
        }
        
        return response.json();
    }
    
    async removeFromSyncQueue(id) {
        if (!this.db) return;
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['syncQueue'], 'readwrite');
            const store = transaction.objectStore('syncQueue');
            const request = store.delete(id);
            
            request.onsuccess = () => {
                this.syncQueue = this.syncQueue.filter(item => item.id !== id);
                resolve();
            };
            request.onerror = () => reject(request.error);
        });
    }
    
    // Método para fazer requisições com fallback offline
    async fetchWithOffline(url, options = {}) {
        if (this.isOnline) {
            try {
                const response = await fetch(url, options);
                return response;
            } catch (error) {
                // Se falhar, tentar buscar do cache
                return this.fetchFromCache(url);
            }
        } else {
            return this.fetchFromCache(url);
        }
    }
    
    async fetchFromCache(url) {
        // Implementar busca do cache IndexedDB
        const cached = await this.getFromDB('cache', url);
        if (cached) {
            return new Response(JSON.stringify(cached.data), {
                status: 200,
                headers: { 'Content-Type': 'application/json' }
            });
        }
        
        return new Response(
            JSON.stringify({ error: 'Offline', offline: true }),
            { status: 503, headers: { 'Content-Type': 'application/json' } }
        );
    }
}

// Inicializar quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.offlineManager = new OfflineManager();
    });
} else {
    window.offlineManager = new OfflineManager();
}





