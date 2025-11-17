// Inicialização do Capacitor
// Verificar se Capacitor está disponível
const isCapacitorAvailable = typeof window.Capacitor !== 'undefined';
const isNative = isCapacitorAvailable && window.Capacitor.isNativePlatform();

if (isNative && isCapacitorAvailable) {
    console.log('Rodando no Capacitor');
    
    const { App, Network, SplashScreen } = window.Capacitor.Plugins;
    
    // Configurar listeners do Capacitor
    if (App) {
        App.addListener('appStateChange', ({ isActive }) => {
            console.log('App state changed. Is active?', isActive);
        });
        
        // Prevenir voltar para fora do app (Android)
        App.addListener('backButton', ({ canGoBack }) => {
            if (!canGoBack) {
                App.exitApp();
            } else {
                window.history.back();
            }
        });
    }
    
    // Listener de status de rede
    if (Network) {
        Network.addListener('networkStatusChange', status => {
            console.log('Network status changed', status);
            if (window.offlineManager) {
                if (status.connected) {
                    window.offlineManager.handleOnline();
                } else {
                    window.offlineManager.handleOffline();
                }
            }
        });
    }
    
    // Esconder splash screen quando carregar
    if (SplashScreen) {
        window.addEventListener('load', () => {
            setTimeout(() => {
                SplashScreen.hide();
            }, 2000);
        });
    }
}

// Exportar para uso global
window.isCapacitor = isNative;
window.CapacitorAPI = {
    isNative,
    Network,
    App,
    SplashScreen
};

