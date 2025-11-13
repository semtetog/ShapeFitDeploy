// App Principal - Gerencia navegação, autenticação e estado global
import offlineDB from '/assets/js/db.js';
import syncManager from '/assets/js/sync.js';
import offlineManager from '/assets/js/offline.js';

class App {
    constructor() {
        this.currentUser = null;
        this.isAuthenticated = false;
        this.currentPage = 'dashboard';
        this.init();
    }

    async init() {
        // Aguardar DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.start());
        } else {
            this.start();
        }
    }

    async start() {
        console.log('[App] Inicializando...');
        
        try {
            // Inicializar IndexedDB
            await offlineDB.init();
            console.log('[App] IndexedDB inicializado');
            
            // Verificar autenticação
            const token = localStorage.getItem('auth_token');
            if (!token) {
                this.redirectToLogin();
                return;
            }
            
            // Carregar dados do usuário
            await this.loadUserData();
            
            // Inicializar navegação
            this.initNavigation();
            
            // Carregar página atual
            this.loadPage(this.currentPage);
            
            // Listener para eventos de sincronização
            window.addEventListener('sync-complete', () => {
                console.log('[App] Sincronização concluída');
                this.refreshCurrentPage();
            });
            
            window.addEventListener('connection-restored', () => {
                this.showToast('Conexão restaurada! Sincronizando...', 'success');
            });
            
            console.log('[App] Inicializado com sucesso');
        } catch (error) {
            console.error('[App] Erro na inicialização:', error);
            this.showError('Erro ao inicializar aplicativo');
        }
    }

    async loadUserData() {
        // Tentar carregar do IndexedDB primeiro
        let user = await offlineDB.getUser();
        
        if (!user || !user.id) {
            // Se não tem no IndexedDB, buscar da API
            const token = localStorage.getItem('auth_token');
            try {
                const response = await fetch(`${window.APP_CONFIG.API_BASE_URL}/user/profile`, {
                    headers: {
                        'Authorization': `Bearer ${token}`
                    }
                });
                
                if (response.ok) {
                    user = await response.json();
                    await offlineDB.saveUser(user);
                } else if (response.status === 401) {
                    // Token inválido
                    this.logout();
                    return;
                }
            } catch (error) {
                console.error('[App] Erro ao carregar dados do usuário:', error);
                // Continuar com dados do cache se disponível
            }
        }
        
        if (user && user.id) {
            this.currentUser = user;
            this.isAuthenticated = true;
            this.updateUI();
        } else {
            this.redirectToLogin();
        }
    }

    initNavigation() {
        // Navegação bottom nav
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const page = item.dataset.page;
                if (page) {
                    this.navigateTo(page);
                }
            });
        });
        
        // Navegação por links
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a[data-page]');
            if (link) {
                e.preventDefault();
                this.navigateTo(link.dataset.page);
            }
        });
    }

    navigateTo(page, params = {}) {
        this.currentPage = page;
        this.loadPage(page, params);
        this.updateActiveNav();
    }

    async loadPage(page, params = {}) {
        console.log(`[App] Carregando página: ${page}`);
        
        const container = document.getElementById('app-content');
        if (!container) {
            console.error('[App] Container #app-content não encontrado');
            return;
        }
        
        // Mostrar loading
        container.innerHTML = '<div class="loading-screen"><div class="spinner"></div><p>Carregando...</p></div>';
        
        try {
            // Carregar página
            const pageModule = await import(`/pages/${page}.js`);
            const html = await pageModule.default(this.currentUser, params);
            
            container.innerHTML = html;
            
            // Executar scripts da página se houver
            if (pageModule.onLoad) {
                await pageModule.onLoad(this.currentUser, params);
            }
            
            // Atualizar título
            document.title = pageModule.title || 'ShapeFit';
            
        } catch (error) {
            console.error(`[App] Erro ao carregar página ${page}:`, error);
            container.innerHTML = `
                <div class="error-screen">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Erro ao carregar página</p>
                    <button onclick="app.navigateTo('dashboard')">Voltar ao Início</button>
                </div>
            `;
        }
    }

    updateActiveNav() {
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.classList.remove('active');
            if (item.dataset.page === this.currentPage) {
                item.classList.add('active');
            }
        });
    }

    updateUI() {
        // Atualizar pontos
        const pointsDisplay = document.getElementById('user-points-display');
        if (pointsDisplay && this.currentUser) {
            pointsDisplay.textContent = new Intl.NumberFormat('pt-BR').format(this.currentUser.points || 0);
        }
        
        // Atualizar nome/avatar
        const userNameDisplay = document.getElementById('user-name-display');
        if (userNameDisplay && this.currentUser) {
            userNameDisplay.textContent = this.currentUser.name || 'Usuário';
        }
    }

    refreshCurrentPage() {
        // Recarregar dados da página atual
        this.loadPage(this.currentPage);
    }

    redirectToLogin() {
        window.location.href = '/pages/login.html';
    }

    logout() {
        localStorage.removeItem('auth_token');
        offlineDB.clearAll();
        this.redirectToLogin();
    }

    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    showError(message) {
        this.showToast(message, 'error');
    }
}

// Export singleton
const app = new App();
window.app = app;
export default app;

