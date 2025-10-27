<?php
// public_html/includes/layout_header.php (CORRIGIDO)

require_once __DIR__ . '/config.php';

if (empty($_SESSION['csrf_token'])) {
    try { 
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = md5(uniqid(rand(), true));
    }
}

$main_css_file_path = APP_ROOT_PATH . '/assets/css/style.css';
$main_css_version = file_exists($main_css_file_path) ? filemtime($main_css_file_path) : time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    
    <!-- ======================================================= -->
    <!--      VIEWPORT E META TAGS PARA EXPERIÊNCIA NATIVA (PWA) -->
    <!-- ======================================================= -->
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover, shrink-to-fit=no">
    
    <!-- PWA Meta Tags - FULLSCREEN -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ShapeFIT">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="ShapeFIT">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-orientations" content="portrait">
    
    <!-- Theme Colors - TRANSPARENTE -->
    <meta name="theme-color" content="transparent">
    <meta name="msapplication-navbutton-color" content="transparent">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="./manifest.json">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" sizes="180x180" href="./assets/images/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="152x152" href="./assets/images/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="144x144" href="./assets/images/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="120x120" href="./assets/images/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="114x114" href="./assets/images/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/images/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="72x72" href="./assets/images/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="60x60" href="./assets/images/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="57x57" href="./assets/images/icon-192x192.png">
    
    <!-- CSS inline removido para evitar conflito; regras vivem em assets/css/base/_global.css -->
    
    <meta name="apple-mobile-web-app-title" content="ShapeFit">
    
    <!-- Script Critical - Define altura real do viewport -->
    <script>
        function setRealViewportHeight() { 
            const vh = window.innerHeight * 0.01; 
            document.documentElement.style.setProperty('--vh', `${vh}px`); 
        }
        setRealViewportHeight();
        window.addEventListener('resize', setRealViewportHeight);
        window.addEventListener('orientationchange', function() {
            setTimeout(setRealViewportHeight, 100);
        });
        
        // Previne scroll global, mas permite scroll dentro de .app-container
        document.addEventListener('touchmove', function(event) {
            const scrollable = event.target.closest('.app-container, .container');
            if (!scrollable) {
                event.preventDefault();
            }
        }, { passive: false });
        
        // Previne scroll em inputs no iOS
        (function preventIOSScroll() {
            const inputs = document.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('focusin', () => { 
                    setTimeout(() => { window.scrollTo(0, 0); }, 0); 
                });
                input.addEventListener('blur', () => { 
                    window.scrollTo(0, 0); 
                });
            });
        })();
    </script>
    
    <!-- Service Worker Registration -->
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('./sw.js')
                .then(function(registration) {
                    console.log('ServiceWorker registrado com sucesso:', registration.scope);
                })
                .catch(function(error) {
                    console.log('Falha no registro do ServiceWorker:', error);
                });
        });
    }
    </script>
    
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Fontes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- CSS Principal -->
    <link rel="stylesheet" href="<?php echo BASE_APP_URL; ?>/assets/css/style.css?v=<?php echo $main_css_version; ?>">
    
    <!-- CSS Específico da Página (se houver) -->
    <?php
    if (isset($extra_css) && is_array($extra_css)) {
        foreach ($extra_css as $css_file_name) {
            $extra_css_file_path = APP_ROOT_PATH . '/assets/css/' . htmlspecialchars(trim($css_file_name));
            if (file_exists($extra_css_file_path)) {
                echo '<link rel="stylesheet" href="' . BASE_APP_URL . '/assets/css/' . htmlspecialchars($css_file_name) . '?v=' . filemtime($extra_css_file_path) . '">';
            }
        }
    }
    ?>
    
    <!-- Favicon -->
    <link rel="icon" href="<?php echo BASE_APP_URL; ?>/favicon.ico" type="image/x-icon">

    <!-- Variáveis Globais para JavaScript -->
    <script>
        const isUserLoggedInPHP = <?php echo json_encode(isset($_SESSION['user_id'])); ?>;
        const BASE_APP_URL = "<?php echo rtrim(BASE_APP_URL, '/'); ?>";
    </script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            -webkit-user-select: none; /* Safari */
            -ms-user-select: none; /* IE 10+ */
            user-select: none; /* Padrão */
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
        }
        
        /* Comportamento nativo para links e botões */
        a, button, .btn, [role="button"] {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        
        /* Melhor comportamento de scroll para app nativo - já definido no CSS global */
    </style>
    
</head>
<body>
    
    <div class="fixed-background"></div>
    <input type="hidden" id="csrf_token_main_app" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <div id="alert-container"></div>
    
    <!-- Conteúdo principal da página começa aqui -->