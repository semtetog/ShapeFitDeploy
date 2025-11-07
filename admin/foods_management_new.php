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

// Total de alimentos globais (excluindo USER_OFF e alimentos criados por usuários)
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM sf_food_items WHERE source_table != 'USER_OFF' AND source_table != 'user_off' AND source_table != 'user-off' AND added_by_user_id IS NULL")->fetch_assoc()['count'];

// Por fonte (excluindo USER_OFF e alimentos criados por usuários para contagem geral)
$stats_query = "SELECT source_table, COUNT(*) as count 
                FROM sf_food_items 
                WHERE source_table != 'USER_OFF' 
                  AND source_table != 'user_off' 
                  AND source_table != 'user-off'
                  AND added_by_user_id IS NULL
                GROUP BY source_table 
                ORDER BY count DESC";
$stats_result = $conn->query($stats_query);
while ($row = $stats_result->fetch_assoc()) {
    $stats['by_source'][$row['source_table']] = $row['count'];
}

// Contagem separada para alimentos criados por usuários (para exibição no card)
$user_created_count = $conn->query("SELECT COUNT(*) as count FROM sf_food_items WHERE source_table = 'user_created' AND added_by_user_id IS NOT NULL")->fetch_assoc()['count'];
$stats['by_source']['user_created'] = $user_created_count;

// --- Construir query de busca ---
$sql = "SELECT * FROM sf_food_items";
$conditions = [];
$params = [];
$types = '';

// Sempre excluir USER_OFF (alimentos bugados)
$conditions[] = "source_table != 'USER_OFF' AND source_table != 'user_off' AND source_table != 'user-off'";

if (!empty($search_term)) {
    $conditions[] = "name_pt LIKE ?";
    $params[] = '%' . $search_term . '%';
    $types .= 's';
}

if (!empty($source_filter)) {
    if ($source_filter === 'user_created') {
        // Para "Criados por Usuário", mostrar apenas alimentos com added_by_user_id IS NOT NULL
        $conditions[] = "source_table = ? AND added_by_user_id IS NOT NULL";
        $params[] = $source_filter;
        $types .= 's';
    } else {
        // Para outras fontes, mostrar apenas alimentos globais (added_by_user_id IS NULL)
        $conditions[] = "source_table = ? AND added_by_user_id IS NULL";
        $params[] = $source_filter;
        $types .= 's';
    }
} else {
    // Se não há filtro, mostrar apenas alimentos globais (não mostrar alimentos criados por usuários)
    $conditions[] = "added_by_user_id IS NULL";
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

// Contar total para paginação (excluindo USER_OFF)
$count_sql = "SELECT COUNT(*) as count FROM sf_food_items";
$count_conditions = [];
$count_params = [];
$count_types = '';

// Sempre excluir USER_OFF (alimentos bugados)
$count_conditions[] = "source_table != 'USER_OFF' AND source_table != 'user_off' AND source_table != 'user-off'";

if (!empty($search_term)) {
    $count_conditions[] = "name_pt LIKE ?";
    $count_params[] = '%' . $search_term . '%';
    $count_types .= 's';
}

if (!empty($source_filter)) {
    if ($source_filter === 'user_created') {
        // Para "Criados por Usuário", mostrar apenas alimentos com added_by_user_id IS NOT NULL
        $count_conditions[] = "source_table = ? AND added_by_user_id IS NOT NULL";
        $count_params[] = $source_filter;
        $count_types .= 's';
    } else {
        // Para outras fontes, mostrar apenas alimentos globais (added_by_user_id IS NULL)
        $count_conditions[] = "source_table = ? AND added_by_user_id IS NULL";
        $count_params[] = $source_filter;
        $count_types .= 's';
    }
} else {
    // Se não há filtro, mostrar apenas alimentos globais (não mostrar alimentos criados por usuários)
    $count_conditions[] = "added_by_user_id IS NULL";
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
    overflow: visible;
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
    overflow: visible;
    z-index: 1;
}

.foods-header-card.dropdown-active {
    z-index: 9998 !important;
    will-change: z-index;
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
}

.header-title p {
    color: var(--text-secondary);
    font-size: 0.95rem;
    margin: 0;
}

/* Stats Grid - cards responsivos, mesma fileira, mesmo tamanho, alinhados com header/lista */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow: visible;
    position: relative;
}

.stat-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 1.25rem 1rem;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    cursor: pointer;
    aspect-ratio: 1.4;
    min-height: 110px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    box-sizing: border-box;
    width: 100%;
    position: relative;
    overflow: visible;
    z-index: 1;
    gap: 0;
}

.stat-card:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-2px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
    border-color: var(--accent-orange);
    z-index: 2;
}


.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--accent-orange);
    margin: 0;
    padding: 0;
    line-height: 1.2;
    white-space: nowrap;
    text-align: center;
    width: 100%;
    display: block;
    flex-shrink: 0;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    line-height: 1.4;
    margin: 0.5rem 0 0 0;
    padding: 0;
    text-align: center;
    width: 100%;
    display: block;
    word-break: break-word;
    hyphens: auto;
    flex-shrink: 0;
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
    width: 100%;
    z-index: 1;
}

.custom-select-wrapper.active {
    z-index: 9999 !important;
    position: relative;
    will-change: z-index;
}

.custom-select {
    position: relative;
    width: 100%;
}

.custom-select-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.875rem 1.25rem;
    font-size: 0.95rem;
    color: var(--text-primary);
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
    user-select: none;
}

.custom-select-trigger:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.15);
}

.custom-select.active .custom-select-trigger {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
}

.custom-select-trigger i {
    font-size: 0.875rem;
    color: var(--text-secondary);
    transition: transform 0.3s ease;
    margin-left: 0.75rem;
}

.custom-select.active .custom-select-trigger i {
    transform: rotate(180deg);
    color: var(--accent-orange);
}

.custom-select-value {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.custom-select-options {
    display: none;
    position: absolute;
    top: calc(100% + 0.5rem);
    left: 0;
    right: 0;
    z-index: 9999 !important;
    background: rgb(28, 28, 28);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.5);
    max-height: 300px;
    overflow-y: auto;
    overflow-x: hidden;
    box-sizing: border-box;
    -webkit-overflow-scrolling: touch;
}

.custom-select.active .custom-select-options {
    display: block;
}

.custom-select-option {
    padding: 0.875rem 1.25rem;
    font-size: 0.95rem;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: 'Montserrat', sans-serif;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.custom-select-option:first-child {
    border-radius: 12px 12px 0 0;
}

.custom-select-option:last-child {
    border-bottom: none;
    border-radius: 0 0 12px 12px;
}

.custom-select-option:hover {
    background: rgba(255, 107, 0, 0.15);
    color: var(--accent-orange);
}

.custom-select-option.selected {
    background: rgba(255, 107, 0, 0.2);
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
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border: 1px solid;
    max-width: 100%;
    box-sizing: border-box;
    word-wrap: break-word;
    white-space: nowrap;
    line-height: 1.3;
    text-align: center;
    overflow: hidden;
    text-overflow: ellipsis;
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
    padding: 0.375rem 0.5rem;
    font-size: 0.65rem;
    line-height: 1.4;
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

.btn-action.approve {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #22C55E;
}

.btn-action.approve:hover {
    background: rgba(34, 197, 94, 0.2);
    border-color: #22C55E;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
}

/* Botão circular de aprovar - estilo igual ao botão de criar */
.btn-approve-circular {
    width: 40px;
    height: 40px;
    min-width: 40px;
    min-height: 40px;
    max-width: 40px;
    max-height: 40px;
    border-radius: 50%;
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #22C55E;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 0;
    flex-shrink: 0;
}

.btn-approve-circular:hover {
    background: rgba(34, 197, 94, 0.2);
    border-color: #22C55E;
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
}

.btn-approve-circular i {
    font-size: 1rem;
    margin: 0;
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
    margin-top: 2rem;
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
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }
    
    .stat-card {
        min-height: 100px;
        padding: 1rem 0.75rem;
    }
    
    .stat-number {
        font-size: 1.25rem;
    }
    
    .stat-label {
        font-size: 0.7rem;
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
                <h2>Gerenciar Alimentos</h2>
                <p>Gerencie todos os alimentos cadastrados no sistema</p>
            </div>
            <a href="#" onclick="openAddFoodModal(); return false;" class="btn-add-recipe-circular" title="Novo Alimento">
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
            <div class="stat-label">Total</div>
        </div>
        
        <?php foreach ($stats['by_source'] as $source => $count): 
            // Pular user_off - alimentos bugados que serão removidos
            $sourceLower = strtolower($source);
            if ($sourceLower === 'user_off' || $sourceLower === 'user-off') {
                continue;
            }
            
            $sourceParam = urlencode($source);
            $labelText = '';
            switch ($sourceLower) {
                case 'taco': 
                    $labelText = 'TACO'; 
                    break;
                case 'sonia tucunduva': 
                    $labelText = 'Sonia'; 
                    break;
                case 'sonia tucunduva (prioridade)': 
                    $labelText = 'Sonia (Atualizado)'; 
                    break;
                case 'usda': 
                    $labelText = 'USDA'; 
                    break;
                case 'fatsecret': 
                    $labelText = 'FatSecret'; 
                    break;
                case 'manual': 
                    $labelText = 'Manual'; 
                    break;
                case 'user_created': 
                    $labelText = 'Criado por Usuário'; 
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
            Legenda de Fontes
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
                    <a href="#" onclick="openAddFoodModal(); return false;" class="btn-primary">Adicionar Primeiro Alimento</a>
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
                                <td style="text-align: left; vertical-align: middle; width: 180px; min-width: 180px; max-width: 180px; overflow: hidden; padding-right: 1rem;">
                                    <?php 
                                    $source = $food['source_table'];
                                    $badgeClass = '';
                                    $badgeText = '';
                                    $sourceLower = strtolower($source);
                                    
                                    switch ($sourceLower) {
                                        case 'taco':
                                            $badgeClass = 'taco';
                                            $badgeText = 'TACO';
                                            break;
                                        case 'sonia tucunduva':
                                            $badgeClass = 'sonia';
                                            $badgeText = 'Sonia';
                                            break;
                                        case 'sonia tucunduva (prioridade)':
                                            $badgeClass = 'sonia-updated';
                                            $badgeText = 'Sonia (Atualizado)';
                                            break;
                                        case 'usda':
                                            $badgeClass = 'usda';
                                            $badgeText = 'USDA';
                                            break;
                                        case 'fatsecret':
                                            $badgeClass = 'fatsecret';
                                            $badgeText = 'FatSecret';
                                            break;
                                        case 'manual':
                                            $badgeClass = 'manual';
                                            $badgeText = 'Manual';
                                            break;
                                        case 'user_created':
                                            $badgeClass = 'user-created';
                                            $badgeText = 'Por Usuário';
                                            break;
                                        default:
                                            $badgeClass = 'manual';
                                            $badgeText = htmlspecialchars($source);
                                    }
                                    ?>
                                    <span class="source-badge <?php echo $badgeClass; ?>" style="display: inline-block; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo $badgeText; ?>
                                    </span>
                                </td>
                                <td style="text-align: right; vertical-align: middle; width: 240px; min-width: 240px; max-width: 240px; padding-left: 1.5rem;">
                                    <div class="actions" style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem; flex-wrap: nowrap;">
                                        <?php if (!empty($food['added_by_user_id'])): ?>
                                            <!-- Botão Aprovar para alimentos criados por usuários -->
                                            <button type="button" 
                                                    onclick="approveFood(<?php echo $food['id']; ?>)" 
                                                    class="btn-approve-circular" 
                                                    title="Adicionar ao banco de dados global">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" onclick="openEditFoodModal(<?php echo $food['id']; ?>)" class="btn-action edit" style="white-space: nowrap; flex-shrink: 0;">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <a href="process_food.php?action=delete&id=<?php echo $food['id']; ?>" 
                                           class="btn-action delete" 
                                           style="white-space: nowrap; flex-shrink: 0;"
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
// Custom Select Dropdown Functionality - VERSÃO SIMPLIFICADA (CSS FAZ O TRABALHO)
// Copiado exatamente do recipes.php
(function() {
    const customSelect = document.getElementById('source_select');
    if (!customSelect) return;
    
    const hiddenInput = document.getElementById('source_input');
    const trigger = customSelect.querySelector('.custom-select-trigger');
    const optionsContainer = customSelect.querySelector('.custom-select-options');
    const options = customSelect.querySelectorAll('.custom-select-option');
    const valueDisplay = customSelect.querySelector('.custom-select-value');

    // Abre/fecha o dropdown - COPIADO DO food_classification.php
    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpening = !customSelect.classList.contains('active');
        
        // Se estiver abrindo, fecha todos os outros primeiro
        if (isOpening) {
            document.querySelectorAll('.custom-select.active').forEach(select => {
                if (select !== customSelect) {
                    select.classList.remove('active');
                    const otherWrapper = select.closest('.custom-select-wrapper');
                    if (otherWrapper) {
                        otherWrapper.classList.remove('active');
                    }
                    const otherCard = otherWrapper ? otherWrapper.closest('.foods-header-card') : null;
                    if (otherCard) {
                        otherCard.classList.remove('dropdown-active');
                    }
                }
            });
            
            // Aplica classes do novo dropdown - wrapper primeiro para garantir z-index
            const wrapper = customSelect.closest('.custom-select-wrapper');
            if (wrapper) {
                wrapper.classList.add('active');
                // Adiciona classe no card também
                const card = wrapper.closest('.foods-header-card');
                if (card) {
                    card.classList.add('dropdown-active');
                }
            }
            // Adiciona active DEPOIS do wrapper para garantir z-index
            customSelect.classList.add('active');
        } else {
            // Fechando
            customSelect.classList.remove('active');
            const wrapper = customSelect.closest('.custom-select-wrapper');
            if (wrapper) {
                wrapper.classList.remove('active');
                const card = wrapper.closest('.foods-header-card');
                if (card) {
                    card.classList.remove('dropdown-active');
                }
            }
        }
    });

    // Seleciona uma opção
    options.forEach(option => {
        option.addEventListener('click', function(e) {
            e.stopPropagation();
            
            // Atualiza o valor do input escondido
            hiddenInput.value = this.getAttribute('data-value') || '';
            // Atualiza o texto visível
            valueDisplay.textContent = this.textContent;
            
            // Remove a classe 'selected' de todos e adiciona na clicada
            options.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');

            // Fecha o dropdown
            customSelect.classList.remove('active');
            const wrapper = customSelect.closest('.custom-select-wrapper');
            if (wrapper) {
                wrapper.classList.remove('active');
                const card = wrapper.closest('.foods-header-card');
                if (card) {
                    card.classList.remove('dropdown-active');
                }
            }
            
            // Submete o formulário
            const form = customSelect.closest('form');
            if (form) {
                form.submit();
            }
        });
    });

    // Função para fechar dropdown
    function closeDropdown() {
        customSelect.classList.remove('active');
        const wrapper = customSelect.closest('.custom-select-wrapper');
        if (wrapper) {
            wrapper.classList.remove('active');
            const card = wrapper.closest('.foods-header-card');
            if (card) {
                card.classList.remove('dropdown-active');
            }
        }
    }

    // Fecha o dropdown se clicar fora
    document.addEventListener('click', function(e) {
        if (!customSelect.contains(e.target)) {
            closeDropdown();
        }
    });

    // Fecha com a tecla Esc
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDropdown();
        }
    });
})();

// Modal de Edição de Alimento
let currentEditingFoodId = null;

function openAddFoodModal() {
    currentEditingFoodId = null;
    
    // Limpar formulário
    document.getElementById('food-edit-title').textContent = 'Novo Alimento';
    document.getElementById('food-edit-action').value = 'add';
    document.getElementById('food-edit-id').value = '';
    document.getElementById('food-name-pt').value = '';
    document.getElementById('food-energy').value = '';
    document.getElementById('food-protein').value = '';
    document.getElementById('food-carbs').value = '';
    document.getElementById('food-fat').value = '';
    
    // Definir fonte padrão como Manual
    const sourceInput = document.getElementById('food-source');
    if (sourceInput) {
        sourceInput.value = 'Manual';
    }
    
    // Ocultar botão de excluir
    document.getElementById('food-delete-btn').style.display = 'none';
    
    // Abrir modal
    document.getElementById('food-edit-modal').classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Selecionar tag Manual
    setTimeout(() => {
        const sourceTags = document.querySelectorAll('.source-tag');
        sourceTags.forEach(tag => {
            tag.classList.remove('active');
            if (tag.dataset.value === 'Manual') {
                tag.classList.add('active');
            }
        });
    }, 50);
}

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
                
                // Atualizar tags de fonte
                const sourceValue = food.source_table || 'Manual';
                const sourceInput = document.getElementById('food-source');
                if (sourceInput) {
                    sourceInput.value = sourceValue;
                }
                
                // Aguarda o modal abrir para atualizar as tags
                document.getElementById('food-delete-btn').style.display = food.id ? 'flex' : 'none';
                document.getElementById('food-edit-modal').classList.add('active');
                document.body.style.overflow = 'hidden';
                
                // Atualiza tags após o modal estar visível
                setTimeout(() => {
                    const sourceTags = document.querySelectorAll('.source-tag');
                    sourceTags.forEach(tag => {
                        tag.classList.remove('active');
                        if (tag.dataset.value === sourceValue) {
                            tag.classList.add('active');
                        }
                    });
                }, 100);
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
    const modal = document.getElementById('food-edit-modal');
    if (modal) {
        modal.classList.remove('active');
    }
    document.body.style.overflow = '';
    currentEditingFoodId = null;
}

// Removido: função updateCalculations (card de cálculos removido)

function saveFood() {
    const form = document.getElementById('food-edit-form');
    const formData = new FormData(form);
    const name = formData.get('name_pt').trim();
    if (!name) {
        alert('Nome do alimento é obrigatório');
        return;
    }
    const saveBtn = document.querySelector('.food-edit-footer .btn-save');
    if (!saveBtn) {
        alert('Erro: botão de salvar não encontrado');
        return;
    }
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
    saveBtn.disabled = true;
    fetch('process_food.php?ajax=1', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Erro na resposta do servidor');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Mantém os parâmetros da URL atual (filtros, página, etc)
            window.location.href = window.location.pathname + window.location.search;
        } else {
            alert('Erro ao salvar: ' + (data.message || 'Erro desconhecido'));
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar alimento: ' + error.message);
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
    .then(response => {
        if (!response.ok) {
            throw new Error('Erro na resposta do servidor');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Mantém os parâmetros da URL atual (filtros, página, etc)
            window.location.href = window.location.pathname + window.location.search;
        } else {
            alert('Erro ao excluir: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao excluir alimento: ' + error.message);
    });
}

function approveFood(foodId) {
    if (!confirm('Tem certeza que deseja APROVAR este alimento?\n\nO alimento ficará disponível para todos os usuários.')) {
        return;
    }
    const formData = new FormData();
    formData.append('action', 'approve');
    formData.append('id', foodId);
    fetch('process_food.php?ajax=1', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Erro na resposta do servidor');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Mantém os parâmetros da URL atual (filtros, página, etc)
            window.location.href = window.location.pathname + window.location.search;
        } else {
            alert('Erro ao aprovar: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao aprovar alimento: ' + error.message);
    });
}

// Inicializar tags de fonte no modal
document.addEventListener('DOMContentLoaded', function() {
    const sourceTags = document.querySelectorAll('.source-tag');
    const sourceInput = document.getElementById('food-source');
    
    if (sourceTags.length > 0 && sourceInput) {
        sourceTags.forEach(tag => {
            tag.addEventListener('click', function() {
                // Remove active de todas as tags
                sourceTags.forEach(t => t.classList.remove('active'));
                
                // Adiciona active na tag clicada
                this.classList.add('active');
                
                // Atualiza o input hidden
                const value = this.dataset.value || '';
                sourceInput.value = value;
            });
        });
    }
});
</script>

<!-- Modal Adicionar/Editar Alimento - Estilo view_user -->
<div id="food-edit-modal" class="food-edit-modal">
    <div class="food-edit-overlay" onclick="closeFoodEditModal()"></div>
    <div class="food-edit-content">
        <button class="sleep-modal-close" onclick="closeFoodEditModal()" type="button">
            <i class="fas fa-times"></i>
        </button>
        <div class="food-edit-header">
            <h3 id="food-edit-title">Editar Alimento</h3>
        </div>
        
        <div class="food-edit-body">
            <form id="food-edit-form">
                <input type="hidden" id="food-edit-id" name="id" value="">
                <input type="hidden" name="action" id="food-edit-action" value="edit">
                
                <div class="food-form-group">
                    <label for="food-name-pt">Nome do Alimento *</label>
                    <input type="text" id="food-name-pt" name="name_pt" class="food-form-input" required placeholder="Digite o nome do alimento">
                </div>
                
                <div class="food-info-section">
                    <div class="food-info-badge">
                        <i class="fas fa-info-circle"></i>
                        <span>Valores nutricionais por 100 gramas</span>
                    </div>
                </div>
                
                <div class="food-macros-section">
                    <div class="food-macros-grid">
                        <div class="food-form-group">
                            <label for="food-energy">Calorias</label>
                            <input type="number" step="0.1" id="food-energy" name="energy_kcal_100g" class="food-form-input" required placeholder="0.0">
                        </div>
                        
                        <div class="food-form-group">
                            <label for="food-protein">Proteína</label>
                            <input type="number" step="0.1" id="food-protein" name="protein_g_100g" class="food-form-input" required placeholder="0.0">
                        </div>
                        
                        <div class="food-form-group">
                            <label for="food-carbs">Carboidratos</label>
                            <input type="number" step="0.1" id="food-carbs" name="carbohydrate_g_100g" class="food-form-input" required placeholder="0.0">
                        </div>
                        
                        <div class="food-form-group">
                            <label for="food-fat">Gorduras</label>
                            <input type="number" step="0.1" id="food-fat" name="fat_g_100g" class="food-form-input" required placeholder="0.0">
                        </div>
                    </div>
                </div>
                
                <div class="food-form-group">
                    <label for="food-source">Fonte</label>
                    <input type="hidden" id="food-source" name="source_table" value="Manual">
                    <div class="food-source-tags">
                        <span class="source-badge source-tag manual active" data-value="Manual">Manual</span>
                        <span class="source-badge source-tag taco" data-value="TACO">TACO</span>
                        <span class="source-badge source-tag sonia" data-value="Sonia Tucunduva">Sonia</span>
                        <span class="source-badge source-tag sonia-updated" data-value="Sonia Tucunduva (Prioridade)">Sonia (Atualizado)</span>
                        <span class="source-badge source-tag usda" data-value="USDA">USDA</span>
                        <span class="source-badge source-tag fatsecret" data-value="FatSecret">FatSecret</span>
                        <span class="source-badge source-tag user-created" data-value="user_created">Criado por Usuário</span>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="food-edit-footer">
            <button type="button" class="btn-cancel" onclick="closeFoodEditModal()">Cancelar</button>
            <button type="button" id="food-delete-btn" class="btn-delete" onclick="deleteFood()" style="display: none;">
                <i class="fas fa-trash"></i> Excluir
            </button>
            <button type="button" class="btn-save" onclick="saveFood()">
                <i class="fas fa-save"></i> Salvar
            </button>
        </div>
    </div>
</div>

<style>
/* ========================================================================= */
/*       FOOD EDIT MODAL - ESTILO VIEW_USER MODERNO                          */
/* ========================================================================= */

/* Modal principal - estilo view_user */
.food-edit-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.1s ease;
}

.food-edit-modal.active {
    opacity: 1;
    pointer-events: all;
}

/* Overlay separado - igual view_user para blur mais rápido */
.food-edit-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    transition: none !important;
}

.food-edit-content {
    position: relative;
    background: linear-gradient(135deg, rgba(30, 30, 30, 0.98) 0%, rgba(20, 20, 20, 0.98) 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    width: 90%;
    max-width: 480px;
    max-height: 90vh;
    overflow: visible;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
    transform: scale(0.95);
    transition: transform 0.3s ease;
}

.food-edit-modal.active .food-edit-content {
    transform: scale(1);
}

/* Botão X - copiado do sleep-modal-close do view_user */
.sleep-modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    width: 2.5rem;
    height: 2.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    z-index: 10;
}

.sleep-modal-close:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--accent-orange);
}

.food-edit-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.food-edit-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    font-family: 'Montserrat', sans-serif;
}

.food-edit-body {
    padding: 1.25rem;
    flex: 1;
    overflow-y: visible;
    overflow-x: hidden;
    position: relative;
}

/* Form Groups */
.food-form-group {
    margin-bottom: 1rem;
}

.food-form-group:last-child {
    margin-bottom: 0;
}

.food-form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
    font-family: 'Montserrat', sans-serif;
}

/* Info Section */
.food-info-section {
    margin-bottom: 1rem;
}

.food-info-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 0.875rem;
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.2);
    border-radius: 10px;
    color: var(--accent-orange);
    font-size: 0.8125rem;
    font-weight: 500;
    font-family: 'Montserrat', sans-serif;
}

.food-info-badge i {
    font-size: 0.875rem;
}

/* Macros Section */
.food-macros-section {
    margin-bottom: 1rem;
}

.food-form-input {
    width: 100%;
    padding: 0.625rem 0.875rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
    box-sizing: border-box;
    -moz-appearance: textfield;
}

.food-form-input::-webkit-outer-spin-button,
.food-form-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.food-form-input:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
}

.food-form-input::placeholder {
    color: var(--text-secondary);
    opacity: 0.5;
}

/* Grid de Macros - 4 colunas alinhadas */
.food-macros-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.75rem;
    margin-bottom: 0;
}

.food-macros-grid .food-form-group {
    margin-bottom: 0;
}

/* Container de tags de fonte */
.food-source-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

/* Tags clicáveis - Estado padrão (cinza/baixa saturação) */
.source-tag {
    cursor: pointer;
    transition: all 0.3s ease;
    user-select: none;
    background: rgba(255, 255, 255, 0.05) !important;
    border-color: rgba(255, 255, 255, 0.15) !important;
    color: rgba(255, 255, 255, 0.5) !important;
    opacity: 0.7;
}

.source-tag:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    opacity: 0.85;
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: rgba(255, 255, 255, 0.2) !important;
    color: rgba(255, 255, 255, 0.7) !important;
}

/* Tags ativas - Estado colorido */
.source-tag.active {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    opacity: 1;
    font-weight: 700;
}

/* Cores específicas para cada tag quando ativa */
.source-tag.active.taco {
    background: rgba(34, 197, 94, 0.15) !important;
    border-color: rgba(34, 197, 94, 0.4) !important;
    color: #22C55E !important;
}

.source-tag.active.sonia {
    background: rgba(59, 130, 246, 0.15) !important;
    border-color: rgba(59, 130, 246, 0.4) !important;
    color: #3B82F6 !important;
}

.source-tag.active.sonia-updated {
    background: rgba(147, 51, 234, 0.15) !important;
    border-color: rgba(147, 51, 234, 0.4) !important;
    color: #9333EA !important;
}

.source-tag.active.usda {
    background: rgba(236, 72, 153, 0.15) !important;
    border-color: rgba(236, 72, 153, 0.4) !important;
    color: #EC4899 !important;
}

.source-tag.active.fatsecret {
    background: rgba(255, 107, 0, 0.15) !important;
    border-color: rgba(255, 107, 0, 0.4) !important;
    color: #FF6B00 !important;
}

.source-tag.active.manual {
    background: rgba(168, 85, 247, 0.15) !important;
    border-color: rgba(168, 85, 247, 0.4) !important;
    color: #A855F7 !important;
}

.source-tag.active.user-created {
    background: rgba(251, 191, 36, 0.15) !important;
    border-color: rgba(251, 191, 36, 0.4) !important;
    color: #FBBF24 !important;
}

/* Footer */
.food-edit-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    flex-wrap: wrap;
}

/* Botões - Estilo view_user */
.btn-cancel,
.btn-save,
.btn-delete {
    padding: 0.625rem 1.25rem;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border: none;
    font-family: 'Montserrat', sans-serif;
}

.btn-cancel {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-secondary);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-cancel:hover {
    background: rgba(255, 255, 255, 0.08);
    color: var(--text-primary);
}

.btn-save {
    background: linear-gradient(135deg, #FF6600, #FF8533);
    color: white;
}

.btn-save:hover {
    background: linear-gradient(135deg, #FF8533, #FF6600);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 102, 0, 0.3);
}

.btn-delete {
    background: rgba(244, 67, 54, 0.15);
    color: var(--danger-red);
    border: 1px solid rgba(244, 67, 54, 0.4);
}

.btn-delete:hover {
    background: rgba(244, 67, 54, 0.25);
    border-color: var(--danger-red);
    transform: translateY(-2px);
}

/* Responsividade */
@media (max-width: 768px) {
    .food-edit-content {
        width: 95%;
        max-height: 90vh;
    }
    
    .food-edit-body {
        padding: 1.5rem;
    }
    
    .food-macros-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .food-edit-footer {
        flex-direction: column;
    }
    
    .food-edit-footer button {
        width: 100%;
        justify-content: center;
    }
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
    width: 180px;
    min-width: 180px;
    max-width: 180px;
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
    width: 180px;
    min-width: 180px;
    max-width: 180px;
    text-align: left;
    white-space: normal;
    overflow: hidden;
    word-wrap: break-word;
    padding-right: 1rem;
}

.data-table td:last-child {
    text-align: right;
    width: 240px;
    min-width: 240px;
    max-width: 240px;
    white-space: nowrap;
    overflow: visible;
    padding-left: 1.5rem;
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

.btn-action.approve {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #22C55E;
}

.btn-action.approve:hover {
    background: rgba(34, 197, 94, 0.2);
    border-color: #22C55E;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
}

/* Botão circular de aprovar - estilo igual ao botão de criar */
.btn-approve-circular {
    width: 40px;
    height: 40px;
    min-width: 40px;
    min-height: 40px;
    max-width: 40px;
    max-height: 40px;
    border-radius: 50%;
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #22C55E;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 0;
    flex-shrink: 0;
}

.btn-approve-circular:hover {
    background: rgba(34, 197, 94, 0.2);
    border-color: #22C55E;
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
}

.btn-approve-circular i {
    font-size: 1rem;
    margin: 0;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

