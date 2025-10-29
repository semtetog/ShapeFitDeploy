<?php
// public_html/add_food_to_diary.php - VERSÃO REFATORADA COM NOVO ESTILO

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';

// --- Lógica PHP para obter dados da página ---
$user_id = $_SESSION['user_id'];
$target_date_str = $_GET['date'] ?? date('Y-m-d');
$target_meal_type_slug = $_GET['meal_type'] ?? 'breakfast';

$date_obj_target = DateTime::createFromFormat('Y-m-d', $target_date_str);
if (!$date_obj_target || $date_obj_target->format('Y-m-d') !== $target_date_str) {
    $target_date_str = date('Y-m-d');
}

$meal_type_options = [
    'breakfast' => 'Café da Manhã', 
    'morning_snack' => 'Lanche da Manhã', 
    'lunch' => 'Almoço',
    'afternoon_snack' => 'Lanche da Tarde', 
    'dinner' => 'Jantar', 
    'supper' => 'Ceia'
];

if (empty($target_meal_type_slug) || !isset($meal_type_options[$target_meal_type_slug])) {
    $current_hour_for_select = (int)date('G');
    if ($current_hour_for_select >= 5 && $current_hour_for_select < 10) { $target_meal_type_slug = 'breakfast'; }
    elseif ($current_hour_for_select >= 10 && $current_hour_for_select < 12) { $target_meal_type_slug = 'morning_snack'; }
    elseif ($current_hour_for_select >= 12 && $current_hour_for_select < 15) { $target_meal_type_slug = 'lunch'; }
    elseif ($current_hour_for_select >= 15 && $current_hour_for_select < 18) { $target_meal_type_slug = 'afternoon_snack'; }
    elseif ($current_hour_for_select >= 18 && $current_hour_for_select < 21) { $target_meal_type_slug = 'dinner'; }
    else { $target_meal_type_slug = 'supper'; }
}

// Buscar receitas favoritas do usuário
$favorite_recipes = [];
$stmt_fav = $conn->prepare("
    SELECT r.id, r.name, r.image_filename, r.kcal_per_serving, r.protein_g_per_serving, r.carbs_g_per_serving, r.fat_g_per_serving
    FROM sf_recipes r
    JOIN sf_user_favorite_recipes ufr ON r.id = ufr.recipe_id
    WHERE ufr.user_id = ? AND r.is_public = TRUE
    ORDER BY r.name ASC
    LIMIT 20
");
$stmt_fav->bind_param("i", $user_id);
$stmt_fav->execute();
$result_fav = $stmt_fav->get_result();
while ($recipe = $result_fav->fetch_assoc()) {
    $favorite_recipes[] = $recipe;
}
$stmt_fav->close();

// Buscar receitas recentes
$recent_recipes = [];
$stmt_recent = $conn->prepare("
    SELECT DISTINCT r.id, r.name, r.image_filename, r.kcal_per_serving, r.protein_g_per_serving, r.carbs_g_per_serving, r.fat_g_per_serving
    FROM sf_recipes r
    JOIN sf_user_meal_log log ON r.id = log.recipe_id
    WHERE log.user_id = ? AND r.is_public = TRUE
    ORDER BY log.logged_at DESC
    LIMIT 10
");
$stmt_recent->bind_param("i", $user_id);
$stmt_recent->execute();
$result_recent = $stmt_recent->get_result();
while ($recipe = $result_recent->fetch_assoc()) {
    $recent_recipes[] = $recipe;
}
$stmt_recent->close();

$page_title = "Adicionar Refeição";
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* === ADD FOOD PAGE - ESTILO COMPACTO E MODERNO === */

/* Header compacto */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-title {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.back-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.2s ease;
}

.back-btn:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

/* Formulário de configuração compacto */
.meal-setup {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 20px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group label {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-control {
    -webkit-appearance: none; /* Adicione esta linha */
    appearance: none; /* Adicione esta linha */
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    padding: 10px 12px;
    color: var(--text-primary);
    font-size: 14px;
    transition: all 0.2s ease;
    width: 100%; /* Garante que ocupe todo o espaço disponível */
    box-sizing: border-box; /* Evita que o padding cause overflow */
}

.form-control:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

/* Busca compacta */
.search-section {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 20px;
}

.search-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
}

.tab-btn {
    flex: 1;
    padding: 8px 12px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 8px;
    color: var(--text-secondary);
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.tab-btn.active {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
    color: #fff;
}

.tab-btn:hover:not(.active) {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.search-results {
    margin-top: 12px;
    max-height: 300px;
    overflow-y: auto;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.08);
}

/* Botões de criar alimento customizado */
.custom-food-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-top: 12px;
}

.custom-food-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 16px 12px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.2s ease;
    text-align: center;
}

.custom-food-btn:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
    transform: translateY(-2px);
}

.custom-food-btn i {
    font-size: 24px;
    color: var(--accent-orange);
}

.custom-food-btn span {
    font-size: 12px;
    font-weight: 500;
    line-height: 1.3;
}

@media (max-width: 768px) {
    .custom-food-actions {
        grid-template-columns: 1fr;
    }
    
    .custom-food-btn {
        flex-direction: row;
        justify-content: flex-start;
        padding: 12px 16px;
        gap: 12px;
    }
    
    .custom-food-btn i {
        font-size: 20px;
    }
    
    .custom-food-btn span {
        font-size: 13px;
        text-align: left;
    }
}

.search-result-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    cursor: pointer;
    transition: all 0.2s ease;
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-item:hover {
    background: rgba(255, 255, 255, 0.05);
}

.search-result-type {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    padding: 2px 6px;
    border-radius: 4px;
    background: var(--accent-orange);
    color: #fff;
}

.search-result-type.food {
    background: #4CAF50;
}

.search-result-info {
    flex: 1;
}

.search-result-name {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary);
    margin: 0 0 2px 0;
}

.search-result-macros {
    font-size: 11px;
    color: var(--text-secondary);
    margin: 0;
}

.search-input-wrapper {
    position: relative;
    display: flex;
    gap: 10px;
}

.search-input {
    flex: 1;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    padding: 10px 12px;
    color: var(--text-primary);
    font-size: 14px;
    transition: all 0.2s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

.search-btn {
    background: var(--accent-orange);
    border: none;
    border-radius: 12px;
    padding: 10px 16px;
    color: white;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 60px;
}

.search-btn:hover {
    background: #ff7a1a;
    transform: translateY(-1px);
}

/* Seções de receitas */
.recipes-section {
    margin-bottom: 20px;
}

.section-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 12px 0;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.recipes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
}

.recipe-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    color: inherit;
}

.recipe-card:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
    transform: translateY(-2px);
}

.recipe-image {
    width: 100%;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 8px;
}

.recipe-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 4px 0;
    line-height: 1.3;
}

.recipe-macros {
    font-size: 11px;
    color: var(--text-secondary);
    margin: 0;
}

.recipe-kcal {
    font-size: 12px;
    color: var(--accent-orange);
    font-weight: 600;
    margin-top: 4px;
}

/* Modal completamente redesenhado */
.recipe-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    pointer-events: none;
}

.recipe-modal.visible {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

.modal-content {
    position: fixed;
    top: 50%;
    left: 50%;
    width: calc(100% - 40px);
    max-width: 500px;
    max-height: calc(100vh - 40px);
    background: #1a1a1a;
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    transform: translate(-50%, -50%) scale(0.9);
    transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
    overflow: hidden;
    box-sizing: border-box;
}

.recipe-modal.visible .modal-content {
    transform: translate(-50%, -50%) scale(1);
}

.modal-header {
    padding: 16px 24px 12px 24px;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    position: relative;
    flex-shrink: 0;
}

.modal-drag-indicator {
    width: 36px;
    height: 4px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
    margin: 0 auto 12px auto;
}

.modal-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    padding: 0;
}


.modal-body {
    flex: 1;
    padding: 16px 20px;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
    min-height: 0;
    max-height: calc(100vh - 200px); /* Garante que não ultrapasse a tela */
}

.form-section {
    margin-bottom: 8px; /* Reduz ainda mais a margem entre seções */
}

.portion-section {
    margin-bottom: 8px; /* Reduz ainda mais a margem entre seções */
}

.portion-label {
    display: block;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.portion-input {
    -webkit-appearance: none; /* Adicione esta linha para compatibilidade com iOS/Safari */
    appearance: none; /* Adicione esta linha para o padrão moderno */
    width: 100%;
    display: block; /* Adicione esta linha */
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 12px 16px;
    color: var(--text-primary);
    font-size: 1rem;
    font-weight: 500;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.portion-input:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

/* Sistema de unidades */
.quantity-input-group {
    display: flex;
    gap: 8px;
    align-items: center;
    width: 100%; /* Adicione esta linha para forçar o container a respeitar os limites */
}

.quantity-input {
    width: 70px;      /* Largura ainda menor para o número */
    flex-shrink: 0;   /* Impede que o campo encolha */
    -webkit-appearance: none;
    appearance: none;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 12px 8px; /* Reduz padding lateral */
    color: var(--text-primary);
    font-size: 1rem;
    font-weight: 500;
    transition: all 0.2s ease;
    box-sizing: border-box;
    text-align: center;
}

/* Quando o seletor de unidade estiver oculto, o campo de quantidade ocupa toda a largura */
.quantity-input-full-width {
    flex: 1 !important;
    width: 100% !important;
}

.quantity-input:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

.unit-select {
    flex-grow: 1;     /* Faz o seletor crescer para ocupar o espaço restante */
    width: auto;      /* A largura se torna automática baseada no flex-grow */
    -webkit-appearance: none;
    appearance: none;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 12px 12px; /* Reduz padding lateral para ficar mais compacto */
    color: var(--text-primary);
    font-size: 0.9rem; /* Reduz ligeiramente o tamanho da fonte */
    font-weight: 500;
    transition: all 0.2s ease;
    box-sizing: border-box;
    cursor: pointer;
}

.unit-select:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

.quantity-info {
    margin-top: 8px;
    padding: 8px 12px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 8px;
    font-size: 0.85rem;
}

.text-muted {
    color: var(--text-secondary);
}

.nutrition-display {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 8px; /* Reduz ainda mais o padding interno */
    margin-bottom: 8px; /* Reduz ainda mais a margem */
}

.nutrition-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 6px; /* Reduz ainda mais o espaçamento interno */
}

.nutrition-item {
    text-align: center;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 8px;
    padding: 8px 6px; /* Reduz o padding dos itens nutricionais */
}

.nutrition-item-label {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.nutrition-item-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
}

.nutrition-item-unit {
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--accent-orange);
    margin-left: 2px;
}

.modal-footer {
    padding: 16px 24px; /* Aumentado de 12px para 16px */
    padding-bottom: calc(16px + env(safe-area-inset-bottom)); /* Aumentado para dar mais espaço */
    background: #1a1a1a;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    flex-shrink: 0;
    margin-top: auto; /* Garante que fique na parte inferior */
}

.btn-add-meal {
    width: 100%;
    height: 48px;
    border-radius: 12px;
    background: var(--accent-orange);
    border: none;
    color: #fff;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-add-meal:hover {
    background: #ff7a1a;
}

.btn-add-meal i {
    font-size: 0.9rem;
}

/* Responsividade para mobile */
@media (max-width: 768px) {
    .modal-content {
        width: calc(100% - 20px);
        max-height: calc(100vh - 20px);
        border-radius: 16px;
    }
    
    .modal-header {
        padding: 16px 20px 12px 20px;
    }
    
    .modal-title {
        font-size: 1.1rem;
    }
    
    .modal-body {
        padding: 12px 20px;
        max-height: calc(100vh - 160px);
    }
    
    .modal-footer {
        padding: 16px 20px;
        padding-bottom: calc(16px + env(safe-area-inset-bottom));
    }
    
    .btn-add-meal {
        height: 48px;
        font-size: 1rem;
    }
}

/* Ajuste para telas muito pequenas */
@media (max-width: 480px) {
    .modal-content {
        width: calc(100% - 16px);
        max-height: calc(100vh - 16px);
    }
    
    .modal-body {
        padding: 10px 16px;
        max-height: calc(100vh - 140px);
    }
    
    .modal-footer {
        padding: 12px 16px;
        padding-bottom: calc(12px + env(safe-area-inset-bottom));
    }
}

/* Estado vazio */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 48px;
    color: var(--accent-orange);
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 16px;
    color: var(--text-primary);
    margin: 0 0 8px 0;
}

.empty-state p {
    font-size: 12px;
    margin: 0;
}

/* Responsividade */
@media (max-width: 768px) {
    .meal-setup {
        grid-template-columns: 1fr;
        gap: 12px;
        padding: 12px;
    }
    
    .recipes-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 8px;
    }
    
    .recipe-card {
        padding: 8px;
    }
    
    .recipe-image {
        height: 60px;
    }
    
    .recipe-name {
        font-size: 12px;
    }
    
    .page-title {
        font-size: 20px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 8px;
    }
}

/* Adicione este novo bloco de código ao seu CSS */
.app-container {
    padding-top: 24px; /* Um valor de fallback para navegadores que não suportam a próxima linha */
    padding-top: env(safe-area-inset-top); /* Adiciona padding automático para se ajustar a notches e status bars de celulares modernos */
}
</style>

<div class="app-container">
    <!-- Header -->
    <div class="page-header">
        <a href="<?php echo BASE_APP_URL; ?>/diary.php?date=<?php echo $target_date_str; ?>" class="back-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <h1 class="page-title">Adicionar Refeição</h1>
        <div></div>
    </div>

    <!-- Configuração da refeição -->
    <div class="meal-setup">
                        <div class="form-group">
            <label>Data</label>
            <input type="date" class="form-control" id="meal-date" value="<?php echo $target_date_str; ?>">
                        </div>
                        <div class="form-group">
            <label>Tipo de Refeição</label>
            <select class="form-control" id="meal-type">
                <?php foreach ($meal_type_options as $slug => $name): ?>
                    <option value="<?php echo $slug; ?>" <?php echo $slug === $target_meal_type_slug ? 'selected' : ''; ?>>
                        <?php echo $name; ?>
                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

    <!-- Busca de receitas e alimentos -->
    <div class="search-section">
        <div class="search-tabs">
            <button class="tab-btn active" data-tab="recipes">Receitas</button>
            <button class="tab-btn" data-tab="foods">Alimentos</button>
        </div>
        <div class="search-input-wrapper">
            <input type="text" class="search-input" id="search-input" placeholder="Buscar receitas e alimentos...">
            <button class="search-btn" onclick="performSearch()">
                <i class="fas fa-search"></i>
            </button>
        </div>
        <div class="search-results" id="search-results" style="display: none;"></div>
        
        <!-- Novos botões para criar alimentos -->
        <div class="custom-food-actions">
            <a href="<?php echo BASE_APP_URL; ?>/scan_barcode.php" class="custom-food-btn">
                <i class="fas fa-barcode"></i>
                <span>Escanear Código de Barras</span>
            </a>
            <a href="<?php echo BASE_APP_URL; ?>/create_custom_food.php" class="custom-food-btn">
                <i class="fas fa-plus-circle"></i>
                <span>Cadastrar Alimento Manual</span>
            </a>
        </div>
    </div>

    <!-- Receitas favoritas -->
    <?php if (!empty($favorite_recipes)): ?>
        <div class="recipes-section">
            <h2 class="section-title">Favoritas</h2>
            <div class="recipes-grid" id="favorite-recipes">
                <?php foreach ($favorite_recipes as $recipe): ?>
                    <div class="recipe-card" onclick="selectRecipe(<?php echo htmlspecialchars(json_encode($recipe)); ?>)">
                        <?php if (!empty($recipe['image_filename'])): ?>
                            <img src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/' . htmlspecialchars($recipe['image_filename']); ?>" 
                                 alt="<?php echo htmlspecialchars($recipe['name']); ?>" class="recipe-image">
                        <?php else: ?>
                            <img src="<?php echo BASE_ASSET_URL; ?>/assets/images/recipes/placeholder_food.jpg" 
                                 alt="Placeholder" class="recipe-image">
                        <?php endif; ?>
                        <h3 class="recipe-name"><?php echo htmlspecialchars($recipe['name']); ?></h3>
                        <p class="recipe-macros">
                            P: <?php echo round($recipe['protein_g_per_serving']); ?>g | 
                            C: <?php echo round($recipe['carbs_g_per_serving']); ?>g | 
                            G: <?php echo round($recipe['fat_g_per_serving']); ?>g
                        </p>
                        <div class="recipe-kcal"><?php echo round($recipe['kcal_per_serving']); ?> kcal</div>
                    </div>
                <?php endforeach; ?>
                    </div>
                </div>
    <?php endif; ?>

    <!-- Receitas recentes -->
    <?php if (!empty($recent_recipes)): ?>
        <div class="recipes-section">
            <h2 class="section-title">Recentes</h2>
            <div class="recipes-grid" id="recent-recipes">
                <?php foreach ($recent_recipes as $recipe): ?>
                    <div class="recipe-card" onclick="selectRecipe(<?php echo htmlspecialchars(json_encode($recipe)); ?>)">
                        <?php if (!empty($recipe['image_filename'])): ?>
                            <img src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/' . htmlspecialchars($recipe['image_filename']); ?>" 
                                 alt="<?php echo htmlspecialchars($recipe['name']); ?>" class="recipe-image">
                        <?php else: ?>
                            <img src="<?php echo BASE_ASSET_URL; ?>/assets/images/recipes/placeholder_food.jpg" 
                                 alt="Placeholder" class="recipe-image">
                        <?php endif; ?>
                        <h3 class="recipe-name"><?php echo htmlspecialchars($recipe['name']); ?></h3>
                        <p class="recipe-macros">
                            P: <?php echo round($recipe['protein_g_per_serving']); ?>g | 
                            C: <?php echo round($recipe['carbs_g_per_serving']); ?>g | 
                            G: <?php echo round($recipe['fat_g_per_serving']); ?>g
                        </p>
                        <div class="recipe-kcal"><?php echo round($recipe['kcal_per_serving']); ?> kcal</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Estado vazio se não há receitas -->
    <?php if (empty($favorite_recipes) && empty($recent_recipes)): ?>
        <div class="empty-state">
            <i class="fas fa-utensils"></i>
            <h3>Nenhuma receita encontrada</h3>
            <p>Explore receitas favoritas ou adicione novas receitas</p>
                </div>
    <?php endif; ?>
    </div> 

<!-- Modal de configuração -->
<div class="recipe-modal" id="recipe-modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-drag-indicator"></div>
            <h2 class="modal-title" id="modal-recipe-name">Nome da Receita</h2>
        </div>
        <div class="modal-body">
            <div class="form-section">
                <label for="custom_meal_name" class="portion-label">Nome da Refeição</label>
                <input type="text" id="custom_meal_name" class="portion-input" placeholder="Ex: Arroz com frango grelhado">
            </div>
            
            <div class="form-section">
                <label for="meal_time" class="portion-label">Horário da Refeição</label>
                <input type="time" id="meal_time" class="portion-input" value="<?php echo date('H:i'); ?>">
            </div>
            
            <div class="portion-section">
                <label class="portion-label" id="quantity-label">Quantidade</label>
                <div class="quantity-input-group">
                    <input type="number" id="quantity" class="quantity-input" value="1" min="0.1" step="0.1" placeholder="1">
                    <select id="unit-select" class="unit-select" style="display: none;">
                        <option value="">Carregando...</option>
                    </select>
        </div>
                <div class="quantity-info" id="quantity-info" style="display: none;">
                    <small class="text-muted">
                        <span id="conversion-info"></span>
                    </small>
    </div>
</div>
    
            <div class="nutrition-display">
                <div class="nutrition-grid">
                    <div class="nutrition-item">
                        <div class="nutrition-item-label">Calorias</div>
                        <div class="nutrition-item-value" id="total-kcal">0 <span class="nutrition-item-unit">kcal</span></div>
                    </div>
                    <div class="nutrition-item">
                        <div class="nutrition-item-label">Proteínas</div>
                        <div class="nutrition-item-value" id="total-protein">0 <span class="nutrition-item-unit">g</span></div>
                    </div>
                    <div class="nutrition-item">
                        <div class="nutrition-item-label">Carboidratos</div>
                        <div class="nutrition-item-value" id="total-carbs">0 <span class="nutrition-item-unit">g</span></div>
                    </div>
                    <div class="nutrition-item">
                        <div class="nutrition-item-label">Gorduras</div>
                        <div class="nutrition-item-value" id="total-fat">0 <span class="nutrition-item-unit">g</span></div>
                    </div>
                </div>
            </div>
            <input type="hidden" id="selected-recipe-id">
        </div>
        <div class="modal-footer">
            <button class="btn-add-meal" onclick="confirmMeal()">
                <i class="fas fa-plus"></i>
                Adicionar ao Diário
            </button>
            </div>
        </div>
    </div>

<script>
let selectedRecipe = null;

function selectRecipe(recipe) {
    console.log('🎯 SELECT RECIPE - INÍCIO');
    console.log('Recipe recebido:', recipe);
    console.log('É alimento?', recipe.is_food);
    
    selectedRecipe = recipe;
    document.getElementById('modal-recipe-name').textContent = recipe.name;
    document.getElementById('selected-recipe-id').value = recipe.id;
    
    // Preencher nome da refeição automaticamente
    document.getElementById('custom_meal_name').value = recipe.name;
    
    // RESETAR ESTADO ANTERIOR - CORRIGIR BUG DA COR VERMELHA E LEGENDA
    const quantityLabel = document.getElementById('quantity-label');
    const quantityInfo = document.getElementById('quantity-info');
    
    quantityLabel.style.color = ''; // Resetar cor
    quantityLabel.innerHTML = 'Quantidade'; // Resetar texto
    quantityInfo.innerHTML = '<small class="text-muted"><span id="conversion-info"></span></small>'; // Resetar HTML
    quantityInfo.style.display = 'none'; // Ocultar inicialmente
    
    // Mostrar/ocultar seletor de unidade baseado no tipo
    const unitSelect = document.getElementById('unit-select');
    
    if (recipe.is_food) {
        console.log('🍎 É ALIMENTO - Carregando unidades específicas');
        // Para alimentos, mostrar seletor de unidade
        unitSelect.style.display = 'block';
        quantityLabel.textContent = 'Quantidade';
        document.getElementById('quantity').classList.remove('quantity-input-full-width');
        loadUnitsForFood(recipe.id, '0');
    } else {
        console.log('📝 É RECEITA - Ocultando seletor de unidades');
        // Para receitas, ocultar seletor de unidade e usar "porções"
        unitSelect.style.display = 'none';
        quantityLabel.textContent = 'Porções';
        document.getElementById('quantity').classList.add('quantity-input-full-width');
        updateMacros(); // Usar cálculo direto para receitas
    }
    
    // Mostrar modal
    document.getElementById('recipe-modal').classList.add('visible');
    console.log('✅ Modal aberto');
}

function updateMacros() {
    if (!selectedRecipe) return;
    
    const quantity = parseFloat(document.getElementById('quantity').value) || 1;
    const unitSelect = document.getElementById('unit-select');
    
    // Se for receita ou não houver seletor de unidade visível
    if (!selectedRecipe.is_food || unitSelect.style.display === 'none') {
        // Cálculo direto para receitas (sistema antigo)
        const totalKcal = Math.round(selectedRecipe.kcal_per_serving * quantity);
        const totalProtein = Math.round(selectedRecipe.protein_g_per_serving * quantity * 10) / 10;
        const totalCarbs = Math.round(selectedRecipe.carbs_g_per_serving * quantity * 10) / 10;
        const totalFat = Math.round(selectedRecipe.fat_g_per_serving * quantity * 10) / 10;
        
        document.getElementById('total-kcal').innerHTML = totalKcal + ' <span class="nutrition-item-unit">kcal</span>';
        document.getElementById('total-protein').innerHTML = totalProtein + ' <span class="nutrition-item-unit">g</span>';
        document.getElementById('total-carbs').innerHTML = totalCarbs + ' <span class="nutrition-item-unit">g</span>';
        document.getElementById('total-fat').innerHTML = totalFat + ' <span class="nutrition-item-unit">g</span>';
        
        // Ocultar informação de conversão para receitas
        document.getElementById('quantity-info').style.display = 'none';
        return;
    }
    
    // Para alimentos, usar API de cálculo com unidades
    const unitId = unitSelect.value;
    if (unitId) {
        calculateNutritionWithUnits(quantity, unitId);
    }
}

// Função auxiliar para extrair ID numérico de um ID que pode ter prefixo
function extractNumericId(id) {
    if (typeof id === 'string' && id.includes('_')) {
        const parts = id.split('_');
        const numeric = parseInt(parts[parts.length - 1]);
        return isNaN(numeric) || numeric <= 0 ? null : numeric;
    }
    const numeric = parseInt(id);
    return isNaN(numeric) || numeric <= 0 ? null : numeric;
}

function calculateNutritionWithUnits(quantity, unitId) {
    // Extrair ID numérico do alimento
    const numericFoodId = extractNumericId(selectedRecipe.id);
    if (!numericFoodId) {
        console.error('❌ ID inválido para cálculo:', selectedRecipe.id);
        return;
    }
    
    const formData = new FormData();
    formData.append('food_id', numericFoodId);
    formData.append('quantity', quantity);
    formData.append('unit_id', unitId);
    formData.append('is_recipe', selectedRecipe.is_food ? '0' : '1');
    
    fetch('<?php echo BASE_APP_URL; ?>/api/calculate_nutrition.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const nutrition = data.data.nutrition;
            const unitInfo = data.data.unit_info;
            
            document.getElementById('total-kcal').innerHTML = nutrition.kcal + ' <span class="nutrition-item-unit">kcal</span>';
            document.getElementById('total-protein').innerHTML = nutrition.protein + ' <span class="nutrition-item-unit">g</span>';
            document.getElementById('total-carbs').innerHTML = nutrition.carbs + ' <span class="nutrition-item-unit">g</span>';
            document.getElementById('total-fat').innerHTML = nutrition.fat + ' <span class="nutrition-item-unit">g</span>';
            
            // Mostrar informação de conversão
            const conversionInfo = document.getElementById('conversion-info');
            const quantityInfo = document.getElementById('quantity-info');
            conversionInfo.textContent = `${quantity} ${unitInfo.name} = ${data.data.quantity_in_base_unit}${data.data.quantity_in_base_unit >= 1000 ? 'g' : 'g'}`;
            quantityInfo.style.display = 'block';
        } else {
            console.error('Erro ao calcular nutrição:', data.error);
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
    });
}

function closeModal() {
    const modal = document.getElementById('recipe-modal');
    const modalContent = modal.querySelector('.modal-content');
    modal.classList.remove('visible');

    // Adicione esta linha para resetar a posição do modal
    modalContent.style.transform = ''; 

    selectedRecipe = null;
}

function loadUnitsForFood(foodId, isRecipe) {
    console.log('🔍 LOAD UNITS FOR FOOD - INÍCIO');
    console.log('Food ID original:', foodId);
    console.log('Is Recipe:', isRecipe);
    
    // Extrair o número do ID se vier com prefixo (ex: "taco_66" -> "66")
    const numericId = extractNumericId(foodId);
    if (!numericId) {
        console.error('❌ ID inválido após extração:', foodId);
        showNoUnitsMessage();
        return;
    }
    
    console.log('✅ Food ID numérico final:', numericId);
    
    const unitSelect = document.getElementById('unit-select');
    unitSelect.innerHTML = '<option value="">Carregando...</option>';
    
    const url = `<?php echo BASE_APP_URL; ?>/api/get_units.php?action=for_food&food_id=${numericId}`;
    console.log('URL da API:', url);
    
    fetch(url)
    .then(response => {
        console.log('📡 Resposta da API recebida:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('📊 Dados da API:', data);
        
        if (data.success && data.data.length > 0) {
            console.log('✅ Unidades específicas encontradas:', data.data.length);
            unitSelect.innerHTML = '';
            data.data.forEach(unit => {
                console.log('➕ Adicionando unidade:', unit);
                const option = document.createElement('option');
                option.value = unit.id;
                option.textContent = `${unit.name} (${unit.abbreviation})`;
                if (unit.is_default) {
                    option.selected = true;
                }
                unitSelect.appendChild(option);
            });
            
            // Atualizar macros após carregar unidades
            updateMacros();
        } else {
            console.log('⚠️ Nenhuma unidade específica encontrada - ALIMENTO NÃO CLASSIFICADO');
            // Mostrar mensagem de não classificado em vez de carregar todas as unidades
            showNoUnitsMessage();
        }
    })
    .catch(error => {
        console.error('❌ Erro ao carregar unidades:', error);
        showNoUnitsMessage();
    });
}

function showNoUnitsMessage() {
    console.log('🚫 SHOW NO UNITS MESSAGE - ALIMENTO NÃO CLASSIFICADO');
    
    const unitSelect = document.getElementById('unit-select');
    const quantityLabel = document.getElementById('quantity-label');
    const quantityInfo = document.getElementById('quantity-info');
    
    // Ocultar o seletor de unidades
    unitSelect.style.display = 'none';
    
    // Mostrar mensagem de não classificado
    quantityLabel.innerHTML = '⚠️ Alimento não classificado';
    quantityLabel.style.color = '#ff6b6b';
    
    // Mostrar informação sobre classificação
    quantityInfo.innerHTML = `
        <div class="no-units-message">
            <p>⚠️ Este alimento ainda não foi classificado pelas estagiárias.</p>
            <p>Unidades de medida não disponíveis.</p>
            <p>Peça para uma estagiária classificar este alimento no painel administrativo.</p>
        </div>
    `;
    quantityInfo.style.display = 'block';
    
    // Fazer o campo de quantidade ocupar toda a largura
    document.getElementById('quantity').classList.add('quantity-input-full-width');
    
    // Atualizar macros (vai usar valores padrão)
    updateMacros();
}

function loadDefaultUnits() {
    console.log('🔄 LOAD DEFAULT UNITS - INÍCIO');
    
    const unitSelect = document.getElementById('unit-select');
    const url = `<?php echo BASE_APP_URL; ?>/api/get_units.php?action=all`;
    console.log('URL da API (todas as unidades):', url);
    
    fetch(url)
    .then(response => {
        console.log('📡 Resposta da API (todas as unidades):', response.status);
        return response.json();
    })
    .then(data => {
        console.log('📊 Dados da API (todas as unidades):', data);
        
        if (data.success) {
            console.log('✅ Carregando unidades padrão + todas as unidades');
            unitSelect.innerHTML = '';
            
            // Adicionar unidades padrão com IDs reais do banco
            const defaultUnits = [
                { id: '26', name: 'Grama', abbreviation: 'g' }, // ID real do banco
                { id: '28', name: 'Mililitro', abbreviation: 'ml' }, // ID real do banco  
                { id: '31', name: 'Unidade', abbreviation: 'un' } // ID real do banco
            ];
            
            console.log('➕ Adicionando unidades padrão:', defaultUnits);
            defaultUnits.forEach(unit => {
                const option = document.createElement('option');
                option.value = unit.id;
                option.textContent = `${unit.name} (${unit.abbreviation})`;
                if (unit.id === '31') { // Unidade como padrão
                    option.selected = true;
                }
                unitSelect.appendChild(option);
            });
            
            // Adicionar outras unidades
            console.log('➕ Adicionando outras unidades:', data.data);
            data.data.forEach(unit => {
                const option = document.createElement('option');
                option.value = unit.id;
                option.textContent = `${unit.name} (${unit.abbreviation})`;
                unitSelect.appendChild(option);
            });
            
            console.log('🎯 Total de opções no select:', unitSelect.options.length);
            updateMacros();
        } else {
            console.log('❌ Falha ao carregar unidades padrão');
        }
    })
    .catch(error => {
        console.error('❌ Erro ao carregar unidades padrão:', error);
    });
}

function confirmMeal() {
    if (!selectedRecipe) return;
    
    // Validar campos obrigatórios
    const customMealName = document.getElementById('custom_meal_name').value.trim();
    const quantity = document.getElementById('quantity').value;
    const unitSelect = document.getElementById('unit-select');
    const unitId = unitSelect.value;
    
    if (!customMealName) {
        alert('Por favor, insira o nome da refeição.');
        return;
    }
    
    if (!quantity || parseFloat(quantity) <= 0) {
        alert('Por favor, insira uma quantidade válida.');
        return;
    }
    
    // Só exigir unidade para alimentos
    if (selectedRecipe.is_food && (!unitId || unitSelect.style.display === 'block')) {
        alert('Por favor, selecione uma unidade de medida.');
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?php echo BASE_APP_URL; ?>/process_log_meal.php';
    
    const fields = {
        'csrf_token': '<?php echo $_SESSION['csrf_token']; ?>',
        'custom_meal_name': customMealName,
        'meal_time': document.getElementById('meal_time').value,
        'servings_consumed': quantity,
        'meal_type': document.getElementById('meal-type').value,
        'date_consumed': document.getElementById('meal-date').value,
        'kcal_per_serving': selectedRecipe.kcal_per_serving,
        'protein_per_serving': selectedRecipe.protein_g_per_serving,
        'carbs_per_serving': selectedRecipe.carbs_g_per_serving,
        'fat_per_serving': selectedRecipe.fat_g_per_serving
    };
    
    // Adicionar ID correto baseado no tipo
    if (selectedRecipe.is_food) {
        fields['food_name'] = selectedRecipe.name;
        fields['is_food'] = '1';
    } else {
        fields['recipe_id'] = selectedRecipe.id;
    }
    
    for (const [name, value] of Object.entries(fields)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
}

let currentTab = 'recipes';
let searchTimeout = null;

// Função para alternar entre abas
function switchTab(tab) {
    currentTab = tab;
    
    // Atualizar botões das abas
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
    
    // Atualizar placeholder
    const searchInput = document.getElementById('search-input');
    if (tab === 'recipes') {
        searchInput.placeholder = 'Buscar receitas...';
    } else {
        searchInput.placeholder = 'Buscar alimentos...';
    }
    
    // Limpar resultados se houver
    clearSearchResults();
}

// Função para limpar resultados de busca
function clearSearchResults() {
    const resultsDiv = document.getElementById('search-results');
    resultsDiv.style.display = 'none';
    resultsDiv.innerHTML = '';
}

// Função para realizar busca
function performSearch() {
    const query = document.getElementById('search-input').value.trim();
    
    if (query.length < 2) {
        clearSearchResults();
        return;
    }
    
    if (currentTab === 'recipes') {
        searchRecipes(query);
    } else {
        searchFoods(query);
    }
}

// Função para buscar receitas
async function searchRecipes(query) {
    try {
        const response = await fetch(`<?php echo BASE_APP_URL; ?>/api/ajax_search_foods_recipes.php?term=${encodeURIComponent(query)}&type=recipes`);
        const data = await response.json();
        
        if (data.success && data.data.length > 0) {
            displaySearchResults(data.data, 'recipe');
        } else {
            clearSearchResults();
        }
    } catch (error) {
        console.error('Erro ao buscar receitas:', error);
        clearSearchResults();
    }
}

// Função para buscar alimentos
async function searchFoods(query) {
    try {
        const response = await fetch(`<?php echo BASE_APP_URL; ?>/api/ajax_search_food.php?term=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.success && data.data.length > 0) {
            displaySearchResults(data.data, 'food');
        } else {
            clearSearchResults();
        }
    } catch (error) {
        console.error('Erro ao buscar alimentos:', error);
        clearSearchResults();
    }
}

// Função para exibir resultados de busca
function displaySearchResults(results, type) {
    const resultsDiv = document.getElementById('search-results');
    resultsDiv.innerHTML = '';
    
    results.forEach(item => {
        const resultItem = document.createElement('div');
        resultItem.className = 'search-result-item';
        resultItem.onclick = () => selectSearchResult(item, type);
        
        let macros = '';
        if (type === 'recipe') {
            macros = `P: ${Math.round(item.protein_g_per_serving || 0)}g | C: ${Math.round(item.carbs_g_per_serving || 0)}g | G: ${Math.round(item.fat_g_per_serving || 0)}g`;
        } else {
            // Para alimentos, usar os campos corretos da API
            macros = `P: ${Math.round(item.protein_100g || 0)}g | C: ${Math.round(item.carbs_100g || 0)}g | G: ${Math.round(item.fat_100g || 0)}g`;
        }
        
        resultItem.innerHTML = `
            <div class="search-result-type ${type}">${type === 'recipe' ? 'RECEITA' : 'ALIMENTO'}</div>
            <div class="search-result-info">
                <div class="search-result-name">${item.name}</div>
                <div class="search-result-macros">${macros}</div>
            </div>
        `;
        
        resultsDiv.appendChild(resultItem);
    });
    
    resultsDiv.style.display = 'block';
}

// Função para selecionar resultado da busca
function selectSearchResult(item, type) {
    console.log('🎯 SELECT SEARCH RESULT - INÍCIO');
    console.log('Item selecionado:', item);
    console.log('Tipo:', type);
    
    if (type === 'recipe') {
        console.log('📝 Processando como RECEITA');
        // Converter para formato de receita
        const recipe = {
            id: item.id,
            name: item.name,
            kcal_per_serving: item.kcal_per_serving || 0,
            protein_g_per_serving: item.protein_g_per_serving || 0,
            carbs_g_per_serving: item.carbs_g_per_serving || 0,
            fat_g_per_serving: item.fat_g_per_serving || 0
        };
        console.log('📝 Receita formatada:', recipe);
        selectRecipe(recipe);
    } else {
        console.log('🍎 Processando como ALIMENTO');
        // Converter para formato de alimento
        const food = {
            id: item.id,
            name: item.name,
            kcal_per_serving: item.kcal_per_serving || 0,
            protein_g_per_serving: item.protein_g_per_serving || 0,
            carbs_g_per_serving: item.carbs_g_per_serving || 0,
            fat_g_per_serving: item.fat_g_per_serving || 0,
            is_food: true,
            source_table: item.source_table
        };
        console.log('🍎 Alimento formatado:', food);
        selectRecipe(food); // Usar a mesma função, mas marcando como alimento
    }
    
    clearSearchResults();
    document.getElementById('search-input').value = '';
}

// Event listeners
document.getElementById('quantity').addEventListener('input', updateMacros);
document.getElementById('unit-select').addEventListener('change', updateMacros);

// Event listeners para as abas
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        switchTab(btn.dataset.tab);
    });
});

// Busca em tempo real
document.getElementById('search-input').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        performSearch();
    }, 300);
});


// Fechar modal clicando fora
document.getElementById('recipe-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Modal centralizado - sem funcionalidade de arrastar
</script>

<style>
/* Mensagem de sem unidades */
.no-units-message {
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid rgba(255, 193, 7, 0.3);
    border-radius: 8px;
    padding: 16px;
    margin: 16px 0;
    text-align: center;
}

.no-units-message p {
    margin: 0 0 8px 0;
    color: var(--text-primary);
    font-size: 14px;
}

.no-units-message p:last-child {
    margin-bottom: 0;
    font-weight: 600;
    color: var(--accent-orange);
}
</style>

<?php require_once APP_ROOT_PATH . '/includes/layout_bottom_nav.php'; ?>
<?php require_once APP_ROOT_PATH . '/includes/layout_footer.php'; ?>