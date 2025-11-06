<?php
// admin/food_classification.php - Sistema de Classificação de Alimentos

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'food_classification';
$page_title = 'Alimentos';

// Categorias
$categories = [
    'líquido' => ['name' => 'Líquido', 'color' => '#3B82F6', 'icon' => 'fa-tint'],
    'semi_liquido' => ['name' => 'Semi-líquido', 'color' => '#8B5CF6', 'icon' => 'fa-spoon'],
    'granular' => ['name' => 'Granular', 'color' => '#F59E0B', 'icon' => 'fa-seedling'],
    'unidade_inteira' => ['name' => 'Unidade Inteira', 'color' => '#10B981', 'icon' => 'fa-apple-alt'],
    'fatias_pedacos' => ['name' => 'Fatias/Pedaços', 'color' => '#EF4444', 'icon' => 'fa-cheese'],
    'corte_porcao' => ['name' => 'Corte/Porção', 'color' => '#F97316', 'icon' => 'fa-drumstick-bite'],
    'colher_cremoso' => ['name' => 'Colher Cremoso', 'color' => '#EC4899', 'icon' => 'fa-ice-cream'],
    'condimentos' => ['name' => 'Condimentos', 'color' => '#6B7280', 'icon' => 'fa-pepper-hot'],
    'oleos_gorduras' => ['name' => 'Óleos/Gorduras', 'color' => '#FCD34D', 'icon' => 'fa-oil-can'],
    'preparacoes_compostas' => ['name' => 'Preparações Compostas', 'color' => '#8B5A2B', 'icon' => 'fa-utensils']
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
/*       FOOD CLASSIFICATION PAGE - DESIGN LIMPO E MODERNO                    */
/* ========================================================================= */

/* Anti-aliasing de fontes para evitar serrilhado */
* {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    text-rendering: optimizeLegibility;
}

.foods-classification-page {
    padding: 1.5rem 2rem !important;
    min-height: 100vh;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

/* ===== LAYOUT PRINCIPAL ===== */
.foods-classification-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 2rem;
    max-width: 1600px;
    margin: 0 auto;
}

/* ===== PAINEL DE CATEGORIAS (LADO ESQUERDO) ===== */
.categories-sidebar {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 1.5rem !important;
    height: fit-content;
    position: sticky;
    top: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    z-index: 1;
}

.categories-sidebar h3 {
    font-size: 1.25rem !important;
    font-weight: 700 !important;
    color: var(--text-primary) !important;
    margin: 0 0 1rem 0 !important;
}

.categories-sidebar p {
    font-size: 0.875rem !important;
    color: var(--text-secondary) !important;
    margin: 0 0 1.5rem 0 !important;
}

.category-item {
    background: rgba(255, 255, 255, 0.03) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 12px !important;
    padding: 1rem !important;
    margin-bottom: 0.75rem !important;
    cursor: pointer;
    transition: all 0.3s ease !important;
    position: relative;
    z-index: 1;
}

.category-item:hover {
    background: rgba(255, 255, 255, 0.06) !important;
    border-color: var(--category-color) !important;
    transform: translateY(-2px);
}

.category-item-name {
    font-size: 0.95rem !important;
    font-weight: 600 !important;
    color: var(--category-color) !important;
    margin: 0 0 0.75rem 0 !important;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    text-rendering: optimizeLegibility;
}

.category-item-info {
    font-size: 0.75rem !important;
    color: var(--text-secondary) !important;
    margin-top: 0.5rem !important;
    line-height: 1.5 !important;
}

/* ===== CONTEÚDO PRINCIPAL (LADO DIREITO) ===== */
.foods-main-content {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    overflow: visible !important;
}

/* Header Card */
.foods-header-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 2rem !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    position: relative;
    overflow: visible !important;
    z-index: 1;
}


.foods-header-title {
    margin-bottom: 1.5rem !important;
}

.foods-header-title h2 {
    font-size: 1.75rem !important;
    font-weight: 700 !important;
    color: var(--text-primary) !important;
    margin: 0 0 0.5rem 0 !important;
}

.foods-header-title p {
    color: var(--text-secondary) !important;
    font-size: 0.95rem !important;
    margin: 0 !important;
}

/* Stats Simplificadas */
.foods-stats-simple {
    display: flex !important;
    gap: 2rem !important;
    margin-top: 1.5rem !important;
    padding-top: 1.5rem !important;
    border-top: 1px solid var(--glass-border) !important;
    flex-wrap: wrap;
}

.foods-stat-item {
    display: flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
}

.foods-stat-label {
    font-size: 0.95rem !important;
    color: var(--text-secondary) !important;
    font-weight: 500 !important;
}

.foods-stat-number {
    font-size: 1.25rem !important;
    font-weight: 700 !important;
    color: var(--accent-orange) !important;
}

/* Legenda */
.foods-legend {
    display: flex !important;
    gap: 2rem !important;
    margin-top: 1.5rem !important;
    padding-top: 1.5rem !important;
    border-top: 1px solid var(--glass-border) !important;
    flex-wrap: wrap;
}

.legend-item {
    display: flex !important;
    align-items: center !important;
    gap: 0.75rem !important;
}

.legend-indicator {
    width: 20px !important;
    height: 20px !important;
    border-radius: 4px !important;
    border: 2px solid !important;
    flex-shrink: 0;
}

.legend-indicator.classified {
    background: rgba(16, 185, 129, 0.2) !important;
    border-color: #10B981 !important;
}

.legend-indicator.unclassified {
    background: rgba(239, 68, 68, 0.2) !important;
    border-color: #EF4444 !important;
}

.legend-text {
    font-size: 0.875rem !important;
    color: var(--text-secondary) !important;
    font-weight: 500 !important;
}

/* Filter Card */
.foods-filter-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 1.5rem !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    overflow: visible !important;
    position: relative;
    z-index: 1;
}

.foods-filter-card.dropdown-active {
    z-index: 9998 !important;
    will-change: z-index;
}

.foods-filter-title {
    font-size: 1rem !important;
    font-weight: 600 !important;
    color: var(--text-primary) !important;
    margin: 0 0 1rem 0 !important;
}

.foods-filter-row {
    display: flex !important;
    align-items: center !important;
    gap: 1rem !important;
    flex-wrap: wrap !important;
}

.foods-search-input {
    flex: 1 !important;
    min-width: 250px !important;
    padding: 0.875rem 1.25rem !important;
    font-size: 0.95rem !important;
    color: var(--text-primary) !important;
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 12px !important;
    outline: none !important;
    transition: all 0.3s ease !important;
    font-family: 'Montserrat', sans-serif !important;
}

.foods-search-input:focus {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: var(--accent-orange) !important;
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1) !important;
}

.foods-search-input::placeholder {
    color: var(--text-secondary) !important;
    opacity: 0.7 !important;
}

/* Custom Select - Estilo IDÊNTICO ao recipes.php */
.custom-select-wrapper {
    position: relative;
    width: 100%;
    min-width: 200px;
    max-width: 300px;
    flex: 1;
    z-index: 1;
}

.custom-select-wrapper.active {
    z-index: 9999 !important;
    position: relative;
    will-change: z-index;
}

.custom-select {
    position: relative;
}

.custom-select-trigger {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.95rem;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.custom-select-trigger:hover {
    border-color: var(--accent-orange);
}

.custom-select.active .custom-select-trigger i {
    transform: rotate(180deg);
}

.custom-select-trigger i {
    transition: transform 0.3s ease;
    color: var(--text-secondary);
}

.custom-select-value {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* DROPDOWN SIMPLIFICADO - SEM TRANSPARÊNCIA, SEM BLUR, SEM TRANSIÇÕES */
.custom-select-options {
    position: absolute !important;
    top: calc(100% + 8px) !important;
    left: 0 !important;
    right: 0 !important;
    z-index: 9999 !important;
    background: #232323 !important; /* Background sólido e opaco - SEM rgba */
    border: 1px solid var(--glass-border) !important;
    border-radius: 8px !important;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.5) !important;
    max-height: 250px !important;
    overflow-y: auto !important;
    box-sizing: border-box !important;
    pointer-events: none !important;
    display: block !important; /* SEMPRE renderizado */
    visibility: hidden !important;
    opacity: 0 !important;
    /* ZERO transições - remove TUDO */
    transition: none !important;
    -webkit-transition: none !important;
    -moz-transition: none !important;
    -ms-transition: none !important;
    -o-transition: none !important;
    /* ZERO transforms que possam causar delay */
    transform: none !important;
    -webkit-transform: none !important;
    -moz-transform: none !important;
    -ms-transform: none !important;
    -o-transform: none !important;
    /* ZERO backdrop-filter */
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
}

.custom-select.active .custom-select-options {
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
    /* Garante que não há transição mesmo quando ativo */
    transition: none !important;
    -webkit-transition: none !important;
    -moz-transition: none !important;
    -ms-transition: none !important;
    -o-transition: none !important;
    transform: none !important;
    -webkit-transform: none !important;
}

.custom-select-option {
    padding: 0.75rem 1rem;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.2s ease;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.custom-select-option:last-child {
    border-bottom: none;
}

.custom-select-option:hover {
    background: rgba(255, 107, 0, 0.1);
    color: var(--accent-orange);
}

.custom-select-option.selected {
    background: rgba(255, 107, 0, 0.15);
    color: var(--accent-orange);
    font-weight: 600;
}

.custom-select-options::-webkit-scrollbar {
    width: 8px;
}

.custom-select-options::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 4px;
}

.custom-select-options::-webkit-scrollbar-thumb {
    background: rgba(255, 107, 0, 0.3);
    border-radius: 4px;
}

.custom-select-options::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 107, 0, 0.5);
}

.btn-filter-circular {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: rgba(255, 107, 0, 0.08);
    border: 1px solid rgba(255, 107, 0, 0.2);
    color: var(--accent-orange);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.btn-filter-circular:hover {
    background: rgba(255, 107, 0, 0.15);
    border-color: var(--accent-orange);
    transform: scale(1.05);
}

.btn-filter-circular i {
    font-size: 1.25rem;
}

.foods-clear-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    white-space: nowrap;
    text-decoration: none;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    border: 1px solid var(--glass-border);
    transition: all 0.3s ease;
}

.foods-clear-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--accent-orange);
    color: var(--accent-orange);
}

/* Bulk Actions Card */
.foods-bulk-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 1.5rem !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    overflow: visible !important;
    position: relative;
    z-index: 1;
}

.foods-bulk-card.dropdown-active {
    z-index: 9998 !important;
    will-change: z-index;
}

/* Garante que os cards de alimentos não interceptem cliques quando dropdown está aberto */
.foods-main-content.dropdown-open .food-item-card {
    pointer-events: none;
}

.foods-bulk-title {
    font-size: 1rem !important;
    font-weight: 600 !important;
    color: var(--text-primary) !important;
    margin: 0 0 1rem 0 !important;
}

.foods-bulk-controls {
    display: flex !important;
    align-items: center !important;
    gap: 1rem !important;
    flex-wrap: wrap !important;
}


.foods-bulk-btn {
    padding: 0.875rem 1.5rem !important;
    background: var(--accent-orange) !important;
    border: none !important;
    border-radius: 12px !important;
    color: #FFFFFF !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    font-size: 0.95rem !important;
    transition: all 0.3s ease !important;
    font-family: 'Montserrat', sans-serif !important;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.foods-bulk-btn:hover:not(:disabled) {
    background: #e65c00 !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 24px rgba(255, 107, 0, 0.4) !important;
}

.foods-bulk-btn:disabled {
    background: rgba(255, 255, 255, 0.05) !important;
    color: var(--text-secondary) !important;
    cursor: not-allowed !important;
    opacity: 0.6 !important;
}

.foods-select-all {
    display: flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
    color: var(--text-secondary) !important;
    font-size: 0.95rem !important;
    cursor: pointer;
    user-select: none;
}

.foods-select-all input[type="checkbox"] {
    transform: scale(1.1) !important;
    accent-color: var(--accent-orange) !important;
    cursor: pointer;
}

/* ===== GRID DE ALIMENTOS ===== */
.foods-grid {
    display: grid !important;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)) !important;
    gap: 1.5rem !important;
    align-items: stretch;
}

/* Card do Alimento - ESTRUTURA LIMPA */
.food-item-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 2px solid var(--glass-border) !important;
    border-radius: 16px !important;
    padding: 1.5rem !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2) !important;
    position: relative;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    min-height: 400px;
    box-sizing: border-box;
    overflow: visible !important;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    backface-visibility: hidden;
    transform: translateZ(0);
    z-index: 1;
}

.food-item-card:hover {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: var(--accent-orange) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3) !important;
}

.food-item-card.classified {
    border: 2px solid #10B981 !important;
    border-radius: 16px !important;
}

.food-item-card.unclassified {
    border: 2px solid #EF4444 !important;
    border-radius: 16px !important;
}

/* Checkbox para seleção em lote - NOVA VERSÃO LIMPA */
.food-item-checkbox {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 18px;
    height: 18px;
    margin: 0;
    padding: 0;
    cursor: pointer;
    z-index: 10;
    opacity: 0;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
}

.food-item-card:hover .food-item-checkbox {
    opacity: 1;
}

.food-item-checkbox:checked {
    opacity: 1;
}

.food-item-checkbox:checked ~ .food-item-content {
    opacity: 0.7;
}

.food-item-checkbox::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 18px;
    height: 18px;
    background: rgba(255, 255, 255, 0.05);
    border: 2px solid var(--glass-border);
    border-radius: 4px;
    transition: all 0.2s ease;
    pointer-events: none;
    box-sizing: border-box;
}

.food-item-checkbox:checked::before {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
}

.food-item-checkbox:checked::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 6px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: translate(-50%, -60%) rotate(45deg);
    pointer-events: none;
    box-sizing: border-box;
}

/* Conteúdo do Card */
.food-item-content {
    transition: opacity 0.2s ease;
    display: flex;
    flex-direction: column;
    flex: 1;
    width: 100%;
}

.food-item-name {
    font-size: 1.125rem !important;
    font-weight: 600 !important;
    color: var(--text-primary) !important;
    margin: 0 0 0.75rem 0 !important;
    line-height: 1.4 !important;
    padding-right: 2rem;
    min-height: 3rem;
    display: flex;
    align-items: flex-start;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    text-rendering: optimizeLegibility;
}

.food-item-brand {
    font-size: 0.875rem !important;
    color: var(--text-secondary) !important;
    margin: 0 0 1rem 0 !important;
    min-height: 1.5rem;
    display: flex;
    align-items: center;
}


/* Macros em linha horizontal */
.food-item-macros {
    display: flex !important;
    gap: 0.75rem !important;
    margin-bottom: 1rem !important;
    flex-wrap: wrap;
}

.food-item-macro {
    flex: 1;
    min-width: 90px;
    background: rgba(255, 255, 255, 0.03) !important;
    border-radius: 8px !important;
    padding: 0.75rem 0.5rem !important;
    text-align: center !important;
}

.food-item-macro-label {
    font-size: 0.6rem !important;
    color: var(--text-secondary) !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    margin-bottom: 0.5rem !important;
    font-weight: 600 !important;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    text-rendering: optimizeLegibility;
}

.food-item-macro-value {
    font-size: 1rem !important;
    font-weight: 700 !important;
    color: var(--text-primary) !important;
    line-height: 1.2 !important;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    text-rendering: optimizeLegibility;
}

/* Categorias atuais */
.food-item-categories {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 0.5rem !important;
    margin-bottom: 1rem !important;
    min-height: 2.5rem;
    align-items: flex-start;
}

.food-category-badge {
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
    padding: 0.5rem 0.75rem !important;
    border-radius: 8px !important;
    font-size: 0.75rem !important;
    font-weight: 600 !important;
    white-space: nowrap !important;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    text-rendering: optimizeLegibility;
}

.food-category-badge i {
    font-size: 0.7rem !important;
}

.food-unclassified-badge {
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
    padding: 0.5rem 0.75rem !important;
    border-radius: 8px !important;
    font-size: 0.75rem !important;
    font-weight: 600 !important;
    background: rgba(239, 68, 68, 0.2) !important;
    color: #EF4444 !important;
    border: 1px solid rgba(239, 68, 68, 0.4) !important;
}

/* Botões de categoria - Grid compacto */
.food-item-actions {
    display: grid !important;
    grid-template-columns: repeat(3, 1fr) !important;
    gap: 0.5rem !important;
    margin-bottom: 0.75rem !important;
    min-height: 6rem;
    align-items: start;
    overflow: visible !important;
}

.food-category-btn {
    background: var(--category-bg) !important;
    border: 1px solid var(--category-color) !important;
    border-radius: 8px !important;
    padding: 0.5rem 0.5rem !important;
    color: var(--category-color) !important;
    font-size: 0.65rem !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    transition: all 0.2s ease !important;
    text-align: center !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow: visible !important;
    text-overflow: clip !important;
    line-height: 1.2 !important;
    min-height: 32px !important;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    text-rendering: optimizeLegibility;
}


.food-category-btn:hover {
    background: var(--category-color) !important;
    color: white !important;
    transform: translateY(-1px) !important;
}

.food-category-btn.selected {
    background: var(--category-color) !important;
    color: white !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2) !important;
}

/* Botão de unidades */
.food-units-btn {
    width: 100% !important;
    background: rgba(59, 130, 246, 0.1) !important;
    color: #3B82F6 !important;
    border: 1px solid #3B82F6 !important;
    border-radius: 8px !important;
    padding: 0.75rem !important;
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    transition: all 0.2s ease !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin-top: auto;
    flex-shrink: 0;
}

.food-units-btn:hover:not(.disabled) {
    background: #3B82F6 !important;
    color: white !important;
    transform: translateY(-1px) !important;
}

.food-units-btn.disabled {
    background: rgba(255, 255, 255, 0.05) !important;
    color: var(--text-secondary) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    cursor: not-allowed !important;
    opacity: 0.6 !important;
}

/* Empty State */
.foods-empty-state {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 4rem 2rem !important;
    text-align: center !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
}


.foods-empty-state p {
    font-size: 1.125rem !important;
    color: var(--text-secondary) !important;
    margin: 0 0 1.5rem 0 !important;
}

.foods-empty-state .btn-primary {
    padding: 0.875rem 2rem !important;
    background: var(--accent-orange) !important;
    border: none !important;
    border-radius: 12px !important;
    color: #FFFFFF !important;
    text-decoration: none !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
    font-size: 0.95rem !important;
    font-weight: 600 !important;
    transition: all 0.3s ease !important;
    cursor: pointer !important;
    font-family: 'Montserrat', sans-serif !important;
}

.foods-empty-state .btn-primary:hover {
    background: #e65c00 !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 24px rgba(255, 107, 0, 0.4) !important;
}

/* Pagination */
.foods-pagination {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 16px !important;
    padding: 1.5rem !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 1rem !important;
    flex-wrap: wrap !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
}

.foods-pagination-info {
    color: var(--text-secondary) !important;
    font-size: 0.95rem !important;
}

.foods-pagination-controls {
    display: flex !important;
    align-items: center !important;
    gap: 0.75rem !important;
}

.foods-pagination-btn {
    padding: 0.625rem 1.25rem !important;
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 10px !important;
    color: var(--text-primary) !important;
    text-decoration: none !important;
    display: flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
    font-size: 0.875rem !important;
    font-weight: 500 !important;
    transition: all 0.3s ease !important;
    font-family: 'Montserrat', sans-serif !important;
}

.foods-pagination-btn:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: var(--accent-orange) !important;
    color: var(--accent-orange) !important;
}

.foods-pagination-numbers {
    display: flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
}

.foods-pagination-number {
    min-width: 40px !important;
    height: 40px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 10px !important;
    color: var(--text-primary) !important;
    text-decoration: none !important;
    font-size: 0.875rem !important;
    font-weight: 500 !important;
    transition: all 0.3s ease !important;
}

.foods-pagination-number:hover {
    background: rgba(255, 107, 0, 0.1) !important;
    border-color: var(--accent-orange) !important;
    color: var(--accent-orange) !important;
}

.foods-pagination-number.current {
    background: var(--accent-orange) !important;
    border-color: var(--accent-orange) !important;
    color: #FFFFFF !important;
}

.foods-pagination-ellipsis {
    color: var(--text-secondary) !important;
    padding: 0 0.5rem !important;
}

/* Auto-save indicator */
.auto-save-indicator {
    position: fixed !important;
    top: 20px !important;
    right: 20px !important;
    background: #10B981 !important;
    color: white !important;
    padding: 0.75rem 1.25rem !important;
    border-radius: 12px !important;
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    z-index: 1000 !important;
    opacity: 0 !important;
    transform: translateY(-20px) !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4) !important;
    display: flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
}

.auto-save-indicator.show {
    opacity: 1 !important;
    transform: translateY(0) !important;
}

/* Loading overlay */
.loading-overlay {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    background: rgba(0,0,0,0.6) !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    z-index: 9999 !important;
    opacity: 0 !important;
    visibility: hidden !important;
    transition: all 0.3s ease !important;
}

.loading-overlay.show {
    opacity: 1 !important;
    visibility: visible !important;
}

.loading-content {
    text-align: center !important;
    color: var(--text-primary) !important;
}

.loading-spinner {
    width: 40px !important;
    height: 40px !important;
    border: 3px solid rgba(255, 255, 255, 0.2) !important;
    border-top: 3px solid var(--accent-orange) !important;
    border-radius: 50% !important;
    animation: spin 1s linear infinite !important;
    margin-bottom: 15px !important;
}

.loading-text {
    font-size: 1rem !important;
    font-weight: 600 !important;
    color: var(--text-primary) !important;
    margin-bottom: 5px !important;
}

.loading-subtext {
    font-size: 0.85rem !important;
    color: var(--text-secondary) !important;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 1200px) {
    .foods-classification-layout {
        grid-template-columns: 1fr !important;
        gap: 1.5rem !important;
    }
    
    .categories-sidebar {
        position: static !important;
        order: 2 !important;
    }
    
    .foods-main-content {
        order: 1 !important;
    }
}

@media (max-width: 768px) {
    .foods-classification-page {
        padding: 1rem !important;
    }
    
    .foods-filter-row {
        flex-direction: column !important;
        align-items: stretch !important;
    }
    
    .foods-grid {
        grid-template-columns: 1fr !important;
    }
    
    .foods-stats-simple {
        flex-direction: column !important;
        gap: 1rem !important;
    }
    
    .foods-legend {
        flex-direction: column !important;
        gap: 1rem !important;
    }
    
    .foods-bulk-controls {
        flex-direction: column !important;
        align-items: stretch !important;
    }
    
    .food-item-actions {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}
</style>

<div class="foods-classification-page">
    <div class="foods-classification-layout">
        <!-- CATEGORIAS (LADO ESQUERDO) -->
        <div class="dashboard-card categories-sidebar">
            <h3>Categorias</h3>
            <p>Clique para classificar</p>
        
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
                <div class="category-item" data-category="<?= $key ?>" style="--category-color: <?= $cat['color'] ?>; --category-bg: <?= $cat['color'] ?>20;">
                    <h4 class="category-item-name"><?= $cat['name'] ?></h4>
                    <div class="category-item-info">
                        <strong>Exemplos:</strong> <?= implode(', ', $examples) ?><br>
                        <strong>Unidades:</strong> <?= implode(', ', array_map(fn($u) => $unit_names[$u] ?? $u, $units)) ?>
                </div>
                </div>
                        <?php endforeach; ?>
                    </div>

        <!-- CONTEÚDO PRINCIPAL (LADO DIREITO) -->
        <div class="foods-main-content">
            <!-- Header Card -->
            <div class="dashboard-card foods-header-card">
                <div class="foods-header-title">
                    <h2>Alimentos</h2>
                    <p>Gerencie e classifique todos os alimentos do sistema</p>
                </div>
                
                <!-- Estatísticas Simplificadas -->
                <div class="foods-stats-simple">
                    <div class="foods-stat-item">
                        <span class="foods-stat-label">Total:</span>
                        <span class="foods-stat-number"><?= $total_items ?></span>
                    </div>
                    <div class="foods-stat-item">
                        <span class="foods-stat-label">Classificados:</span>
                        <span class="foods-stat-number" id="classified-count"><?= $classified_count ?></span>
                </div>
                    <div class="foods-stat-item">
                        <span class="foods-stat-label">Restantes:</span>
                        <span class="foods-stat-number" id="remaining-count"><?= $total_items - $classified_count ?></span>
            </div>
    </div>

                <!-- Legenda -->
                <div class="foods-legend">
                    <div class="legend-item">
                        <div class="legend-indicator classified"></div>
                        <span class="legend-text">Borda verde = Categorizado</span>
        </div>
                    <div class="legend-item">
                        <div class="legend-indicator unclassified"></div>
                        <span class="legend-text">Borda vermelha = Não categorizado</span>
            </div>
            </div>
        </div>

        <!-- Filtros -->
            <div class="dashboard-card foods-filter-card">
                <h3 class="foods-filter-title">Buscar</h3>
                <form method="GET" class="foods-filter-row">
                    <input type="text" 
                           class="foods-search-input" 
                           name="search" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Nome do alimento...">
                    <div class="custom-select-wrapper" id="category_filter_wrapper">
                        <input type="hidden" name="category" id="category_filter_input" value="<?= htmlspecialchars($category_filter) ?>">
                        <div class="custom-select" id="category_filter_select">
                            <div class="custom-select-trigger">
                                <span class="custom-select-value">
                                    <?php 
                                    if ($category_filter && isset($categories[$category_filter])) {
                                        echo htmlspecialchars($categories[$category_filter]['name']);
                                    } else {
                                        echo 'Todas as categorias';
                                    }
                                    ?>
                                </span>
                                <i class="fas fa-chevron-down"></i>
            </div>
                            <div class="custom-select-options">
                                <div class="custom-select-option" data-value="">Todas as categorias</div>
                    <?php foreach ($categories as $key => $cat): ?>
                                    <div class="custom-select-option <?= $category_filter === $key ? 'selected' : '' ?>" data-value="<?= $key ?>">
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </div>
                    <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn-filter-circular" title="Filtrar">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if (!empty($search) || !empty($category_filter)): ?>
                    <a href="food_classification.php" class="foods-clear-btn">Limpar</a>
                    <?php endif; ?>
            </form>
        </div>

        <!-- Ações em Lote -->
            <div class="dashboard-card foods-bulk-card">
                <h3 class="foods-bulk-title">Ações em Lote</h3>
                <div class="foods-bulk-controls">
                    <div class="custom-select-wrapper" id="bulk_category_wrapper">
                        <input type="hidden" id="bulk-category" value="">
                        <div class="custom-select" id="bulk_category_select">
                            <div class="custom-select-trigger">
                                <span class="custom-select-value">Selecione uma categoria</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="custom-select-options">
                                <div class="custom-select-option" data-value="">Selecione uma categoria</div>
                    <?php foreach ($categories as $key => $cat): ?>
                                    <div class="custom-select-option" data-value="<?= $key ?>">
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </div>
                    <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <button class="foods-bulk-btn" onclick="applyBulkClassification()" id="bulk-btn" disabled>
                    Aplicar aos Selecionados
                </button>
                    <label class="foods-select-all">
                        <input type="checkbox" id="select-all">
                    Selecionar Todos
                </label>
            </div>
        </div>

            <!-- Grid de Alimentos -->
            <?php if (empty($foods)): ?>
                <div class="dashboard-card foods-empty-state">
                    <p>Nenhum alimento encontrado.</p>
                    <?php if (!empty($search) || !empty($category_filter)): ?>
                        <a href="food_classification.php" class="btn-primary">Ver Todos os Alimentos</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
        <div class="foods-grid" id="foods-list">
            <?php foreach ($foods as $food): ?>
                        <div class="food-item-card <?= !empty($food['categories']) ? 'classified' : 'unclassified' ?>" data-food-id="<?= $food['id'] ?>">
                            <input type="checkbox" class="food-item-checkbox" value="<?= $food['id'] ?>">
                            
                            <div class="food-item-content">
                                <h4 class="food-item-name"><?= htmlspecialchars($food['name_pt']) ?></h4>
                                
                                <div class="food-item-brand">
                                <?php if (!empty($food['brand']) && $food['brand'] !== 'TACO'): ?>
                                        <?= htmlspecialchars($food['brand']) ?>
                                <?php endif; ?>
                            </div>
                                
                                <div class="food-item-macros">
                                    <div class="food-item-macro">
                                        <div class="food-item-macro-label">Cal.</div>
                                        <div class="food-item-macro-value"><?= number_format($food['energy_kcal_100g'], 0) ?>kcal</div>
                                </div>
                                    <div class="food-item-macro">
                                        <div class="food-item-macro-label">Prot.</div>
                                        <div class="food-item-macro-value"><?= number_format($food['protein_g_100g'], 1) ?>g</div>
                                </div>
                                    <div class="food-item-macro">
                                        <div class="food-item-macro-label">Carb.</div>
                                        <div class="food-item-macro-value"><?= number_format($food['carbohydrate_g_100g'], 1) ?>g</div>
                                </div>
                                    <div class="food-item-macro">
                                        <div class="food-item-macro-label">Gord.</div>
                                        <div class="food-item-macro-value"><?= number_format($food['fat_g_100g'], 1) ?>g</div>
                                </div>
                            </div>
                                
                                <div class="food-item-categories" 
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
                                                    '<span class="food-category-badge" style="background: %s20; color: %s; border: 1px solid %s40;">%s</span>',
                                                    $cat_info['color'], $cat_info['color'], $cat_info['color'], $cat_info['name']
                                            );
                                        }
                                    }
                                    echo $tagsHtml;
                                } else {
                                        echo '<span class="food-unclassified-badge">Não classificado</span>';
                                }
                                ?>
                            </div>
                                
                                <div class="food-item-actions">
                                <?php foreach ($categories as $key => $cat): ?>
                                        <button class="food-category-btn" 
                                            data-food-id="<?= $food['id'] ?>"
                                            data-category="<?= $key ?>"
                                            style="--category-color: <?= $cat['color'] ?>; --category-bg: <?= $cat['color'] ?>20;">
                                            <?= $cat['name'] ?>
                                    </button>
                                <?php endforeach; ?>
                                </div>
                                
                                <button class="food-units-btn <?= empty($food['categories']) ? 'disabled' : '' ?>" 
                                        data-food-id="<?= $food['id'] ?>"
                                        onclick="openUnitsEditor(<?= $food['id'] ?>, '<?= htmlspecialchars($food['name_pt']) ?>', getFoodCategories(<?= $food['id'] ?>))">
                                    Unidades
                                </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

                <!-- Paginação -->
                <?php if ($total_pages > 1): ?>
                    <div class="dashboard-card foods-pagination">
                        <div class="foods-pagination-info">
                            Mostrando <?= ($offset + 1) ?> - <?= min($offset + $per_page, $total_items) ?> de <?= number_format($total_items) ?> alimentos
                        </div>
                        <div class="foods-pagination-controls">
                <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>" 
                                   class="foods-pagination-btn">
                                    Anterior
                                </a>
                <?php endif; ?>
                
                            <div class="foods-pagination-numbers">
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1): ?>
                                    <a href="?page=1&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>" 
                                       class="foods-pagination-number">1</a>
                                    <?php if ($start_page > 2): ?>
                                        <span class="foods-pagination-ellipsis">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <?php if ($i == $page): ?>
                                        <span class="foods-pagination-number current"><?= $i ?></span>
                    <?php else: ?>
                                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>" 
                                           class="foods-pagination-number"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <span class="foods-pagination-ellipsis">...</span>
                <?php endif; ?>
                                    <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>" 
                                       class="foods-pagination-number"><?= $total_pages ?></a>
        <?php endif; ?>
</div>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>" 
                                   class="foods-pagination-btn">
                                    Próxima
                                </a>
                            <?php endif; ?>
      </div>
    </div>
                <?php endif; ?>
            <?php endif; ?>
    </div>
  </div>
</div>

<!-- Auto-save indicator -->
<div class="auto-save-indicator" id="auto-save-indicator">
    Salvo!
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
    document.querySelectorAll('.food-item-card').forEach(card => {
        const foodId = card.dataset.foodId;
        const existingCategories = card.querySelector('.food-item-categories').dataset.categories;
        if (existingCategories) {
            classifications[foodId] = existingCategories.split(',');
        } else {
            classifications[foodId] = [];
        }
        updateFoodVisual(foodId); // Atualiza o visual para refletir o estado carregado
    });

    // 2. Adicionar listener de clique para TODOS os botões de categoria
    document.getElementById('foods-list').addEventListener('click', function(e) {
        if (e.target.closest('.food-category-btn')) {
            const btn = e.target.closest('.food-category-btn');
            const foodId = btn.dataset.foodId;
            const category = btn.dataset.category;
            toggleCategory(foodId, category);
        }
    });
    
    // 3. Selecionar todos
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.food-item-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateBulkButton();
        });
    }
    
    // 4. Atualizar botão bulk quando checkboxes mudarem
    document.querySelectorAll('.food-item-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            updateBulkButton();
        });
    });
    
    // 5. Custom Select para filtro de categoria
    initCustomSelect('category_filter_select', 'category_filter_input', true);
    
    // 6. Custom Select para bulk category
    initCustomSelect('bulk_category_select', 'bulk-category', false);
});

function updateBulkButton() {
    const selected = document.querySelectorAll('.food-item-checkbox:checked');
    const bulkCategoryInput = document.getElementById('bulk-category');
    const bulkCategory = bulkCategoryInput ? bulkCategoryInput.value : '';
    const bulkBtn = document.getElementById('bulk-btn');
    
    if (bulkBtn) {
        bulkBtn.disabled = selected.length === 0 || !bulkCategory;
    }
}

// Função para fechar todos os dropdowns (exceto o que está sendo aberto)
function closeAllDropdowns(excludeSelect = null) {
    document.querySelectorAll('.custom-select.active').forEach(select => {
        // Não fecha o dropdown que está sendo aberto
        if (select === excludeSelect) {
            return;
        }
        
        // Remove estilos inline primeiro
        const optionsContainer = select.querySelector('.custom-select-options');
        if (optionsContainer) {
            optionsContainer.style.visibility = 'hidden';
            optionsContainer.style.opacity = '0';
            optionsContainer.style.pointerEvents = 'none';
        }
        
        select.classList.remove('active');
        const wrapper = select.closest('.custom-select-wrapper');
        if (wrapper) {
            wrapper.classList.remove('active');
            // Remove classe do card pai
            const card = wrapper.closest('.foods-filter-card, .foods-bulk-card');
            if (card) {
                card.classList.remove('dropdown-active');
            }
        }
    });
    
    // Remove classe do container principal apenas se não houver nenhum dropdown aberto
    // (exceto o que está sendo aberto)
    const hasOtherActive = document.querySelectorAll('.custom-select.active').length > (excludeSelect ? 1 : 0);
    if (!hasOtherActive && !excludeSelect) {
        const mainContent = document.querySelector('.foods-main-content');
        if (mainContent) {
            mainContent.classList.remove('dropdown-open');
        }
    }
}

// Função para inicializar custom select - VERSÃO SIMPLIFICADA (igual recipes.php)
function initCustomSelect(selectId, inputId, submitForm) {
    const customSelect = document.getElementById(selectId);
    if (!customSelect) return;
    
    const hiddenInput = document.getElementById(inputId);
    if (!hiddenInput) return;
    
    const wrapper = customSelect.closest('.custom-select-wrapper');
    const trigger = customSelect.querySelector('.custom-select-trigger');
    const options = customSelect.querySelectorAll('.custom-select-option');
    const valueDisplay = customSelect.querySelector('.custom-select-value');
    
    // Abre/fecha o dropdown
    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpening = !customSelect.classList.contains('active');
        
        if (isOpening) {
            // Fecha todos os outros dropdowns PRIMEIRO
            closeAllDropdowns(customSelect);
            
            // Aplica todas as classes de uma vez
            customSelect.classList.add('active');
            let card = null;
            if (wrapper) {
                wrapper.classList.add('active');
                card = wrapper.closest('.foods-filter-card, .foods-bulk-card');
                if (card) {
                    card.classList.add('dropdown-active');
                }
                const mainContent = document.querySelector('.foods-main-content');
                if (mainContent) {
                    mainContent.classList.add('dropdown-open');
                }
            }
            
            // FORÇA RENDERIZAÇÃO IMEDIATA - aplica estilos diretamente no elemento
            const optionsContainer = customSelect.querySelector('.custom-select-options');
            if (optionsContainer) {
                // Aplica estilos diretamente para garantir renderização imediata
                optionsContainer.style.visibility = 'visible';
                optionsContainer.style.opacity = '1';
                optionsContainer.style.pointerEvents = 'auto';
                optionsContainer.style.background = '#232323'; // Background sólido
                
                // Força reflow para garantir que o background seja renderizado
                void optionsContainer.offsetHeight;
            }
        } else {
            // Fechando o dropdown
            customSelect.classList.remove('active');
            if (wrapper) {
                wrapper.classList.remove('active');
                // Remove classe do card pai
                const card = wrapper.closest('.foods-filter-card, .foods-bulk-card');
                if (card) {
                    card.classList.remove('dropdown-active');
                }
                // Remove classe do container principal
                const mainContent = document.querySelector('.foods-main-content');
                if (mainContent) {
                    mainContent.classList.remove('dropdown-open');
                }
            }
        }
    });
    
    // Seleciona uma opção
    options.forEach(option => {
        option.addEventListener('click', function(e) {
            e.stopPropagation();
            
            const value = this.getAttribute('data-value');
            
            // Atualiza o valor do input escondido
            hiddenInput.value = value;
            
            // Atualiza o texto visível
            valueDisplay.textContent = this.textContent;
            
            // Remove a classe 'selected' de todos e adiciona na clicada
            options.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            
            // Fecha o dropdown
            customSelect.classList.remove('active');
            if (wrapper) {
                wrapper.classList.remove('active');
                // Remove classe do card pai
                const card = wrapper.closest('.foods-filter-card, .foods-bulk-card');
                if (card) {
                    card.classList.remove('dropdown-active');
                }
                // Remove classe do container principal
                const mainContent = document.querySelector('.foods-main-content');
                if (mainContent) {
                    mainContent.classList.remove('dropdown-open');
                }
            }
            
            // Se for o filtro de categoria, submete o formulário
            if (submitForm) {
                const form = customSelect.closest('form');
                if (form) {
                    form.submit();
                }
            } else {
                // Se for bulk category, atualiza o botão
                updateBulkButton();
            }
        });
    });
    
    // Fecha o dropdown se clicar fora
    document.addEventListener('click', function(e) {
        // Se clicar fora de qualquer dropdown, fecha todos
        if (!e.target.closest('.custom-select')) {
            closeAllDropdowns();
        }
    });
    
    // Fecha com a tecla Esc
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllDropdowns();
        }
    });
}

function applyBulkClassification() {
    const selected = document.querySelectorAll('.food-item-checkbox:checked');
    const bulkCategoryInput = document.getElementById('bulk-category');
    const bulkCategory = bulkCategoryInput ? bulkCategoryInput.value : '';
    
    if (selected.length === 0 || !bulkCategory) return;
    
    selected.forEach(checkbox => {
        const foodId = checkbox.value;
        // Adiciona a categoria se não existir
        const currentCategories = classifications[foodId] || [];
        if (!currentCategories.includes(bulkCategory)) {
            currentCategories.push(bulkCategory);
            classifications[foodId] = currentCategories;
            updateFoodVisual(foodId);
            saveClassification(foodId);
        }
    });
    
    // Limpa seleção
    document.getElementById('select-all').checked = false;
    document.querySelectorAll('.food-item-checkbox').forEach(cb => {
        cb.checked = false;
    });
    updateBulkButton();
}

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
    const foodCard = document.querySelector(`.food-item-card[data-food-id="${foodId}"]`);
    if (!foodCard) return;

    const currentCategories = classifications[foodId] || [];
    
    // Atualiza os botões (adicionando/removendo a classe 'selected')
    foodCard.querySelectorAll('.food-category-btn').forEach(btn => {
        btn.classList.toggle('selected', currentCategories.includes(btn.dataset.category));
    });

    // Atualiza o display de texto/tags
    const categoryDisplay = foodCard.querySelector('.food-item-categories');
    if (currentCategories.length > 0) {
        const tagsHtml = currentCategories.map(catKey => {
            const catInfo = window.categories[catKey];
            if (!catInfo) return ''; // Segurança caso a categoria não exista
            return `<span class="food-category-badge" style="background: ${catInfo.color}20; color: ${catInfo.color}; border: 1px solid ${catInfo.color}40;">${catInfo.name}</span>`;
        }).join('');
        categoryDisplay.innerHTML = tagsHtml;
        foodCard.classList.add('classified');
        foodCard.classList.remove('unclassified');
        
        // Habilita botão de unidades
        const unitsBtn = foodCard.querySelector('.food-units-btn');
        if (unitsBtn) {
            unitsBtn.classList.remove('disabled');
        }
    } else {
        categoryDisplay.innerHTML = '<span class="food-unclassified-badge"><i class="fas fa-exclamation-circle"></i> Não classificado</span>';
        foodCard.classList.remove('classified');
        foodCard.classList.add('unclassified');
        
        // Desabilita botão de unidades
        const unitsBtn = foodCard.querySelector('.food-units-btn');
        if (unitsBtn) {
            unitsBtn.classList.add('disabled');
        }
    }
    
    // Atualiza contadores
    updateCounters();
}

// Atualiza contadores
function updateCounters() {
    const totalClassified = Object.values(classifications).filter(cats => cats.length > 0).length;
    const classifiedCountEl = document.getElementById('classified-count');
    const remainingCountEl = document.getElementById('remaining-count');
    
    if (classifiedCountEl) {
        classifiedCountEl.textContent = totalClassified;
    }
    if (remainingCountEl) {
        const totalItems = <?= (int)$total_items ?>;
        remainingCountEl.textContent = totalItems - totalClassified;
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
            updateCounters();
        } else {
            alert('Erro ao salvar: ' + (data.message || 'Erro desconhecido.'));
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
