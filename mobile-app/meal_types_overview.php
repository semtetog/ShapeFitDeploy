<?php
require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$user_data = getUserProfileData($conn, $user_id);

$page_title = "Refeições";
$extra_css = ['meal_types_overview_specific.css'];
// Adiciona o CSS da página principal para garantir a consistência do layout
$extra_css[] = 'main_app_specific.css'; 

require_once APP_ROOT_PATH . '/includes/layout_header.php';

// Array com os tipos de refeição
$meal_types = [
    ['name' => 'Café da Manhã', 'slug' => 'cafe_da_manha', 'image' => 'breakfast.png'],
    ['name' => 'Almoço', 'slug' => 'almoco', 'image' => 'lunch.png'],
    ['name' => 'Lanche', 'slug' => 'lanche', 'image' => 'snack.png'],
    ['name' => 'Jantar', 'slug' => 'jantar', 'image' => 'dinner.png'],
];
?>

<style>
/* CSS do layout moderno para tipos de refeição */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 0;
    margin-bottom: 20px;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.points-counter-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    height: 44px;
    padding: 0 16px;
    border-radius: 22px;
    background-color: var(--surface-color);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.2s ease;
}

.points-counter-badge:hover {
    border-color: var(--accent-orange);
}

.points-counter-badge i {
    color: var(--accent-orange);
    font-size: 1rem;
}

.points-counter-badge span {
    font-weight: 600;
    font-size: 1rem;
}

/* Estilo do container principal */
.main-app-container {
    max-width: 100%;
    margin: 0 auto;
    padding: 0 20px 100px 20px;
    min-height: 100vh;
    background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
}

/* Cards com glassmorphism */
.glass-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.page-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.page-subtitle {
    font-size: 1rem;
    color: var(--text-secondary);
    text-align: center;
    margin: 0 0 32px 0;
    line-height: 1.5;
}

/* Grid de tipos de refeição */
.meal-type-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 32px;
}

.meal-type-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 20px;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.3s ease;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    position: relative;
    overflow: hidden;
}

.meal-type-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
    border-color: var(--accent-orange);
}

.meal-type-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--accent-orange), #ff8c00);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.meal-type-card:hover::before {
    opacity: 1;
}

.meal-type-image {
    width: 100%;
    height: 120px;
    object-fit: cover;
    border-radius: 12px;
    margin-bottom: 16px;
    transition: transform 0.3s ease;
}

.meal-type-card:hover .meal-type-image {
    transform: scale(1.05);
}

.meal-type-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    text-align: center;
    transition: color 0.3s ease;
}

.meal-type-card:hover .meal-type-title {
    color: var(--accent-orange);
}

/* Responsividade */
@media (max-width: 768px) {
    .meal-type-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .main-app-container {
        padding: 0 16px 100px 16px;
    }
    
    .glass-card {
        padding: 20px;
    }
    
    .meal-type-card {
        padding: 16px;
    }
    
    .meal-type-image {
        height: 100px;
    }
}
</style>

<div class="main-app-container">
    <!-- Header -->
    <div class="header">
        <h1 class="page-title">Refeições</h1>
        
        <div class="header-actions">
            <a href="<?php echo BASE_APP_URL; ?>/points_history.php" class="points-counter-badge">
                <i class="fas fa-coins"></i>
                <span><?php echo number_format($user_data['points'] ?? 0); ?> pts</span>
            </a>
        </div>
    </div>

    <!-- Subtítulo -->
    <p class="page-subtitle">Veja nossas opções de cardápio e programe suas próximas refeições.</p>

    <!-- Grid de tipos de refeição -->
    <div class="meal-type-grid">
        <?php foreach ($meal_types as $meal): ?>
            <?php
                $link_param = isset($meal['category_slug']) ? 'category_slug=' . $meal['category_slug'] : 'meal_type=' . $meal['slug'];
            ?>
            <a href="<?php echo BASE_APP_URL; ?>/recipe_list.php?<?php echo $link_param; ?>" class="meal-type-card">
                <img src="<?php echo BASE_ASSET_URL; ?>/assets/images/meal_types/<?php echo htmlspecialchars($meal['image']); ?>" 
                     alt="<?php echo htmlspecialchars($meal['name']); ?>" 
                     class="meal-type-image">
                <h3 class="meal-type-title"><?php echo htmlspecialchars($meal['name']); ?></h3>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php
// ===================================================================
//      ADICIONANDO O MENU DE NAVEGAÇÃO INFERIOR
// ===================================================================
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php'; 
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
// ===================================================================
?>