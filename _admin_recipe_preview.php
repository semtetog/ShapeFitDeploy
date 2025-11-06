<?php
// /_admin_recipe_preview.php (VERSÃO IDÊNTICA AO VIEW_RECIPE.PHP)

require_once 'includes/config.php';
$conn = require APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';

$recipe_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$recipe = null;
$ingredients = [];
$categories = [];

if ($recipe_id) {
    $stmt_recipe = $conn->prepare("SELECT * FROM sf_recipes WHERE id = ?");
    $stmt_recipe->bind_param("i", $recipe_id);
    $stmt_recipe->execute();
    $result_recipe = $stmt_recipe->get_result();
    if ($result_recipe->num_rows > 0) {
        $recipe = $result_recipe->fetch_assoc();
        
        // Buscar ingredientes
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
        
        // Buscar categorias
        $stmt_categories = $conn->prepare("SELECT c.id, c.name FROM sf_categories c JOIN sf_recipe_has_categories rhc ON c.id = rhc.category_id WHERE rhc.recipe_id = ? ORDER BY c.name ASC");
        $stmt_categories->bind_param("i", $recipe_id);
        $stmt_categories->execute();
        $result_categories = $stmt_categories->get_result();
        while($row = $result_categories->fetch_assoc()) {
            $categories[] = $row;
        }
        $stmt_categories->close();
    }
    $stmt_recipe->close();
}

if (!$recipe) { // Placeholder
    $recipe = [
        'name' => 'Nova Receita', 
        'description' => 'Comece a digitar...', 
        'image_filename' => 'placeholder_food.jpg',
        'kcal_per_serving' => 0, 
        'carbs_g_per_serving' => 0, 
        'fat_g_per_serving' => 0, 
        'protein_g_per_serving' => 0,
        'prep_time_minutes' => 0, 
        'cook_time_minutes' => 0, 
        'instructions' => '',
        'notes' => '',
        'servings' => '1 porção'
    ];
    $ingredients = [];
    $categories = [];
}

$page_title = htmlspecialchars($recipe['name']);
$extra_css = ['recipe_detail_page.css'];
require_once APP_ROOT_PATH . '/includes/layout_header_preview.php';
?>

<style>
/* === VARIÁVEIS CSS === */
:root {
    --bg-primary: #121212;
    --text-primary: #EAEAEA;
    --text-secondary: #8E8E93;
    --accent-orange: #ff6b00;
}

/* === FORÇAR ESTILOS DAS BOLINHAS === */
.instruction-step .step-number {
    width: 28px !important;
    height: 28px !important;
    background: #ff6b00 !important;
    color: #fff !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-weight: 600 !important;
    font-size: 14px !important;
    flex-shrink: 0 !important;
    min-width: 28px !important;
    min-height: 28px !important;
}

/* === CONTAINER === */
/* Garantir que app-container tenha padding igual ao view_recipe.php */
.app-container {
    padding: 20px 24px 20px 24px !important;
    padding-top: 20px !important;
    padding-bottom: 20px !important;
    width: 100% !important;
    max-width: 480px !important;
    height: 100% !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    position: relative !important;
    background-color: transparent !important;
}

.recipe-detail-container {
    max-width: 600px;
    margin: 0 auto;
    padding: 0;
    background: var(--bg-primary);
    min-height: 100vh;
}


/* === IMAGEM === */
.recipe-detail-image {
    width: 100% !important;
    height: 280px !important;
    object-fit: cover !important;
    border-radius: 20px !important;
    margin-top: 0 !important;
    margin-bottom: 20px !important;
    display: block !important;
    position: relative !important;
    z-index: 1 !important;
    cursor: pointer !important;
    transition: all 0.3s ease !important;
}

.recipe-detail-image:hover {
    opacity: 0.8 !important;
    transform: scale(1.02) !important;
}

.recipe-image-placeholder {
    width: 100% !important;
    height: 280px !important;
    border-radius: 20px !important;
    margin-top: 0 !important;
    margin-bottom: 20px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: rgba(255, 255, 255, 0.04) !important;
    border: 2px dashed rgba(255, 255, 255, 0.12) !important;
    cursor: pointer !important;
    transition: all 0.3s ease !important;
    color: var(--text-secondary) !important;
    font-size: 16px !important;
    flex-direction: column !important;
    gap: 12px !important;
}

.recipe-image-placeholder:hover {
    background: rgba(255, 255, 255, 0.06) !important;
    border-color: var(--accent-orange) !important;
    color: var(--accent-orange) !important;
}

.recipe-image-placeholder i {
    font-size: 48px !important;
    opacity: 0.5 !important;
}

/* === INFORMAÇÕES PRINCIPAIS === */
.recipe-main-info {
    background: rgba(255, 255, 255, 0.04) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    border-radius: 20px !important;
    padding: 24px !important;
    margin-bottom: 20px !important;
    margin-top: 0 !important;
    transition: all 0.2s ease !important;
    display: block !important;
    position: relative !important;
    z-index: 1 !important;
    overflow: hidden !important;
    word-wrap: break-word !important;
    overflow-wrap: break-word !important;
    box-sizing: border-box !important;
}

.recipe-main-info:hover {
    background: rgba(255, 255, 255, 0.06);
}

.recipe-name-main {
    font-size: 28px !important;
    font-weight: 700 !important;
    color: var(--text-primary) !important;
    margin: 0 0 12px 0 !important;
    line-height: 1.2 !important;
    word-wrap: break-word !important;
    overflow-wrap: break-word !important;
    word-break: break-word !important;
    max-width: 100% !important;
    overflow: hidden !important;
}

.recipe-description-short {
    font-size: 16px !important;
    color: var(--text-secondary) !important;
    line-height: 1.5 !important;
    margin: 0 0 16px 0 !important;
    word-wrap: break-word !important;
    overflow-wrap: break-word !important;
    word-break: break-word !important;
    max-width: 100% !important;
    overflow: hidden !important;
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
    color: #ff6b00;
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
    color: #ff6b00;
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
    border-left: 3px solid #ff6b00;
}

.step-number {
    width: 28px !important;
    height: 28px !important;
    background: #ff6b00 !important;
    color: #fff !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-weight: 600 !important;
    font-size: 14px !important;
    flex-shrink: 0 !important;
}

.instruction-step p {
    color: var(--text-secondary);
    line-height: 1.5;
    margin: 0;
}

/* === OBSERVAÇÕES === */
.recipe-notes-card {
    background: rgba(255, 255, 255, 0.04) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    border-radius: 20px !important;
    padding: 24px !important;
    margin-bottom: 20px !important;
    transition: all 0.2s ease !important;
}

.recipe-notes-card:hover {
    background: rgba(255, 255, 255, 0.06) !important;
}

/* Remover margin-bottom do último elemento para evitar espaço extra */
.app-container > .recipe-section:last-child,
.app-container > .recipe-notes-card:last-child {
    margin-bottom: 0 !important;
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

    <?php if (!empty($recipe['image_filename']) && $recipe['image_filename'] !== 'placeholder_food.jpg'): ?>
        <img id="recipe-image" 
             src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/' . htmlspecialchars($recipe['image_filename']); ?>" 
             alt="<?php echo htmlspecialchars($recipe['name']); ?>" 
             class="recipe-detail-image"
             data-has-image="true">
    <?php else: ?>
        <div id="recipe-image-placeholder" class="recipe-image-placeholder" data-has-image="false">
            <i class="fas fa-image"></i>
            <span>Clique para adicionar imagem</span>
        </div>
    <?php endif; ?>

    <div class="recipe-main-info card-shadow-light">
        <h1 id="recipe-name" class="recipe-name-main" contenteditable="true" data-placeholder="Nome da Receita"><?php echo htmlspecialchars($recipe['name']); ?></h1>
        <p id="recipe-description" class="recipe-description-short" contenteditable="true" data-placeholder="Descreva brevemente a receita..."><?php echo !empty($recipe['description']) ? nl2br(htmlspecialchars($recipe['description'])) : ''; ?></p>
        <div class="category-tags-container" id="category-tags-clickable" style="cursor: pointer;">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $category): ?><span class="category-tag"><?php echo htmlspecialchars($category['name']); ?></span><?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

   <div class="recipe-macros-summary card-shadow-light">
        <div class="macro-info-item"><span id="kcal-value" class="value"><?php echo round($recipe['kcal_per_serving'] ?? 0); ?></span><span class="label">Kcal</span></div>
        <div class="macro-info-item"><span id="carbs-value" class="value"><?php echo number_format($recipe['carbs_g_per_serving'] ?? 0, 1, ',', '.'); ?>g</span><span class="label">Carbo</span></div>
        <div class="macro-info-item"><span id="fat-value" class="value"><?php echo number_format($recipe['fat_g_per_serving'] ?? 0, 1, ',', '.'); ?>g</span><span class="label">Gordura</span></div>
        <div class="macro-info-item"><span id="protein-value" class="value"><?php echo number_format($recipe['protein_g_per_serving'] ?? 0, 1, ',', '.'); ?>g</span><span class="label">Proteína</span></div>
        
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
        <?php if ($total_time > 0): ?><div id="total-time" class="timing-item"><i class="far fa-clock"></i> <?php echo $total_time; ?> min</div><?php endif; ?>
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
        <ul id="ingredient-list" class="recipe-ingredient-list">
            <?php foreach($ingredients as $ingredient): ?><li><?php echo htmlspecialchars($ingredient); ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="recipe-section card-shadow-light">
        <h3 class="recipe-section-title">Modo de Preparo</h3>
        <div id="instructions-list" class="recipe-instructions" contenteditable="true" data-placeholder="1. Primeiro passo...&#10;2. Segundo passo...&#10;3. Terceiro passo...">
            <?php
            if (!empty($recipe['instructions'])) {
                $steps = explode("\n", trim($recipe['instructions']));
                $step_number = 1;
                foreach ($steps as $step_text) {
                    if ($step_text_trimmed = trim($step_text)) {
                        $step_text_cleaned = preg_replace('/^\d+[\.\)]\s*/', '', $step_text_trimmed);
                        echo '<div class="instruction-step"><span class="step-number">' . $step_number++ . '</span><p>' . nl2br(htmlspecialchars($step_text_cleaned)) . '</p></div>';
                    }
                }
            }
            ?>
        </div>
    </div>

    <?php if (!empty($recipe['notes'])): ?>
    <div class="recipe-section recipe-notes-card card-shadow-light">
        <h3 class="recipe-section-title">Observação</h3>
        <p><?php echo nl2br(htmlspecialchars($recipe['notes'])); ?></p>
    </div>
    <?php endif; ?>

</div>

<script>
// Funções de atualização em tempo real
const updateName = (value) => { 
    document.getElementById('recipe-name').textContent = value; 
};

const updateDescription = (value) => { 
    document.getElementById('recipe-description').innerHTML = value.replace(/\n/g, '<br>'); 
};

const updateImage = (value) => { 
    document.getElementById('recipe-image').src = value; 
};

const updateIngredients = (ingredients) => {
    const list = document.getElementById('ingredient-list'); 
    list.innerHTML = '';
    if (ingredients) { 
        ingredients.forEach(ingText => { 
            if (ingText.trim()) { 
                const li = document.createElement('li'); 
                li.textContent = ingText; 
                list.appendChild(li); 
            } 
        }); 
    }
};

const updateInstructions = (text) => {
    const list = document.getElementById('instructions-list'); 
    list.innerHTML = '';
    if (text) {
        const steps = text.split('\n').filter(line => line.trim() !== '');
        steps.forEach((stepText, index) => {
            const stepDiv = document.createElement('div'); 
            stepDiv.className = 'instruction-step';
            const stepNumber = index + 1;
            stepDiv.innerHTML = `
                <span class="step-number">${stepNumber}</span>
                <p>${stepText.replace(/^\d+[\.\)]\s*/, '')}</p>
            `; 
            list.appendChild(stepDiv);
        });
    } else {
        // Se não há texto, manter as instruções originais do PHP
        console.log('No instructions text provided, keeping original content');
    }
};

const updateCategories = (categories) => {
    const container = document.querySelector('.category-tags-container');
    if (!container) return;
    
    container.innerHTML = '';
    if (categories && categories.length > 0) {
        categories.forEach(categoryName => {
            if (categoryName && categoryName.trim()) {
                const tag = document.createElement('span');
                tag.className = 'category-tag';
                tag.textContent = categoryName.trim();
                container.appendChild(tag);
            }
        });
    }
};

const updateMacrosAndTime = (data) => {
    if (!data) return;
    const prep = parseInt(data.prep) || 0; 
    const cook = parseInt(data.cook) || 0;
    const totalTimeElement = document.getElementById('total-time');
    if (totalTimeElement) {
        totalTimeElement.innerHTML = `<i class="far fa-clock"></i> ${prep + cook} min`;
    }
    
    const formatNumber = (val, dec) => parseFloat(String(val).replace(',', '.') || 0).toLocaleString('pt-BR', { 
        minimumFractionDigits: dec, 
        maximumFractionDigits: dec 
    });
    
    document.getElementById('kcal-value').textContent = formatNumber(data.kcal, 0);
    document.getElementById('carbs-value').textContent = `${formatNumber(data.carbs, 1)}g`;
    document.getElementById('protein-value').textContent = `${formatNumber(data.protein, 1)}g`;
    document.getElementById('fat-value').textContent = `${formatNumber(data.fat, 1)}g`;
    
    // Atualizar informações de porção
    if (data.servings || data.serving_size_g) {
        updateServingInfo(data.servings, data.serving_size_g);
    }
};

// Função para atualizar informações de porção
const updateServingInfo = (servings, servingSizeG) => {
    // Atualizar o card de macros com informação de peso
    const servingInfoElement = document.querySelector('.recipe-serving-info');
    if (servingInfoElement) {
        let servingInfoText = 'Valores por porção';
        if (servingSizeG && servingSizeG > 0) {
            servingInfoText += ' de ' + Math.round(servingSizeG) + 'g';
        }
        servingInfoElement.textContent = servingInfoText;
    }
    
    // Atualizar o card de tempo e porções
    const servingsItem = document.querySelector('.servings-item');
    if (servingsItem && servings) {
        let servingsText = "Rende " + servings;
        servingsText += (isNaN(servings) || servings == 1) ? ' porção' : ' porções';
        if (servingSizeG && servingSizeG > 0) {
            servingsText += ' de ' + Math.round(servingSizeG) + 'g';
        }
        servingsItem.innerHTML = `<i class="fas fa-utensils"></i> ${servingsText}`;
    }
};

// Listener para mensagens do editor
window.addEventListener('message', function(event) {
    const { type, value } = event.data;
    const actions = { 
        'updateName': updateName, 
        'updateDescription': updateDescription, 
        'updateImage': updateImage, 
        'updateInstructions': updateInstructions, 
        'updateIngredients': updateIngredients, 
        'updateMacrosAndTime': updateMacrosAndTime,
        'updateCategories': updateCategories
    };
    
    if (actions[type]) { 
        actions[type](value); 
    }
});

// =========================================================================
//       EDIÇÃO INLINE - SINCRONIZAÇÃO BIDIRECIONAL COM O EDITOR
// =========================================================================

document.addEventListener('DOMContentLoaded', function() {
    // Sincronizar edições inline do preview com o editor pai
    function syncToParent(type, value) {
        if (window.parent && window.parent.postMessage) {
            window.parent.postMessage({ type: 'previewChanged', field: type, value: value }, '*');
        }
    }
    
    // Nome editável
    const nameElement = document.getElementById('recipe-name');
    if (nameElement) {
        nameElement.addEventListener('input', function() {
            syncToParent('name', this.textContent.trim());
        });
        nameElement.addEventListener('blur', function() {
            if (!this.textContent.trim()) {
                this.textContent = this.dataset.placeholder || 'Nome da Receita';
            }
        });
    }
    
    // Descrição editável
    const descElement = document.getElementById('recipe-description');
    if (descElement) {
        descElement.addEventListener('input', function() {
            syncToParent('description', this.textContent.trim());
        });
        descElement.addEventListener('blur', function() {
            if (!this.textContent.trim()) {
                this.textContent = this.dataset.placeholder || 'Descreva brevemente a receita...';
            }
        });
    }
    
    // Instruções editáveis
    const instructionsElement = document.getElementById('instructions-list');
    if (instructionsElement) {
        instructionsElement.addEventListener('input', function() {
            // Extrair texto puro de todas as instruções
            const steps = Array.from(this.querySelectorAll('.instruction-step p')).map(p => p.textContent.trim()).filter(t => t);
            syncToParent('instructions', steps.join('\n'));
        });
    }
    
    // Estilos para contenteditable
    const style = document.createElement('style');
    style.textContent = `
        [contenteditable="true"] {
            outline: none;
            transition: all 0.2s ease;
        }
        [contenteditable="true"]:focus {
            background: rgba(255, 107, 0, 0.05) !important;
            border-radius: 8px;
            padding: 0.5rem;
            margin: -0.5rem;
        }
        [contenteditable="true"]:empty:before {
            content: attr(data-placeholder);
            color: var(--text-secondary);
            opacity: 0.5;
        }
    `;
    document.head.appendChild(style);
    
    // Configurar click listener para rolar até o card de categorias
    const categoryContainer = document.getElementById('category-tags-clickable');
    if (categoryContainer) {
        categoryContainer.addEventListener('click', function() {
            // Enviar mensagem para o parent para rolar até o card de categorias
            if (window.parent && window.parent.postMessage) {
                window.parent.postMessage({ 
                    type: 'scrollToCategories' 
                }, '*');
            }
        });
    }
});

// Debug: Verificar se os números estão sendo renderizados
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        const stepNumbers = document.querySelectorAll('.step-number');
        console.log('Step numbers found:', stepNumbers.length);
        stepNumbers.forEach((step, index) => {
            console.log(`Step ${index + 1}:`, step.textContent, 'Visible:', step.offsetWidth > 0);
        });
    }, 100);
});

</script>

</body>
</html>