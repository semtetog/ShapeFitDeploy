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
<script src="<?php echo BASE_APP_URL; ?>/assets/js/offline-manager.js"></script>

<!-- Registrar Service Worker -->
<script>
    (function() {
        if ('serviceWorker' in navigator) {
            // Registrar o Service Worker da RAIZ com scope completo para cobrir TODAS as páginas
            // Isso é igual ao que o login faz e garante que funcione em todas as páginas
            const swPath = '<?php echo BASE_APP_URL; ?>/sw.js';
            
            // Registrar imediatamente (não esperar load)
            navigator.serviceWorker.register(swPath, {
                scope: '<?php echo BASE_APP_URL; ?>/'
            })
            .then(function(registration) {
                console.log('✅ Service Worker registrado com sucesso:', registration.scope);
                
                // Verificar atualizações periodicamente
                setInterval(function() {
                    registration.update();
                }, 60000); // A cada 1 minuto
                
                // Listener para atualizações
                registration.addEventListener('updatefound', function() {
                    const newWorker = registration.installing;
                    console.log('🔄 Nova versão do Service Worker encontrada');
                    
                    newWorker.addEventListener('statechange', function() {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            console.log('✨ Nova versão disponível! Recarregue a página.');
                        }
                    });
                });
            })
            .catch(function(error) {
                console.error('❌ Erro ao registrar Service Worker:', error);
            });
            
            // Listener para quando o service worker estiver pronto
            navigator.serviceWorker.ready.then(function(registration) {
                console.log('🚀 Service Worker pronto e ativo');
            });
            
            // Detectar quando voltar online
            window.addEventListener('online', function() {
                console.log('🌐 Conexão restaurada');
                // Forçar atualização do service worker
                if (navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage({ type: 'SKIP_WAITING' });
                }
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

