<?php
// public_html/shapefit/includes/layout_footer.php

if (!defined('BASE_ASSET_URL')) { define('BASE_ASSET_URL', '/shapefit'); }
if (!defined('APP_ROOT_PATH')) { define('APP_ROOT_PATH', dirname(__DIR__)); }
?>
    <!-- Conteúdo principal da página vem antes desta linha -->
    
    </div> <!-- Esta div fecha um .container ou .main-app-container que está no layout_header.php ou no corpo da página -->

    <!-- Chat de suporte removido completamente -->

    <!-- ======================================================= -->
    <!-- CENTRAL DE CARREGAMENTO DE SCRIPTS (SEU CÓDIGO ORIGINAL)-->
    <!-- ======================================================= -->

    <!-- CDNs de Bibliotecas Externas -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/raphael/2.3.0/raphael.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/justgage/1.6.1/justgage.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>

    <!-- ADICIONADO: Scripts que estavam no header, agora centralizados aqui. -->
    <script src="https://cdn.jsdelivr.net/npm/quagga/dist/quagga.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js'></script>

    <!-- JavaScript global da aplicação -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            // Prevenir o menu de contexto em elementos interativos
            document.addEventListener('contextmenu', function(e) {
                const target = e.target.closest('a, button, .btn, [role="button"]');
                if (target) {
                    e.preventDefault();
                }
            });
            
            // Prevenir seleção de texto em elementos interativos
            document.addEventListener('selectstart', function(e) {
                const target = e.target.closest('a, button, .btn, [role="button"]');
                if (target) {
                    e.preventDefault();
                }
            });
            
            // Prevenir arrastar elementos
            document.addEventListener('dragstart', function(e) {
                const target = e.target.closest('a, button, .btn, [role="button"]');
                if (target) {
                    e.preventDefault();
                }
            });
        
            // Prevenir comportamento de long press em links/botões
            let touchStartTime = 0;
            let touchedElement = null;
            
            document.addEventListener('touchstart', function(e) {
                touchStartTime = Date.now();
                touchedElement = e.target.closest('a, button, .btn, [role="button"]');
            });
            
            document.addEventListener('touchend', function(e) {
                if (touchedElement) {
                    const touchDuration = Date.now() - touchStartTime;
                    if (touchDuration > 500 && e.cancelable) { // Se segurou por mais de 500ms e é cancelável
                        e.preventDefault();
                    }
                }
                touchedElement = null;
            });
            
            // Melhorar comportamento de scroll em iOS
            if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
                document.addEventListener('touchmove', function(e) {
                    const target = e.target.closest('.app-container, .main-app-container');
                    if (!target) {
                        e.preventDefault();
                    }
                }, { passive: false });
            }
        });
    </script>


    <?php
    // =======================================================
    // LÓGICA DE CARREGAMENTO DE SCRIPTS DA APLICAÇÃO (SEU CÓDIGO ORIGINAL - MANTIDO INTEGRALMENTE)
    // =======================================================

    // 1. Carrega o script global primeiro para que suas funções estejam disponíveis para os outros.
    $global_script_path = APP_ROOT_PATH . '/assets/js/script.js';
    if (file_exists($global_script_path)) {
        echo "<script src='" . BASE_ASSET_URL . "/assets/js/script.js'></script>\n";
    }

    // Carrossel de banners (Lottie)
    $banner_script_path = APP_ROOT_PATH . '/assets/js/banner-carousel.js';
    if (file_exists($banner_script_path)) {
        echo "<script src='" . BASE_ASSET_URL . "/assets/js/banner-carousel.js'></script>\n";
    }
    

    // 2. Se estiver definida a variável de scripts extras, carrega na ordem especificada
    if (isset($extra_js) && is_array($extra_js)) {
        foreach ($extra_js as $script_name) {
            $script_path = APP_ROOT_PATH . "/assets/js/" . $script_name;
            if (file_exists($script_path)) {
                echo "<script src='" . BASE_ASSET_URL . "/assets/js/" . $script_name . "'></script>\n";
            }
        }
    }
    ?>

</body>
</html>