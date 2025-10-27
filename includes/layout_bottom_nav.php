<?php
// public_html/includes/layout_bottom_nav.php (VERSÃO FINAL COM ALINHAMENTO CORRIGIDO)

$current_page_script = basename($_SERVER['PHP_SELF']);

$nav_map = [
    'main_app.php' => 'home',
    'progress.php' => 'stats',
    'diary.php' => 'diary',
    'add_food_to_diary.php' => 'diary',
    'meal_types_overview.php' => 'diary',
    'explore_recipes.php' => 'explore',
    'favorite_recipes.php' => 'explore',
    'view_recipe.php' => 'explore',
    'profile_overview.php' => 'settings',
    'more_options.php' => 'settings'
];

$active_item = $nav_map[$current_page_script] ?? 'home';
?>
<style>
/* === ESTILO FINAL E CLEAN PARA A BARRA DE NAVEGAÇÃO === */
.bottom-nav {
    position: fixed;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 100%;
    max-width: 480px;
    /* sem height fixa; altura vem do padding + conteúdo + safe-area */
    padding: 12px 0 calc(12px + env(safe-area-inset-bottom)) 0;
    margin: 0;
    background: rgba(24, 24, 24, 0.85);
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    border-top: 1px solid var(--glass-border, rgba(255, 255, 255, 0.1));
    display: flex;
    justify-content: space-around;
    align-items: center;
    z-index: 1000;
}

.nav-item {
    flex: 1; /* Garante que todos os itens tenham a mesma largura */
    display: flex;
    align-items: center;      /* Redundante agora, mas bom manter */
    justify-content: center;  /* Centraliza horizontalmente o ícone */
    text-decoration: none;
    color: var(--text-secondary, #8E8E93);
    transition: color 0.2s ease;
    -webkit-tap-highlight-color: transparent;
}

.nav-item i {
    font-size: 1.5rem;
}

.nav-item.active {
    color: var(--accent-orange, #ff6b00);
}
</style>

<nav class="bottom-nav">
    <!-- Início -->
    <a href="/main_app.php" class="nav-item <?php if($active_item === 'home') echo 'active'; ?>">
        <i class="fas fa-home"></i>
    </a>

    <!-- Progresso -->
    <a href="/progress.php" class="nav-item <?php if($active_item === 'stats') echo 'active'; ?>">
        <i class="fas fa-chart-line"></i>
    </a>

    <!-- Diário -->
    <a href="/diary.php" class="nav-item <?php if($active_item === 'diary') echo 'active'; ?>">
        <i class="fas fa-book"></i>
    </a>

    <!-- Receitas/Explorar -->
    <a href="/explore_recipes.php" class="nav-item <?php if($active_item === 'explore') echo 'active'; ?>">
        <i class="fas fa-utensils"></i>
    </a>

    <!-- Mais Opções/Configurações -->
    <a href="/more_options.php" class="nav-item <?php if($active_item === 'settings') echo 'active'; ?>">
        <i class="fas fa-cog"></i>
    </a>
</nav>