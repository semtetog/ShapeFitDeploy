<?php
// admin/food_classification.php - Sistema ULTRA SIMPLES de classificação

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'food_classification';
$page_title = 'Classificar Alimentos';

// Categorias SIMPLIFICADAS
$categories = [
    'líquido' => ['name' => 'Líquido', 'color' => '#3B82F6', 'icon' => '💧'],
    'semi_liquido' => ['name' => 'Semi-líquido', 'color' => '#8B5CF6', 'icon' => '🥄'],
    'granular' => ['name' => 'Granular', 'color' => '#F59E0B', 'icon' => '🌾'],
    'unidade_inteira' => ['name' => 'Unidade Inteira', 'color' => '#10B981', 'icon' => '🍎'],
    'fatias_pedacos' => ['name' => 'Fatias/Pedaços', 'color' => '#EF4444', 'icon' => '🧀'],
    'corte_porcao' => ['name' => 'Corte/Porção', 'color' => '#F97316', 'icon' => '🥩'],
    'colher_cremoso' => ['name' => 'Colher Cremoso', 'color' => '#EC4899', 'icon' => '🍦'],
    'condimentos' => ['name' => 'Condimentos', 'color' => '#6B7280', 'icon' => '🧂'],
    'oleos_gorduras' => ['name' => 'Óleos/Gorduras', 'color' => '#FCD34D', 'icon' => '🫒'],
    'preparacoes_compostas' => ['name' => 'Preparações Compostas', 'color' => '#8B5A2B', 'icon' => '🍽️']
];

// Buscar alimentos
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Adicionar filtros de intervalo de página no topo
$page_start = isset($_GET['page_start']) ? max(1, (int)$_GET['page_start']) : 1;
$page_end = isset($_GET['page_end']) ? max($page_start, (int)$_GET['page_end']) : $total_pages;

// Restringir o offset e a quantidade conforme o intervalo
if ($page_start > $page_end) $page_end = $page_start;
$effective_offset = ($page_start - 1) * $per_page;
$effective_limit = ($page_end - $page_start + 1) * $per_page;

$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "name_pt LIKE ?";
    $params[] = "%{$search}%";
    $param_types .= 's';
}

if (!empty($category_filter)) {
    $where_conditions[] = "food_type = ?";
    $params[] = $category_filter;
    $param_types .= 's';
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Contar total
$count_sql = "SELECT COUNT(*) as total FROM sf_food_items {$where_sql}";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_items = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $per_page);

// Buscar alimentos com suas classificações múltiplas
$sql = "SELECT 
            sfi.id, 
            sfi.name_pt, 
            sfi.food_type, 
            sfi.energy_kcal_100g, 
            sfi.protein_g_100g, 
            sfi.carbohydrate_g_100g, 
            sfi.fat_g_100g,
            GROUP_CONCAT(sfc.category_type ORDER BY sfc.is_primary DESC, sfc.category_type ASC) as categories
        FROM sf_food_items sfi
        LEFT JOIN sf_food_categories sfc ON sfi.id = sfc.food_id
        {$where_sql} 
        GROUP BY sfi.id
        ORDER BY sfi.name_pt 
        LIMIT ? OFFSET ?"; // <-- ONLY THIS LIMIT/OFFSET IS AFFECTED BY INTERVALO
$params[] = $effective_limit;
$params[] = $effective_offset;
$param_types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$foods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Buscar estatísticas
$stats_sql = "SELECT food_type, COUNT(*) as count FROM sf_food_items GROUP BY food_type";
$stats_result = $conn->query($stats_sql);
$stats = [];
while ($row = $stats_result->fetch_assoc()) {
    $stats[$row['food_type']] = $row['count'];
}

// Contar alimentos realmente classificados (que têm pelo menos uma categoria)
$classified_sql = "SELECT COUNT(DISTINCT sfi.id) as classified_count 
                   FROM sf_food_items sfi 
                   INNER JOIN sf_food_categories sfc ON sfi.id = sfc.food_id";
$classified_result = $conn->query($classified_sql);
$classified_count = $classified_result->fetch_assoc()['classified_count'];

include 'includes/header.php';
?>

<style>
/* ========================================================================= */
/*          SISTEMA ULTRA SIMPLES - DESIGN MINIMALISTA                      */
/* ========================================================================= */

.classification-container {
    max-width: 1400px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 25px;
    min-height: calc(100vh - 200px);
}

/* ===== LEGENDAS SIMPLES (LADO ESQUERDO) ===== */
.legends-panel {
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
    height: fit-content;
    position: sticky;
    top: 20px;
}

.legends-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--primary-text-color);
    margin-bottom: 15px;
    text-align: center;
}

.legends-subtitle {
    font-size: 0.85rem;
    color: var(--secondary-text-color);
    text-align: center;
    margin-bottom: 20px;
}

.category-legend {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.category-legend:hover {
    border-color: var(--category-color);
    background: var(--category-bg);
}

.category-legend.selected {
    border-color: var(--category-color);
    background: var(--category-bg);
}

.category-legend-header {
    display: flex;
    align-items: center;
    gap: 8px;
}

.category-legend-icon {
    font-size: 1rem;
}

.category-legend-name {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--category-color);
    margin: 0;
}

.category-legend-examples {
    margin-top: 8px;
    margin-bottom: 8px;
}

.examples-label {
    font-size: 0.7rem;
    color: var(--secondary-text-color);
    margin-bottom: 4px;
    font-weight: 500;
}

.examples-list {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
}

.example-tag {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.65rem;
    font-weight: 500;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.category-legend-units {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.units-label {
    font-size: 0.7rem;
    color: var(--secondary-text-color);
    margin-bottom: 4px;
    font-weight: 500;
}

.units-list {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
}

.unit-tag {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.65rem;
    font-weight: 500;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

/* ===== CLASSIFICADOR (LADO DIREITO) ===== */
.classifier-panel {
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
}

.classifier-header {
    text-align: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}

.classifier-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-text-color);
    margin-bottom: 5px;
}

.classifier-subtitle {
    font-size: 0.9rem;
    color: var(--secondary-text-color);
}

/* Estatísticas simples */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.stat-item {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 12px;
    text-align: center;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--accent-orange);
    margin-bottom: 2px;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--secondary-text-color);
    font-weight: 500;
}

/* Filtros simples */
.filters-section {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 20px;
}

.filters-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--primary-text-color);
    margin-bottom: 10px;
}

.filters-grid {
    display: grid;
    grid-template-columns: 2fr 1fr auto auto;
    gap: 10px;
    align-items: end;
}

.search-input, .category-select {
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 8px 10px;
    color: var(--primary-text-color);
    font-size: 0.85rem;
}

.search-input:focus, .category-select:focus {
    outline: none;
    border-color: var(--accent-orange);
}

.filter-btn, .clear-btn {
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
}

.filter-btn {
    background: var(--accent-orange);
    color: white;
}

.filter-btn:hover {
    background: var(--accent-orange-hover);
}

.clear-btn {
    background: var(--surface-color);
    color: var(--secondary-text-color);
    border: 1px solid var(--border-color);
}

.clear-btn:hover {
    background: var(--border-color);
    color: var(--primary-text-color);
}

/* Ações em lote simples */
.bulk-actions {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 20px;
}

.bulk-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--primary-text-color);
    margin-bottom: 10px;
}

.bulk-controls {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.bulk-select {
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 8px 10px;
    color: var(--primary-text-color);
    font-size: 0.85rem;
    min-width: 180px;
}

.bulk-btn {
    background: var(--accent-orange);
    border: none;
    border-radius: 4px;
    padding: 8px 12px;
    color: white;
    font-weight: 500;
    cursor: pointer;
    font-size: 0.85rem;
}

.bulk-btn:hover {
    background: var(--accent-orange-hover);
}

.bulk-btn:disabled {
    background: var(--border-color);
    cursor: not-allowed;
}

.bulk-checkbox {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--secondary-text-color);
    font-size: 0.85rem;
}

/* Lista de alimentos simples */
.foods-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
    margin-bottom: 25px;
}

.food-card {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 15px;
    transition: all 0.2s ease;
}

.food-card:hover {
    border-color: var(--accent-orange);
}

/* Estados visuais dos cards */
.food-card.classified {
    background: rgba(16, 185, 129, 0.05);
    border-color: #10B981;
    box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.2);
}

.food-card.unclassified {
    background: rgba(239, 68, 68, 0.05);
    border-color: #EF4444;
    box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.2);
}

.food-header {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 12px;
}

.food-checkbox {
    margin-top: 2px;
    transform: scale(1.1);
    accent-color: var(--accent-orange);
}

.food-info {
    flex-grow: 1;
}

.food-name {
    font-size: 1rem;
    font-weight: 600;
    color: var(--primary-text-color);
    margin-bottom: 6px;
    line-height: 1.3;
}

.food-macros {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 6px;
    margin-bottom: 10px;
}

.macro-item {
    background: var(--surface-color);
    border-radius: 3px;
    padding: 4px 6px;
    text-align: center;
}

.macro-label {
    font-size: 0.65rem;
    color: var(--secondary-text-color);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 1px;
}

.macro-value {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--primary-text-color);
}

.food-current-category {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-bottom: 10px;
}

.category-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    white-space: nowrap;
    transition: all 0.2s ease;
}

.category-tag:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.unclassified-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    background: #ef444420;
    color: #ef4444;
    border: 1px solid #ef444440;
}

.food-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 4px;
}

.category-btn {
    background: var(--category-bg);
    border: 1px solid var(--category-color);
    border-radius: 4px;
    padding: 6px 8px;
    color: var(--category-color);
    font-size: 0.7rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    opacity: 0.7;
}

.category-btn:hover {
    background: var(--category-color);
    color: white;
    opacity: 1;
    transform: translateY(-1px);
}

.category-btn.selected {
    background: var(--category-color) !important;
    color: white !important;
    opacity: 1;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    transform: translateY(-1px);
}

.units-btn {
    background: rgba(59, 130, 246, 0.1);
    color: #3B82F6;
    border: 1px solid #3B82F6;
    border-radius: 4px;
    padding: 8px 12px;
    font-size: 0.7rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 2px;
    margin-top: 8px;
    width: 100%;
    justify-content: center;
}

.units-btn:hover:not(.disabled) {
    background: #3B82F6;
    color: white;
    transform: translateY(-1px);
}

.units-btn.disabled {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-secondary);
    border-color: rgba(255, 255, 255, 0.1);
    cursor: not-allowed;
    opacity: 0.6;
}

.units-btn.disabled:hover {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-secondary);
    transform: none;
}

.units-hint {
    display: block;
    font-size: 0.6rem;
    color: var(--text-secondary);
    margin-top: 2px;
    font-style: italic;
}

/* Indicador de classificado */
.classified-indicator {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #10B981;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
    animation: slideIn 0.3s ease;
}

.classified-indicator i {
    font-size: 0.6rem;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Paginação simples */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 6px;
    margin-top: 25px;
}

.pagination a, .pagination span {
    padding: 6px 10px;
    border: 1px solid var(--border-color);
    border-radius: 3px;
    text-decoration: none;
    color: var(--secondary-text-color);
    font-weight: 500;
    font-size: 0.85rem;
    transition: all 0.2s ease;
}

.pagination a:hover {
    background: var(--surface-color);
    color: var(--primary-text-color);
    border-color: var(--accent-orange);
}

.pagination .current {
    background: var(--accent-orange);
    color: white;
    border-color: var(--accent-orange);
}

/* Auto-save indicator */
.auto-save-indicator {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--success-green);
    color: white;
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
    z-index: 1000;
    opacity: 0;
    transform: translateY(-20px);
    transition: all 0.3s ease;
}

.auto-save-indicator.show {
    opacity: 1;
    transform: translateY(0);
}

/* Loading overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.loading-overlay.show {
    opacity: 1;
    visibility: visible;
}

.loading-content {
    text-align: center;
    color: var(--text-primary);
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(255, 255, 255, 0.2);
    border-top: 3px solid var(--accent-orange);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 15px;
}

.loading-text {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.loading-subtext {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 1200px) {
    .classification-container {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .legends-panel {
        position: static;
        order: 2;
    }
    
    .classifier-panel {
        order: 1;
    }
}

@media (max-width: 768px) {
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .foods-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .bulk-controls {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<div class="classification-container">
    <!-- LEGENDAS SIMPLES (LADO ESQUERDO) -->
    <div class="legends-panel">
        <h2 class="legends-title">📋 Categorias</h2>
        <p class="legends-subtitle">Clique para classificar</p>
        
        <?php 
        // Definir unidades e exemplos para cada categoria
        $category_units = [
            'líquido' => ['ml', 'l', 'cs', 'cc', 'xc'],
            'semi_liquido' => ['g', 'ml', 'cs', 'cc', 'xc'],
            'granular' => ['g', 'kg', 'cs', 'cc', 'xc'],
            'unidade_inteira' => ['un', 'g', 'kg'],
            'fatias_pedacos' => ['fat', 'g', 'kg'],
            'corte_porcao' => ['g', 'kg', 'un'],
            'colher_cremoso' => ['cs', 'cc', 'g'],
            'condimentos' => ['cc', 'cs', 'g'],
            'oleos_gorduras' => ['cs', 'cc', 'ml', 'l'],
            'preparacoes_compostas' => ['g', 'kg', 'un']
        ];
        
        $category_examples = [
            'líquido' => ['Água', 'Suco', 'Leite', 'Refrigerante', 'Café'],
            'semi_liquido' => ['Iogurte', 'Pudim', 'Mingau', 'Vitamina', 'Abacate'],
            'granular' => ['Arroz', 'Feijão', 'Açúcar', 'Sal', 'Farinha'],
            'unidade_inteira' => ['Maçã', 'Banana', 'Ovo', 'Pão', 'Biscoito'],
            'fatias_pedacos' => ['Queijo', 'Presunto', 'Tomate', 'Cenoura', 'Batata'],
            'corte_porcao' => ['Carne', 'Frango', 'Peixe', 'Lasanha', 'Pizza'],
            'colher_cremoso' => ['Manteiga', 'Cream Cheese', 'Doce de Leite', 'Maionese'],
            'condimentos' => ['Sal', 'Pimenta', 'Açúcar', 'Canela', 'Orégano'],
            'oleos_gorduras' => ['Azeite', 'Óleo', 'Manteiga', 'Margarina', 'Banha'],
            'preparacoes_compostas' => ['Lasanha', 'Pizza', 'Bolo', 'Torta', 'Sopa']
        ];
        
        // Nomes das unidades
        $unit_names = [
            'ml' => 'Mililitro',
            'l' => 'Litro', 
            'cs' => 'Colher de Sopa',
            'cc' => 'Colher de Chá',
            'xc' => 'Xícara',
            'g' => 'Grama',
            'kg' => 'Quilograma',
            'un' => 'Unidade',
            'fat' => 'Fatia'
        ];
        
        foreach ($categories as $key => $cat): 
            $units = $category_units[$key] ?? [];
            $examples = $category_examples[$key] ?? [];
        ?>
            <div class="category-legend" data-category="<?= $key ?>" style="--category-color: <?= $cat['color'] ?>; --category-bg: <?= $cat['color'] ?>20;">
                <div class="category-legend-header">
                    <span class="category-legend-icon"><?= $cat['icon'] ?></span>
                    <h3 class="category-legend-name"><?= $cat['name'] ?></h3>
                </div>
                
                <div class="category-legend-examples">
                    <div class="examples-label">Exemplos:</div>
                    <div class="examples-list">
                        <?php foreach ($examples as $example): ?>
                            <span class="example-tag"><?= $example ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="category-legend-units">
                    <div class="units-label">Unidades:</div>
                    <div class="units-list">
                        <?php foreach ($units as $unit): ?>
                            <span class="unit-tag"><?= $unit_names[$unit] ?? $unit ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- CLASSIFICADOR (LADO DIREITO) -->
    <div class="classifier-panel">
        <div class="classifier-header">
            <h1 class="classifier-title">Classificar Alimentos</h1>
            <p class="classifier-subtitle">Selecione uma categoria para cada alimento</p>
        </div>

        <!-- Estatísticas -->
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-number"><?= $total_items ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="classified-count"><?= $classified_count ?></div>
                <div class="stat-label">Classificados</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="remaining-count"><?= $total_items - $classified_count ?></div>
                <div class="stat-label">Restantes</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="session-count">0</div>
                <div class="stat-label">Nesta Sessão</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
            <h3 class="filters-title">🔍 Buscar</h3>
            <form method="GET" class="filters-grid">
                <!-- Adicionar campos página inicial e final -->
                <input type="number" min="1" max="<?php echo htmlspecialchars($total_pages) ?>" name="page_start" value="<?php echo htmlspecialchars($page_start) ?>" placeholder="Página inicial" style="width: 110px;">
                <input type="number" min="<?php echo htmlspecialchars($page_start) ?>" max="<?php echo htmlspecialchars($total_pages) ?>" name="page_end" value="<?php echo htmlspecialchars($page_end) ?>" placeholder="Página final" style="width: 110px;">
                <!-- Demais filtros abaixo -->
                <input type="text" class="search-input" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nome do alimento...">
                <select class="category-select" name="category">
                    <option value="">Todas</option>
                    <?php foreach ($categories as $key => $cat): ?>
                        <option value="<?= $key ?>" <?= $category_filter === $key ? 'selected' : '' ?>>
                            <?= $cat['icon'] ?> <?= $cat['name'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="filter-btn">Buscar</button>
                <a href="food_classification.php" class="clear-btn">Limpar</a>
            </form>
            <div style='font-size:0.97rem;margin-bottom:6px;color:#737373'>Exibindo páginas <b><?php echo $page_start ?></b> até <b><?php echo $page_end ?></b> de <b><?php echo $total_pages ?></b>. Cada estagiária pega um bloco e ninguém se sobrepõe!</div>
        </div>

        <!-- Ações em Lote -->
        <div class="bulk-actions">
            <h3 class="bulk-title">⚡ Ações em Lote</h3>
            <div class="bulk-controls">
                <select class="bulk-select" id="bulk-category">
                    <option value="">Selecione uma categoria</option>
                    <?php foreach ($categories as $key => $cat): ?>
                        <option value="<?= $key ?>"><?= $cat['icon'] ?> <?= $cat['name'] ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="bulk-btn" onclick="applyBulkClassification()" id="bulk-btn" disabled>
                    Aplicar aos Selecionados
                </button>
                <label class="bulk-checkbox">
                    <input type="checkbox" id="select-all" style="transform: scale(1.1); accent-color: var(--accent-orange);">
                    Selecionar Todos
                </label>
            </div>
        </div>

        <!-- Lista de Alimentos -->
        <div class="foods-grid" id="foods-list">
            <?php foreach ($foods as $food): ?>
                <div class="food-card" data-food-id="<?= $food['id'] ?>">
                    <div class="food-header">
                        <input class="food-checkbox" type="checkbox" value="<?= $food['id'] ?>">
                        <div class="food-info">
                            <div class="food-name">
                                <?= htmlspecialchars($food['name_pt']) ?>
                                <?php if (!empty($food['brand']) && $food['brand'] !== 'TACO'): ?>
                                    <br><small style="color: #6b7280; font-size: 0.85em;">🏷️ <?= htmlspecialchars($food['brand']) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="food-macros">
                                <div class="macro-item">
                                    <div class="macro-label">Calorias</div>
                                    <div class="macro-value"><?= $food['energy_kcal_100g'] ?>kcal</div>
                                </div>
                                <div class="macro-item">
                                    <div class="macro-label">Proteína</div>
                                    <div class="macro-value"><?= $food['protein_g_100g'] ?>g</div>
                                </div>
                                <div class="macro-item">
                                    <div class="macro-label">Carboidratos</div>
                                    <div class="macro-value"><?= $food['carbohydrate_g_100g'] ?>g</div>
                                </div>
                                <div class="macro-item">
                                    <div class="macro-label">Gorduras</div>
                                    <div class="macro-value"><?= $food['fat_g_100g'] ?>g</div>
                                </div>
                            </div>
                            <div class="food-current-category" id="category-display-<?= $food['id'] ?>" style="background: #e5e7eb20; color: #6b7280;">
                                <?php 
                                if (!empty($food['categories'])) {
                                    $food_categories = explode(',', $food['categories']);
                                    $category_names = [];
                                    foreach ($food_categories as $cat) {
                                        $cat = trim($cat);
                                        if (isset($categories[$cat])) {
                                            $category_names[] = $categories[$cat]['name'];
                                        }
                                    }
                                    echo implode(', ', $category_names);
                                } else {
                                    echo 'Não classificado';
                                }
                                ?>
                            </div>
                            <div class="food-actions">
                                <?php foreach ($categories as $key => $cat): ?>
                                    <button class="category-btn" 
                                            data-food-id="<?= $food['id'] ?>"
                                            data-category="<?= $key ?>"
                                            style="--category-color: <?= $cat['color'] ?>; --category-bg: <?= $cat['color'] ?>20;">
                                        <?= $cat['icon'] ?> <?= $cat['name'] ?>
                                    </button>
                                <?php endforeach; ?>
                                
                                <!-- Botão para editar unidades -->
                                <button class="units-btn" 
                                        data-food-id="<?= $food['id'] ?>"
                                        onclick="openUnitsEditor(<?= $food['id'] ?>, '<?= htmlspecialchars($food['name_pt']) ?>', getFoodCategories(<?= $food['id'] ?>))">
                                    <i class="fas fa-ruler"></i>
                                    <span>Unidades</span>
                                    <small class="units-hint">Salve a classificação primeiro</small>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Paginação -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>">« Anterior</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>">Próximo »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Auto-save indicator -->
<div class="auto-save-indicator" id="auto-save-indicator">
    💾 Salvo!
</div>

<!-- Loading overlay -->
<div class="loading-overlay" id="loading-overlay">
    <div class="loading-content">
        <div class="loading-spinner"></div>
        <div class="loading-text">Salvando...</div>
        <div class="loading-subtext">Aguarde um momento</div>
    </div>
</div>

<script>
// Definir categorias globalmente
window.categories = <?= json_encode($categories) ?>;

let classifications = {}; // {foodId: [category1, category2, ...]}
let sessionCount = 0;
let classificationsLoaded = false; // Flag para evitar recarregar

// Inicializar classificações vazias
document.addEventListener('DOMContentLoaded', function() {
    // Carregar classificações existentes APENAS uma vez
    loadExistingClassifications();
    
    // Adicionar event listeners para os botões de categoria
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const foodId = this.dataset.foodId;
            const category = this.dataset.category;
            toggleCategory(foodId, category);
        });
    });
});

// Carregar classificações existentes dos alimentos
function loadExistingClassifications() {
    if (classificationsLoaded) {
        console.log('⚠️ Classificações já carregadas, pulando...');
        return;
    }
    
    console.log('🔄 Carregando classificações existentes...');
    
    document.querySelectorAll('.food-card').forEach(card => {
        const foodId = card.dataset.foodId;
        const categoryDisplay = card.querySelector('.food-current-category');
        const categoryText = categoryDisplay.textContent.trim();
        
        console.log(`📋 Alimento ${foodId}: "${categoryText}"`);
        
        // Só carregar se não for "Não classificado" e se o texto não estiver vazio
        if (categoryText !== 'Não classificado' && categoryText !== '') {
            // Extrair categorias do texto exibido
            const categoryNames = categoryText.split(', ').map(name => name.trim());
            const categoryKeys = [];
            
            // Converter nomes para chaves
            Object.keys(window.categories).forEach(key => {
                if (categoryNames.includes(window.categories[key].name)) {
                    categoryKeys.push(key);
                }
            });
            
            // Só adicionar se encontrou categorias válidas
            if (categoryKeys.length > 0) {
                classifications[foodId] = categoryKeys;
                console.log(`✅ Carregado ${foodId}:`, categoryKeys);
                updateFoodVisual(foodId);
            }
        } else {
            // Se está "Não classificado", garantir que não está no objeto classifications
            delete classifications[foodId];
            console.log(`❌ Não classificado ${foodId}`);
        }
    });
    
    classificationsLoaded = true;
    console.log('📊 Classificações finais:', classifications);
}

// Alternar categoria (adicionar/remover) - INSTANTÂNEO
function toggleCategory(foodId, category) {
    console.log('🔄 Toggle category:', foodId, category);
    
    if (!classifications[foodId]) {
        classifications[foodId] = [];
    }
    
    const foodCategories = classifications[foodId];
    const categoryIndex = foodCategories.indexOf(category);
    
    if (categoryIndex > -1) {
        // Remover categoria
        foodCategories.splice(categoryIndex, 1);
        console.log('❌ Removido:', category, 'Categorias restantes:', foodCategories);
    } else {
        // Adicionar categoria
        foodCategories.push(category);
        console.log('✅ Adicionado:', category, 'Categorias totais:', foodCategories);
    }
    
    // Se não há mais categorias, remover o alimento completamente do objeto
    if (foodCategories.length === 0) {
        delete classifications[foodId];
        console.log('🗑️ Removido alimento do objeto classifications');
        
        // Salvar o estado "Não classificado" no banco
        saveDeclassification(foodId);
    }
    
    // Atualizar visual IMEDIATAMENTE
    updateFoodVisual(foodId);
    
    // Salvar IMEDIATAMENTE (sem delay)
    saveClassificationsInstant();
}

// Atualizar visual do alimento
function updateFoodVisual(foodId) {
    console.log(`🎨 Atualizando visual para ${foodId}`);
    
    const foodCard = document.querySelector(`[data-food-id="${foodId}"]`);
    const categoryDisplay = foodCard.querySelector('.food-current-category');
    const foodCategories = classifications[foodId] || [];
    
    console.log(`📋 Categorias atuais para ${foodId}:`, foodCategories);
    
    // Atualizar botões
    foodCard.querySelectorAll('.category-btn').forEach(btn => {
        const btnCategory = btn.dataset.category;
        const isSelected = foodCategories.includes(btnCategory);
        
        if (isSelected) {
            btn.classList.add('selected');
            console.log(`✅ Botão ${btnCategory} selecionado`);
        } else {
            btn.classList.remove('selected');
            console.log(`❌ Botão ${btnCategory} desmarcado`);
        }
    });
    
    // Atualizar display de categorias e estado do card
    if (foodCategories.length === 0 || !classifications[foodId]) {
        // Estado não classificado
        categoryDisplay.innerHTML = '<span class="unclassified-tag">Não classificado</span>';
        
        // Aplicar classe CSS para card não classificado
        foodCard.classList.remove('classified');
        foodCard.classList.add('unclassified');
        
        // Desabilitar botão de unidades
        const unitsBtn = foodCard.querySelector('.units-btn');
        if (unitsBtn) {
            unitsBtn.classList.add('disabled');
            unitsBtn.disabled = true;
            const hint = unitsBtn.querySelector('.units-hint');
            if (hint) {
                hint.textContent = 'Classifique primeiro';
            }
        }
        
        // Remover indicador visual de classificado
        const indicator = foodCard.querySelector('.classified-indicator');
        if (indicator) {
            indicator.remove();
        }
    } else {
        // Estado classificado - TAGS COLORIDAS
        const tagsHtml = foodCategories.map(cat => {
            const categoryInfo = window.categories[cat];
            return `<span class="category-tag" style="background: ${categoryInfo.color}20; color: ${categoryInfo.color}; border: 1px solid ${categoryInfo.color}40;">${categoryInfo.icon} ${categoryInfo.name}</span>`;
        }).join('');
        
        categoryDisplay.innerHTML = tagsHtml;
        
        // Remover estilos de background do container
        categoryDisplay.style.background = '';
        categoryDisplay.style.color = '';
        categoryDisplay.style.borderColor = '';
        
        // Aplicar classe CSS para card classificado
        foodCard.classList.remove('unclassified');
        foodCard.classList.add('classified');
        
        // Desabilitar botão de unidades até salvar
        const unitsBtn = foodCard.querySelector('.units-btn');
        if (unitsBtn) {
            unitsBtn.classList.add('disabled');
            unitsBtn.disabled = true;
            const hint = unitsBtn.querySelector('.units-hint');
            if (hint) {
                hint.textContent = 'Aguarde salvamento...';
            }
        }
        
        // Adicionar indicador visual de classificado
        if (!foodCard.querySelector('.classified-indicator')) {
            const indicator = document.createElement('div');
            indicator.className = 'classified-indicator';
            indicator.innerHTML = '<i class="fas fa-check-circle"></i> Classificado';
            foodCard.querySelector('.food-header').appendChild(indicator);
        }
    }
}

// Classificar diretamente (usado por ações em lote)
function classifyFood(foodId, category) {
    console.log('⚡ classifyFood (bulk):', foodId, category);
    // Define somente esta categoria para o alimento no objeto em memória
    classifications[foodId] = [category];
    // Atualiza o visual imediatamente
    updateFoodVisual(foodId);
    // Salva instantaneamente
    saveClassificationsInstant();
}

// Aplicar classificação em lote
function applyBulkClassification() {
    const selectedCategory = document.getElementById('bulk-category').value;
    if (!selectedCategory) {
        alert('Selecione uma categoria!');
        return;
    }
    
    const selectedFoods = document.querySelectorAll('.food-checkbox:checked');
    if (selectedFoods.length === 0) {
        alert('Selecione pelo menos um alimento!');
        return;
    }
    
    selectedFoods.forEach(checkbox => {
        const foodId = parseInt(checkbox.value);
        classifyFood(foodId, selectedCategory);
    });
    
    alert(`${selectedFoods.length} alimentos classificados!`);
}

// Selecionar todos
document.getElementById('select-all').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.food-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateBulkButton();
});

// Atualizar botão de lote
function updateBulkButton() {
    const selectedCount = document.querySelectorAll('.food-checkbox:checked').length;
    const bulkBtn = document.getElementById('bulk-btn');
    const bulkCategory = document.getElementById('bulk-category');
    
    bulkBtn.disabled = selectedCount === 0 || !bulkCategory.value;
    bulkBtn.textContent = selectedCount > 0 ? `Aplicar aos ${selectedCount} Selecionados` : 'Aplicar aos Selecionados';
}

// Event listeners
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('food-checkbox')) {
        updateBulkButton();
    }
});

document.getElementById('bulk-category').addEventListener('change', updateBulkButton);

// Salvar desclassificação (quando remove todas as categorias)
function saveDeclassification(foodId) {
    console.log('🗑️ Salvando desclassificação para:', foodId);
    
    const formData = new FormData();
    formData.append('action', 'declassify_food');
    formData.append('food_id', foodId);
    
    fetch('ajax_food_classification.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ Desclassificação salva com sucesso');
            
            // Atualizar contadores em tempo real
            updateCountersAfterDeclassification();
            
        } else {
            console.error('❌ Erro ao salvar desclassificação:', data);
        }
    })
    .catch(error => {
        console.error('❌ Erro na requisição de desclassificação:', error);
    });
}

// Atualizar contadores após desclassificação
function updateCountersAfterDeclassification() {
    const totalCount = parseInt(document.querySelector('.stat-item:first-child .stat-number').textContent);
    const classifiedCount = document.querySelector('.stat-item:nth-child(2) .stat-number');
    const remainingCount = document.querySelector('.stat-item:nth-child(3) .stat-number');
    
    // Decrementar classificados
    const currentClassified = parseInt(classifiedCount.textContent);
    const newClassified = Math.max(0, currentClassified - 1);
    classifiedCount.textContent = newClassified;
    
    // Incrementar restantes
    const currentRemaining = parseInt(remainingCount.textContent);
    const newRemaining = Math.min(totalCount, currentRemaining + 1);
    remainingCount.textContent = newRemaining;
    
    console.log(`📊 Contadores atualizados: ${newClassified} classificados, ${newRemaining} restantes`);
}

// Salvar classificações INSTANTÂNEO (sem loading)
function saveClassificationsInstant() {
    if (Object.keys(classifications).length === 0) {
        return;
    }
    
    console.log('💾 Salvando classificações:', classifications);
    
    const formData = new FormData();
    formData.append('action', 'save_classifications');
    formData.append('classifications', JSON.stringify(classifications));
    
    fetch('ajax_food_classification.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ Classificações salvas instantaneamente');
            
            // Atualizar contadores em tempo real
            const savedCount = Object.keys(classifications).length;
            const totalCount = parseInt(document.querySelector('.stat-item:first-child .stat-number').textContent);
            const classifiedCount = document.querySelector('.stat-item:nth-child(2) .stat-number');
            const remainingCount = document.querySelector('.stat-item:nth-child(3) .stat-number');
            
            if (classifiedCount) {
                classifiedCount.textContent = savedCount;
            }
            if (remainingCount) {
                remainingCount.textContent = totalCount - savedCount;
            }
            
            // Habilitar botões de unidades para itens classificados
            document.querySelectorAll('.food-card.classified .units-btn').forEach(btn => {
                btn.classList.remove('disabled');
                btn.disabled = false;
                const hint = btn.querySelector('.units-hint');
                if (hint) {
                    hint.textContent = 'Editar unidades';
                }
            });
            
            // NÃO limpar classificações - manter para permitir múltiplas seleções
            console.log('📊 Classificações mantidas para múltiplas seleções:', classifications);
            
        } else {
            console.error('❌ Erro ao salvar:', data);
        }
    })
    .catch(error => {
        console.error('❌ Erro na requisição:', error);
    });
}

// Salvar classificações
function saveClassifications() {
    showLoading();
    
    // Coletar todos os IDs de alimentos da página
    const allFoodIds = Array.from(document.querySelectorAll('.food-card')).map(card => card.dataset.foodId);
    
    // Debug logs
    console.log('Classificações antes de enviar:', classifications);
    console.log('IDs de alimentos:', allFoodIds);
    console.log('Classificações vazias:', Object.keys(classifications).length === 0);
    
    const formData = new FormData();
    formData.append('action', 'save_classifications');
    formData.append('classifications', JSON.stringify(classifications));
    formData.append('all_food_ids', JSON.stringify(allFoodIds));
    
    fetch('ajax_food_classification.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return response.text().then(text => {
            console.log('Raw response:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response text:', text);
                throw new Error('Invalid JSON response: ' + text.substring(0, 100));
            }
        });
    })
    .then(data => {
        hideLoading();
        if (data.success) {
            showAutoSaveIndicator();
            
            // Habilitar botões de unidades para alimentos classificados
            Object.keys(classifications).forEach(foodId => {
                const foodCard = document.querySelector(`[data-food-id="${foodId}"]`);
                if (foodCard) {
                    const unitsBtn = foodCard.querySelector('.units-btn');
                    if (unitsBtn) {
                        unitsBtn.classList.remove('disabled');
                        unitsBtn.disabled = false;
                        const hint = unitsBtn.querySelector('.units-hint');
                        if (hint) {
                            hint.textContent = 'Clique para editar unidades';
                        }
                    }
                }
            });
            
            // Atualizar contadores ANTES de limpar classifications
            const classifiedCount = document.getElementById('classified-count');
            const remainingCount = document.getElementById('remaining-count');
            const currentClassified = parseInt(classifiedCount.textContent);
            const currentRemaining = parseInt(remainingCount.textContent);
            const savedCount = Object.keys(classifications).length;
            
            classifiedCount.textContent = currentClassified + savedCount;
            remainingCount.textContent = currentRemaining - savedCount;
            
            classifications = {};
            sessionCount = 0;
            document.getElementById('session-count').textContent = '0';
        } else {
            alert('❌ Erro ao salvar: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Erro:', error);
        alert('❌ Erro ao salvar: ' + error.message);
    });
}

// Mostrar indicador de auto-save
function showAutoSaveIndicator() {
    const indicator = document.getElementById('auto-save-indicator');
    indicator.classList.add('show');
    setTimeout(() => {
        indicator.classList.remove('show');
    }, 2000);
}

// Mostrar/esconder loading
function showLoading() {
    document.getElementById('loading-overlay').classList.add('show');
}

function hideLoading() {
    document.getElementById('loading-overlay').classList.remove('show');
}

// Atalhos de teclado
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        saveClassifications();
    }
});

// Auto-save a cada 30 segundos
setInterval(() => {
    if (Object.keys(classifications).length > 0) {
        saveClassifications();
    }
}, 30000);

// Função para obter categorias de um alimento
function getFoodCategories(foodId) {
    const foodCard = document.querySelector(`[data-food-id="${foodId}"]`);
    if (!foodCard) return [];
    
    const selectedButtons = foodCard.querySelectorAll('.category-btn.selected');
    return Array.from(selectedButtons).map(btn => btn.dataset.category);
}
</script>

<?php require_once __DIR__ . '/includes/units_editor.php'; ?>
<?php include 'includes/footer.php'; ?>