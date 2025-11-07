<?php
// admin/foods_management_new.php - Gerenciamento de Alimentos - Design Profissional

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'foods';
$page_title = 'Gerenciar Alimentos';

// --- Lógica de busca e filtro ---
$search_term = trim($_GET['search'] ?? '');
$source_filter = $_GET['source'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// --- Estatísticas gerais ---
$stats = [];

// Total de alimentos
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM sf_food_items")->fetch_assoc()['count'];

// Por fonte
$stats_query = "SELECT source_table, COUNT(*) as count FROM sf_food_items GROUP BY source_table ORDER BY count DESC";
$stats_result = $conn->query($stats_query);
while ($row = $stats_result->fetch_assoc()) {
    $stats['by_source'][$row['source_table']] = $row['count'];
}

// --- Construir query de busca ---
$sql = "SELECT * FROM sf_food_items";
$conditions = [];
$params = [];
$types = '';

if (!empty($search_term)) {
    $conditions[] = "name_pt LIKE ?";
    $params[] = '%' . $search_term . '%';
    $types .= 's';
}

if (!empty($source_filter)) {
    $conditions[] = "source_table = ?";
    $params[] = $source_filter;
    $types .= 's';
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY name_pt ASC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

// Executar query
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $foods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $foods = [];
}

// Contar total para paginação
$count_sql = "SELECT COUNT(*) as count FROM sf_food_items";
$count_conditions = [];
$count_params = [];
$count_types = '';

if (!empty($search_term)) {
    $count_conditions[] = "name_pt LIKE ?";
    $count_params[] = '%' . $search_term . '%';
    $count_types .= 's';
}

if (!empty($source_filter)) {
    $count_conditions[] = "source_table = ?";
    $count_params[] = $source_filter;
    $count_types .= 's';
}

if (!empty($count_conditions)) {
    $count_sql .= " WHERE " . implode(" AND ", $count_conditions);
}

$count_stmt = $conn->prepare($count_sql);
if ($count_stmt) {
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
    $count_stmt->execute();
    $total_items = $count_stmt->get_result()->fetch_assoc()['count'];
    $count_stmt->close();
} else {
    $total_items = 0;
}

$total_pages = ceil($total_items / $per_page);

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ========================================================================= */
/*       FOODS MANAGEMENT PAGE - ESTILO PROFISSIONAL                        */
/* ========================================================================= */

.foods-page-container {
    padding: 1.5rem 2rem;
    min-height: 100vh;
}

/* Header Card - usando estilo dashboard-card */
.foods-header-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.foods-header-card:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-1px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
    border-color: var(--accent-orange);
}

.card-header-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    gap: 1.5rem;
}

.header-title h2 {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.header-title h2 i {
    color: var(--accent-orange);
}

.header-title p {
    color: var(--text-secondary);
    font-size: 0.95rem;
    margin: 0;
}

/* Stats Grid - cards menores e mesmo tamanho */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 250px));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 1rem;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    cursor: pointer;
    min-height: 100px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.stat-card:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-1px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
    border-color: var(--accent-orange);
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--accent-orange);
    margin-bottom: 0.25rem;
    line-height: 1.2;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

/* Filter Form */
.foods-filter-form {
    flex: 1;
    min-width: 0;
}

.filter-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.food-search-input {
    flex: 1;
    min-width: 200px;
    max-width: 400px;
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
    color: var(--text-primary);
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    outline: none;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
    font-weight: 600;
}

.food-search-input:focus {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
}

.food-search-input::placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

.custom-select-wrapper {
    position: relative;
    min-width: 180px;
    max-width: 250px;
}

.custom-select {
    position: relative;
    width: 100%;
}

.custom-select-trigger {
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    color: var(--text-primary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.3s ease;
    font-size: 0.95rem;
    font-weight: 600;
}

.custom-select-trigger:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.custom-select-trigger i {
    color: var(--text-secondary);
    transition: transform 0.3s ease;
}

.custom-select.active .custom-select-trigger i {
    transform: rotate(180deg);
}

.custom-select-options {
    position: absolute;
    top: calc(100% + 0.5rem);
    left: 0;
    right: 0;
    background: rgba(26, 26, 26, 0.98);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
    z-index: 1000;
    max-height: 300px;
    overflow-y: auto;
    display: none;
}

.custom-select.active .custom-select-options {
    display: block;
}

.custom-select-option {
    padding: 0.875rem 1.25rem;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.95rem;
}

.custom-select-option:hover {
    background: rgba(255, 107, 0, 0.1);
    color: var(--accent-orange);
}

.custom-select-option.selected {
    background: rgba(255, 107, 0, 0.15);
    color: var(--accent-orange);
}

/* Buttons */
.btn-add-recipe-circular {
    width: 64px;
    height: 64px;
    min-width: 64px;
    min-height: 64px;
    max-width: 64px;
    max-height: 64px;
    border-radius: 50%;
    background: rgba(255, 107, 0, 0.08);
    border: 1px solid rgba(255, 107, 0, 0.2);
    color: var(--accent-orange);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    margin: 0;
    transition: all 0.3s ease;
    text-decoration: none;
    flex-shrink: 0;
}

.btn-add-recipe-circular:hover {
    background: rgba(255, 107, 0, 0.15);
    border-color: var(--accent-orange);
    transform: scale(1.05);
}

.btn-add-recipe-circular i {
    font-size: 1.5rem;
}

.btn-filter-circular {
    width: 48px;
    height: 48px;
    min-width: 48px;
    min-height: 48px;
    max-width: 48px;
    max-height: 48px;
    border-radius: 50%;
    background: rgba(255, 107, 0, 0.08);
    border: 1px solid rgba(255, 107, 0, 0.2);
    color: var(--accent-orange);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    margin: 0;
    transition: transform 0.2s ease;
}

.btn-filter-circular:hover {
    transform: scale(1.1);
}

.btn-filter-circular:active {
    transform: scale(0.95);
}

.btn-secondary {
    padding: 0.75rem 1.5rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    color: #FFFFFF;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    font-weight: 600;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.3);
    transform: translateY(-1px);
}

/* Data Table - usando estilo dashboard-card idêntico */
.foods-table-container {
    /* Herda todos os estilos de .dashboard-card do admin_novo_style.css */
    /* padding: 2rem já vem do dashboard-card */
}

.foods-table-container h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: #FFFFFF;
    margin: 0 0 1.5rem 0;
    font-family: 'Montserrat', sans-serif;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.foods-table-container h3 i {
    color: var(--accent-orange);
}

.table-content {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
    background: transparent !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    box-shadow: none !important;
    filter: none !important;
    table-layout: fixed;
}

.data-table thead {
    background: transparent !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    box-shadow: none !important;
}

.data-table th {
    padding: 1rem 1.5rem;
    text-align: left;
    font-size: 0.875rem;
    font-weight: 700;
    color: #FFFFFF;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    background: transparent !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    box-shadow: none !important;
    filter: none !important;
    vertical-align: middle;
}

.data-table th:last-child {
    text-align: right;
}

.data-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-size: 0.95rem;
    vertical-align: middle;
    background: transparent !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    box-shadow: none !important;
    filter: none !important;
}

.data-table tbody {
    background: transparent !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
}

.data-table tbody tr {
    background: transparent !important;
    transition: background-color 0.2s ease;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    box-shadow: none !important;
    filter: none !important;
}

.data-table tbody tr:hover {
    background-color: rgba(255, 255, 255, 0.05) !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    box-shadow: none !important;
    filter: none !important;
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}

.food-name-cell {
    font-weight: 600;
    color: var(--text-primary);
}

.food-brand-cell {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.food-brand-cell i {
    color: var(--accent-orange);
    font-size: 0.75rem;
}

.macro-value {
    font-weight: 600;
    color: var(--accent-orange);
}

/* Source Badges - cores diferentes para cada fonte */
.source-badge {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: 1px solid;
    max-width: 100%;
    box-sizing: border-box;
    word-wrap: break-word;
    white-space: normal;
}

.source-badge.taco {
    background: rgba(34, 197, 94, 0.15);
    border-color: rgba(34, 197, 94, 0.4);
    color: #22C55E;
}

.source-badge.sonia {
    background: rgba(59, 130, 246, 0.15);
    border-color: rgba(59, 130, 246, 0.4);
    color: #3B82F6;
}

.source-badge.sonia-updated {
    background: rgba(147, 51, 234, 0.15);
    border-color: rgba(147, 51, 234, 0.4);
    color: #9333EA;
}

.source-badge.usda {
    background: rgba(236, 72, 153, 0.15);
    border-color: rgba(236, 72, 153, 0.4);
    color: #EC4899;
}

.source-badge.fatsecret {
    background: rgba(255, 107, 0, 0.15);
    border-color: rgba(255, 107, 0, 0.4);
    color: #FF6B00;
}

.source-badge.manual {
    background: rgba(168, 85, 247, 0.15);
    border-color: rgba(168, 85, 247, 0.4);
    color: #A855F7;
}

.source-badge.user-created {
    background: rgba(251, 191, 36, 0.15);
    border-color: rgba(251, 191, 36, 0.4);
    color: #FBBF24;
}

.source-badge.user-off {
    background: rgba(107, 114, 128, 0.15);
    border-color: rgba(107, 114, 128, 0.4);
    color: #6B7280;
}

/* Legenda de Fontes */
.source-legend {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.source-legend-title {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.source-legend-items {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.source-legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: var(--text-secondary);
}

/* Action Buttons */
.actions {
    display: flex;
    gap: 0.75rem;
}

.btn-action {
    padding: 0.625rem 1rem;
    border-radius: 10px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    white-space: nowrap;
}

.btn-action.edit {
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.3);
    color: var(--accent-orange);
}

.btn-action.edit:hover {
    background: rgba(255, 107, 0, 0.2);
    border-color: var(--accent-orange);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(255, 107, 0, 0.3);
}

.btn-action.delete {
    background: rgba(244, 67, 54, 0.1);
    border: 1px solid rgba(244, 67, 54, 0.3);
    color: #F44336;
}

.btn-action.delete:hover {
    background: rgba(244, 67, 54, 0.2);
    border-color: #F44336;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(244, 67, 54, 0.3);
}

/* Empty State - usando estilo dashboard-card */
.empty-state-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 4rem 2rem;
    text-align: center;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    transition: all 0.3s ease;
}

.empty-state-card:hover {
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
    border-color: var(--accent-orange);
}

.empty-state-content i {
    font-size: 4rem;
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
    opacity: 0.5;
}

.empty-state-content p {
    font-size: 1.125rem;
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
}

.btn-primary {
    padding: 0.875rem 2rem;
    background: linear-gradient(135deg, #FF6600, #FF8533);
    border: none;
    border-radius: 12px;
    color: #FFFFFF;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    font-weight: 600;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #FF8533, #FF6600);
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(255, 102, 0, 0.3);
}

/* Pagination - usando estilo dashboard-card */
.pagination-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    transition: all 0.3s ease;
}

.pagination-card:hover {
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
    border-color: var(--accent-orange);
}

.pagination-info {
    color: var(--text-secondary);
    font-size: 0.95rem;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pagination-btn {
    padding: 0.625rem 1.25rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    color: #FFFFFF;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.pagination-btn:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.3);
    transform: translateY(-1px);
}

.pagination-numbers {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pagination-number {
    min-width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    color: #FFFFFF;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.pagination-number:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.3);
    transform: translateY(-1px);
}

.pagination-number.current {
    background: linear-gradient(135deg, #FF6600, #FF8533);
    border-color: transparent;
    color: #FFFFFF;
}

.pagination-ellipsis {
    color: var(--text-secondary);
    padding: 0 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .foods-page-container {
        padding: 1rem 0.75rem;
    }
    
    .foods-header-card {
        padding: 1.5rem;
    }
    
    .card-header-section {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .data-table {
        font-size: 0.875rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 1rem;
    }
    
    .actions {
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>

<div class="foods-page-container">
    <!-- Header Card -->
    <div class="dashboard-card foods-header-card">
        <div class="card-header-section">
            <div class="header-title">
                <h2><i class="fas fa-apple-alt"></i> Gerenciar Alimentos</h2>
                <p>Gerencie todos os alimentos cadastrados no sistema</p>
            </div>
            <a href="edit_food_new.php" class="btn-add-recipe-circular" title="Novo Alimento">
                <i class="fas fa-plus"></i>
            </a>
        </div>
        
        <!-- Filtros -->
        <form method="GET" action="foods_management_new.php" class="foods-filter-form">
            <div class="filter-row">
                <div class="form-group">
                    <input type="text" 
                           name="search" 
                           placeholder="Buscar por nome do alimento..." 
                           value="<?php echo htmlspecialchars($search_term); ?>" 
                           class="form-control food-search-input">
                </div>
                <div class="form-group">
                    <div class="custom-select-wrapper" id="source_select_wrapper">
                        <input type="hidden" name="source" id="source_input" value="<?php echo htmlspecialchars($source_filter); ?>">
                        <div class="custom-select" id="source_select">
                            <div class="custom-select-trigger">
                                <span class="custom-select-value">
                                    <?php 
                                    if ($source_filter) {
                                        switch ($source_filter) {
                                            case 'TACO': echo 'TACO'; break;
                                            case 'Sonia Tucunduva': echo 'Sonia Tucunduva'; break;
                                            case 'Sonia Tucunduva (Prioridade)': echo 'Sonia (Atualizado)'; break;
                                            case 'USDA': echo 'USDA'; break;
                                            case 'FatSecret': echo 'FatSecret'; break;
                                            default: echo htmlspecialchars($source_filter);
                                        }
                                    } else {
                                        echo 'Todas as Fontes';
                                    }
                                    ?>
                                </span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="custom-select-options">
                                <div class="custom-select-option <?php echo empty($source_filter) ? 'selected' : ''; ?>" data-value="">Todas as Fontes</div>
                                <div class="custom-select-option <?php echo $source_filter === 'TACO' ? 'selected' : ''; ?>" data-value="TACO">TACO</div>
                                <div class="custom-select-option <?php echo $source_filter === 'Sonia Tucunduva' ? 'selected' : ''; ?>" data-value="Sonia Tucunduva">Sonia Tucunduva</div>
                                <div class="custom-select-option <?php echo $source_filter === 'Sonia Tucunduva (Prioridade)' ? 'selected' : ''; ?>" data-value="Sonia Tucunduva (Prioridade)">Sonia (Atualizado)</div>
                                <div class="custom-select-option <?php echo $source_filter === 'USDA' ? 'selected' : ''; ?>" data-value="USDA">USDA</div>
                                <div class="custom-select-option <?php echo $source_filter === 'FatSecret' ? 'selected' : ''; ?>" data-value="FatSecret">FatSecret</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn-filter-circular" title="Filtrar">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <?php if (!empty($search_term) || !empty($source_filter)): ?>
                <div class="form-group">
                    <a href="foods_management_new.php" class="btn-secondary">
                        <i class="fas fa-times"></i> Limpar
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Estatísticas -->
    <div class="stats-grid">
        <div class="stat-card" onclick="window.location.href='foods_management_new.php'">
            <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label">Total de Alimentos</div>
        </div>
        
        <?php foreach ($stats['by_source'] as $source => $count): 
            $sourceParam = urlencode($source);
            $labelText = '';
            switch ($source) {
                case 'TACO': 
                    $labelText = 'TACO'; 
                    break;
                case 'Sonia Tucunduva': 
                    $labelText = 'Sonia'; 
                    break;
                case 'Sonia Tucunduva (Prioridade)': 
                    $labelText = 'Sonia (Atualizado)'; 
                    break;
                case 'USDA': 
                    $labelText = 'USDA'; 
                    break;
                case 'FatSecret': 
                    $labelText = 'FatSecret'; 
                    break;
                case 'Manual': 
                    $labelText = 'Manual'; 
                    break;
                case 'user_created': 
                    $labelText = 'Criado por Usuário'; 
                    break;
                case 'user_off': 
                    $labelText = 'Desativado'; 
                    break;
                default: 
                    $labelText = htmlspecialchars($source);
            }
        ?>
            <div class="stat-card" onclick="window.location.href='foods_management_new.php?source=<?php echo $sourceParam; ?>'">
                <div class="stat-number"><?php echo number_format($count); ?></div>
                <div class="stat-label"><?php echo $labelText; ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Legenda de Fontes -->
    <div class="source-legend">
        <div class="source-legend-title">
            <i class="fas fa-info-circle"></i> Legenda de Fontes
        </div>
        <div class="source-legend-items">
            <div class="source-legend-item">
                <span class="source-badge taco">TACO</span>
            </div>
            <div class="source-legend-item">
                <span class="source-badge sonia">Sonia</span>
            </div>
            <div class="source-legend-item">
                <span class="source-badge sonia-updated">Sonia (Atualizado)</span>
            </div>
            <div class="source-legend-item">
                <span class="source-badge usda">USDA</span>
            </div>
            <div class="source-legend-item">
                <span class="source-badge fatsecret">FatSecret</span>
            </div>
            <div class="source-legend-item">
                <span class="source-badge manual">Manual</span>
            </div>
            <div class="source-legend-item">
                <span class="source-badge user-created">Criado por Usuário</span>
            </div>
            <div class="source-legend-item">
                <span class="source-badge user-off">Desativado</span>
            </div>
        </div>
    </div>

    <!-- Tabela de Alimentos -->
    <?php if (empty($foods)): ?>
        <div class="dashboard-card empty-state-card">
            <div class="empty-state-content">
                <i class="fas fa-apple-alt"></i>
                <p>Nenhum alimento encontrado.</p>
                <?php if (!empty($search_term) || !empty($source_filter)): ?>
                    <a href="foods_management_new.php" class="btn-primary">Ver Todos os Alimentos</a>
                <?php else: ?>
                    <a href="edit_food_new.php" class="btn-primary">Adicionar Primeiro Alimento</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="dashboard-card foods-table-container">
            <h3>
                <i class="fas fa-list"></i>
                Alimentos 
                <?php if (!empty($search_term) || !empty($source_filter)): ?>
                    - Filtrados
                <?php endif; ?>
                <span style="color: var(--text-secondary); font-weight: 400; margin-left: 0.5rem; font-size: 0.875rem;">
                    (<?php echo number_format($total_items); ?> total)
                </span>
            </h3>
            
            <div class="table-content">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: auto; text-align: left;">Nome</th>
                            <th style="width: 220px; text-align: left;">Fonte</th>
                            <th style="width: 240px; text-align: right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($foods as $food): ?>
                            <tr>
                                <td style="text-align: left; vertical-align: top;">
                                    <div class="food-name-cell">
                                        <?php echo htmlspecialchars($food['name_pt']); ?>
                                    </div>
                                    <?php if (!empty($food['brand']) && $food['brand'] !== 'TACO'): ?>
                                        <div class="food-brand-cell">
                                            <i class="fas fa-tag"></i>
                                            <?php echo htmlspecialchars($food['brand']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: left; vertical-align: middle;">
                                    <?php 
                                    $source = $food['source_table'];
                                    $badgeClass = '';
                                    $badgeText = '';
                                    
                                    switch ($source) {
                                        case 'TACO':
                                            $badgeClass = 'taco';
                                            $badgeText = 'TACO';
                                            break;
                                        case 'Sonia Tucunduva':
                                            $badgeClass = 'sonia';
                                            $badgeText = 'Sonia';
                                            break;
                                        case 'Sonia Tucunduva (Prioridade)':
                                            $badgeClass = 'sonia-updated';
                                            $badgeText = 'Sonia (Atualizado)';
                                            break;
                                        case 'USDA':
                                            $badgeClass = 'usda';
                                            $badgeText = 'USDA';
                                            break;
                                        case 'FatSecret':
                                            $badgeClass = 'fatsecret';
                                            $badgeText = 'FatSecret';
                                            break;
                                        case 'Manual':
                                            $badgeClass = 'manual';
                                            $badgeText = 'Manual';
                                            break;
                                        case 'user_created':
                                            $badgeClass = 'user-created';
                                            $badgeText = 'Criado por Usuário';
                                            break;
                                        case 'user_off':
                                            $badgeClass = 'user-off';
                                            $badgeText = 'Desativado';
                                            break;
                                        default:
                                            $badgeClass = 'manual';
                                            $badgeText = htmlspecialchars($source);
                                    }
                                    ?>
                                    <span class="source-badge <?php echo $badgeClass; ?>">
                                        <?php echo $badgeText; ?>
                                    </span>
                                </td>
                                <td style="text-align: right; vertical-align: middle;">
                                    <div class="actions" style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem;">
                                        <button type="button" onclick="openEditFoodModal(<?php echo $food['id']; ?>)" class="btn-action edit">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <a href="process_food.php?action=delete&id=<?php echo $food['id']; ?>" 
                                           class="btn-action delete" 
                                           onclick="return confirm('Tem certeza que deseja excluir este alimento? Esta ação não pode ser desfeita.');">
                                            <i class="fas fa-trash"></i> Excluir
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Paginação -->
        <?php if ($total_pages > 1): ?>
            <div class="dashboard-card pagination-card">
                <div class="pagination-info">
                    Mostrando <?php echo ($offset + 1); ?> - <?php echo min($offset + $per_page, $total_items); ?> de <?php echo number_format($total_items); ?> alimentos
                </div>
                <div class="pagination-controls">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                           class="btn-secondary pagination-btn">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </a>
                    <?php endif; ?>
                    
                    <div class="pagination-numbers">
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" 
                               class="pagination-number">1</a>
                            <?php if ($start_page > 2): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="pagination-number current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="pagination-number"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" 
                               class="pagination-number"><?php echo $total_pages; ?></a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                           class="btn-secondary pagination-btn">
                            Próxima <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// Custom Select Functionality
document.addEventListener('DOMContentLoaded', function() {
    const sourceSelect = document.getElementById('source_select');
    const sourceInput = document.getElementById('source_input');
    
    if (sourceSelect) {
        const trigger = sourceSelect.querySelector('.custom-select-trigger');
        const options = sourceSelect.querySelectorAll('.custom-select-option');
        
        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            sourceSelect.classList.toggle('active');
        });
        
        options.forEach(option => {
            option.addEventListener('click', function() {
                const value = this.dataset.value;
                sourceInput.value = value;
                trigger.querySelector('.custom-select-value').textContent = this.textContent;
                sourceSelect.querySelectorAll('.custom-select-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                sourceSelect.classList.remove('active');
            });
        });
        
        document.addEventListener('click', function(e) {
            if (!sourceSelect.contains(e.target)) {
                sourceSelect.classList.remove('active');
            }
        });
    }
});

// Modal de Edição de Alimento
let currentEditingFoodId = null;

function openEditFoodModal(foodId) {
    currentEditingFoodId = foodId;
    fetch(`ajax_get_food.php?id=${foodId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.food) {
                const food = data.food;
                document.getElementById('food-edit-title').textContent = 'Editar Alimento';
                document.getElementById('food-edit-action').value = 'edit';
                document.getElementById('food-edit-id').value = food.id;
                document.getElementById('food-name-pt').value = food.name_pt || '';
                document.getElementById('food-energy').value = food.energy_kcal_100g || 0;
                document.getElementById('food-protein').value = food.protein_g_100g || 0;
                document.getElementById('food-carbs').value = food.carbohydrate_g_100g || 0;
                document.getElementById('food-fat').value = food.fat_g_100g || 0;
                
                // Atualizar select customizado
                const sourceValue = food.source_table || 'Manual';
                const sourceInput = document.getElementById('food-source');
                const sourceSelect = document.getElementById('food-source-select');
                const sourceValueDisplay = sourceSelect.querySelector('.custom-select-value');
                const sourceOptions = sourceSelect.querySelectorAll('.custom-select-option');
                
                sourceInput.value = sourceValue;
                sourceOptions.forEach(opt => {
                    opt.classList.remove('selected');
                    if (opt.dataset.value === sourceValue) {
                        opt.classList.add('selected');
                        sourceValueDisplay.textContent = opt.textContent;
                    }
                });
                
                document.getElementById('food-delete-btn').style.display = 'flex';
                updateCalculations();
                document.getElementById('food-edit-modal').classList.add('active');
                document.body.style.overflow = 'hidden';
            } else {
                alert('Erro ao carregar dados do alimento');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao carregar dados do alimento');
        });
}

function closeFoodEditModal() {
    document.getElementById('food-edit-modal').classList.remove('active');
    document.body.style.overflow = '';
    currentEditingFoodId = null;
}

function updateCalculations() {
    const protein = parseFloat(document.getElementById('food-protein').value) || 0;
    const carbs = parseFloat(document.getElementById('food-carbs').value) || 0;
    const fat = parseFloat(document.getElementById('food-fat').value) || 0;
    const total = protein + carbs + fat;
    const calories = (protein * 4) + (carbs * 4) + (fat * 9);
    
    document.getElementById('calc-total').textContent = total.toFixed(1) + 'g';
    document.getElementById('calc-calories').textContent = calories.toFixed(1) + ' kcal';
    document.getElementById('food-calculations').style.display = 'block';
}

function saveFood() {
    const form = document.getElementById('food-edit-form');
    const formData = new FormData(form);
    const name = formData.get('name_pt').trim();
    if (!name) {
        alert('Nome do alimento é obrigatório');
        return;
    }
    const saveBtn = document.querySelector('.custom-modal-footer .btn-modal-confirm');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
    saveBtn.disabled = true;
    fetch('process_food.php?ajax=1', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Erro ao salvar: ' + (data.message || 'Erro desconhecido'));
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar alimento');
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

function deleteFood() {
    if (!currentEditingFoodId) return;
    if (!confirm('Tem certeza que deseja EXCLUIR este alimento?\n\nEsta ação não pode ser desfeita!')) {
        return;
    }
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', currentEditingFoodId);
    fetch('process_food.php?ajax=1', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Erro ao excluir: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao excluir alimento');
    });
}

// Adicionar listeners para cálculos em tempo real
document.addEventListener('DOMContentLoaded', function() {
    const inputs = ['food-protein', 'food-carbs', 'food-fat'];
    inputs.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', updateCalculations);
        }
    });
    
    // Inicializar custom select de fonte
    const sourceSelect = document.getElementById('food-source-select');
    if (sourceSelect) {
        const trigger = sourceSelect.querySelector('.custom-select-trigger');
        const options = sourceSelect.querySelectorAll('.custom-select-option');
        const sourceInput = document.getElementById('food-source');
        const valueDisplay = sourceSelect.querySelector('.custom-select-value');
        
        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            sourceSelect.classList.toggle('active');
        });
        
        options.forEach(option => {
            option.addEventListener('click', function() {
                const value = this.dataset.value;
                sourceInput.value = value;
                valueDisplay.textContent = this.textContent;
                options.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                sourceSelect.classList.remove('active');
            });
        });
        
        document.addEventListener('click', function(e) {
            if (!sourceSelect.contains(e.target)) {
                sourceSelect.classList.remove('active');
            }
        });
    }
});
</script>

<!-- Modal Adicionar/Editar Alimento -->
<div id="food-edit-modal" class="custom-modal">
    <div class="custom-modal-overlay" onclick="closeFoodEditModal()"></div>
    <div class="custom-modal-content">
        <button class="sleep-modal-close" onclick="closeFoodEditModal()" type="button">
            <i class="fas fa-times"></i>
        </button>
        <div class="custom-modal-header">
            <i class="fas fa-apple-alt"></i>
            <h3 id="food-edit-title">Editar Alimento</h3>
        </div>
        
        <div class="custom-modal-body">
            <form id="food-edit-form">
                <input type="hidden" id="food-edit-id" name="id" value="">
                <input type="hidden" name="action" id="food-edit-action" value="edit">
                
                <div class="form-group">
                    <label for="food-name-pt">Nome do Alimento *</label>
                    <input type="text" id="food-name-pt" name="name_pt" class="form-control" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="food-energy">Calorias (por 100g) *</label>
                        <input type="number" id="food-energy" name="energy_kcal_100g" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="food-protein">Proteína (por 100g) *</label>
                        <input type="number" id="food-protein" name="protein_g_100g" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="food-carbs">Carboidratos (por 100g) *</label>
                        <input type="number" id="food-carbs" name="carbohydrate_g_100g" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="food-fat">Gordura (por 100g) *</label>
                        <input type="number" id="food-fat" name="fat_g_100g" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="food-source">Fonte</label>
                    <div class="custom-select-wrapper" id="food-source-wrapper">
                        <input type="hidden" id="food-source" name="source_table" value="Manual">
                        <div class="custom-select" id="food-source-select">
                            <div class="custom-select-trigger">
                                <span class="custom-select-value">Manual</span>
                            </div>
                            <div class="custom-select-options">
                                <div class="custom-select-option" data-value="Manual">Manual</div>
                                <div class="custom-select-option" data-value="TACO">TACO</div>
                                <div class="custom-select-option" data-value="Sonia Tucunduva">Sonia Tucunduva</div>
                                <div class="custom-select-option" data-value="Sonia Tucunduva (Prioridade)">Sonia Tucunduva (Prioridade)</div>
                                <div class="custom-select-option" data-value="USDA">USDA</div>
                                <div class="custom-select-option" data-value="FatSecret">FatSecret</div>
                                <div class="custom-select-option" data-value="user_created">Criado por Usuário</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="food-calculations" class="food-calculations" style="display: none;">
                    <div class="calculation-item">
                        <span class="calc-label">Total Macronutrientes:</span>
                        <span class="calc-value" id="calc-total">0g</span>
                    </div>
                    <div class="calculation-item">
                        <span class="calc-label">Calorias Calculadas:</span>
                        <span class="calc-value" id="calc-calories">0 kcal</span>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="custom-modal-footer">
            <button type="button" class="btn-modal-cancel" onclick="closeFoodEditModal()">Cancelar</button>
            <button type="button" id="food-delete-btn" class="btn-modal-danger" onclick="deleteFood()" style="display: none;">
                <i class="fas fa-trash"></i> Excluir
            </button>
            <button type="button" class="btn-modal-confirm" onclick="saveFood()">
                <i class="fas fa-save"></i> Salvar
            </button>
        </div>
    </div>
</div>

<style>
/* ========== MODAL - REFATORADO DO ZERO ========== */
/* Custom Select - estilo do recipes.php */
.custom-modal-body .custom-select-wrapper {
    position: relative;
    width: 100%;
}

.custom-modal-body .custom-select {
    position: relative;
    width: 100%;
}

.custom-modal-body .custom-select-trigger {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.95rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
    box-sizing: border-box;
    font-weight: 600;
    font-family: 'Montserrat', sans-serif;
}

.custom-modal-body .custom-select-trigger:hover {
    border-color: var(--accent-orange);
}

.custom-modal-body .custom-select-value {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.custom-modal-body .custom-select-options {
    display: none;
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    z-index: 1000;
    background: rgba(35, 35, 35, 0.9);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
    max-height: 250px;
    overflow-y: auto;
    box-sizing: border-box;
}

.custom-modal-body .custom-select.active .custom-select-options {
    display: block;
}

.custom-modal-body .custom-select-option {
    padding: 0.75rem 1rem;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.2s ease;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.custom-modal-body .custom-select-option:last-child {
    border-bottom: none;
}

.custom-modal-body .custom-select-option:hover {
    background: rgba(255, 107, 0, 0.1);
    color: var(--accent-orange);
}

.custom-modal-body .custom-select-option.selected {
    background: rgba(255, 107, 0, 0.15);
    color: var(--accent-orange);
    font-weight: 600;
}

/* Form Groups - Alinhamento Perfeito */
.custom-modal-body .form-group {
    margin-bottom: 1.5rem;
    display: flex;
    flex-direction: column;
    width: 100%;
}

.custom-modal-body .form-group:last-child {
    margin-bottom: 0;
}

.custom-modal-body .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    width: 100%;
}

.custom-modal-body .form-group .form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.95rem;
    font-weight: 600;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
    box-sizing: border-box;
    margin: 0;
}

.custom-modal-body .form-group .form-control:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
}

/* Form Row - Alinhamento Simétrico */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    align-items: start;
    width: 100%;
}

.form-row .form-group {
    margin-bottom: 0;
    width: 100%;
}

/* Food Calculations */
.food-calculations {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 1rem;
    margin-top: 1rem;
    width: 100%;
    box-sizing: border-box;
}

.calculation-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    width: 100%;
}

.calculation-item:last-child {
    margin-bottom: 0;
}

.calc-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-weight: 600;
}

.calc-value {
    font-size: 1rem;
    color: var(--accent-orange);
    font-weight: 700;
}

/* Botão Excluir - Estilo Correto */
.btn-modal-danger {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border: none;
    font-family: 'Montserrat', sans-serif;
    background: var(--danger-red);
    color: white;
    border: 1px solid var(--danger-red);
    height: auto;
    min-height: auto;
}

.btn-modal-danger:hover {
    background: #d32f2f;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(244, 67, 54, 0.4);
}

/* Footer do Modal - Alinhamento Perfeito */
.custom-modal-footer {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    align-items: center;
    padding: 1.5rem 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    flex-wrap: wrap;
}

.custom-modal-footer button {
    flex-shrink: 0;
    white-space: nowrap;
}

/* ========== TABELA - ALINHAMENTO PERFEITO ========== */
.data-table th {
    padding: 1rem 1.5rem;
    text-align: left;
    font-size: 0.875rem;
    font-weight: 700;
    color: #FFFFFF;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    background: transparent !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    box-shadow: none !important;
    filter: none !important;
    vertical-align: middle;
    overflow: hidden;
    text-overflow: ellipsis;
}

.data-table th:first-child {
    width: auto;
    white-space: normal;
}

.data-table th:nth-child(2) {
    width: 220px;
    min-width: 220px;
    max-width: 220px;
}

.data-table th:last-child {
    text-align: right;
    width: 240px;
    min-width: 240px;
    max-width: 240px;
}

.data-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-size: 0.95rem;
    vertical-align: middle;
    background: transparent !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    box-shadow: none !important;
    filter: none !important;
    text-align: left;
    overflow: hidden;
    word-wrap: break-word;
}

.data-table td:first-child {
    white-space: normal;
    text-align: left;
    word-break: break-word;
    overflow-wrap: break-word;
}

.data-table td:nth-child(2) {
    width: 220px;
    min-width: 220px;
    max-width: 220px;
    text-align: left;
    white-space: normal;
    overflow: visible;
    word-wrap: break-word;
}

.data-table td:last-child {
    text-align: right;
    width: 240px;
    min-width: 240px;
    max-width: 240px;
    white-space: nowrap;
    overflow: visible;
}

/* Actions - Alinhamento Perfeito */
.actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
    flex-wrap: nowrap;
    width: 100%;
    box-sizing: border-box;
}

.btn-action {
    padding: 0.625rem 1rem;
    border-radius: 10px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
    height: auto;
    min-height: auto;
}

.btn-action.edit {
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.3);
    color: var(--accent-orange);
}

.btn-action.edit:hover {
    background: rgba(255, 107, 0, 0.2);
    border-color: var(--accent-orange);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(255, 107, 0, 0.3);
}

.btn-action.delete {
    background: rgba(244, 67, 54, 0.1);
    border: 1px solid rgba(244, 67, 54, 0.3);
    color: #F44336;
}

.btn-action.delete:hover {
    background: rgba(244, 67, 54, 0.2);
    border-color: #F44336;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(244, 67, 54, 0.3);
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
