<?php
// Cópia simplificada do main_app.php apenas com o carrossel
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$current_date = date('Y-m-d');

$user_profile_data = getUserProfileData($conn, $user_id);
if (!$user_profile_data || !$user_profile_data['onboarding_complete']) {
    header("Location: " . BASE_APP_URL . "/onboarding/onboarding.php");
    exit();
}

$page_title = "Dashboard";
$extra_js = [];
$extra_css = ['pages/_dashboard.css'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
    .lottie-animation-container {
        width: 100%;
        height: 100%;
    }
</style>

<div class="app-container">
    <header class="header">
        <div class="header-actions">
            <span>Teste Simplificado</span>
        </div>
    </header>

    <section class="main-carousel">
        <div class="carousel-container">
            <div class="carousel-loading">
                <div class="loading-spinner"></div>
                <span>Carregando...</span>
            </div>
            <div class="lottie-slide active">
                <div class="lottie-animation-container"></div>
            </div>
            <div class="lottie-slide">
                <div class="lottie-animation-container"></div>
            </div>
            <div class="lottie-slide">
                <div class="lottie-animation-container"></div>
            </div>
            <div class="lottie-slide">
                <div class="lottie-animation-container"></div>
            </div>
        </div>
        <div class="pagination-container"></div>
        <div class="lottie-click-overlay"></div>
    </section>
    
    <div class="debug-info">
        <p><strong>Teste:</strong> Este é um teste simplificado do main_app.php</p>
        <p><strong>Console:</strong> Abra o DevTools (F12) e vá na aba Console</p>
    </div>
</div>

<script>
// Teste simples - código exato do test_lottie.php
console.log("=== TESTE SIMPLES ===");
console.log("Página carregada. Tentando iniciar Lottie...");

// Verifica se lottie está disponível
if (typeof lottie === 'undefined') {
    console.error('ERRO: lottie não está disponível!');
} else {
    console.log('SUCESSO: lottie está disponível');
    
    // Seleciona o container onde a animação vai entrar
    const container = document.querySelector('.lottie-slide.active .lottie-animation-container');
    console.log('Container encontrado:', container);
    
    if (container) {
        // Configura e carrega a animação
        const animation = lottie.loadAnimation({
            container: container,
            renderer: 'svg',
            loop: true,
            autoplay: true,
            path: '/banner_receitas.json'
        });
        
        console.log("Comando lottie.loadAnimation foi executado.");
        
        // Adiciona um "ouvinte" para sabermos se a animação carregou com sucesso
        animation.addEventListener('DOMLoaded', function() {
            console.log("SUCESSO! O evento DOMLoaded da animação foi disparado.");
            
            // Esconde o loading
            const loadingElement = document.querySelector('.carousel-loading');
            if (loadingElement) {
                loadingElement.style.display = 'none';
            }
        });
        
        animation.addEventListener('data_failed', function() {
            console.error("ERRO: Falha ao carregar dados da animação!");
        });
    } else {
        console.error('ERRO: Container não encontrado!');
    }
}
</script>

<?php
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>
