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

/* Header Card */
.foods-header-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    position: relative;
}

.foods-header-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--accent-orange), #FFA500);
    border-radius: 20px 20px 0 0;
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

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--accent-orange);
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
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
    padding: 0.875rem 1.25rem;
    font-size: 0.95rem;
    color: var(--text-primary);
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    outline: none;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
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
    padding: 0.875rem 1.25rem;
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    color: var(--text-primary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.3s ease;
    font-size: 0.95rem;
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
    backdrop-filter: blur(20px);
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
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    color: var(--text-primary);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    font-weight: 500;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--accent-orange);
    color: var(--accent-orange);
}

/* Data Table */
.foods-table-container {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 0;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    overflow: hidden;
}

.table-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.02);
}

.table-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.table-title i {
    color: var(--accent-orange);
}

.table-content {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.data-table thead {
    background: rgba(255, 255, 255, 0.03);
    border-bottom: 2px solid rgba(255, 255, 255, 0.1);
}

.data-table th {
    padding: 1.25rem 1.5rem;
    text-align: left;
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--text-primary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.data-table td {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-size: 0.95rem;
    vertical-align: middle;
}

.data-table tbody tr {
    transition: background-color 0.2s ease;
}

.data-table tbody tr:hover {
    background-color: rgba(255, 107, 0, 0.08);
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

.source-badge {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.3);
    border-radius: 8px;
    color: var(--accent-orange);
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
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
    transform: translateY(-2px);
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
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(244, 67, 54, 0.3);
}

/* Empty State */
.empty-state-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 4rem 2rem;
    text-align: center;
    margin-bottom: 2rem;
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
    background: var(--accent-orange);
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
    background: #e65c00;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(255, 107, 0, 0.4);
}

/* Pagination */
.pagination-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
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
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    color: var(--text-primary);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.pagination-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--accent-orange);
    color: var(--accent-orange);
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
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    color: var(--text-primary);
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.pagination-number:hover {
    background: rgba(255, 107, 0, 0.1);
    border-color: var(--accent-orange);
    color: var(--accent-orange);
}

.pagination-number.current {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
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
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label">Total de Alimentos</div>
        </div>
        
        <?php foreach ($stats['by_source'] as $source => $count): ?>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($count); ?></div>
                <div class="stat-label">
                    <?php 
                    switch ($source) {
                        case 'TACO': echo 'TACO'; break;
                        case 'Sonia Tucunduva': echo 'Sonia'; break;
                        case 'Sonia Tucunduva (Prioridade)': echo 'Sonia (Atualizado)'; break;
                        case 'USDA': echo 'USDA'; break;
                        case 'FatSecret': echo 'FatSecret'; break;
                        default: echo htmlspecialchars($source);
                    }
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
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
            <div class="table-header">
                <h3 class="table-title">
                    <i class="fas fa-list"></i>
                    Alimentos 
                    <?php if (!empty($search_term) || !empty($source_filter)): ?>
                        - Filtrados
                    <?php endif; ?>
                    <span style="color: var(--text-secondary); font-weight: 400; margin-left: 0.5rem;">
                        (<?php echo number_format($total_items); ?> total)
                    </span>
                </h3>
            </div>
            
            <div class="table-content">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Calorias</th>
                            <th>Proteína</th>
                            <th>Carboidratos</th>
                            <th>Gordura</th>
                            <th>Fonte</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($foods as $food): ?>
                            <tr>
                                <td>
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
                                <td>
                                    <span class="macro-value"><?php echo number_format($food['energy_kcal_100g'], 0); ?></span>
                                    <span style="color: var(--text-secondary); font-size: 0.875rem;"> kcal</span>
                                </td>
                                <td>
                                    <span class="macro-value"><?php echo number_format($food['protein_g_100g'], 1); ?></span>
                                    <span style="color: var(--text-secondary); font-size: 0.875rem;">g</span>
                                </td>
                                <td>
                                    <span class="macro-value"><?php echo number_format($food['carbohydrate_g_100g'], 1); ?></span>
                                    <span style="color: var(--text-secondary); font-size: 0.875rem;">g</span>
                                </td>
                                <td>
                                    <span class="macro-value"><?php echo number_format($food['fat_g_100g'], 1); ?></span>
                                    <span style="color: var(--text-secondary); font-size: 0.875rem;">g</span>
                                </td>
                                <td>
                                    <span class="source-badge">
                                        <?php 
                                        $source = $food['source_table'];
                                        switch ($source) {
                                            case 'Sonia Tucunduva (Prioridade)': echo 'Sonia (Atualizado)'; break;
                                            default: echo htmlspecialchars($source);
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="edit_food_new.php?id=<?php echo $food['id']; ?>" class="btn-action edit">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
