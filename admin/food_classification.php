<?php
// admin/food_classification.php - Sistema ULTRA SIMPLES de classificação

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'food_classification';
$page_title = 'Alimentos';

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
$per_page = 20;
$offset = ($page - 1) * $per_page;

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

// Buscar alimentos
$sql = "SELECT 
    sfi.id, sfi.name_pt, sfi.food_type, sfi.energy_kcal_100g, sfi.protein_g_100g, sfi.carbohydrate_g_100g, sfi.fat_g_100g, sfi.brand,
    GROUP_CONCAT(sfc.category_type ORDER BY sfc.is_primary DESC, sfc.category_type ASC) as categories
    FROM sf_food_items sfi
    LEFT JOIN sf_food_categories sfc ON sfi.id = sfc.food_id
    $where_sql
    GROUP BY sfi.id
    ORDER BY sfi.name_pt
    LIMIT ? OFFSET ?";
$param_types .= 'ii';
array_push($params, $per_page, $offset);
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
    max-width: 1600px;
    margin: 0 auto;
    padding: 1.5rem 2rem;
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 2rem;
    min-height: calc(100vh - 200px);
}

/* ===== LEGENDAS SIMPLES (LADO ESQUERDO) ===== */
.legends-panel {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 1.5rem;
    height: fit-content;
    position: sticky;
    top: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
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
    background: transparent;
    border: none;
    border-radius: 0;
    padding: 0;
}

.classifier-header {
    text-align: left;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.classifier-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.classifier-title i {
    color: var(--accent-orange);
}

.classifier-subtitle {
    font-size: 0.95rem;
    color: var(--text-secondary);
    margin: 0;
}

/* Estatísticas simples */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-item {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
}

.stat-item:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
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
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
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
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
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
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
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
            <h1 class="classifier-title"><i class="fas fa-apple-alt"></i> Alimentos</h1>
            <p class="classifier-subtitle">Gerencie e classifique todos os alimentos do sistema</p>
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
                <input type="text" class="search-input" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nome do alimento...">
                <select class="category-select" name="category">
                    <option value="">Todas as categorias</option>
                    <?php foreach ($categories as $key => $cat): ?>
                        <option value="<?= $key ?>" <?= $category_filter === $key ? 'selected' : '' ?>>
                            <?= $cat['icon'] ?> <?= $cat['name'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="filter-btn">Buscar</button>
                <a href="food_classification.php" class="clear-btn">Limpar</a>
            </form>
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
                            <div class="food-current-category" 
                                 id="category-display-<?= $food['id'] ?>" 
                                 data-categories="<?= htmlspecialchars($food['categories'] ?? '') ?>">
                                <?php 
                                if (!empty($food['categories'])) {
                                    $food_categories = explode(',', $food['categories']);
                                    $tagsHtml = '';
                                    foreach ($food_categories as $cat_key) {
                                        if (isset($categories[$cat_key])) {
                                            $cat_info = $categories[$cat_key];
                                            $tagsHtml .= sprintf(
                                                '<span class="category-tag" style="background: %s20; color: %s; border: 1px solid %s40;">%s %s</span>',
                                                $cat_info['color'], $cat_info['color'], $cat_info['color'], $cat_info['icon'], $cat_info['name']
                                            );
                                        }
                                    }
                                    echo $tagsHtml;
                                } else {
                                    echo '<span class="unclassified-tag">Não classificado</span>';
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
// Definir categorias globalmente para acesso no JS
window.categories = <?= json_encode($categories) ?>;

// Definir unidades padrão por categoria
window.categoryUnits = {
    'líquido': ['ml', 'l', 'cs', 'cc', 'xc'],
    'semi_liquido': ['g', 'ml', 'cs', 'cc', 'xc'],
    'granular': ['g', 'kg', 'cs', 'cc'],
    'unidade_inteira': ['un', 'g', 'kg'],
    'fatias_pedacos': ['fat', 'g', 'kg'],
    'corte_porcao': ['g', 'kg', 'un'],
    'colher_cremoso': ['cs', 'cc', 'g'],
    'condimentos': ['cc', 'cs', 'g'],
    'oleos_gorduras': ['cs', 'cc', 'ml', 'l'],
    'preparacoes_compostas': ['g', 'kg', 'un']
};

window.unitNames = {
    'ml': 'Mililitro',
    'l': 'Litro', 
    'cs': 'Colher de Sopa',
    'cc': 'Colher de Chá',
    'xc': 'Xícara',
    'g': 'Grama',
    'kg': 'Quilograma',
    'un': 'Unidade',
    'fat': 'Fatia'
};

let classifications = {}; // Estrutura para { foodId: [category1, category2] }
let sessionClassifiedCount = 0;

document.addEventListener('DOMContentLoaded', function() {
    // 1. Carregar o estado inicial das classificações a partir do HTML
    document.querySelectorAll('.food-card').forEach(card => {
        const foodId = card.dataset.foodId;
        const existingCategories = card.querySelector('.food-current-category').dataset.categories;
        if (existingCategories) {
            classifications[foodId] = existingCategories.split(',');
        } else {
            classifications[foodId] = [];
        }
        updateFoodVisual(foodId); // Atualiza o visual para refletir o estado carregado
    });

    // 2. Adicionar listener de clique para TODOS os botões de categoria
    document.getElementById('foods-list').addEventListener('click', function(e) {
        if (e.target.classList.contains('category-btn')) {
            const foodId = e.target.dataset.foodId;
            const category = e.target.dataset.category;
            toggleCategory(foodId, category);
        }
    });
    
    // ... (listeners de ações em lote, se mantidos) ...
});

// Lida com a lógica de adicionar/remover uma categoria
function toggleCategory(foodId, category) {
    const currentCategories = classifications[foodId] || [];
    const index = currentCategories.indexOf(category);

    if (index > -1) {
        // Categoria já existe, então remove
        currentCategories.splice(index, 1);
    } else {
        // Categoria não existe, então adiciona
        currentCategories.push(category);
    }
    classifications[foodId] = currentCategories;
    
    updateFoodVisual(foodId);
    saveClassification(foodId); // Salva a alteração imediatamente
}

// Atualiza a aparência de um card de alimento com base nas categorias selecionadas
function updateFoodVisual(foodId) {
    const foodCard = document.querySelector(`.food-card[data-food-id="${foodId}"]`);
    if (!foodCard) return;

    const currentCategories = classifications[foodId] || [];
    
    // Atualiza os botões (adicionando/removendo a classe 'selected')
    foodCard.querySelectorAll('.category-btn').forEach(btn => {
        btn.classList.toggle('selected', currentCategories.includes(btn.dataset.category));
    });

    // Atualiza o display de texto/tags
    const categoryDisplay = foodCard.querySelector('.food-current-category');
    if (currentCategories.length > 0) {
        const tagsHtml = currentCategories.map(catKey => {
            const catInfo = window.categories[catKey];
            if (!catInfo) return ''; // Segurança caso a categoria não exista
            return `<span class="category-tag" style="background: ${catInfo.color}20; color: ${catInfo.color}; border: 1px solid ${catInfo.color}40;">${catInfo.icon} ${catInfo.name}</span>`;
        }).join('');
        categoryDisplay.innerHTML = tagsHtml;
        foodCard.classList.add('classified');
        foodCard.classList.remove('unclassified');
    } else {
        categoryDisplay.innerHTML = '<span class="unclassified-tag">Não classificado</span>';
        foodCard.classList.remove('classified');
        foodCard.classList.add('unclassified');
    }
}

// Salva a classificação para um ÚNICO alimento
function saveClassification(foodId) {
    showLoading();
    const categoriesToSave = classifications[foodId] || [];
    
    const formData = new FormData();
    formData.append('action', 'save_classifications');
    // Envia o payload no formato esperado pelo backend: { "food_id": ["cat1", "cat2"] }
    formData.append('classifications', JSON.stringify({ [foodId]: categoriesToSave }));

    fetch('ajax_food_classification.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAutoSaveIndicator();
            // Atualiza contadores (opcional, pode ser feito de forma mais robusta)
        } else {
            alert('Erro ao salvar: ' + (data.message || 'Erro desconhecido.'));
            // Reverter a mudança visual em caso de erro
            // (implementação mais complexa, omitida por simplicidade agora)
        }
    })
    .catch(error => {
        hideLoading();
        alert('Erro de conexão ao salvar.');
        console.error('Save Error:', error);
    });
}
// Função para obter as categorias de um alimento
function getFoodCategories(foodId) {
    return classifications[foodId] || [];
}

// Funções de loading e indicadores
function showLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.classList.add('show');
    }
}

function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.classList.remove('show');
    }
}

function showAutoSaveIndicator() {
    const indicator = document.getElementById('auto-save-indicator');
    if (indicator) {
        indicator.classList.add('show');
        setTimeout(() => {
            indicator.classList.remove('show');
        }, 2000);
    }
}
</script>

<?php require_once __DIR__ . '/includes/units_editor.php'; ?>
<?php include 'includes/footer.php'; ?>