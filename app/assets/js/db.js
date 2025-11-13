// IndexedDB Manager para App Offline-First
class OfflineDB {
  constructor() {
    this.dbName = 'ShapeFitDB';
    this.version = 1;
    this.db = null;
  }

  async init() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(this.dbName, this.version);

      request.onerror = () => reject(request.error);
      request.onsuccess = () => {
        this.db = request.result;
        resolve(this.db);
      };

      request.onupgradeneeded = (event) => {
        const db = event.target.result;

        // Store de usuário
        if (!db.objectStoreNames.contains('user')) {
          const userStore = db.createObjectStore('user', { keyPath: 'id' });
          userStore.createIndex('email', 'email', { unique: true });
        }

        // Store de refeições
        if (!db.objectStoreNames.contains('meals')) {
          const mealsStore = db.createObjectStore('meals', { keyPath: 'id', autoIncrement: true });
          mealsStore.createIndex('date', 'date', { unique: false });
          mealsStore.createIndex('meal_type', 'meal_type', { unique: false });
          mealsStore.createIndex('synced', 'synced', { unique: false });
        }

        // Store de alimentos
        if (!db.objectStoreNames.contains('foods')) {
          const foodsStore = db.createObjectStore('foods', { keyPath: 'id' });
          foodsStore.createIndex('name', 'name', { unique: false });
        }

        // Store de receitas
        if (!db.objectStoreNames.contains('recipes')) {
          const recipesStore = db.createObjectStore('recipes', { keyPath: 'id' });
          recipesStore.createIndex('title', 'title', { unique: false });
        }

        // Store de histórico de peso
        if (!db.objectStoreNames.contains('weight_history')) {
          const weightStore = db.createObjectStore('weight_history', { keyPath: 'id', autoIncrement: true });
          weightStore.createIndex('date', 'date', { unique: true });
          weightStore.createIndex('synced', 'synced', { unique: false });
        }

        // Store de ações pendentes (queue)
        if (!db.objectStoreNames.contains('action_queue')) {
          const queueStore = db.createObjectStore('action_queue', { keyPath: 'id', autoIncrement: true });
          queueStore.createIndex('timestamp', 'timestamp', { unique: false });
          queueStore.createIndex('type', 'type', { unique: false });
        }

        // Store de cache de API
        if (!db.objectStoreNames.contains('api_cache')) {
          const cacheStore = db.createObjectStore('api_cache', { keyPath: 'url' });
          cacheStore.createIndex('timestamp', 'timestamp', { unique: false });
        }
      };
    });
  }

  // User Operations
  async saveUser(userData) {
    const tx = this.db.transaction('user', 'readwrite');
    const store = tx.objectStore('user');
    await store.put({ id: 'current', ...userData });
    return tx.complete;
  }

  async getUser() {
    const tx = this.db.transaction('user', 'readonly');
    const store = tx.objectStore('user');
    return store.get('current');
  }

  // Meal Operations
  async saveMeal(meal) {
    const tx = this.db.transaction('meals', 'readwrite');
    const store = tx.objectStore('meals');
    const mealData = {
      ...meal,
      synced: false,
      local_id: meal.id || `local_${Date.now()}`,
      timestamp: new Date().toISOString()
    };
    await store.put(mealData);
    return tx.complete;
  }

  async getMeals(date) {
    const tx = this.db.transaction('meals', 'readonly');
    const store = tx.objectStore('meals');
    const index = store.index('date');
    return index.getAll(date);
  }

  async getUnsyncedMeals() {
    const tx = this.db.transaction('meals', 'readonly');
    const store = tx.objectStore('meals');
    const index = store.index('synced');
    return index.getAll(false);
  }

  async markMealSynced(mealId) {
    const tx = this.db.transaction('meals', 'readwrite');
    const store = tx.objectStore('meals');
    const meal = await store.get(mealId);
    if (meal) {
      meal.synced = true;
      await store.put(meal);
    }
    return tx.complete;
  }

  async deleteMeal(mealId) {
    const tx = this.db.transaction('meals', 'readwrite');
    const store = tx.objectStore('meals');
    await store.delete(mealId);
    return tx.complete;
  }

  // Action Queue Operations
  async addToQueue(action) {
    const tx = this.db.transaction('action_queue', 'readwrite');
    const store = tx.objectStore('action_queue');
    await store.add({
      ...action,
      timestamp: Date.now(),
      retries: 0
    });
    return tx.complete;
  }

  async getQueue() {
    const tx = this.db.transaction('action_queue', 'readonly');
    const store = tx.objectStore('action_queue');
    return store.getAll();
  }

  async removeFromQueue(queueId) {
    const tx = this.db.transaction('action_queue', 'readwrite');
    const store = tx.objectStore('action_queue');
    await store.delete(queueId);
    return tx.complete;
  }

  // API Cache Operations
  async cacheAPIResponse(url, data, maxAge = 3600000) { // 1 hora default
    const tx = this.db.transaction('api_cache', 'readwrite');
    const store = tx.objectStore('api_cache');
    await store.put({
      url,
      data,
      timestamp: Date.now(),
      maxAge
    });
    return tx.complete;
  }

  async getCachedAPIResponse(url) {
    const tx = this.db.transaction('api_cache', 'readonly');
    const store = tx.objectStore('api_cache');
    const cached = await store.get(url);
    
    if (!cached) return null;
    
    // Verificar se expirou
    const age = Date.now() - cached.timestamp;
    if (age > cached.maxAge) {
      // Remover cache expirado
      const writeTx = this.db.transaction('api_cache', 'readwrite');
      writeTx.objectStore('api_cache').delete(url);
      await writeTx.complete;
      return null;
    }
    
    return cached.data;
  }

  // Clear all data
  async clearAll() {
    const stores = ['user', 'meals', 'foods', 'recipes', 'weight_history', 'action_queue', 'api_cache'];
    const tx = this.db.transaction(stores, 'readwrite');
    
    await Promise.all(
      stores.map(storeName => {
        const store = tx.objectStore(storeName);
        return store.clear();
      })
    );
    
    return tx.complete;
  }
}

// Export singleton
const offlineDB = new OfflineDB();
export default offlineDB;

