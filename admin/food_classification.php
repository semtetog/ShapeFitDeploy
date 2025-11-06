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
/*       FOOD CLASSIFICATION PAGE - ESTILO PROFISSIONAL                      */
/* ========================================================================= */

.foods-classification-container {
    padding: 1.5rem 2rem !important;
    min-height: 100vh;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 2rem;
}

/* ===== LEGENDAS (LADO ESQUERDO) ===== */
.categories-panel {
    background: rgba(255, 255, 255, 0.05) !important;
    backdrop-filter: blur(10px) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 1.5rem !important;
    height: fit-content;
    position: sticky;
    top: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
}

.categories-panel h2 {
    font-size: 1.25rem !important;
    font-weight: 700 !important;
    color: var(--text-primary) !important;
    margin: 0 0 1rem 0 !important;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.categories-panel h2 i {
    color: var(--accent-orange) !important;
}

.categories-panel p {
    font-size: 0.875rem !important;
    color: var(--text-secondary) !important;
    margin: 0 0 1.5rem 0 !important;
}

.category-legend {
    background: rgba(255, 255, 255, 0.03) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 12px !important;
    padding: 1rem !important;
    margin-bottom: 0.75rem !important;
    cursor: pointer;
    transition: all 0.3s ease !important;
}

.category-legend:hover {
    background: rgba(255, 255, 255, 0.06) !important;
    border-color: var(--category-color) !important;
    transform: translateY(-2px);
}

.category-legend.selected {
    border-color: var(--category-color) !important;
    background: var(--category-bg) !important;
}

.category-legend-header {
    display: flex !important;
    align-items: center !important;
    gap: 0.75rem !important;
    margin-bottom: 0.75rem !important;
}

.category-legend-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--category-color);
    color: white;
    font-size: 0.875rem;
}

.category-legend-name {
    font-size: 0.95rem !important;
    font-weight: 600 !important;
    color: var(--category-color) !important;
    margin: 0 !important;
}

.category-legend-examples,
.category-legend-units {
    margin-top: 0.75rem !important;
    padding-top: 0.75rem !important;
    border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
}

.examples-label,
.units-label {
    font-size: 0.7rem !important;
    color: var(--text-secondary) !important;
    margin-bottom: 0.5rem !important;
    font-weight: 600 !important;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.examples-list,
.units-list {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 0.5rem !important;
}

.example-tag,
.unit-tag {
    background: rgba(255, 255, 255, 0.1) !important;
    color: var(--text-primary) !important;
    padding: 0.25rem 0.5rem !important;
    border-radius: 6px !important;
    font-size: 0.7rem !important;
    font-weight: 500 !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
}

/* ===== CONTEÚDO PRINCIPAL (LADO DIREITO) ===== */
.classifier-content {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

/* Header Card */
.classifier-header-card {
    background: rgba(255, 255, 255, 0.05) !important;
    backdrop-filter: blur(10px) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 2rem !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    position: relative;
}

.classifier-header-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--accent-orange), #FFA500);
    border-radius: 20px 20px 0 0;
}

.header-title h2 {
    font-size: 1.75rem !important;
    font-weight: 700 !important;
    color: var(--text-primary) !important;
    margin: 0 0 0.5rem 0 !important;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.header-title h2 i {
    color: var(--accent-orange) !important;
}

.header-title p {
    color: var(--text-secondary) !important;
    font-size: 0.95rem !important;
    margin: 0 !important;
}

/* Stats Grid */
.stats-grid {
    display: grid !important;
    grid-template-columns: repeat(4, 1fr) !important;
    gap: 1rem !important;
    margin-bottom: 0 !important;
}

.stats-card {
    background: rgba(255, 255, 255, 0.05) !important;
    backdrop-filter: blur(10px) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 16px !important;
    padding: 1.5rem !important;
    text-align: center !important;
    transition: all 0.3s ease !important;
}

.stats-card:hover {
    background: rgba(255, 255, 255, 0.08) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3) !important;
    border-color: var(--accent-orange) !important;
}

.stats-card .stat-number {
    font-size: 2rem !important;
    font-weight: 700 !important;
    color: var(--accent-orange) !important;
    margin-bottom: 0.5rem !important;
}

.stats-card .stat-label {
    font-size: 0.875rem !important;
    color: var(--text-secondary) !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    font-weight: 600 !important;
}

/* Filter Form */
.filters-section {
    background: rgba(255, 255, 255, 0.05) !important;
    backdrop-filter: blur(10px) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 1.5rem !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
}

.filters-title {
    font-size: 1rem !important;
    font-weight: 600 !important;
    color: var(--text-primary) !important;
    margin: 0 0 1rem 0 !important;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filters-title i {
    color: var(--accent-orange) !important;
}

.filter-row {
    display: flex !important;
    align-items: center !important;
    gap: 1rem !important;
    flex-wrap: wrap !important;
}

.filter-row .form-group {
    margin: 0 !important;
    padding: 0 !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 0.5rem !important;
}

.filter-row .form-group label {
    display: none !important;
}

.food-search-input {
    flex: 1 !important;
    min-width: 200px !important;
    max-width: 400px !important;
    padding: 0.875rem 1.25rem !important;
    font-size: 0.95rem !important;
    color: var(--text-primary) !important;
    background: rgba(255, 255, 255, 0.05) !important;
    backdrop-filter: blur(5px) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 12px !important;
    outline: none !important;
    transition: all 0.3s ease !important;
    font-family: 'Montserrat', sans-serif !important;
}

.food-search-input:focus {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: var(--accent-orange) !important;
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1) !important;
}

.food-search-input::placeholder {
    color: var(--text-secondary) !important;
    opacity: 0.7 !important;
}

.category-select {
    min-width: 200px !important;
    padding: 0.875rem 1.25rem !important;
    font-size: 0.95rem !important;
    color: var(--text-primary) !important;
    background: rgba(255, 255, 255, 0.05) !important;
    backdrop-filter: blur(5px) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 12px !important;
    outline: none !important;
    transition: all 0.3s ease !important;
    font-family: 'Montserrat', sans-serif !important;
    cursor: pointer;
}

.category-select:focus {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: var(--accent-orange) !important;
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1) !important;
}

.filter-btn,
.clear-btn {
    padding: 0.875rem 1.5rem !important;
    border-radius: 12px !important;
    font-size: 0.95rem !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    border: none !important;
    transition: all 0.3s ease !important;
    text-decoration: none !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
    font-family: 'Montserrat', sans-serif !important;
}

.filter-btn {
    background: var(--accent-orange) !important;
    color: #FFFFFF !important;
}

.filter-btn:hover {
    background: #e65c00 !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 24px rgba(255, 107, 0, 0.4) !important;
}

.clear-btn {
    background: rgba(255, 255, 255, 0.05) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--glass-border) !important;
}

.clear-btn:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: var(--accent-orange) !important;
    color: var(--accent-orange) !important;
}

/* Bulk Actions */
.bulk-actions {
    background: rgba(255, 255, 255, 0.05) !important;
    backdrop-filter: blur(10px) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 1.5rem !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
}

.bulk-title {
    font-size: 1rem !important;
    font-weight: 600 !important;
    color: var(--text-primary) !important;
    margin: 0 0 1rem 0 !important;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.bulk-title i {
    color: var(--accent-orange) !important;
}

.bulk-controls {
    display: flex !important;
    align-items: center !important;
    gap: 1rem !important;
    flex-wrap: wrap !important;
}

.bulk-select {
    flex: 1 !important;
    min-width: 200px !important;
    padding: 0.875rem 1.25rem !important;
    font-size: 0.95rem !important;
    color: var(--text-primary) !important;
    background: rgba(255, 255, 255, 0.05) !important;
    backdrop-filter: blur(5px) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 12px !important;
    outline: none !important;
    transition: all 0.3s ease !important;
    font-family: 'Montserrat', sans-serif !important;
    cursor: pointer;
}

.bulk-select:focus {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: var(--accent-orange) !important;
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1) !important;
}

.bulk-btn {
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
}

.bulk-btn:hover:not(:disabled) {
    background: #e65c00 !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 24px rgba(255, 107, 0, 0.4) !important;
}

.bulk-btn:disabled {
    background: rgba(255, 255, 255, 0.05) !important;
    color: var(--text-secondary) !important;
    cursor: not-allowed !important;
    opacity: 0.6 !important;
}

.bulk-checkbox {
    display: flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
    color: var(--text-secondary) !important;
    font-size: 0.95rem !important;
    cursor: pointer;
}

.bulk-checkbox input[type="checkbox"] {
    transform: scale(1.1) !important;
    accent-color: var(--accent-orange) !important;
    cursor: pointer;
}

/* Foods Grid */
.foods-grid {
    display: grid !important;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)) !important;
    gap: 1.5rem !important;
    margin-bottom: 2rem !important;
}

.food-card {
    background: rgba(255, 255, 255, 0.05) !important;
    backdrop-filter: blur(10px) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 16px !important;
    padding: 1.5rem !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2) !important;
}

.food-card:hover {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: var(--accent-orange) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3) !important;
}

.food-card.classified {
    background: rgba(16, 185, 129, 0.08) !important;
    border-color: #10B981 !important;
}

.food-card.unclassified {
    background: rgba(239, 68, 68, 0.08) !important;
    border-color: #EF4444 !important;
}

.food-header {
    display: flex !important;
    align-items: flex-start !important;
    gap: 1rem !important;
    margin-bottom: 1rem !important;
}

.food-checkbox {
    margin-top: 0.25rem !important;
    transform: scale(1.1) !important;
    accent-color: var(--accent-orange) !important;
    cursor: pointer;
}

.food-info {
    flex-grow: 1 !important;
}

.food-name {
    font-size: 1.125rem !important;
    font-weight: 600 !important;
    color: var(--text-primary) !important;
    margin-bottom: 0.75rem !important;
    line-height: 1.4 !important;
}

.food-brand {
    font-size: 0.875rem !important;
    color: var(--text-secondary) !important;
    margin-top: 0.25rem !important;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.food-brand i {
    color: var(--accent-orange) !important;
    font-size: 0.75rem !important;
}

.food-macros {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 0.75rem !important;
    margin-bottom: 1rem !important;
}

.macro-item {
    background: rgba(255, 255, 255, 0.03) !important;
    border-radius: 8px !important;
    padding: 0.75rem !important;
    text-align: center !important;
}

.macro-label {
    font-size: 0.7rem !important;
    color: var(--text-secondary) !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    margin-bottom: 0.25rem !important;
    font-weight: 600 !important;
}

.macro-value {
    font-size: 1rem !important;
    font-weight: 700 !important;
    color: var(--text-primary) !important;
}

.food-current-category {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 0.5rem !important;
    margin-bottom: 1rem !important;
}

.category-tag {
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
    padding: 0.5rem 0.75rem !important;
    border-radius: 8px !important;
    font-size: 0.75rem !important;
    font-weight: 600 !important;
    white-space: nowrap !important;
    transition: all 0.2s ease !important;
}

.category-tag i {
    font-size: 0.7rem !important;
}

.unclassified-tag {
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

.food-actions {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 0.5rem !important;
    margin-bottom: 0.75rem !important;
}

.category-btn {
    background: var(--category-bg) !important;
    border: 1px solid var(--category-color) !important;
    border-radius: 8px !important;
    padding: 0.625rem 0.75rem !important;
    color: var(--category-color) !important;
    font-size: 0.75rem !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    transition: all 0.2s ease !important;
    text-align: center !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.5rem !important;
}

.category-btn i {
    font-size: 0.7rem !important;
}

.category-btn:hover {
    background: var(--category-color) !important;
    color: white !important;
    transform: translateY(-1px) !important;
}

.category-btn.selected {
    background: var(--category-color) !important;
    color: white !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2) !important;
}

.units-btn {
    grid-column: 1 / -1 !important;
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
    gap: 0.5rem !important;
    margin-top: 0.5rem !important;
}

.units-btn:hover:not(.disabled) {
    background: #3B82F6 !important;
    color: white !important;
    transform: translateY(-1px) !important;
}

.units-btn.disabled {
    background: rgba(255, 255, 255, 0.05) !important;
    color: var(--text-secondary) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    cursor: not-allowed !important;
    opacity: 0.6 !important;
}

.units-hint {
    display: block !important;
    font-size: 0.65rem !important;
    color: var(--text-secondary) !important;
    margin-top: 0.25rem !important;
    font-style: italic !important;
}

/* Empty State */
.empty-state-card {
    background: rgba(255, 255, 255, 0.05) !important;
    backdrop-filter: blur(10px) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 3rem 2rem !important;
    text-align: center !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
}

.empty-state-content {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    gap: 1.5rem !important;
}

.empty-state-content i {
    font-size: 4rem !important;
    color: var(--text-secondary) !important;
    opacity: 0.5 !important;
}

.empty-state-content p {
    font-size: 1.125rem !important;
    color: var(--text-secondary) !important;
    margin: 0 !important;
}

.empty-state-content .btn-primary {
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

.empty-state-content .btn-primary:hover {
    background: #e65c00 !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 24px rgba(255, 107, 0, 0.4) !important;
}

/* Pagination */
.pagination-card {
    background: rgba(255, 255, 255, 0.05) !important;
    backdrop-filter: blur(10px) !important;
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

.pagination-info {
    color: var(--text-secondary) !important;
    font-size: 0.95rem !important;
}

.pagination-controls {
    display: flex !important;
    align-items: center !important;
    gap: 0.75rem !important;
}

.pagination-btn {
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

.pagination-btn:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: var(--accent-orange) !important;
    color: var(--accent-orange) !important;
}

.pagination-numbers {
    display: flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
}

.pagination-number {
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

.pagination-number:hover {
    background: rgba(255, 107, 0, 0.1) !important;
    border-color: var(--accent-orange) !important;
    color: var(--accent-orange) !important;
}

.pagination-number.current {
    background: var(--accent-orange) !important;
    border-color: var(--accent-orange) !important;
    color: #FFFFFF !important;
}

.pagination-ellipsis {
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
    .foods-classification-container {
        grid-template-columns: 1fr !important;
        gap: 1.5rem !important;
    }
    
    .categories-panel {
        position: static !important;
        order: 2 !important;
    }
    
    .classifier-content {
        order: 1 !important;
    }
}

@media (max-width: 768px) {
    .foods-classification-container {
        padding: 1rem !important;
    }
    
    .filter-row {
        flex-direction: column !important;
        align-items: stretch !important;
    }
    
    .foods-grid {
        grid-template-columns: 1fr !important;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    
    .bulk-controls {
        flex-direction: column !important;
        align-items: stretch !important;
    }
}
</style>

<div class="foods-classification-container">
    <!-- CATEGORIAS (LADO ESQUERDO) -->
    <div class="dashboard-card categories-panel">
        <h2><i class="fas fa-tags"></i> Categorias</h2>
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
            <div class="category-legend" data-category="<?= $key ?>" style="--category-color: <?= $cat['color'] ?>; --category-bg: <?= $cat['color'] ?>20;">
                <div class="category-legend-header">
                    <div class="category-legend-icon">
                        <i class="fas <?= $cat['icon'] ?>"></i>
                    </div>
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

    <!-- CONTEÚDO PRINCIPAL (LADO DIREITO) -->
    <div class="classifier-content">
        <!-- Header Card -->
        <div class="dashboard-card classifier-header-card">
            <div class="header-title">
                <h2><i class="fas fa-apple-alt"></i> Alimentos</h2>
                <p>Gerencie e classifique todos os alimentos do sistema</p>
            </div>

            <!-- Estatísticas -->
            <div class="stats-grid">
                <div class="stats-card">
                    <div class="stat-number"><?= $total_items ?></div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stats-card">
                    <div class="stat-number" id="classified-count"><?= $classified_count ?></div>
                    <div class="stat-label">Classificados</div>
                </div>
                <div class="stats-card">
                    <div class="stat-number" id="remaining-count"><?= $total_items - $classified_count ?></div>
                    <div class="stat-label">Restantes</div>
                </div>
                <div class="stats-card">
                    <div class="stat-number" id="session-count">0</div>
                    <div class="stat-label">Nesta Sessão</div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="dashboard-card filters-section">
            <h3 class="filters-title"><i class="fas fa-search"></i> Buscar</h3>
            <form method="GET" class="filter-row">
                <div class="form-group">
                    <input type="text" 
                           class="form-control food-search-input" 
                           name="search" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Nome do alimento...">
                </div>
                <div class="form-group">
                    <select class="category-select" name="category">
                        <option value="">Todas as categorias</option>
                        <?php foreach ($categories as $key => $cat): ?>
                            <option value="<?= $key ?>" <?= $category_filter === $key ? 'selected' : '' ?>>
                                <i class="fas <?= $cat['icon'] ?>"></i> <?= $cat['name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
                <?php if (!empty($search) || !empty($category_filter)): ?>
                <div class="form-group">
                    <a href="food_classification.php" class="clear-btn">
                        <i class="fas fa-times"></i> Limpar
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Ações em Lote -->
        <div class="dashboard-card bulk-actions">
            <h3 class="bulk-title"><i class="fas fa-bolt"></i> Ações em Lote</h3>
            <div class="bulk-controls">
                <select class="bulk-select" id="bulk-category">
                    <option value="">Selecione uma categoria</option>
                    <?php foreach ($categories as $key => $cat): ?>
                        <option value="<?= $key ?>"><i class="fas <?= $cat['icon'] ?>"></i> <?= $cat['name'] ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="bulk-btn" onclick="applyBulkClassification()" id="bulk-btn" disabled>
                    <i class="fas fa-check"></i> Aplicar aos Selecionados
                </button>
                <label class="bulk-checkbox">
                    <input type="checkbox" id="select-all">
                    Selecionar Todos
                </label>
            </div>
        </div>

        <!-- Lista de Alimentos -->
        <?php if (empty($foods)): ?>
            <div class="dashboard-card empty-state-card">
                <div class="empty-state-content">
                    <i class="fas fa-apple-alt"></i>
                    <p>Nenhum alimento encontrado.</p>
                    <?php if (!empty($search) || !empty($category_filter)): ?>
                        <a href="food_classification.php" class="btn-primary">Ver Todos os Alimentos</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="foods-grid" id="foods-list">
                <?php foreach ($foods as $food): ?>
                    <div class="food-card <?= !empty($food['categories']) ? 'classified' : 'unclassified' ?>" data-food-id="<?= $food['id'] ?>">
                        <div class="food-header">
                            <input class="food-checkbox" type="checkbox" value="<?= $food['id'] ?>">
                            <div class="food-info">
                                <div class="food-name">
                                    <?= htmlspecialchars($food['name_pt']) ?>
                                </div>
                                <?php if (!empty($food['brand']) && $food['brand'] !== 'TACO'): ?>
                                    <div class="food-brand">
                                        <i class="fas fa-tag"></i>
                                        <?= htmlspecialchars($food['brand']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="food-macros">
                                    <div class="macro-item">
                                        <div class="macro-label">Calorias</div>
                                        <div class="macro-value"><?= number_format($food['energy_kcal_100g'], 0) ?>kcal</div>
                                    </div>
                                    <div class="macro-item">
                                        <div class="macro-label">Proteína</div>
                                        <div class="macro-value"><?= number_format($food['protein_g_100g'], 1) ?>g</div>
                                    </div>
                                    <div class="macro-item">
                                        <div class="macro-label">Carboidratos</div>
                                        <div class="macro-value"><?= number_format($food['carbohydrate_g_100g'], 1) ?>g</div>
                                    </div>
                                    <div class="macro-item">
                                        <div class="macro-label">Gorduras</div>
                                        <div class="macro-value"><?= number_format($food['fat_g_100g'], 1) ?>g</div>
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
                                                    '<span class="category-tag" style="background: %s20; color: %s; border: 1px solid %s40;"><i class="fas %s"></i> %s</span>',
                                                    $cat_info['color'], $cat_info['color'], $cat_info['color'], $cat_info['icon'], $cat_info['name']
                                                );
                                            }
                                        }
                                        echo $tagsHtml;
                                    } else {
                                        echo '<span class="unclassified-tag"><i class="fas fa-exclamation-circle"></i> Não classificado</span>';
                                    }
                                    ?>
                                </div>
                                <div class="food-actions">
                                    <?php foreach ($categories as $key => $cat): ?>
                                        <button class="category-btn" 
                                                data-food-id="<?= $food['id'] ?>"
                                                data-category="<?= $key ?>"
                                                style="--category-color: <?= $cat['color'] ?>; --category-bg: <?= $cat['color'] ?>20;">
                                            <i class="fas <?= $cat['icon'] ?>"></i>
                                            <span><?= $cat['name'] ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                    
                                    <!-- Botão para editar unidades -->
                                    <button class="units-btn <?= empty($food['categories']) ? 'disabled' : '' ?>" 
                                            data-food-id="<?= $food['id'] ?>"
                                            onclick="openUnitsEditor(<?= $food['id'] ?>, '<?= htmlspecialchars($food['name_pt']) ?>', getFoodCategories(<?= $food['id'] ?>))">
                                        <i class="fas fa-ruler"></i>
                                        <span>Unidades</span>
                                        <?php if (empty($food['categories'])): ?>
                                            <small class="units-hint">Salve a classificação primeiro</small>
                                        <?php endif; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Paginação -->
            <?php if ($total_pages > 1): ?>
                <div class="dashboard-card pagination-card">
                    <div class="pagination-info">
                        Mostrando <?= ($offset + 1) ?> - <?= min($offset + $per_page, $total_items) ?> de <?= number_format($total_items) ?> alimentos
                    </div>
                    <div class="pagination-controls">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>" 
                               class="pagination-btn">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </a>
                        <?php endif; ?>
                        
                        <div class="pagination-numbers">
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1): ?>
                                <a href="?page=1&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>" 
                                   class="pagination-number">1</a>
                                <?php if ($start_page > 2): ?>
                                    <span class="pagination-ellipsis">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="pagination-number current"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>" 
                                       class="pagination-number"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <span class="pagination-ellipsis">...</span>
                                <?php endif; ?>
                                <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>" 
                                   class="pagination-number"><?= $total_pages ?></a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>" 
                               class="pagination-btn">
                                Próxima <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Auto-save indicator -->
<div class="auto-save-indicator" id="auto-save-indicator">
    <i class="fas fa-save"></i> Salvo!
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
        if (e.target.closest('.category-btn')) {
            const btn = e.target.closest('.category-btn');
            const foodId = btn.dataset.foodId;
            const category = btn.dataset.category;
            toggleCategory(foodId, category);
        }
    });
    
    // 3. Selecionar todos
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.food-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBulkButton();
        });
    }
    
    // 4. Atualizar botão bulk quando checkboxes mudarem
    document.querySelectorAll('.food-checkbox').forEach(cb => {
        cb.addEventListener('change', updateBulkButton);
    });
    
    // 5. Bulk category select
    const bulkCategorySelect = document.getElementById('bulk-category');
    if (bulkCategorySelect) {
        bulkCategorySelect.addEventListener('change', updateBulkButton);
    }
});

function updateBulkButton() {
    const selected = document.querySelectorAll('.food-checkbox:checked');
    const bulkCategory = document.getElementById('bulk-category').value;
    const bulkBtn = document.getElementById('bulk-btn');
    
    if (bulkBtn) {
        bulkBtn.disabled = selected.length === 0 || !bulkCategory;
    }
}

function applyBulkClassification() {
    const selected = document.querySelectorAll('.food-checkbox:checked');
    const bulkCategory = document.getElementById('bulk-category').value;
    
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
    document.querySelectorAll('.food-checkbox').forEach(cb => cb.checked = false);
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
            return `<span class="category-tag" style="background: ${catInfo.color}20; color: ${catInfo.color}; border: 1px solid ${catInfo.color}40;"><i class="fas ${catInfo.icon}"></i> ${catInfo.name}</span>`;
        }).join('');
        categoryDisplay.innerHTML = tagsHtml;
        foodCard.classList.add('classified');
        foodCard.classList.remove('unclassified');
        
        // Habilita botão de unidades
        const unitsBtn = foodCard.querySelector('.units-btn');
        if (unitsBtn) {
            unitsBtn.classList.remove('disabled');
        }
    } else {
        categoryDisplay.innerHTML = '<span class="unclassified-tag"><i class="fas fa-exclamation-circle"></i> Não classificado</span>';
        foodCard.classList.remove('classified');
        foodCard.classList.add('unclassified');
        
        // Desabilita botão de unidades
        const unitsBtn = foodCard.querySelector('.units-btn');
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
