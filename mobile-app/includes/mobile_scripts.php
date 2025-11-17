<?php
// Scripts comuns para o app mobile
// Incluir este arquivo antes do </body> em todas as páginas
?>
<!-- Banner Offline -->
<div class="offline-banner" id="offlineBanner" style="position: fixed; top: 0; left: 0; right: 0; background: #FF6B00; color: white; padding: 12px; text-align: center; z-index: 10000; display: none; font-size: 14px; font-weight: 600;">
    ⚠️ Você está offline. Algumas funcionalidades podem estar limitadas.
</div>

<!-- Scripts do Capacitor e Offline Manager -->
<script src="<?php echo BASE_APP_URL; ?>/assets/js/config.js"></script>
<script src="<?php echo BASE_APP_URL; ?>/assets/js/capacitor-init.js"></script>
<script src="<?php echo BASE_APP_URL; ?>/assets/js/network-error-handler.js"></script>
<script src="<?php echo BASE_APP_URL; ?>/assets/js/offline-manager.js"></script>

<!-- Registrar Service Worker -->
<script>
    (function() {
        if ('serviceWorker' in navigator) {
            // Registrar o Service Worker da RAIZ com scope completo para cobrir TODAS as páginas
            const swPath = '<?php echo BASE_APP_URL; ?>/sw.js';
            
            // Função para garantir que o SW esteja ativo
            async function ensureServiceWorkerActive() {
                try {
                    const registration = await navigator.serviceWorker.register(swPath, {
                        scope: '<?php echo BASE_APP_URL; ?>/',
                        updateViaCache: 'none' // Sempre busca nova versão do SW
                    });
                    
                    console.log('✅ Service Worker registrado:', registration.scope);
                    
                    // Esperar o Service Worker estar pronto
                    if (registration.installing) {
                        console.log('[SW] Instalando...');
                        await new Promise((resolve) => {
                            registration.installing.addEventListener('statechange', function() {
                                if (this.state === 'activated') {
                                    console.log('[SW] Instalado e ativado!');
                                    resolve();
                                }
                            });
                        });
                    } else if (registration.waiting) {
                        console.log('[SW] Esperando ativação...');
                        registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                    }
                    
                    // Garantir que está controlando
                    if (!navigator.serviceWorker.controller) {
                        console.log('[SW] Aguardando controle...');
                        await navigator.serviceWorker.ready;
                    }
                    
                    console.log('🚀 Service Worker ativo e controlando!');
                    
                    // Verificar atualizações periodicamente
                    setInterval(function() {
                        registration.update();
                    }, 60000);
                    
                } catch (error) {
                    console.error('❌ Erro ao registrar Service Worker:', error);
                }
            }
            
            // Registrar IMEDIATAMENTE
            ensureServiceWorkerActive();
            
            // Detectar quando voltar online
            window.addEventListener('online', function() {
                console.log('🌐 Conexão restaurada');
            });
            
            // Detectar quando ficar offline
            window.addEventListener('offline', function() {
                console.log('📴 Modo offline ativado');
            });
        } else {
            console.warn('⚠️ Service Worker não suportado neste navegador');
        }
    })();
</script>

