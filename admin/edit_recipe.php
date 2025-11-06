<?php
// admin/edit_recipe.php - REFATORAÇÃO COMPLETA: EDIÇÃO INLINE NO CELULAR + CONFIGURAÇÕES À DIREITA

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
$conn = require __DIR__ . '/../includes/db.php';
requireAdminLogin();

function format_decimal_for_input($value) { 
    return $value !== null ? str_replace('.', ',', $value) : ''; 
}

$suggestion_options = [ 
    'cafe_da_manha' => 'Café da Manhã', 
    'lanche_da_manha' => 'Lanche da Manhã', 
    'almoco' => 'Almoço', 
    'lanche_da_tarde' => 'Lanche da Tarde', 
    'jantar' => 'Jantar', 
    'ceia' => 'Ceia', 
    'qualquer_hora' => 'Qualquer Hora' 
];

$page_slug = 'recipes';
$page_title = 'Nova Receita';
$recipe_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);
$recipe = []; 
$ingredients = []; 
$selected_category_ids = []; 
$selected_suggestions = [];
$all_categories = $conn->query("SELECT id, name FROM sf_categories ORDER BY display_order ASC, name ASC")->fetch_all(MYSQLI_ASSOC);

if ($recipe_id) {
    $page_title = 'Editar Receita';
    $stmt = $conn->prepare("SELECT * FROM sf_recipes WHERE id = ?");
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $recipe = $result->fetch_assoc();
        if (!empty($recipe['meal_type_suggestion'])) { 
            $selected_suggestions = explode(',', $recipe['meal_type_suggestion']); 
        }
        $stmt_ing = $conn->prepare("SELECT ingredient_description, quantity_value, quantity_unit FROM sf_recipe_ingredients WHERE recipe_id = ? ORDER BY id ASC");
        $stmt_ing->bind_param("i", $recipe_id); 
        $stmt_ing->execute(); 
        $ingredients = $stmt_ing->get_result()->fetch_all(MYSQLI_ASSOC); 
        $stmt_ing->close();
        $stmt_cat = $conn->prepare("SELECT category_id FROM sf_recipe_has_categories WHERE recipe_id = ?");
        $stmt_cat->bind_param("i", $recipe_id); 
        $stmt_cat->execute(); 
        $cat_result = $stmt_cat->get_result();
        while($row = $cat_result->fetch_assoc()){ 
            $selected_category_ids[] = $row['category_id']; 
        }
        $stmt_cat->close();
    } else { 
        die("Receita não encontrada."); 
    }
}

if (empty($_SESSION['csrf_token'])) { 
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
}
$csrf_token = $_SESSION['csrf_token'];
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<style>
/* ========================================================================= */
/*       CSS FINAL, SIMPLES E CORRETO.                                     */
/* ========================================================================= */

:root {
    --accent-orange: #FF6B00;
    --text-primary: #F5F5F5;
    --text-secondary: #A3A3A3;
    --glass-border: rgba(255, 255, 255, 0.1);
    
    --sidebar-width: 256px;
    --layout-gap: 2rem;       /* O "respiro" que você quer */
    --mockup-width: 410px;      /* A largura que você gostou no zoom 110% */
}

/* 1. O CONTAINER PRINCIPAL - CRIA O ESPAÇO VAZIO PARA O CELULAR */
.edit-recipe-container {
    /* Padding à esquerda para criar a "calha" onde o celular fixo vai ficar */
    padding-left: calc(var(--sidebar-width) + var(--mockup-width) + (var(--layout-gap) * 2));
    padding-right: var(--layout-gap);
    padding-top: var(--layout-gap);
    padding-bottom: var(--layout-gap);
}

/* 2. O PAINEL DO CELULAR (ESQUERDA) - A MÁGICA FINAL */
.mobile-mockup-panel {
    position: fixed; /* <<<< FIXO! NÃO ROLA COM A PÁGINA. */
    
    /* Define o respiro em cima e embaixo, ocupando a altura toda */
    top: var(--layout-gap);
    bottom: var(--layout-gap);
    
    /* Posição horizontal correta */
    left: calc(var(--sidebar-width) + var(--layout-gap));
    
    width: var(--mockup-width); /* LARGURA FIXA EM PIXELS */
    z-index: 10;
}

.mobile-mockup-wrapper {
    width: 100%;
    height: 100%;
    padding: 12px;
    background: #1a1a1a;
    border-radius: 40px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
    border: 1px solid var(--glass-border);
}

.mobile-screen {
    width: 100%;
    height: 100%;
    background: #121212;
    border-radius: 28px;
    overflow: hidden;
    position: relative;
}

#recipe-preview-frame {
    width: 100%;
    height: 100%;
    border: none;
}

/* 3. O PAINEL DE CONFIGURAÇÕES (DIREITA) */
.config-panel {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    width: 100%; /* Ocupa 100% do espaço que o padding-left deixou */
}

/* ===== ESTILOS INTERNOS (COPIADOS, SEM GRANDES MUDANÇAS) ===== */
.config-header {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
}

.config-header h3 {
    font-size: 1.5rem !important;
    color: #FFFFFF !important;
    margin: 0 !important;
    font-family: 'Montserrat', sans-serif !important;
}

.header-buttons {
    display: flex !important;
    gap: 0.75rem !important;
}

.dashboard-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 1.5rem !important;
}

.section-header {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    margin-bottom: 1.5rem !important;
}

.section-header h4 {
    font-size: 1.25rem !important;
    font-weight: 700 !important;
    color: #FFFFFF !important;
    margin: 0 !important;
    font-family: 'Montserrat', sans-serif !important;
    display: flex !important;
    align-items: center !important;
    gap: 0.75rem !important;
}

.section-header h4 i {
    color: var(--accent-orange) !important;
}

.form-group {
    margin-bottom: 1.5rem !important;
}

.form-group:last-child {
    margin-bottom: 0 !important;
}

.form-group label {
    font-size: 0.875rem !important;
    margin-bottom: 0.5rem !important;
    display: block !important;
    color: var(--text-secondary) !important;
}

.form-control {
    width: 100% !important;
    padding: 0.75rem 1rem !important;
    font-size: 0.95rem !important;
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 12px !important;
    color: var(--text-primary) !important;
    transition: all 0.3s ease !important;
    box-sizing: border-box !important;
}

.form-control:focus {
    border-color: var(--accent-orange) !important;
    outline: none !important;
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1) !important;
}

.form-group-grid-2 {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 1rem !important;
}

.ingredient-row {
    display: grid !important;
    grid-template-columns: 1fr auto auto auto !important;
    gap: 0.75rem !important;
    align-items: center !important;
    margin-bottom: 0.75rem !important;
}

.ingredient-row .form-control[type="number"] {
    min-width: 110px !important;
}

.ingredient-row select.form-control {
    min-width: 140px !important;
}

.btn-remove-ingredient {
    background: rgba(244, 67, 54, 0.1) !important;
    border-radius: 12px !important;
    color: #F44336 !important;
    cursor: pointer !important;
    width: 48px !important;
    height: 48px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: none !important;
    font-size: 1.2rem !important;
}

.custom-select-wrapper {
    position: relative !important;
}

.custom-select-wrapper::after {
    content: '\f078' !important;
    font-family: 'Font Awesome 5 Free' !important;
    font-weight: 900 !important;
    position: absolute !important;
    top: 50% !important;
    right: 15px !important;
    transform: translateY(-50%) !important;
    color: var(--text-secondary) !important;
    pointer-events: none !important;
}

.custom-select-wrapper select {
    -webkit-appearance: none !important;
    appearance: none !important;
    padding-right: 40px !important;
}

.custom-file-input-wrapper {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 12px !important;
    padding: 0.75rem 1rem !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    cursor: pointer !important;
    transition: all 0.3s ease !important;
}

.custom-file-input-wrapper:hover {
    border-color: var(--accent-orange) !important;
    background: rgba(255, 255, 255, 0.08) !important;
}

.file-input-label {
    color: var(--text-secondary) !important;
}

.file-input-filename {
    color: var(--text-primary) !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}

input[type="file"].form-control {
    display: none;
}

input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type=number] {
    -moz-appearance: textfield;
}

.checkbox-grid {
    display: grid !important;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)) !important;
    gap: 0.75rem !important;
}

.checkbox-item {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    margin-bottom: 0.5rem !important;
}

.checkbox-item input[type="checkbox"] {
    opacity: 0 !important;
    position: absolute !important;
}

.checkbox-item label {
    flex-grow: 1 !important;
    color: var(--text-secondary) !important;
    padding-left: 32px !important;
    position: relative !important;
    cursor: pointer !important;
    font-size: 0.9rem !important;
}

.checkbox-item label::before {
    content: '' !important;
    position: absolute !important;
    left: 0 !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    width: 18px !important;
    height: 18px !important;
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 6px !important;
}

.checkbox-item input:checked + label::before {
    background: var(--accent-orange) !important;
    border-color: var(--accent-orange) !important;
}

.checkbox-item input:checked + label::after {
    content: '\f00c' !important;
    font-family: 'Font Awesome 5 Free' !important;
    font-weight: 900 !important;
    position: absolute !important;
    left: 4px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    color: white !important;
    font-size: 10px !important;
}

.add-category-form {
    display: flex !important;
    gap: 0.75rem !important;
    margin-top: 1.5rem !important;
    border-top: 1px solid var(--glass-border) !important;
    padding-top: 1.5rem !important;
}

.btn-delete-category {
    background: transparent !important;
    border: none !important;
    color: var(--text-secondary) !important;
    cursor: pointer !important;
    opacity: 0.6 !important;
}

.checkbox-item:hover .btn-delete-category {
    opacity: 1 !important;
}

.btn, button.btn, a.btn {
    padding: 0.75rem 1.5rem !important;
    border-radius: 12px !important;
    font-size: 0.95rem !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    text-decoration: none !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.5rem !important;
    border: none !important;
}

.btn-primary {
    background: var(--accent-orange) !important;
    color: white !important;
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.05) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--glass-border) !important;
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.1) !important;
}

.btn-add-circular {
    width: 48px !important;
    height: 48px !important;
    border-radius: 50% !important;
    background: rgba(255, 107, 0, 0.08) !important;
    border: 1px solid rgba(255, 107, 0, 0.2) !important;
    color: var(--accent-orange) !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: all 0.3s ease !important;
}


.section-divider {
    border: none !important;
    border-top: 1px solid var(--glass-border) !important;
    margin: 1.5rem 0 !important;
}

.calculation-tool {
    background: rgba(0, 0, 0, 0.2) !important;
    padding: 1.5rem !important;
    border-radius: 12px !important;
    margin-bottom: 1.5rem !important;
    border: 1px solid var(--glass-border) !important;
}

.calculation-label {
    font-size: 0.9rem !important;
    color: var(--text-primary) !important;
    margin-bottom: 1rem !important;
    display: block !important;
}

.form-control-readonly {
    background-color: rgba(17, 17, 17, 0.6) !important;
    color: var(--accent-orange) !important;
    font-weight: 600 !important;
    border-style: dashed !important;
    cursor: not-allowed !important;
}

.field-help {
    font-size: 0.75rem !important;
    color: var(--text-secondary) !important;
    margin-top: 0.5rem !important;
    display: block !important;
}

/* ===== ESTILOS INTERNOS (SIMPLIFICADOS) ===== */
.config-header {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    margin-bottom: 1rem !important;
    padding-bottom: 1rem !important;
    border-bottom: 1px solid var(--glass-border) !important;
}

.config-header h3 {
    font-size: 1.5rem !important;
    color: #FFFFFF !important;
    margin: 0 !important;
    font-family: 'Montserrat', sans-serif !important;
}

.header-buttons {
    display: flex !important;
    gap: 0.75rem !important;
}

.dashboard-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 1.5rem !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
}

/* RESPONSIVIDADE */
@media (max-width: 1200px) {
    .edit-recipe-container {
        padding-left: var(--layout-gap) !important;
    }
    .mobile-mockup-panel {
        display: none !important;
    }
}
</style>

<!-- Mensagem de sucesso quando salvar -->
<?php if ($status === 'saved'): ?>
<div id="save-success-message" style="position: fixed; top: 20px; right: 20px; background: rgba(76, 175, 80, 0.9); color: white; padding: 1rem 1.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 10000; display: flex; align-items: center; gap: 0.75rem; transition: opacity 0.3s ease;">
    <i class="fas fa-check-circle"></i>
    <span>Receita salva com sucesso!</span>
</div>
<script>
    setTimeout(() => {
        const msg = document.getElementById('save-success-message');
        if (msg) {
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 300);
        }
    }, 3000);
</script>
<?php endif; ?>

<div class="edit-recipe-container">
    <!-- PAINEL DO CELULAR (ESQUERDA) -->
    <div class="mobile-mockup-panel">
        <div class="mobile-mockup-wrapper">
            <div class="mobile-screen">
                <iframe id="recipe-preview-frame" src="../_admin_recipe_preview.php?id=<?php echo htmlspecialchars($recipe_id ?? ''); ?>"></iframe>
            </div>
        </div>
    </div>

    <!-- PAINEL DE CONFIGURAÇÕES (DIREITA) -->
    <div class="config-panel">
        <form action="save_recipe.php" method="POST" enctype="multipart/form-data" id="recipe-form">
            <input type="hidden" name="recipe_id" value="<?php echo htmlspecialchars($recipe['id'] ?? ''); ?>">
            <input type="hidden" name="existing_image_filename" value="<?php echo htmlspecialchars($recipe['image_filename'] ?? ''); ?>">
            <input type="hidden" id="csrf-token" value="<?php echo $csrf_token; ?>">

            <!-- INPUTS OCULTOS PARA SINCRONIZAÇÃO COM PREVIEW -->
            <input type="hidden" id="name" name="name" value="<?php echo htmlspecialchars($recipe['name'] ?? ''); ?>" required>
            <input type="hidden" id="description" name="description" value="<?php echo htmlspecialchars($recipe['description'] ?? ''); ?>">
            <input type="hidden" id="instructions" name="instructions" value="<?php echo htmlspecialchars($recipe['instructions'] ?? ''); ?>">
                <!-- HEADER COM AÇÕES -->
                <div class="config-header">
                    <h3><?php echo $page_title; ?></h3>
                    <div class="header-buttons">
                        <a href="recipes.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salvar
                        </button>
                    </div>
                </div>

                <!-- CARD: CÁLCULO NUTRICIONAL -->
                <div class="dashboard-card">
                    <div class="section-header">
                        <h4><i class="fas fa-calculator"></i> Cálculo Nutricional</h4>
                </div>
                    
                    <div class="calculation-tool">
                        <label class="calculation-label">1. Preencha os dados da sua receita pronta:</label>
                        <div class="form-group-grid-2">
                            <div class="form-group">
                                <label for="helper_total_weight">Peso Total da Receita (g)</label>
                                <input type="number" id="helper_total_weight" class="form-control" placeholder="Ex: 1200">
            </div>
                            <div class="form-group">
                                <label for="servings">Rendimento (Nº de Porções)</label>
                                <input type="number" id="servings" name="servings" class="form-control" value="<?php echo htmlspecialchars($recipe['servings'] ?? '1'); ?>" step="1" min="1" placeholder="Ex: 4">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="serving_size_g">2. Peso Final por Porção (calculado)</label>
                        <input type="number" id="serving_size_g" name="serving_size_g" class="form-control form-control-readonly" 
                               value="<?php echo htmlspecialchars($recipe['serving_size_g'] ?? ''); ?>" 
                               step="0.01" readonly>
                        <small class="field-help">Este valor é calculado automaticamente a partir do Peso Total e do Rendimento.</small>
                    </div>
                    
                    <hr class="section-divider">

                    <div class="form-group-grid-2">
                        <div class="form-group">
                            <label>Calorias (kcal)</label>
                            <input type="text" id="kcal_per_serving" name="kcal_per_serving" class="form-control" value="<?php echo format_decimal_for_input($recipe['kcal_per_serving'] ?? ''); ?>" placeholder="0">
                        </div>
                        <div class="form-group">
                            <label>Carboidratos (g)</label>
                            <input type="text" id="carbs_g_per_serving" name="carbs_g_per_serving" class="form-control" value="<?php echo format_decimal_for_input($recipe['carbs_g_per_serving'] ?? ''); ?>" placeholder="0">
                        </div>
                        <div class="form-group">
                            <label>Gorduras (g)</label>
                            <input type="text" id="fat_g_per_serving" name="fat_g_per_serving" class="form-control" value="<?php echo format_decimal_for_input($recipe['fat_g_per_serving'] ?? ''); ?>" placeholder="0">
                        </div>
                        <div class="form-group">
                            <label>Proteínas (g)</label>
                            <input type="text" id="protein_g_per_serving" name="protein_g_per_serving" class="form-control" value="<?php echo format_decimal_for_input($recipe['protein_g_per_serving'] ?? ''); ?>" placeholder="0">
                        </div>
                    </div>
                    <div class="form-group-grid-2">
                        <div class="form-group">
                            <label>Preparo (min)</label>
                            <input type="number" id="prep_time_minutes" name="prep_time_minutes" class="form-control" value="<?php echo htmlspecialchars($recipe['prep_time_minutes'] ?? ''); ?>" placeholder="0">
                        </div>
                        <div class="form-group">
                            <label>Cozimento (min)</label>
                            <input type="number" id="cook_time_minutes" name="cook_time_minutes" class="form-control" value="<?php echo htmlspecialchars($recipe['cook_time_minutes'] ?? ''); ?>" placeholder="0">
                        </div>
                    </div>
                </div>

                <!-- CARD: INGREDIENTES -->
                <div class="dashboard-card">
                    <div class="section-header">
                        <h4><i class="fas fa-utensils"></i> Ingredientes</h4>
                        <button type="button" id="btn-add-ingredient" class="btn-add-circular" title="Adicionar Ingrediente">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                        <div id="ingredients-container">
                            <?php if (!empty($ingredients)) : foreach ($ingredients as $ing) : ?>
                            <div class="ingredient-row">
                                <input type="text" name="ingredient_description[]" class="form-control" value="<?php echo htmlspecialchars($ing['ingredient_description']); ?>" placeholder="Ex: Farinha de trigo">
                                <input type="number" name="ingredient_quantity[]" class="form-control" value="<?php echo htmlspecialchars($ing['quantity_value'] ?? ''); ?>" placeholder="Quantidade" step="0.01">
                                <select name="ingredient_unit[]" class="form-control">
                                    <option value="">Unidade</option>
                                    <option value="g" <?php if(($ing['quantity_unit'] ?? '') == 'g') echo 'selected'; ?>>g (gramas)</option>
                                    <option value="kg" <?php if(($ing['quantity_unit'] ?? '') == 'kg') echo 'selected'; ?>>kg (quilogramas)</option>
                                    <option value="ml" <?php if(($ing['quantity_unit'] ?? '') == 'ml') echo 'selected'; ?>>ml (mililitros)</option>
                                    <option value="l" <?php if(($ing['quantity_unit'] ?? '') == 'l') echo 'selected'; ?>>l (litros)</option>
                                    <option value="xícara" <?php if(($ing['quantity_unit'] ?? '') == 'xícara') echo 'selected'; ?>>xícara (240ml)</option>
                                    <option value="colher_sopa" <?php if(($ing['quantity_unit'] ?? '') == 'colher_sopa') echo 'selected'; ?>>colher de sopa (15ml)</option>
                                    <option value="colher_cha" <?php if(($ing['quantity_unit'] ?? '') == 'colher_cha') echo 'selected'; ?>>colher de chá (5ml)</option>
                                </select>
                                <button type="button" class="btn-remove-ingredient" title="Remover">×</button>
                            </div>
                            <?php endforeach; else: ?>
                            <div class="ingredient-row">
                                <input type="text" name="ingredient_description[]" class="form-control" value="" placeholder="Ex: Farinha de trigo">
                                <input type="number" name="ingredient_quantity[]" class="form-control" value="" placeholder="Quantidade" step="0.01">
                                <select name="ingredient_unit[]" class="form-control">
                                    <option value="">Unidade</option>
                                    <option value="g">g (gramas)</option>
                                    <option value="kg">kg (quilogramas)</option>
                                    <option value="ml">ml (mililitros)</option>
                                    <option value="l">l (litros)</option>
                                    <option value="xícara">xícara (240ml)</option>
                                    <option value="colher_sopa">colher de sopa (15ml)</option>
                                    <option value="colher_cha">colher de chá (5ml)</option>
                                </select>
                                <button type="button" class="btn-remove-ingredient" title="Remover">×</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <!-- CARD: IMAGEM -->
                <div class="dashboard-card">
                    <div class="section-header">
                        <h4><i class="fas fa-image"></i> Imagem da Receita</h4>
                                </div>
                                <div class="form-group">
                        <label for="image">Escolher Imagem</label>
                        <label for="image" class="custom-file-input-wrapper">
                            <span class="file-input-label" id="file-label-text">Escolher arquivo</span>
                            <span class="file-input-filename" id="file-name-display"><?php echo !empty($recipe['image_filename']) ? htmlspecialchars($recipe['image_filename']) : 'Nenhum arquivo escolhido'; ?></span>
                        </label>
                        <input type="file" id="image" name="image" class="form-control" accept="image/jpeg, image/png, image/webp">
                            </div>
                        </div>

                <!-- CARD: CONFIGURAÇÕES -->
                <div class="dashboard-card">
                    <div class="section-header">
                        <h4><i class="fas fa-cog"></i> Configurações</h4>
                        </div>
                    <div class="form-group">
                        <label for="is_public">Status</label>
                        <div class="custom-select-wrapper">
                            <select id="is_public" name="is_public" class="form-control">
                                <option value="1" <?php if(!isset($recipe['is_public']) || $recipe['is_public'] == 1) echo 'selected'; ?>>Pública</option>
                                <option value="0" <?php if(isset($recipe['is_public']) && $recipe['is_public'] == 0) echo 'selected'; ?>>Privada (Rascunho)</option>
                            </select>
                        </div>
                    </div>
                    </div>

                <!-- CARD: SUGESTÕES -->
                <div class="dashboard-card">
                    <div class="section-header">
                        <h4><i class="fas fa-clock"></i> Sugestões para o Dashboard (por Horário)</h4>
                    </div>
                    <div class="checkbox-grid">
                        <?php foreach ($suggestion_options as $value => $label): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" id="suggestion_<?php echo $value; ?>" name="meal_type_suggestion[]" value="<?php echo htmlspecialchars($value); ?>" <?php if (in_array($value, $selected_suggestions)) echo 'checked'; ?>>
                            <label for="suggestion_<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- CARD: CATEGORIAS -->
                <div class="dashboard-card">
                    <div class="section-header">
                        <h4><i class="fas fa-tags"></i> Categorias</h4>
            </div>
                    <p class="field-help categories-help">Selecione todas as categorias que se aplicam.</p>
                    <div class="checkbox-grid" id="categories-grid-container">
                        <?php foreach($all_categories as $category): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" id="category_<?php echo $category['id']; ?>" name="categories[]" value="<?php echo $category['id']; ?>" <?php if (in_array($category['id'], $selected_category_ids)) echo 'checked'; ?>>
                            <label for="category_<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></label>
                            <button type="button" class="btn-delete-category" data-id="<?php echo $category['id']; ?>" title="Excluir Categoria">&times;</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="add-category-form">
                        <input type="text" id="new-category-name" class="form-control" placeholder="Ou crie uma nova categoria">
                        <button type="button" id="btn-add-category" class="btn-add-circular" title="Adicionar Categoria">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <span id="add-category-feedback" class="add-category-feedback"></span>
                </div>
            </div>
        </div>
        </form>
    </div>
</div>

<script>
// =========================================================================
//       EDIÇÃO INLINE COM PREVIEW EM TEMPO REAL
// =========================================================================

document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('image');
    const fileNameDisplay = document.getElementById('file-name-display');
    const fileLabelText = document.getElementById('file-label-text');
    const iframe = document.getElementById('recipe-preview-frame');
    
    // Atualizar nome do arquivo quando selecionado
    if(fileInput) fileInput.addEventListener('change', function() { 
        if (this.files && this.files.length > 0) { 
            fileNameDisplay.textContent = this.files[0].name; 
            fileLabelText.style.display = 'none'; 
        } else { 
            fileNameDisplay.textContent = 'Nenhum arquivo escolhido'; 
            fileLabelText.style.display = 'inline'; 
        } 
    });
    
    // Inicializar preview quando iframe carregar
    if(iframe) iframe.addEventListener('load', function() {
        const iframeWindow = iframe.contentWindow;
        
        // Função para atualizar preview
        function updatePreview(type, value) { 
            if (iframeWindow && iframeWindow.postMessage) {
                iframeWindow.postMessage({ type, value }, '*'); 
            }
        }
        
        // Listener para receber mudanças do preview (contenteditable)
        window.addEventListener('message', function(event) {
            if (event.data && event.data.type === 'previewChanged') {
                const { field, value } = event.data;
                const hiddenInput = document.getElementById(field);
                if (hiddenInput) {
                    hiddenInput.value = value;
                }
            }
        });
        
        // Sincronizar inputs ocultos com preview (bidirecional)
        const nameInput = document.getElementById('name');
        const descInput = document.getElementById('description');
        const instructionsInput = document.getElementById('instructions');
        
        // Atualizar preview quando inputs ocultos mudarem (vindo de outras fontes)
        if (nameInput) {
            nameInput.addEventListener('input', function() {
                updatePreview('updateName', this.value);
            });
        }
        
        if (descInput) {
            descInput.addEventListener('input', function() {
                updatePreview('updateDescription', this.value);
            });
        }
        
        if (instructionsInput) {
            instructionsInput.addEventListener('input', function() {
                updatePreview('updateInstructions', this.value);
            });
        }
        
        // Função para atualizar macros e tempo
        function sendMacroAndTimeUpdate() { 
            updatePreview('updateMacrosAndTime', getAllMacroAndTimeData()); 
        }
        
        // Adicionar listeners para macros e tempo
        ['#prep_time_minutes', '#cook_time_minutes', '#kcal_per_serving', '#carbs_g_per_serving', '#fat_g_per_serving', '#protein_g_per_serving', '#servings', '#serving_size_g'].forEach(selector => { 
            const element = document.querySelector(selector);
            if (element) {
                element.addEventListener('input', sendMacroAndTimeUpdate);
            }
        });
        
        // Função para atualizar ingredientes
        function handleIngredientUpdates() { 
            const ingredients = [];
            document.querySelectorAll('.ingredient-row').forEach(row => {
                const description = row.querySelector('input[name="ingredient_description[]"]').value;
                const quantity = row.querySelector('input[name="ingredient_quantity[]"]').value;
                const unit = row.querySelector('select[name="ingredient_unit[]"]').value;
                
                let ingredientText = description;
                if (quantity && unit) {
                    ingredientText = quantity + ' ' + unit + ' de ' + description;
                } else if (description) {
                    ingredientText = description;
                }
                
                if (ingredientText.trim()) {
                ingredients.push(ingredientText);
                }
            });
            updatePreview('updateIngredients', ingredients); 
        }
        
        // Função para anexar listeners aos ingredientes
        function attachIngredientListeners(row) { 
            row.querySelectorAll('input, select').forEach(input => {
                input.addEventListener('input', handleIngredientUpdates);
                input.addEventListener('change', handleIngredientUpdates);
            });
            
            const removeBtn = row.querySelector('.btn-remove-ingredient');
            if (removeBtn) {
                removeBtn.addEventListener('click', () => { 
                if (document.querySelectorAll('.ingredient-row').length > 1) { 
                    row.remove(); 
                    handleIngredientUpdates(); 
                } 
            }); 
        }
        }
        
        // Anexar listeners aos ingredientes existentes
        document.querySelectorAll('.ingredient-row').forEach(attachIngredientListeners);
        
        // Botão para adicionar ingrediente
        document.getElementById('btn-add-ingredient')?.addEventListener('click', () => { 
            const container = document.getElementById('ingredients-container'); 
            const newRow = document.createElement('div'); 
            newRow.className = 'ingredient-row'; 
            newRow.innerHTML = `
                <input type="text" name="ingredient_description[]" class="form-control" placeholder="Ex: Farinha de trigo">
                <input type="number" name="ingredient_quantity[]" class="form-control" placeholder="Quantidade" step="0.01">
                <select name="ingredient_unit[]" class="form-control">
                    <option value="">Unidade</option>
                    <option value="g">g (gramas)</option>
                    <option value="kg">kg (quilogramas)</option>
                    <option value="ml">ml (mililitros)</option>
                    <option value="l">l (litros)</option>
                    <option value="xícara">xícara (240ml)</option>
                    <option value="colher_sopa">colher de sopa (15ml)</option>
                    <option value="colher_cha">colher de chá (5ml)</option>
                </select>
                <button type="button" class="btn-remove-ingredient" title="Remover">×</button>
            `; 
            container.appendChild(newRow); 
            attachIngredientListeners(newRow); 
        });
        
        // Atualizar imagem quando selecionada
        if (fileInput) {
            fileInput.addEventListener('change', function(e) { 
                if (e.target.files && e.target.files[0]) { 
                    const reader = new FileReader(); 
                    reader.onload = (event) => updatePreview('updateImage', event.target.result); 
                    reader.readAsDataURL(e.target.files[0]); 
                } 
            });
        }
        
        // Função para obter todos os dados de macros e tempo
        function getAllMacroAndTimeData() { 
            const servingsInput = document.getElementById('servings');
            const servingSizeInput = document.getElementById('serving_size_g');
            return { 
                prep: document.getElementById('prep_time_minutes')?.value || 0, 
                cook: document.getElementById('cook_time_minutes')?.value || 0, 
                kcal: document.getElementById('kcal_per_serving')?.value || 0, 
                carbs: document.getElementById('carbs_g_per_serving')?.value || 0, 
                protein: document.getElementById('protein_g_per_serving')?.value || 0, 
                fat: document.getElementById('fat_g_per_serving')?.value || 0,
                servings: servingsInput?.value || 1,
                serving_size_g: servingSizeInput?.value || 0
            }; 
        }
    });
});

// =========================================================================
//       GERENCIAMENTO DE CATEGORIAS (CRIAR E EXCLUIR)
// =========================================================================

document.addEventListener('DOMContentLoaded', function() {
    const addCategoryBtn = document.getElementById('btn-add-category');
    const newCategoryInput = document.getElementById('new-category-name');
    const feedbackSpan = document.getElementById('add-category-feedback');
    const gridContainer = document.getElementById('categories-grid-container');
    const csrfToken = document.getElementById('csrf-token').value;

    const showFeedback = (message, type = 'error') => {
        feedbackSpan.textContent = message;
        feedbackSpan.className = `add-category-feedback ${type}`;
        setTimeout(() => { feedbackSpan.textContent = ''; }, 4000);
    };

    const createCategory = () => {
        const categoryName = newCategoryInput.value.trim();
        if (!categoryName) { 
            showFeedback('Por favor, insira um nome para a categoria.'); 
            return; 
        }
        const formData = new FormData();
        formData.append('category_name', categoryName);
        formData.append('csrf_token', csrfToken);
        fetch('ajax_create_category.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const newItem = document.createElement('div');
                newItem.className = 'checkbox-item';
                const newId = `category_${data.id}`;
                newItem.innerHTML = `
                    <input type="checkbox" id="${newId}" name="categories[]" value="${data.id}" checked>
                    <label for="${newId}">${data.name}</label>
                    <button type="button" class="btn-delete-category" data-id="${data.id}" title="Excluir Categoria">&times;</button>
                `;
                gridContainer.appendChild(newItem);
                newCategoryInput.value = '';
                showFeedback('Categoria criada e selecionada!', 'success');
                
                // Atualizar preview com a nova categoria imediatamente
                setTimeout(() => {
                    const iframe = document.getElementById('recipe-preview-frame');
                    if (iframe && iframe.contentWindow) {
                        const selectedCategories = Array.from(gridContainer.querySelectorAll('input[type="checkbox"]:checked'))
                            .map(cb => {
                                const label = cb.nextElementSibling;
                                return label ? label.textContent.trim() : '';
                            })
                            .filter(name => name);
                        iframe.contentWindow.postMessage({ type: 'updateCategories', value: selectedCategories }, '*');
                    }
                }, 100);
            } else { 
                showFeedback(data.message || 'Ocorreu um erro.'); 
            }
        })
        .catch(error => { 
            console.error('Erro na requisição:', error); 
            showFeedback('Erro de conexão ao criar categoria.'); 
        });
    };

    const deleteCategory = (button) => {
        const categoryId = button.dataset.id;
        const categoryItem = button.closest('.checkbox-item');
        const categoryName = categoryItem.querySelector('label').innerText;

        if (!confirm(`Tem certeza que deseja excluir a categoria "${categoryName}"? Esta ação removerá a categoria de todas as receitas e não pode ser desfeita.`)) {
            return;
        }

        const formData = new FormData();
        formData.append('category_id', categoryId);
        formData.append('csrf_token', csrfToken);
        fetch('ajax_delete_category.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                categoryItem.remove();
                showFeedback(`Categoria "${categoryName}" excluída.`, 'success');
                
                // Atualizar preview removendo a categoria
                const iframe = document.getElementById('recipe-preview-frame');
                if (iframe && iframe.contentWindow) {
                    const selectedCategories = Array.from(gridContainer.querySelectorAll('input[type="checkbox"]:checked'))
                        .map(cb => cb.nextElementSibling.textContent.trim());
                    iframe.contentWindow.postMessage({ type: 'updateCategories', value: selectedCategories }, '*');
                }
            } else { 
                showFeedback(data.message || 'Ocorreu um erro.'); 
            }
        })
        .catch(error => { 
            console.error('Erro na requisição:', error); 
            showFeedback('Erro de conexão ao excluir categoria.'); 
        });
    };

    if(addCategoryBtn) addCategoryBtn.addEventListener('click', createCategory);
    if(newCategoryInput) newCategoryInput.addEventListener('keypress', function(event) { 
        if (event.key === 'Enter') { 
            event.preventDefault(); 
            createCategory(); 
        } 
    });

    if(gridContainer) {
        gridContainer.addEventListener('click', function(event) {
        if (event.target && event.target.classList.contains('btn-delete-category')) {
            deleteCategory(event.target);
        }
    });
        
        // Atualizar preview quando categorias são selecionadas/desselecionadas
        gridContainer.addEventListener('change', function(event) {
            if (event.target && event.target.type === 'checkbox' && event.target.name === 'categories[]') {
                const iframe = document.getElementById('recipe-preview-frame');
                if (iframe && iframe.contentWindow) {
                    const selectedCategories = Array.from(gridContainer.querySelectorAll('input[type="checkbox"]:checked'))
                        .map(cb => cb.nextElementSibling.textContent.trim());
                    iframe.contentWindow.postMessage({ type: 'updateCategories', value: selectedCategories }, '*');
                }
            }
        });
    }
});

// =========================================================================
//       FERRAMENTA DE CÁLCULO DE PESO DA PORÇÃO
// =========================================================================

document.addEventListener('DOMContentLoaded', function() {
    const totalWeightInput = document.getElementById('helper_total_weight');
    const servingsInput = document.getElementById('servings');
    const servingSizeResultInput = document.getElementById('serving_size_g');
    const iframe = document.getElementById('recipe-preview-frame');

    function sendServingInfoUpdate() {
        if (iframe && iframe.contentWindow) {
            const prep = document.getElementById('prep_time_minutes')?.value || 0;
            const cook = document.getElementById('cook_time_minutes')?.value || 0;
            const kcal = document.getElementById('kcal_per_serving')?.value || 0;
            const carbs = document.getElementById('carbs_g_per_serving')?.value || 0;
            const protein = document.getElementById('protein_g_per_serving')?.value || 0;
            const fat = document.getElementById('fat_g_per_serving')?.value || 0;
            
            iframe.contentWindow.postMessage({
                type: 'updateMacrosAndTime',
                value: {
                    prep: prep,
                    cook: cook,
                    kcal: kcal,
                    carbs: carbs,
                    protein: protein,
                    fat: fat,
                    servings: servingsInput?.value || 1,
                    serving_size_g: servingSizeResultInput?.value || 0
                }
            }, '*');
        }
    }

    const calculateServingSize = () => {
        const totalWeight = parseFloat(totalWeightInput?.value || 0);
        const servings = parseInt(servingsInput?.value || 1);

        if (totalWeight > 0 && servings > 0) {
            const calculatedSize = totalWeight / servings;
            servingSizeResultInput.value = calculatedSize.toFixed(2);
        } else {
            servingSizeResultInput.value = '';
        }
        sendServingInfoUpdate();
    };

    if (totalWeightInput && servingsInput && servingSizeResultInput) {
        totalWeightInput.addEventListener('input', calculateServingSize);
        servingsInput.addEventListener('input', calculateServingSize);
    }
    
    // Adicionar listeners para macros e tempo
    ['#prep_time_minutes', '#cook_time_minutes', '#kcal_per_serving', '#carbs_g_per_serving', '#fat_g_per_serving', '#protein_g_per_serving'].forEach(selector => {
        const element = document.querySelector(selector);
        if (element) {
            element.addEventListener('input', sendServingInfoUpdate);
        }
    });
});

</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
