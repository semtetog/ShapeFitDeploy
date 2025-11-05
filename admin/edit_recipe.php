<?php
// admin/edit_recipe.php (VERSÃO FINAL COMPLETA - LAYOUT E CHECKBOX CORRIGIDOS)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
$conn = require __DIR__ . '/../includes/db.php';
requireAdminLogin();

function format_decimal_for_input($value) { return $value !== null ? str_replace('.', ',', $value) : ''; }

$suggestion_options = [ 'cafe_da_manha' => 'Café da Manhã', 'lanche_da_manha' => 'Lanche da Manhã', 'almoco' => 'Almoço', 'lanche_da_tarde' => 'Lanche da Tarde', 'jantar' => 'Jantar', 'ceia' => 'Ceia', 'qualquer_hora' => 'Qualquer Hora' ];

$page_slug = 'recipes';
$page_title = 'Nova Receita';
$recipe_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$recipe = []; $ingredients = []; $selected_category_ids = []; $selected_suggestions = [];
$all_categories = $conn->query("SELECT id, name FROM sf_categories ORDER BY display_order ASC, name ASC")->fetch_all(MYSQLI_ASSOC);

if ($recipe_id) {
    $page_title = 'Editar Receita';
    $stmt = $conn->prepare("SELECT * FROM sf_recipes WHERE id = ?");
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $recipe = $result->fetch_assoc();
        if (!empty($recipe['meal_type_suggestion'])) { $selected_suggestions = explode(',', $recipe['meal_type_suggestion']); }
        $stmt_ing = $conn->prepare("SELECT ingredient_description, quantity_value, quantity_unit FROM sf_recipe_ingredients WHERE recipe_id = ? ORDER BY id ASC");
        $stmt_ing->bind_param("i", $recipe_id); $stmt_ing->execute(); $ingredients = $stmt_ing->get_result()->fetch_all(MYSQLI_ASSOC); $stmt_ing->close();
        $stmt_cat = $conn->prepare("SELECT category_id FROM sf_recipe_has_categories WHERE recipe_id = ?");
        $stmt_cat->bind_param("i", $recipe_id); $stmt_cat->execute(); $cat_result = $stmt_cat->get_result();
        while($row = $cat_result->fetch_assoc()){ $selected_category_ids[] = $row['category_id']; }
        $stmt_cat->close();
    } else { die("Receita não encontrada."); }
}

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf_token = $_SESSION['csrf_token'];
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<style>
/* ============================================================= */
/*       REFATORAÇÃO COMPLETA PARA PADRÃO VIEW_USER             */
/* ============================================================= */

:root {
    --bg-color: #121212;
    --card-bg-color: #1E1E1E;
    --primary-accent: #ff6b00;
    --text-color: #EAEAEA;
    --text-muted: #8E8E93;
    --border-color: #333333;
    --input-bg-color: #2C2C2E;
    --glass-border: rgba(255, 255, 255, 0.1);
    --text-primary: #FFFFFF;
    --text-secondary: #8E8E93;
    --accent-orange: #FF6B00;
}

.main-content {
    padding-bottom: 100px;
}

.live-editor-container {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 2rem;
    align-items: flex-start;
}

.editor-panel {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.preview-panel {
    position: sticky;
    top: 20px;
}

/* ===== CARDS (PADRÃO VIEW_USER) ===== */
.content-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.content-card:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

/* ===== HEADERS (PADRÃO VIEW_USER) ===== */
.card-header-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 1rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid var(--glass-border);
}

.card-header-actions h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    padding: 0;
    border-bottom: none;
    font-family: 'Montserrat', sans-serif;
}

.content-card h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 1.5rem 0;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--glass-border);
    font-family: 'Montserrat', sans-serif;
}

.header-buttons {
    display: flex;
    gap: 0.75rem;
}

.card-body {
    padding: 0;
}

/* ===== FORM CONTROLS (PADRÃO VIEW_USER) ===== */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
    display: block;
    color: var(--text-secondary);
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    color: var(--text-primary);
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.form-control:focus {
    border-color: var(--accent-orange);
    outline: none;
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
}

textarea.form-control {
    min-height: 120px;
    resize: vertical;
}

.form-group-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

/* ===== INGREDIENT ROW ===== */
.ingredient-row {
    display: grid;
    grid-template-columns: 1fr 1fr 100px 40px;
    gap: 0.75rem;
    align-items: center;
    margin-bottom: 0.75rem;
}

.btn-remove-ingredient {
    background: rgba(244, 67, 54, 0.1);
    border: 1px solid rgba(244, 67, 54, 0.3);
    border-radius: 8px;
    color: #F44336;
    cursor: pointer;
    font-size: 1.2rem;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    padding: 0;
}

.btn-remove-ingredient:hover {
    background: rgba(244, 67, 54, 0.2);
    border-color: #F44336;
    transform: scale(1.05);
}

/* ===== SELECT CUSTOMIZADO ===== */
.custom-select-wrapper {
    position: relative;
}

.custom-select-wrapper::after {
    content: '\f078';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    position: absolute;
    top: 50%;
    right: 15px;
    transform: translateY(-50%);
    color: var(--text-secondary);
    pointer-events: none;
}

.custom-select-wrapper select {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    padding-right: 40px;
}

/* ===== FILE INPUT ===== */
.custom-file-input-wrapper {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 0.75rem 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.custom-file-input-wrapper:hover {
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

.file-input-label {
    color: var(--text-secondary);
}

.file-input-filename {
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
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

/* ===== CHECKBOX GRID ===== */
.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 0.75rem;
}

/* ===== CHECKBOX CUSTOMIZADO ===== */
.checkbox-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.checkbox-item input[type="checkbox"] {
    opacity: 0;
    position: absolute;
    width: 0;
    height: 0;
}

.checkbox-item label {
    flex-grow: 1;
    color: var(--text-secondary);
    padding-left: 32px;
    position: relative;
    cursor: pointer;
    user-select: none;
    font-size: 0.9rem;
    transition: color 0.2s;
}

.checkbox-item label:hover {
    color: var(--text-primary);
}

.checkbox-item label::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 6px;
    transition: all 0.2s;
}

.checkbox-item input:checked + label::before {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
}

.checkbox-item input:checked + label::after {
    content: '\f00c';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    position: absolute;
    left: 4px;
    top: 50%;
    transform: translateY(-50%);
    color: white;
    font-size: 10px;
}

/* ===== ADD CATEGORY FORM ===== */
.add-category-form {
    display: flex;
    gap: 0.75rem;
    margin-top: 1.5rem;
    border-top: 1px solid var(--glass-border);
    padding-top: 1.5rem;
}

.add-category-form .form-control {
    flex-grow: 1;
}

.add-category-feedback {
    font-size: 0.8rem;
    margin-top: 0.5rem;
    display: block;
    height: 1em;
}

.add-category-feedback.success {
    color: #4CAF50;
}

.add-category-feedback.error {
    color: #F44336;
}

.btn-delete-category {
    background: rgba(244, 67, 54, 0.1);
    border: 1px solid rgba(244, 67, 54, 0.3);
    border-radius: 8px;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 1rem;
    padding: 0.25rem 0.5rem;
    opacity: 0.6;
    transition: all 0.2s;
}

.checkbox-item:hover .btn-delete-category {
    opacity: 1;
}

.btn-delete-category:hover {
    color: #F44336;
    background: rgba(244, 67, 54, 0.2);
    border-color: #F44336;
}

/* ===== BUTTONS (PADRÃO VIEW_USER) ===== */
.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--accent-orange) 0%, #FF8533 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(255, 107, 0, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 107, 0, 0.4);
    color: white;
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    border: 1px solid var(--glass-border);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--accent-orange);
    transform: translateY(-1px);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

/* ===== SECTION STYLES ===== */
.ingredients-section,
.nutrition-section {
    margin-bottom: 2rem;
}

.section-subtitle {
    color: var(--text-primary);
    margin-bottom: 1rem;
    font-size: 1.1rem;
    font-weight: 600;
}

.section-divider {
    border: none;
    border-top: 1px solid var(--glass-border);
    margin: 1.5rem 0;
}

.btn-add-ingredient {
    margin-top: 1rem;
}

/* ===== CALCULATION TOOL ===== */
.calculation-tool {
    background: rgba(0, 0, 0, 0.2);
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    border: 1px solid var(--glass-border);
}

.calculation-label {
    font-size: 0.9rem;
    color: var(--text-primary);
    margin-bottom: 1rem;
    display: block;
    font-weight: 500;
}

.calculation-tool .form-group {
    margin-bottom: 1rem;
}

.calculation-tool .form-group:last-child {
    margin-bottom: 0;
}

/* ===== READONLY INPUT ===== */
.form-control-readonly {
    background-color: rgba(17, 17, 17, 0.6) !important;
    color: var(--accent-orange) !important;
    font-weight: 600 !important;
    border-style: dashed !important;
    border-color: var(--accent-orange) !important;
    cursor: not-allowed;
}

.field-help {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-top: 0.5rem;
    display: block;
    line-height: 1.4;
}

.categories-help {
    margin-top: -0.5rem;
    margin-bottom: 1rem;
}

/* ===== PREVIEW ===== */
.mobile-preview-wrapper {
    width: 380px;
    height: 750px;
    padding: 12px;
    background: rgba(28, 28, 28, 0.8);
    border-radius: 40px;
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.5);
    border: 1px solid var(--glass-border);
}

#recipe-preview-frame {
    width: 100%;
    height: 100%;
    border-radius: 28px;
    border: none;
    background: #222;
}
</style>

<form action="save_recipe.php" method="POST" enctype="multipart/form-data" id="recipe-form">
    <input type="hidden" name="recipe_id" value="<?php echo htmlspecialchars($recipe['id'] ?? ''); ?>">
    <input type="hidden" name="existing_image_filename" value="<?php echo htmlspecialchars($recipe['image_filename'] ?? ''); ?>">
    <input type="hidden" id="csrf-token" value="<?php echo $csrf_token; ?>">

    <div class="live-editor-container">
        <div class="editor-panel">
            <div class="content-card">
                <!-- ALTERAÇÃO AQUI: Novo div para o cabeçalho com ações -->
                <div class="card-header-actions">
                    <h3>Conteúdo Principal</h3>
                    <div class="header-buttons">
                        <a href="recipes.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Receita</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-group"><label for="name">Nome da Receita</label><input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($recipe['name'] ?? ''); ?>" required></div>
                    <div class="form-group"><label for="description">Descrição Curta</label><textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($recipe['description'] ?? ''); ?></textarea></div>
                    <div class="form-group"><label for="instructions">Modo de Preparo (um passo por linha)</label><textarea id="instructions" name="instructions" class="form-control" rows="7"><?php echo htmlspecialchars($recipe['instructions'] ?? ''); ?></textarea></div>
                </div>
            </div>
            <div class="content-card">
                <h3>Ingredientes e Informações Nutricionais</h3>
                <div class="card-body">
                    <!-- SEÇÃO DE INGREDIENTES -->
                    <div class="ingredients-section">
                        <h4 class="section-subtitle">Ingredientes</h4>
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
                        <button type="button" id="btn-add-ingredient" class="btn btn-secondary btn-sm btn-add-ingredient"><i class="fas fa-plus"></i> Adicionar Ingrediente</button>
                    </div>

                    <hr class="section-divider">

                    <!-- SEÇÃO DE INFORMAÇÕES NUTRICIONAIS E PORÇÕES -->
                    <div class="nutrition-section">
                        <h4 class="section-subtitle">Informações Nutricionais e Porções</h4>

                        <!-- FERRAMENTA DE CÁLCULO (AGORA É A ENTRADA PRINCIPAL) -->
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

                        <!-- CAMPO DE RESULTADO (O QUE SERÁ SALVO) -->
                        <div class="form-group">
                            <label for="serving_size_g">2. Peso Final por Porção (calculado)</label>
                            <input type="number" id="serving_size_g" name="serving_size_g" class="form-control form-control-readonly" 
                                   value="<?php echo htmlspecialchars($recipe['serving_size_g'] ?? ''); ?>" 
                                   step="0.01" readonly>
                            <small class="field-help">Este valor é calculado automaticamente a partir do Peso Total e do Rendimento. É este valor que será salvo.</small>
                        </div>
                        
                        <hr class="section-divider">

                         <div class="form-group-grid-2">
                            <div class="form-group"><label>Calorias (kcal)</label><input type="text" id="kcal_per_serving" name="kcal_per_serving" class="form-control" value="<?php echo format_decimal_for_input($recipe['kcal_per_serving'] ?? ''); ?>"></div>
                            <div class="form-group"><label>Carboidratos (g)</label><input type="text" id="carbs_g_per_serving" name="carbs_g_per_serving" class="form-control" value="<?php echo format_decimal_for_input($recipe['carbs_g_per_serving'] ?? ''); ?>"></div>
                            <div class="form-group"><label>Gorduras (g)</label><input type="text" id="fat_g_per_serving" name="fat_g_per_serving" class="form-control" value="<?php echo format_decimal_for_input($recipe['fat_g_per_serving'] ?? ''); ?>"></div>
                            <div class="form-group"><label>Proteínas (g)</label><input type="text" id="protein_g_per_serving" name="protein_g_per_serving" class="form-control" value="<?php echo format_decimal_for_input($recipe['protein_g_per_serving'] ?? ''); ?>"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="content-card">
                <h3>Configurações e Imagem</h3>
                 <div class="card-body">
                    <div class="form-group">
                        <label for="is_public">Status</label>
                        <div class="custom-select-wrapper">
                            <select id="is_public" name="is_public" class="form-control">
                                <option value="1" <?php if(!isset($recipe['is_public']) || $recipe['is_public'] == 1) echo 'selected'; ?>>Pública</option>
                                <option value="0" <?php if(isset($recipe['is_public']) && $recipe['is_public'] == 0) echo 'selected'; ?>>Privada (Rascunho)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="image">Imagem da Receita</label>
                        <label for="image" class="custom-file-input-wrapper">
                            <span class="file-input-label" id="file-label-text">Escolher arquivo</span>
                            <span class="file-input-filename" id="file-name-display">Nenhum arquivo escolhido</span>
                        </label>
                        <input type="file" id="image" name="image" class="form-control" accept="image/jpeg, image/png, image/webp">
                    </div>
                    <div class="form-group-grid-2">
                        <div class="form-group"><label>Preparo (min)</label><input type="number" id="prep_time_minutes" name="prep_time_minutes" class="form-control" value="<?php echo htmlspecialchars($recipe['prep_time_minutes'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Cozimento (min)</label><input type="number" id="cook_time_minutes" name="cook_time_minutes" class="form-control" value="<?php echo htmlspecialchars($recipe['cook_time_minutes'] ?? ''); ?>"></div>
                    </div>
                 </div>
            </div>
            <div class="content-card">
                <h3>Sugestões para o Dashboard (por Horário)</h3>
                <div class="card-body">
                    <div class="checkbox-grid">
                        <?php foreach ($suggestion_options as $value => $label): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" id="suggestion_<?php echo $value; ?>" name="meal_type_suggestion[]" value="<?php echo htmlspecialchars($value); ?>" <?php if (in_array($value, $selected_suggestions)) echo 'checked'; ?>>
                            <label for="suggestion_<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
             <div class="content-card">
                <h3>Categorias</h3>
                <div class="card-body">
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
                        <button type="button" id="btn-add-category" class="btn btn-primary">Adicionar</button>
                    </div>
                    <span id="add-category-feedback" class="add-category-feedback"></span>
                </div>
            </div>
        </div>

        <div class="preview-panel">
            <div class="mobile-preview-wrapper"><iframe id="recipe-preview-frame" src="../_admin_recipe_preview.php?id=<?php echo htmlspecialchars($recipe_id ?? ''); ?>"></iframe></div>
        </div>
    </div>
    
    <!-- REMOVIDO: A barra inferior de ações não existe mais aqui -->
    <!-- <div class="form-actions-footer">
        <a href="recipes.php" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Receita</button>
    </div> -->
</form>

<script>
// SCRIPTS EXISTENTES (PREVIEW, ETC.)
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('image');
    const fileNameDisplay = document.getElementById('file-name-display');
    const fileLabelText = document.getElementById('file-label-text');
    if(fileInput) fileInput.addEventListener('change', function() { if (this.files && this.files.length > 0) { fileNameDisplay.textContent = this.files[0].name; fileLabelText.style.display = 'none'; } else { fileNameDisplay.textContent = 'Nenhum arquivo escolhido'; fileLabelText.style.display = 'inline'; } });
    const iframe = document.getElementById('recipe-preview-frame');
    if(iframe) iframe.addEventListener('load', function() {
        const iframeWindow = iframe.contentWindow;
        function updatePreview(type, value) { iframeWindow.postMessage({ type, value }, '*'); }
        const simpleMappings = { '#name': 'updateName', '#description': 'updateDescription', '#instructions': 'updateInstructions' };
        for (const selector in simpleMappings) { document.querySelector(selector)?.addEventListener('input', (e) => updatePreview(simpleMappings[selector], e.target.value)); }
        function sendMacroAndTimeUpdate() { updatePreview('updateMacrosAndTime', getAllMacroAndTimeData()); }
        ['#prep_time_minutes', '#cook_time_minutes', '#kcal_per_serving', '#carbs_g_per_serving', '#fat_g_per_serving', '#protein_g_per_serving'].forEach(selector => { document.querySelector(selector)?.addEventListener('input', sendMacroAndTimeUpdate); });
        function handleIngredientUpdates() { 
            const ingredients = [];
            document.querySelectorAll('.ingredient-row').forEach(row => {
                const description = row.querySelector('input[name="ingredient_description[]"]').value;
                const quantity = row.querySelector('input[name="ingredient_quantity[]"]').value;
                const unit = row.querySelector('select[name="ingredient_unit[]"]').value;
                
                let ingredientText = description;
                if (quantity && unit) {
                    ingredientText = quantity + ' ' + unit + ' de ' + description;
                }
                ingredients.push(ingredientText);
            });
            updatePreview('updateIngredients', ingredients); 
        }
        function attachIngredientListeners(row) { 
            row.querySelectorAll('input, select').forEach(input => {
                input.addEventListener('input', handleIngredientUpdates);
                input.addEventListener('change', handleIngredientUpdates);
            });
            row.querySelector('.btn-remove-ingredient').addEventListener('click', () => { 
                if (document.querySelectorAll('.ingredient-row').length > 1) { 
                    row.remove(); 
                    handleIngredientUpdates(); 
                } 
            }); 
        }
        document.querySelectorAll('.ingredient-row').forEach(attachIngredientListeners);
        document.getElementById('btn-add-ingredient')?.addEventListener('click', () => { const container = document.getElementById('ingredients-container'); const newRow = document.createElement('div'); newRow.className = 'ingredient-row'; newRow.innerHTML = `<input type="text" name="ingredient_description[]" class="form-control" placeholder="Ex: Farinha de trigo"><input type="number" name="ingredient_quantity[]" class="form-control" placeholder="Quantidade" step="0.01"><select name="ingredient_unit[]" class="form-control"><option value="">Unidade</option><option value="g">g (gramas)</option><option value="kg">kg (quilogramas)</option><option value="ml">ml (mililitros)</option><option value="l">l (litros)</option><option value="xícara">xícara (240ml)</option><option value="colher_sopa">colher de sopa (15ml)</option><option value="colher_cha">colher de chá (5ml)</option></select><button type="button" class="btn-remove-ingredient" title="Remover">×</button>`; container.appendChild(newRow); attachIngredientListeners(newRow); });
        fileInput.addEventListener('change', function(e) { if (e.target.files && e.target.files[0]) { const reader = new FileReader(); reader.onload = (event) => updatePreview('updateImage', event.target.result); reader.readAsDataURL(e.target.files[0]); } });
        function getAllMacroAndTimeData() { return { prep: document.getElementById('prep_time_minutes').value, cook: document.getElementById('cook_time_minutes').value, kcal: document.getElementById('kcal_per_serving').value, carbs: document.getElementById('carbs_g_per_serving').value, protein: document.getElementById('protein_g_per_serving').value, fat: document.getElementById('fat_g_per_serving').value }; }
    });
    // REMOVIDO: Funções relacionadas ao posicionamento da barra inferior
    /*
    function adjustFooterPosition() { const sidebar = document.querySelector('.sidebar'); const footer = document.querySelector('.form-actions-footer'); if (sidebar && footer) { const sidebarWidth = document.body.classList.contains('sidebar-collapsed') ? '80px' : '250px'; document.documentElement.style.setProperty('--sidebar-width', sidebarWidth); } }
    adjustFooterPosition();
    const sidebarToggler = document.querySelector('.sidebar-toggler'); 
    if(sidebarToggler) { sidebarToggler.addEventListener('click', () => { setTimeout(adjustFooterPosition, 300); }); }
    */
});

// SCRIPT PARA GERENCIAMENTO DE CATEGORIAS (CRIAR E EXCLUIR)
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
        if (!categoryName) { showFeedback('Por favor, insira um nome para a categoria.'); return; }
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
                // ESTRUTURA HTML CORRIGIDA: <input> e <label> como irmãos
                newItem.innerHTML = `
                    <input type="checkbox" id="${newId}" name="categories[]" value="${data.id}" checked>
                    <label for="${newId}">${data.name}</label>
                    <button type="button" class="btn-delete-category" data-id="${data.id}" title="Excluir Categoria">&times;</button>
                `;
                gridContainer.appendChild(newItem);
                newCategoryInput.value = '';
                showFeedback('Categoria criada e selecionada!', 'success');
            } else { showFeedback(data.message || 'Ocorreu um erro.'); }
        })
        .catch(error => { console.error('Erro na requisição:', error); showFeedback('Erro de conexão ao criar categoria.'); });
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
            } else { showFeedback(data.message || 'Ocorreu um erro.'); }
        })
        .catch(error => { console.error('Erro na requisição:', error); showFeedback('Erro de conexão ao excluir categoria.'); });
    };

    if(addCategoryBtn) addCategoryBtn.addEventListener('click', createCategory);
    if(newCategoryInput) newCategoryInput.addEventListener('keypress', function(event) { if (event.key === 'Enter') { event.preventDefault(); createCategory(); } });

    if(gridContainer) gridContainer.addEventListener('click', function(event) {
        if (event.target && event.target.classList.contains('btn-delete-category')) {
            deleteCategory(event.target);
        }
    });
});

// FERRAMENTA DE CÁLCULO DE PESO DA PORÇÃO - FLUXO PROFISSIONAL
document.addEventListener('DOMContentLoaded', function() {
    // Referências aos elementos do formulário
    const totalWeightInput = document.getElementById('helper_total_weight');
    const servingsInput = document.getElementById('servings');
    const servingSizeResultInput = document.getElementById('serving_size_g');
    const iframe = document.getElementById('recipe-preview-frame');

    // Função para atualizar o preview
    function sendServingInfoUpdate() {
        if (iframe) {
            iframe.contentWindow.postMessage({
                type: 'updateMacrosAndTime',
                value: {
                    ...getAllMacroAndTimeData(), // Função que já existe no seu código
                    servings: servingsInput.value,
                    serving_size_g: servingSizeResultInput.value
                }
            }, '*');
        }
    }

    // Função que realiza o cálculo
    const calculateServingSize = () => {
        const totalWeight = parseFloat(totalWeightInput.value);
        const servings = parseInt(servingsInput.value);

        if (totalWeight > 0 && servings > 0) {
            const calculatedSize = totalWeight / servings;
            servingSizeResultInput.value = calculatedSize.toFixed(2);
        } else {
            // Se os valores não forem válidos, limpa o campo de resultado
            servingSizeResultInput.value = '';
        }
        // Sempre atualiza o preview após um cálculo
        sendServingInfoUpdate();
    };

    // Adiciona os "escutadores" de eventos para calcular em tempo real
    if (totalWeightInput && servingsInput && servingSizeResultInput) {
        totalWeightInput.addEventListener('input', calculateServingSize);
        servingsInput.addEventListener('input', calculateServingSize);
    }
    
    // Garante que o preview também seja atualizado quando outros campos de macro mudarem
    ['#kcal_per_serving', '#carbs_g_per_serving', '#fat_g_per_serving', '#protein_g_per_serving'].forEach(selector => {
        const element = document.querySelector(selector);
        if (element) {
            element.addEventListener('input', sendServingInfoUpdate);
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>