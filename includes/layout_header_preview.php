<?php
// includes/layout_header_preview.php
// Cabeçalho seguro para o preview do admin. Carrega todos os estilos necessários.

// Inclui o config para ter acesso às constantes como BASE_APP_URL
require_once __DIR__ . '/config.php';

// Simula a lógica de cache-busting do header original
$main_css_file_path = APP_ROOT_PATH . '/assets/css/style.css';
$main_css_version = file_exists($main_css_file_path) ? filemtime($main_css_file_path) : time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Preview'; ?></title>
    
    <!-- Fontes e Ícones (copiado do seu header) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- ======================================================= -->
    <!--          A CORREÇÃO FINAL ESTÁ AQUI                     -->
    <!-- ======================================================= -->
    <!-- Carregando o 'style.css' corretamente -->
    <link rel="stylesheet" href="<?php echo BASE_APP_URL; ?>/assets/css/style.css?v=<?php echo $main_css_version; ?>">
    
    <!-- Carregando o CSS extra da página (recipe_detail_page.css) -->
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
</head>
<body class="dark-theme"> <!-- Força o tema escuro se necessário -->
    
    <!-- Fundo Fixo (copiado do seu header para garantir o background) -->
    <div class="fixed-background"></div>