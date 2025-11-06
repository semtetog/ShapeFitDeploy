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
    --layout-gap: 2rem;
    
    /* A MÁGICA FINAL: Tamanho baseado na ALTURA da tela */
    --mockup-height: calc(100vh - (var(--layout-gap) * 2));
    --mockup-width: calc(var(--mockup-height) / 2); /* Força a proporção 2:1 */
}

/* O CONTAINER PRINCIPAL */
.edit-recipe-container {
    display: flex;
    gap: var(--layout-gap);
    padding: var(--layout-gap);
    /* Padding à esquerda para criar espaço onde o celular fixo fica */
    /* O main-content já tem margin-left: 256px (sidebar), então só precisamos: mockup-width + gap */
    padding-left: calc(var(--mockup-width) + var(--layout-gap));
    padding-right: calc(var(--layout-gap) * 2); /* Um pouco mais de espaço à direita */
    width: calc(100vw - var(--sidebar-width)); /* Ocupa toda largura disponível menos o sidebar */
    max-width: none; /* Remove restrição e deixa expandir até a borda */
    box-sizing: border-box;
    overflow-x: hidden;
}

/* PAINEL DO CELULAR (ESQUERDA) */
.mobile-mockup-panel {
    position: fixed; /* FIXO! NÃO ROLA COM A PÁGINA */
    top: 50%;
    transform: translateY(-50%); /* Centraliza verticalmente */
    left: calc(var(--sidebar-width) + var(--layout-gap)); /* Logo após o sidebar com gap pequeno */
    
    width: var(--mockup-width);
    height: var(--mockup-height); /* Usa a variável de altura */
    
    /* Limites para não ficar nem gigante nem minúsculo */
    max-width: 410px;
    max-height: 820px;
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

/* PAINEL DE CONFIGURAÇÕES (DIREITA) */
.config-panel {
    display: flex;
    flex-direction: column;
    gap: 3rem !important; /* Aumenta espaçamento entre os cards - FORÇA COM !important */
    flex-grow: 1; /* Força ocupar todo espaço disponível */
    flex-basis: 0; /* Permite crescer sem restrições */
    width: auto; /* Em vez de width 100% */
    max-width: calc(100vw - var(--sidebar-width) - var(--mockup-width) - (var(--layout-gap) * 2)); /* Limite exato */
    min-width: 600px; /* Evita colapsar em telas menores */
    box-sizing: border-box;
}

/* Garante que os cards dentro do config-panel tenham espaçamento */
.config-panel > * {
    margin-bottom: 0 !important; /* Remove margin-bottom que pode estar conflitando */
}

/* Aplica gap diretamente aos dashboard-card dentro do config-panel */
.config-panel .dashboard-card {
    margin-bottom: 2rem !important; /* Espaçamento entre os cards */
}

.config-panel .dashboard-card:last-child {
    margin-bottom: 0 !important; /* Remove margin do último card */
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
    margin-bottom: 0 !important; /* Remove margin que pode estar conflitando com o gap */
    margin-top: 0 !important;
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
    gap: 1rem !important; /* Aumenta gap entre checkboxes */
    margin-bottom: 2rem !important; /* Espaço antes do formulário de adicionar */
}

.checkbox-item {
    display: flex !important;
    align-items: center !important;
    gap: 0.75rem !important; /* Espaço entre checkbox, label e botão */
    margin-bottom: 0.5rem !important;
    padding: 0.5rem !important;
    border-radius: 8px !important;
    transition: background 0.2s ease !important;
}

.checkbox-item:hover {
    background: rgba(255, 255, 255, 0.03) !important;
}

.checkbox-item input[type="checkbox"] {
    opacity: 0 !important;
    position: absolute !important;
}

.checkbox-item label {
    flex: 1 !important; /* Ocupa espaço disponível mas não cresce demais */
    color: var(--text-secondary) !important;
    padding-left: 32px !important;
    position: relative !important;
    cursor: pointer !important;
    font-size: 0.9rem !important;
    margin: 0 !important; /* Remove margens */
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
    margin-top: 0 !important; /* Remove margin-top pois já tem margin-bottom no checkbox-grid */
    border-top: 1px solid var(--glass-border) !important;
    padding-top: 1.5rem !important;
    padding-bottom: 0.5rem !important; /* Adiciona padding-bottom */
}

.btn-delete-category {
    background: rgba(244, 67, 54, 0.1) !important;
    border: 1px solid rgba(244, 67, 54, 0.2) !important;
    border-radius: 6px !important;
    color: #F44336 !important;
    cursor: pointer !important;
    width: 28px !important;
    height: 28px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 1.2rem !important;
    line-height: 1 !important;
    padding: 0 !important;
    opacity: 0.7 !important;
    transition: all 0.2s ease !important;
    flex-shrink: 0 !important; /* Não encolhe */
}

.btn-delete-category:hover {
    background: rgba(244, 67, 54, 0.2) !important;
    border-color: rgba(244, 67, 54, 0.4) !important;
    opacity: 1 !important;
    transform: scale(1.1) !important;
}

/* Mostra o botão delete apenas quando o checkbox está marcado */
.checkbox-item:has(input[type="checkbox"]:checked) .btn-delete-category {
    opacity: 1 !important;
}

.checkbox-item:has(input[type="checkbox"]:not(:checked)) .btn-delete-category {
    opacity: 0.3 !important;
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
    font-size: 0.875rem !important;
    color: var(--text-secondary) !important;
    margin-top: 1rem !important;
    margin-bottom: 1.5rem !important;
    display: block !important;
    line-height: 1.5 !important;
}

.categories-help {
    margin-top: 1.5rem !important; /* Mais espaço após o header */
    margin-bottom: 2rem !important; /* Mais espaço antes do grid */
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

/* Ajuste específico para zoom 100% - reduz gap entre celular e menu */
@media (min-height: 900px) and (max-height: 1100px) {
    .edit-recipe-container {
        padding-left: calc(var(--mockup-width) + 1rem) !important;
    }
    .mobile-mockup-panel {
        left: calc(var(--sidebar-width) + var(--layout-gap) + 0.5rem) !important; /* Move um pouco para a direita */
    }
}

/* RESPONSIVIDADE */
@media (max-width: 1200px) {
    .edit-recipe-container {
        flex-direction: column;
    }
    .mobile-mockup-panel {
        position: static; /* Deixa de ser "grudento" */
        width: 100%;
        max-width: 410px;
        height: 750px; /* Altura fixa para telas menores */
        margin: 0 auto 2rem auto; /* Centraliza e adiciona espaço embaixo */
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

                <!-- INGREDIENTES INLINE (sem card) -->
                    <div style="margin-bottom: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h4 style="margin: 0; color: var(--text-primary); font-size: 1.125rem;"><i class="fas fa-utensils"></i> Ingredientes</h4>
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

                <!-- INPUT DE IMAGEM OCULTO (gerenciado pelo modal) -->
                <input type="file" id="image" name="image" class="form-control" accept="image/jpeg, image/png, image/webp" style="display: none;">

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
                <div class="dashboard-card" id="categories-card">
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
        
        // =========================================================================
        //       MODAL DE GERENCIAMENTO DE IMAGEM (INLINE NO PREVIEW)
        // =========================================================================
        const imageInput = document.getElementById('image');
        let imageModal = null;

        // Criar popup de imagem com glassmorphism (sobre a imagem)
        function createImageModal() {
            if (imageModal) return imageModal;

            const popup = document.createElement('div');
            popup.id = 'image-management-popup';
            popup.style.cssText = `
                position: absolute;
                display: none;
                z-index: 10000;
                opacity: 0;
                transition: opacity 0.2s ease, transform 0.2s ease;
            `;

            const popupContent = document.createElement('div');
            popupContent.style.cssText = `
                background: rgba(18, 18, 18, 0.4);
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 16px;
                padding: 0;
                min-width: 200px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.05) inset;
                transform: scale(0.95) translateY(-5px);
                transition: transform 0.2s ease, opacity 0.2s ease;
                opacity: 0;
                overflow: hidden;
                will-change: transform, opacity;
            `;

            const buttonsContainer = document.createElement('div');
            buttonsContainer.style.cssText = `
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                padding: 0.75rem;
            `;

            const changeButton = document.createElement('button');
            changeButton.innerHTML = '<i class="fas fa-exchange-alt"></i> Trocar';
            changeButton.style.cssText = `
                width: 100%;
                padding: 0.625rem 1rem;
                border-radius: 10px;
                font-size: 0.875rem;
                font-weight: 600;
                cursor: pointer;
                background: rgba(255, 107, 0, 0.2);
                border: 1px solid rgba(255, 107, 0, 0.35);
                color: var(--accent-orange);
                transition: all 0.2s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                font-family: 'Montserrat', sans-serif;
            `;
            changeButton.onmouseenter = () => {
                changeButton.style.background = 'rgba(255, 107, 0, 0.25)';
                changeButton.style.borderColor = 'rgba(255, 107, 0, 0.4)';
                changeButton.style.transform = 'translateY(-1px)';
            };
            changeButton.onmouseleave = () => {
                changeButton.style.background = 'rgba(255, 107, 0, 0.2)';
                changeButton.style.borderColor = 'rgba(255, 107, 0, 0.35)';
                changeButton.style.transform = 'translateY(0)';
            };
            changeButton.onclick = () => {
                imageInput?.click();
                closeImageModal();
            };

            const deleteButton = document.createElement('button');
            deleteButton.innerHTML = '<i class="fas fa-trash-alt"></i> Excluir';
            deleteButton.style.cssText = `
                width: 100%;
                padding: 0.625rem 1rem;
                border-radius: 10px;
                font-size: 0.875rem;
                font-weight: 600;
                cursor: pointer;
                background: rgba(244, 67, 54, 0.2);
                border: 1px solid rgba(244, 67, 54, 0.35);
                color: #F44336;
                transition: all 0.2s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                font-family: 'Montserrat', sans-serif;
            `;
            deleteButton.onmouseenter = () => {
                deleteButton.style.background = 'rgba(244, 67, 54, 0.25)';
                deleteButton.style.borderColor = 'rgba(244, 67, 54, 0.4)';
                deleteButton.style.transform = 'translateY(-1px)';
            };
            deleteButton.onmouseleave = () => {
                deleteButton.style.background = 'rgba(244, 67, 54, 0.2)';
                deleteButton.style.borderColor = 'rgba(244, 67, 54, 0.35)';
                deleteButton.style.transform = 'translateY(0)';
            };
            deleteButton.onclick = () => {
                deleteImage();
                closeImageModal();
            };

            buttonsContainer.appendChild(changeButton);
            buttonsContainer.appendChild(deleteButton);
            popupContent.appendChild(buttonsContainer);
            popup.appendChild(popupContent);

            // Adicionar ao body
            document.body.appendChild(popup);
            imageModal = popup;
            return popup;
        }

        function openImageModal(eventData = null) {
            const popup = createImageModal();
            const content = popup.querySelector('div');
            
            // Função para calcular e posicionar o popup
            const positionPopup = () => {
                if (!iframe || !iframe.contentWindow) return;
                
                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                const imageElement = iframeDoc.getElementById('recipe-image') || iframeDoc.getElementById('recipe-image-placeholder');
                
                if (!imageElement) return;
                
                // Obter posição do iframe no viewport principal
                const iframeRect = iframe.getBoundingClientRect();
                
                // Obter posição da imagem
                let imageRect;
                
                // Se temos dados do evento (enviados pelo iframe), usar eles (mais preciso)
                if (eventData && eventData.imageRect) {
                    imageRect = eventData.imageRect;
                } else {
                    // Fallback: tentar obter do contexto do iframe
                    try {
                        imageRect = imageElement.getBoundingClientRect();
                    } catch (e) {
                        console.error('Erro ao obter posição da imagem:', e);
                        return;
                    }
                }
                
                // Calcular posição absoluta no viewport principal
                // A posição da imagem (imageRect) é relativa ao viewport do iframe
                // Precisamos somar com a posição do iframe no viewport principal
                const popupX = iframeRect.left + imageRect.left + (imageRect.width / 2);
                const popupY = iframeRect.top + imageRect.top + (imageRect.height / 2);
                
                // Posicionar popup centralizado sobre a imagem
                popup.style.position = 'fixed';
                popup.style.left = popupX + 'px';
                popup.style.top = popupY + 'px';
                popup.style.transform = 'translate(-50%, -50%)';
                
                popup.style.display = 'block';
                
                // Trigger animation - blur já está aplicado no CSS
                requestAnimationFrame(() => {
                    popup.style.opacity = '1';
                    if (content) {
                        content.style.opacity = '1';
                        content.style.transform = 'scale(1) translateY(0)';
                    }
                });
            };
            
            // Se temos dados do evento, usar imediatamente, senão calcular
            if (eventData) {
                positionPopup();
            } else {
                // Aguardar um frame para garantir que o iframe está renderizado
                requestAnimationFrame(() => {
                    positionPopup();
                });
            }
            
            // Fechar ao clicar fora (qualquer lugar - celular ou fora)
            const closeOnClickOutside = (e) => {
                // Verificar se o clique foi fora do popup
                if (!popup.contains(e.target)) {
                    closeImageModal();
                    document.removeEventListener('click', closeOnClickOutside);
                    document.removeEventListener('touchstart', closeOnClickOutside);
                }
            };
            
            // Adicionar listeners para mouse e touch
            setTimeout(() => {
                document.addEventListener('click', closeOnClickOutside, true);
                document.addEventListener('touchstart', closeOnClickOutside, true);
            }, 100);
            
            // Fechar com ESC
            const closeOnEsc = (e) => {
                if (e.key === 'Escape') {
                    closeImageModal();
                    document.removeEventListener('keydown', closeOnEsc);
                }
            };
            document.addEventListener('keydown', closeOnEsc);
        }

        function closeImageModal() {
            if (imageModal) {
                const content = imageModal.querySelector('div');
                imageModal.style.opacity = '0';
                if (content) {
                    content.style.opacity = '0';
                    content.style.transform = 'scale(0.95) translateY(-5px)';
                }
                
                setTimeout(() => {
                    imageModal.style.display = 'none';
                }, 200);
            }
        }

        function deleteImage() {
            // Remover imagem do input
            if (imageInput) {
                imageInput.value = '';
            }

            // Atualizar preview para mostrar placeholder
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.postMessage({
                    type: 'updateImage',
                    value: null
                }, '*');
            }
        }

        // Escutar clicks na imagem do preview
        window.addEventListener('message', function(event) {
            if (event.data.type === 'imageClick') {
                // Se tem imagem, abrir popup com opções
                openImageModal(event.data);
            } else if (event.data.type === 'imagePlaceholderClick') {
                // Se é placeholder (sem imagem), abrir seletor de arquivo diretamente
                if (imageInput) {
                    imageInput.click();
                }
            }
        });

        // Quando uma nova imagem for selecionada
        if (imageInput) {
            imageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Atualizar preview
                        if (iframe && iframe.contentWindow) {
                            iframe.contentWindow.postMessage({
                                type: 'updateImage',
                                value: e.target.result
                            }, '*');
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
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
                    const previewFrame = document.getElementById('recipe-preview-frame');
                    if (previewFrame && previewFrame.contentWindow) {
                        const selectedCategories = Array.from(gridContainer.querySelectorAll('input[type="checkbox"]:checked'))
                            .map(cb => {
                                const label = cb.nextElementSibling;
                                return label ? label.textContent.trim() : '';
                            })
                            .filter(name => name);
                        previewFrame.contentWindow.postMessage({ type: 'updateCategories', value: selectedCategories }, '*');
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
                const previewFrame = document.getElementById('recipe-preview-frame');
                if (previewFrame && previewFrame.contentWindow) {
                    const selectedCategories = Array.from(gridContainer.querySelectorAll('input[type="checkbox"]:checked'))
                        .map(cb => cb.nextElementSibling.textContent.trim());
                    previewFrame.contentWindow.postMessage({ type: 'updateCategories', value: selectedCategories }, '*');
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
                const previewFrame = document.getElementById('recipe-preview-frame');
                if (previewFrame && previewFrame.contentWindow) {
                    const selectedCategories = Array.from(gridContainer.querySelectorAll('input[type="checkbox"]:checked'))
                        .map(cb => cb.nextElementSibling.textContent.trim());
                    previewFrame.contentWindow.postMessage({ type: 'updateCategories', value: selectedCategories }, '*');
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
    const previewFrame = document.getElementById('recipe-preview-frame');

    function sendServingInfoUpdate() {
        if (previewFrame && previewFrame.contentWindow) {
            const prep = document.getElementById('prep_time_minutes')?.value || 0;
            const cook = document.getElementById('cook_time_minutes')?.value || 0;
            const kcal = document.getElementById('kcal_per_serving')?.value || 0;
            const carbs = document.getElementById('carbs_g_per_serving')?.value || 0;
            const protein = document.getElementById('protein_g_per_serving')?.value || 0;
            const fat = document.getElementById('fat_g_per_serving')?.value || 0;
            
            previewFrame.contentWindow.postMessage({
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

    // =========================================================================
    //       CLICK NAS CATEGORIAS DO PREVIEW: ROLAR ATÉ O CARD DE CATEGORIAS
    // =========================================================================
    window.addEventListener('message', function(event) {
        if (event.data.type === 'scrollToCategories') {
            const categoriesCard = document.getElementById('categories-card');
            if (categoriesCard) {
                categoriesCard.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
                
                // Feedback visual - highlight do card
                categoriesCard.style.border = '2px solid var(--accent-orange)';
                categoriesCard.style.transition = 'border 0.3s ease';
                setTimeout(() => {
                    categoriesCard.style.border = '';
                }, 2000);
            }
        }
    });
});

</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
