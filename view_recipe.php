<?php
// public_html/view_recipe.php (VERSÃO FINAL CORRIGIDA E COMPLETA)

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
$extra_js = ['favorite_logic.js'];
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';

$user_id = $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token_for_html = $_SESSION['csrf_token'];

$recipe_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$recipe_id) { header("Location: " . BASE_APP_URL . "/main_app.php"); exit(); }

$recipe = null; $ingredients = []; $categories = []; $is_favorited_by_user = false;

$stmt_recipe = $conn->prepare("SELECT * FROM sf_recipes WHERE id = ? AND is_public = TRUE");
$stmt_recipe->bind_param("i", $recipe_id);
$stmt_recipe->execute();
$result_recipe = $stmt_recipe->get_result();
if ($result_recipe->num_rows > 0) { $recipe = $result_recipe->fetch_assoc(); }
$stmt_recipe->close();

if (!$recipe) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Receita não encontrada ou não está disponível.'];
    header("Location: " . BASE_APP_URL . "/main_app.php");
    exit();
}

$stmt_check_fav = $conn->prepare("SELECT recipe_id FROM sf_user_favorite_recipes WHERE user_id = ? AND recipe_id = ?");
$stmt_check_fav->bind_param("ii", $user_id, $recipe_id);
$stmt_check_fav->execute();
if ($stmt_check_fav->get_result()->num_rows > 0) { $is_favorited_by_user = true; }
$stmt_check_fav->close();

$stmt_ingredients = $conn->prepare("SELECT ingredient_description, quantity_value, quantity_unit FROM sf_recipe_ingredients WHERE recipe_id = ? ORDER BY id ASC");
$stmt_ingredients->bind_param("i", $recipe_id);
$stmt_ingredients->execute();
$result_ingredients = $stmt_ingredients->get_result();
while($row = $result_ingredients->fetch_assoc()) { 
    $ingredient_text = $row['ingredient_description'];
    if (!empty($row['quantity_value']) && !empty($row['quantity_unit'])) {
        $ingredient_text = $row['quantity_value'] . ' ' . $row['quantity_unit'] . ' de ' . $ingredient_text;
    }
    $ingredients[] = $ingredient_text;
}
$stmt_ingredients->close();

$stmt_categories = $conn->prepare("SELECT c.id, c.name FROM sf_categories c JOIN sf_recipe_has_categories rhc ON c.id = rhc.category_id WHERE rhc.recipe_id = ? ORDER BY c.name ASC");
$stmt_categories->bind_param("i", $recipe_id);
$stmt_categories->execute();
$result_categories = $stmt_categories->get_result();
while($row = $result_categories->fetch_assoc()) { $categories[] = $row; }
$stmt_categories->close();

$current_hour = (int)date('G');
$default_meal_type = 'lunch';
if ($current_hour >= 5 && $current_hour < 10) $default_meal_type = 'breakfast';
elseif ($current_hour >= 10 && $current_hour < 12) $default_meal_type = 'morning_snack';
elseif ($current_hour >= 12 && $current_hour < 15) $default_meal_type = 'lunch';
elseif ($current_hour >= 15 && $current_hour < 18) $default_meal_type = 'afternoon_snack';
elseif ($current_hour >= 18 && $current_hour < 21) $default_meal_type = 'dinner';
else $default_meal_type = 'supper';

$page_title = htmlspecialchars($recipe['name']);
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>
<style>
/* === VIEW RECIPE - ESTILO COMPLETO === */

/* === HEADER === */
.recipe-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.back-button {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.2s ease;
}

.back-button:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateX(-2px);
}

.recipe-action-favorite {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
}

.recipe-action-favorite.is-favorited {
    background: var(--accent-orange) !important;
    border-color: var(--accent-orange) !important;
    color: #fff !important;
}

.recipe-action-favorite:hover {
    background: rgba(255, 255, 255, 0.08);
}

.recipe-action-favorite:focus {
    outline: none;
    background: rgba(255, 255, 255, 0.08);
}

.recipe-action-favorite.is-favorited:focus {
    background: var(--accent-orange) !important;
    border-color: var(--accent-orange) !important;
    color: #fff !important;
}

/* === GARANTIR QUE O FAVORITE_LOGIC.JS FUNCIONE === */
.favorite-toggle-btn {
    cursor: pointer !important;
    pointer-events: auto !important;
}

.favorite-toggle-btn i {
    pointer-events: none !important;
}

/* === FORÇAR ESTADO ATIVO DO FAVORITO === */
.favorite-toggle-btn.is-favorited {
    background: var(--accent-orange) !important;
    border-color: var(--accent-orange) !important;
    color: #fff !important;
}

.favorite-toggle-btn.is-favorited i {
    color: #fff !important;
}

.favorite-toggle-btn.is-favorited:hover {
    background: #ff7a1a !important;
    border-color: #ff7a1a !important;
}

.favorite-toggle-btn.is-favorited:focus {
    background: var(--accent-orange) !important;
    border-color: var(--accent-orange) !important;
    color: #fff !important;
}

/* === IMAGEM === */
.recipe-detail-image {
    width: 100%;
    height: 280px;
    object-fit: cover;
    border-radius: 20px;
    margin-bottom: 20px;
}

/* === CARD PRINCIPAL === */
.recipe-main-info {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 20px;
    transition: all 0.2s ease;
    overflow: hidden;
    word-wrap: break-word;
    overflow-wrap: break-word;
    box-sizing: border-box;
}

.recipe-main-info:hover {
    background: rgba(255, 255, 255, 0.06);
}

.recipe-name-main {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 12px 0;
    line-height: 1.2;
    word-wrap: break-word;
    overflow-wrap: break-word;
    word-break: break-word;
    max-width: 100%;
    overflow: hidden;
}

.recipe-description-short {
    font-size: 16px;
    color: var(--text-secondary);
    line-height: 1.5;
    margin: 0 0 16px 0;
    word-wrap: break-word;
    overflow-wrap: break-word;
    word-break: break-word;
    max-width: 100%;
    overflow: hidden;
}

.category-tags-container { 
    display: flex; 
    flex-wrap: wrap; 
    gap: 8px; 
    margin-top: 0; 
}

.category-tag { 
    display: inline-block; 
    padding: 6px 12px; 
    background: rgba(255, 255, 255, 0.04); 
    border: 1px solid rgba(255, 255, 255, 0.12); 
    border-radius: 16px; 
    font-size: 13px; 
    color: var(--text-secondary); 
    text-decoration: none; 
    transition: all 0.2s ease;
    cursor: default;
    pointer-events: none;
}

/* === MACROS === */
.recipe-macros-summary {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 20px;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    text-align: center;
    transition: all 0.2s ease;
}

.recipe-macros-summary:hover {
    background: rgba(255, 255, 255, 0.06);
}

.macro-info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.macro-info-item .value {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-primary);
}

.macro-info-item .label {
    font-size: 12px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.recipe-serving-info {
    grid-column: 1 / -1;
    font-size: 12px;
    color: var(--text-secondary);
    margin: 12px 0 0 0;
    text-align: center;
}

/* === TEMPO E PORÇÕES === */
.recipe-timing-servings {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s ease;
}

.recipe-timing-servings:hover {
    background: rgba(255, 255, 255, 0.06);
}

.timing-item, .servings-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-secondary);
    font-size: 14px;
}

.timing-item i, .servings-item i {
    color: var(--accent-orange);
    width: 16px;
}

/* === SEÇÕES === */
.recipe-section {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 20px;
    transition: all 0.2s ease;
}

.recipe-section:hover {
    background: rgba(255, 255, 255, 0.06);
}

.recipe-section-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.recipe-section-title::before {
    content: '';
    width: 4px;
    height: 20px;
    background: var(--accent-orange);
    border-radius: 2px;
}

/* === INGREDIENTES === */
.recipe-ingredient-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.recipe-ingredient-list li {
    padding: 12px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    color: var(--text-secondary);
    font-size: 15px;
    line-height: 1.4;
    position: relative;
    padding-left: 20px;
}

.recipe-ingredient-list li:last-child {
    border-bottom: none;
}

.recipe-ingredient-list li::before {
    content: '•';
    color: var(--accent-orange);
    position: absolute;
    left: 0;
    top: 12px;
}

/* === INSTRUÇÕES === */
.instruction-step {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
    padding: 16px;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 12px;
    border-left: 3px solid var(--accent-orange);
}

.step-number {
    width: 28px;
    height: 28px;
    background: var(--accent-orange);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    flex-shrink: 0;
}

.instruction-step p {
    color: var(--text-secondary);
    line-height: 1.5;
    margin: 0;
}

/* === OBSERVAÇÕES === */
.recipe-notes-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 20px;
    transition: all 0.2s ease;
}

.recipe-notes-card:hover {
    background: rgba(255, 255, 255, 0.06);
}

/* === REGISTRAR REFEIÇÃO === */
.register-meal-section {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 20px;
    transition: all 0.2s ease;
}

.register-meal-section:hover {
    background: rgba(255, 255, 255, 0.06);
}

.register-meal-section .recipe-section-title {
    text-align: center;
}

/* === FORMULÁRIO === */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.form-control {
    width: 100%;
    height: 48px;
    padding: 0 16px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    color: var(--text-primary);
    font-size: 15px;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

.btn-primary {
    width: 100%;
    height: 52px;
    background: var(--accent-orange);
    border: none;
    border-radius: 16px;
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-primary:hover {
    background: #ff7a1a;
}

/* === RESPONSIVIDADE === */
@media (max-width: 768px) {
    .recipe-name-main {
        font-size: 24px;
    }
    
    .recipe-macros-summary {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .recipe-timing-servings {
        flex-direction: column;
        gap: 12px;
        text-align: center;
    }
}

/* === TOUCH PARA ELEMENTOS INTERATIVOS === */
.back-button,
.recipe-action-favorite,
.btn-primary,
.form-control,
.category-tag {
    touch-action: manipulation !important;
}

</style>

<div class="app-container">
    <div class="header-nav recipe-detail-header">
        <a href="javascript:history.back()" class="back-button" aria-label="Voltar"><i class="fas fa-chevron-left"></i></a>
        <a href="#" class="recipe-action-favorite favorite-toggle-btn <?php echo $is_favorited_by_user ? 'is-favorited' : ''; ?>" data-recipe-id="<?php echo $recipe['id']; ?>" data-csrf-token="<?php echo $csrf_token_for_html; ?>" aria-label="<?php echo $is_favorited_by_user ? 'Desfavoritar receita' : 'Favoritar receita'; ?>"><i class="<?php echo $is_favorited_by_user ? 'fas' : 'far'; ?> fa-heart"></i></a>
    </div>

    <img src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/' . htmlspecialchars($recipe['image_filename'] ?: 'placeholder_food.jpg'); ?>" alt="<?php echo htmlspecialchars($recipe['name']); ?>" class="recipe-detail-image">

    <div class="recipe-main-info card-shadow-light">
        <h1 class="recipe-name-main"><?php echo htmlspecialchars($recipe['name']); ?></h1>
        <?php if (!empty($recipe['description'])): ?><p class="recipe-description-short"><?php echo nl2br(htmlspecialchars($recipe['description'])); ?></p><?php endif; ?>
        <?php if (!empty($categories)): ?>
            <div class="category-tags-container">
                <?php foreach ($categories as $category): ?><span class="category-tag"><?php echo htmlspecialchars($category['name']); ?></span><?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

        <div class="recipe-macros-summary card-shadow-light">
            <div class="macro-info-item"><span class="value"><?php echo round($recipe['kcal_per_serving'] ?? 0); ?></span><span class="label">Kcal</span></div>
            <div class="macro-info-item"><span class="value"><?php echo number_format($recipe['carbs_g_per_serving'] ?? 0, 1, ',', '.'); ?>g</span><span class="label">Carbo</span></div>
            <div class="macro-info-item"><span class="value"><?php echo number_format($recipe['fat_g_per_serving'] ?? 0, 1, ',', '.'); ?>g</span><span class="label">Gordura</span></div>
            <div class="macro-info-item"><span class="value"><?php echo number_format($recipe['protein_g_per_serving'] ?? 0, 1, ',', '.'); ?>g</span><span class="label">Proteína</span></div>
            
            <?php
            $serving_info_text = 'Valores por porção';
            if (!empty($recipe['serving_size_g']) && $recipe['serving_size_g'] > 0) {
                $serving_info_text .= ' de ' . round($recipe['serving_size_g']) . 'g';
            }
            ?>
            <p class="recipe-serving-info"><?php echo htmlspecialchars($serving_info_text); ?></p>
        </div>

    <?php 
    $total_time = ($recipe['prep_time_minutes'] ?? 0) + ($recipe['cook_time_minutes'] ?? 0);
    if ($total_time > 0 || !empty($recipe['servings'])): 
    ?>
    <div class="recipe-timing-servings card-shadow-light">
        <?php if ($total_time > 0): ?><div class="timing-item"><i class="far fa-clock"></i> <?php echo $total_time; ?> min</div><?php endif; ?>
        <?php if (!empty($recipe['servings'])): ?>
            <?php 
            $servings_text = "Rende " . htmlspecialchars($recipe['servings']);
            $servings_text .= (is_numeric($recipe['servings']) && $recipe['servings'] == 1) ? ' porção' : ' porções';
            if (!empty($recipe['serving_size_g']) && $recipe['serving_size_g'] > 0) {
                 $servings_text .= ' de ' . round($recipe['serving_size_g']) . 'g';
            }
            ?>
            <div class="servings-item"><i class="fas fa-utensils"></i> <?php echo $servings_text; ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($ingredients)): ?>
    <div class="recipe-section card-shadow-light">
        <h3 class="recipe-section-title">Ingredientes</h3>
        <ul class="recipe-ingredient-list">
            <?php foreach($ingredients as $ingredient): ?><li>- <?php echo htmlspecialchars($ingredient); ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($recipe['instructions'])): ?>
    <div class="recipe-section card-shadow-light">
        <h3 class="recipe-section-title">Modo de Preparo</h3>
        <div class="recipe-instructions">
            <?php
            $steps = explode("\n", trim($recipe['instructions']));
            $step_number = 1;
            foreach ($steps as $step_text) {
                if ($step_text_trimmed = trim($step_text)) {
                    $step_text_cleaned = preg_replace('/^\d+[\.\)]\s*/', '', $step_text_trimmed);
                    echo '<div class="instruction-step"><span class="step-number">' . $step_number++ . '</span><p>' . nl2br(htmlspecialchars($step_text_cleaned)) . '</p></div>';
                }
            }
            ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($recipe['notes'])): ?>
    <div class="recipe-section recipe-notes-card card-shadow-light">
        <h3 class="recipe-section-title">Observação</h3>
        <p><?php echo nl2br(htmlspecialchars($recipe['notes'])); ?></p>
    </div>
    <?php endif; ?>

    <div class="register-meal-section card-shadow">
        <h3 class="recipe-section-title text-center">Registrar esta Refeição</h3>
        <form action="<?php echo BASE_APP_URL; ?>/process_log_meal.php" method="POST" id="log-meal-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_for_html; ?>">
            <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
            <!-- CORREÇÃO CRÍTICA: Usando os nomes corretos das colunas do banco de dados -->
            <input type="hidden" name="kcal_per_serving" value="<?php echo $recipe['kcal_per_serving']; ?>">
            <input type="hidden" name="protein_per_serving" value="<?php echo $recipe['protein_g_per_serving']; ?>">
            <input type="hidden" name="carbs_per_serving" value="<?php echo $recipe['carbs_g_per_serving']; ?>">
            <input type="hidden" name="fat_per_serving" value="<?php echo $recipe['fat_g_per_serving']; ?>">

            <div class="form-group">
                <label for="meal_type">Refeição:</label>
                <select name="meal_type" id="meal_type" class="form-control">
                    <option value="breakfast" <?php if($default_meal_type == 'breakfast') echo 'selected';?>>Café da Manhã</option>
                    <option value="morning_snack" <?php if($default_meal_type == 'morning_snack') echo 'selected';?>>Lanche da Manhã</option>
                    <option value="lunch" <?php if($default_meal_type == 'lunch') echo 'selected';?>>Almoço</option>
                    <option value="afternoon_snack" <?php if($default_meal_type == 'afternoon_snack') echo 'selected';?>>Lanche da Tarde</option>
                    <option value="dinner" <?php if($default_meal_type == 'dinner') echo 'selected';?>>Jantar</option>
                    <option value="supper" <?php if($default_meal_type == 'supper') echo 'selected';?>>Ceia</option>
                </select>
            </div>
            <div class="form-group">
                <label for="date_consumed">Data:</label>
                <select name="date_consumed" id="date_consumed" class="form-control">
                    <option value="<?php echo date('Y-m-d'); ?>">Hoje, <?php echo date('d/m'); ?></option>
                    <option value="<?php echo date('Y-m-d', strtotime('-1 day')); ?>">Ontem, <?php echo date('d/m', strtotime('-1 day')); ?></option>
                </select>
            </div>
             <div class="form-group">
                <label for="servings_consumed">Porções consumidas:</label>
                <input type="number" name="servings_consumed" id="servings_consumed" class="form-control" value="1.0" min="0.1" step="0.1" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Registrar no Diário</button>
        </form>
    </div>
</div>


<?php
require_once APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>