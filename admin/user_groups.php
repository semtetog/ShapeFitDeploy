<?php
// admin/user_groups.php - Gerenciamento de Grupos de Usuários - Design Profissional

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'user_groups';
$page_title = 'Grupos de Usuários';

$admin_id = $_SESSION['admin_id'] ?? 1;

// --- Lógica de busca e filtro ---
$search_term = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// --- Estatísticas gerais ---
// Verificar estrutura da tabela (pode ter group_name ou name, is_active ou status)
$test_query = "SHOW COLUMNS FROM sf_user_groups LIKE 'name'";
$test_result = $conn->query($test_query);
$has_name_column = $test_result->num_rows > 0;
$test_result->free();

$test_query2 = "SHOW COLUMNS FROM sf_user_groups LIKE 'status'";
$test_result2 = $conn->query($test_query2);
$has_status_column = $test_result2->num_rows > 0;
$test_result2->free();

// Usar nome correto da coluna
$name_column = $has_name_column ? 'name' : 'group_name';
$status_column = $has_status_column ? 'status' : 'is_active';
$status_condition = $has_status_column ? "status = 'active'" : "is_active = 1";
$status_condition_inactive = $has_status_column ? "status = 'inactive'" : "is_active = 0";

// Total de grupos
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM sf_user_groups WHERE admin_id = $admin_id")->fetch_assoc()['count'];

// Por status
if ($has_status_column) {
    $stats_query = "SELECT status, COUNT(*) as count 
                    FROM sf_user_groups 
                    WHERE admin_id = $admin_id
                    GROUP BY status";
    $stats_result = $conn->query($stats_query);
    $stats_by_status = ['active' => 0, 'inactive' => 0];
    while ($row = $stats_result->fetch_assoc()) {
        $stats_by_status[$row['status']] = $row['count'];
    }
    $stats['active'] = $stats_by_status['active'];
    $stats['inactive'] = $stats_by_status['inactive'] ?? 0;
} else {
    $stats['active'] = $conn->query("SELECT COUNT(*) as count FROM sf_user_groups WHERE admin_id = $admin_id AND is_active = 1")->fetch_assoc()['count'];
    $stats['inactive'] = $conn->query("SELECT COUNT(*) as count FROM sf_user_groups WHERE admin_id = $admin_id AND is_active = 0")->fetch_assoc()['count'];
}

// --- Construir query de busca ---
$sql = "SELECT 
    ug.id,
    ug.$name_column as name,
    ug.description,
    ug.$status_column as status,
    ug.admin_id,
    ug.created_at,
    ug.updated_at,
    COUNT(DISTINCT ugm.user_id) as member_count
    FROM sf_user_groups ug
    LEFT JOIN sf_user_group_members ugm ON ug.id = ugm.group_id
    WHERE ug.admin_id = ?";
$conditions = [];
$params = [$admin_id];
$types = 'i';

if (!empty($search_term)) {
    $conditions[] = "ug.$name_column LIKE ?";
    $params[] = '%' . $search_term . '%';
    $types .= 's';
}

if (!empty($status_filter)) {
    if ($has_status_column) {
        $conditions[] = "ug.status = ?";
    } else {
        $conditions[] = $status_filter === 'active' ? "ug.is_active = 1" : "ug.is_active = 0";
    }
    if ($has_status_column) {
        $params[] = $status_filter;
        $types .= 's';
    }
}

if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

$sql .= " GROUP BY ug.id ORDER BY ug.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

// Executar query
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $user_groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Normalizar status
    foreach ($user_groups as &$group) {
        if (!$has_status_column) {
            $group['status'] = $group['status'] == 1 ? 'active' : 'inactive';
        }
    }
    unset($group);
} else {
    $user_groups = [];
}

// Contar total para paginação
$count_sql = "SELECT COUNT(*) as count FROM sf_user_groups ug WHERE ug.admin_id = ?";
$count_params = [$admin_id];
$count_types = 'i';

if (!empty($search_term)) {
    $count_sql .= " AND ug.$name_column LIKE ?";
    $count_params[] = '%' . $search_term . '%';
    $count_types .= 's';
}

if (!empty($status_filter)) {
    if ($has_status_column) {
        $count_sql .= " AND ug.status = ?";
        $count_params[] = $status_filter;
        $count_types .= 's';
    } else {
        $count_sql .= $status_filter === 'active' ? " AND ug.is_active = 1" : " AND ug.is_active = 0";
    }
}

$count_stmt = $conn->prepare($count_sql);
if ($count_stmt) {
    $count_stmt->bind_param($count_types, ...$count_params);
    $count_stmt->execute();
    $total_items = $count_stmt->get_result()->fetch_assoc()['count'];
    $count_stmt->close();
} else {
    $total_items = 0;
}

$total_pages = ceil($total_items / $per_page);

// Buscar usuários para o modal (apenas usuários que completaram onboarding)
$users_query = "SELECT u.id, u.name, u.email, up.profile_image_filename 
                FROM sf_users u 
                LEFT JOIN sf_user_profiles up ON u.id = up.user_id 
                WHERE u.onboarding_complete = 1
                ORDER BY u.name";
$users_result = $conn->query($users_query);
$users = $users_result->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ========================================================================= */
/*       USER GROUPS PAGE - DESIGN MODERNO (IGUAL CHALLENGE_GROUPS)          */
/* ========================================================================= */

.user-groups-page {
    padding: 1.5rem 2rem;
    min-height: 100vh;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

/* Forçar remoção de sombras e efeitos de todos os cards */
.user-groups-page * {
    box-shadow: none !important;
}

.user-groups-page .dashboard-card,
.user-groups-page .content-card,
.user-groups-page [class*="card"] {
    box-shadow: none !important;
    filter: none !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
}

/* Header Card */
.header-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 1.5rem !important;
    margin-bottom: 2rem !important;
    box-shadow: none !important;
    filter: none !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
}

.header-title {
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    flex-wrap: wrap;
}

.header-title h2 {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.header-title p {
    color: var(--text-secondary);
    font-size: 0.95rem;
    margin: 0.5rem 0 0 0;
}

/* Stats Grid - Estilo igual challenge_groups.php */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-top: 1.5rem;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow: visible;
    position: relative;
}

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}

.stat-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 16px !important;
    padding: 1.25rem 1rem !important;
    text-align: center !important;
    transition: all 0.3s ease !important;
    cursor: pointer !important;
    aspect-ratio: 1.4 !important;
    min-height: 110px !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: center !important;
    align-items: center !important;
    box-sizing: border-box !important;
    width: 100% !important;
    position: relative !important;
    overflow: visible !important;
    z-index: 1 !important;
    gap: 0 !important;
}

.stat-card:hover {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: var(--accent-orange) !important;
    transform: translateY(-2px);
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--accent-orange);
    margin-bottom: 0.5rem;
    line-height: 1;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Search and Filter */
.search-filter-section {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    align-items: center;
}

.search-input-wrapper {
    flex: 1;
    min-width: 200px;
    position: relative;
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    color: var(--text-primary);
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
    box-sizing: border-box;
}

.search-input:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

.search-input::placeholder {
    color: var(--text-secondary);
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    font-size: 0.875rem;
}

/* Groups Grid */
.user-groups-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 380px), 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
    width: 100%;
    box-sizing: border-box;
}

.user-group-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 12px !important;
    padding: 1.25rem !important;
    transition: all 0.3s ease !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 1rem !important;
    min-height: 200px !important;
    position: relative !important;
    box-shadow: none !important;
    filter: none !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    width: 100% !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
    min-width: 0 !important;
}

.user-group-card:hover {
    border-color: var(--accent-orange) !important;
    background: rgba(255, 255, 255, 0.08) !important;
    transform: translateY(-2px);
}

.group-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
    flex-wrap: wrap;
    width: 100%;
    box-sizing: border-box;
    min-width: 0;
}

.group-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    flex: 1;
    min-width: 0;
    word-wrap: break-word;
    overflow-wrap: break-word;
    hyphens: auto;
}

.group-description {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-top: 0.5rem;
    line-height: 1.5;
    word-wrap: break-word;
    overflow-wrap: break-word;
    width: 100%;
    box-sizing: border-box;
}

/* Toggle Switch */
.toggle-switch-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.toggle-switch-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
    transition: all 0.3s ease;
}

.toggle-switch-label.active {
    color: #10b981;
}

.toggle-switch-label.inactive {
    color: #ef4444;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
    flex-shrink: 0;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ef4444;
    transition: 0.3s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

.toggle-switch input:checked + .toggle-slider {
    background-color: #10b981;
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(20px);
}

.group-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.group-info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
    min-width: 0;
    white-space: nowrap;
}

.group-info-item i {
    color: var(--accent-orange);
    font-size: 0.875rem;
    flex-shrink: 0;
}

.group-card-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
    width: 100%;
    box-sizing: border-box;
    min-width: 0;
    margin-top: auto;
}

@media (min-width: 768px) {
    .group-card-actions {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 1024px) {
    .group-card-actions {
        grid-template-columns: repeat(5, 1fr);
    }
}

.btn-action {
    min-width: 0;
    max-width: 100%;
    padding: 0.625rem 0.5rem;
    gap: 0.375rem;
    overflow: visible;
    text-overflow: clip;
    line-height: 1.2;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    font-family: 'Montserrat', sans-serif;
    text-align: center;
    white-space: nowrap;
}

.btn-action i {
    font-size: 0.75rem;
    flex-shrink: 0;
}

@media (min-width: 768px) {
    .btn-action {
        font-size: 0.8125rem;
        padding: 0.625rem 0.625rem;
    }
    
    .btn-action i {
        font-size: 0.875rem;
    }
}

.btn-view {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.btn-view:hover {
    background: rgba(59, 130, 246, 0.2);
    border-color: #3b82f6;
}

.btn-edit {
    background: rgba(255, 107, 0, 0.1);
    color: var(--accent-orange);
    border: 1px solid rgba(255, 107, 0, 0.3);
}

.btn-edit:hover {
    background: rgba(255, 107, 0, 0.2);
    border-color: var(--accent-orange);
}

.btn-delete {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.btn-delete:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: #ef4444;
}

.btn-goals {
    background: rgba(168, 85, 247, 0.1);
    color: #a855f7;
    border: 1px solid rgba(168, 85, 247, 0.3);
}

.btn-goals:hover {
    background: rgba(168, 85, 247, 0.2);
    border-color: #a855f7;
}

.btn-patients {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.btn-patients:hover {
    background: rgba(34, 197, 94, 0.2);
    border-color: #22c55e;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-secondary);
}

.empty-state-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    background: rgba(255, 107, 0, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.empty-state-icon i {
    font-size: 2rem;
    color: var(--accent-orange);
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    line-height: 1;
}

.empty-state h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.empty-state p {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
}

/* Button Create */
.btn-create-group {
    background: linear-gradient(135deg, #FF6600, #FF8533);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Montserrat', sans-serif;
}

.btn-create-group:hover {
    background: linear-gradient(135deg, #FF8533, #FF6600);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 102, 0, 0.3);
}

.btn-create-group i {
    font-size: 1.25rem;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    margin-top: 2rem;
}

.pagination button,
.pagination a {
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    color: var(--text-primary);
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.pagination button:hover,
.pagination a:hover {
    background: rgba(255, 107, 0, 0.1);
    border-color: var(--accent-orange);
}

.pagination .current {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
    color: #FFFFFF;
}

/* Responsividade */
@media (max-width: 1024px) {
    .user-groups-grid {
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 340px), 1fr));
        gap: 1.25rem;
    }
    
    .btn-action {
        font-size: 0.75rem;
        padding: 0.5rem 0.5rem;
        gap: 0.375rem;
        flex-shrink: 1;
        max-width: 100%;
    }
    
    .btn-action i {
        font-size: 0.75rem;
        flex-shrink: 0;
    }
}

@media (max-width: 768px) {
    .user-groups-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .user-group-card {
        min-height: auto !important;
    }
    
    .group-card-actions {
        flex-direction: row;
        flex-wrap: wrap;
    }
    
    .btn-action {
        flex: 1 1 calc(50% - 0.25rem);
        min-width: 0;
        max-width: calc(50% - 0.25rem);
        font-size: 0.75rem;
        padding: 0.5rem 0.5rem;
    }
    
    .btn-action i {
        font-size: 0.75rem;
        flex-shrink: 0;
    }
    
    .group-card-header {
        flex-direction: row;
        align-items: center;
    }
    
    .toggle-switch-wrapper {
        flex-shrink: 0;
    }
}

@media (max-width: 480px) {
    .user-group-card {
        padding: 0.875rem !important;
        gap: 0.625rem !important;
    }
    
    .group-name {
        font-size: 1rem;
    }
    
    .group-card-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .btn-action {
        flex: 1;
        width: 100%;
        min-width: 0;
        max-width: 100%;
        padding: 0.5rem 0.75rem;
    }
    
    .btn-action i {
        font-size: 0.75rem;
        flex-shrink: 0;
    }
    
    .group-info {
        gap: 0.75rem;
    }
    
    .group-info-item {
        font-size: 0.8125rem;
    }
}

/* Modal Styles - Igual challenge_groups.php */
.challenge-edit-modal {
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

.challenge-edit-modal.active {
    opacity: 1;
    pointer-events: all;
}

.challenge-edit-overlay {
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

.challenge-edit-content {
    position: relative;
    background: linear-gradient(135deg, rgba(30, 30, 30, 0.98) 0%, rgba(20, 20, 20, 0.98) 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    width: 90%;
    max-width: 700px;
    max-height: 90vh;
    overflow: visible;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
    transform: scale(0.95);
    transition: transform 0.3s ease;
}

.challenge-edit-modal.active .challenge-edit-content {
    transform: scale(1);
}

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

.challenge-edit-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.challenge-edit-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    font-family: 'Montserrat', sans-serif;
}

.challenge-edit-body {
    padding: 1.25rem;
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    position: relative;
}

.challenge-edit-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    flex-wrap: wrap;
}

.challenge-form-group {
    margin-bottom: 1rem;
}

.challenge-form-group:last-child {
    margin-bottom: 0;
}

.challenge-form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
    font-family: 'Montserrat', sans-serif;
}

.challenge-form-group label:has(+ input[required])::after,
.challenge-form-group label:has(+ textarea[required])::after {
    content: ' *';
    color: var(--accent-orange);
    margin-left: 0.25rem;
}

.challenge-form-input,
.challenge-form-textarea {
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
}

.challenge-form-input:focus,
.challenge-form-textarea:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

.challenge-form-textarea {
    resize: vertical;
    min-height: 80px;
}

.challenge-section {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.challenge-section-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.participants-search {
    margin-bottom: 1rem;
}

.participants-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 300px;
    overflow-y: auto;
    padding-right: 0.5rem;
}

.participant-tag {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border-radius: 10px;
    cursor: pointer;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.3s ease;
}

.participant-tag:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.1);
}

.participant-tag.selected {
    background: rgba(255, 107, 0, 0.15);
    border-color: rgba(255, 107, 0, 0.4);
}

.participant-avatar {
    width: 40px;
    height: 40px;
    min-width: 40px;
    min-height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
    overflow: hidden;
    flex-shrink: 0;
}

.participant-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.participant-info {
    flex: 1;
    min-width: 0;
}

.participant-name {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.participant-email {
    font-size: 0.75rem;
    color: var(--text-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.btn-cancel,
.btn-save {
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
</style>

<div class="user-groups-page">
    <!-- Header Card -->
    <div class="header-card">
        <div class="header-title">
            <div>
                <h2><i class="fas fa-users"></i> Grupos de Usuários</h2>
                <p>Organize seus pacientes em grupos para definir metas e missões específicas</p>
            </div>
            <button class="btn-create-group" onclick="openCreateGroupModal()" title="Criar Novo Grupo">
                <i class="fas fa-plus"></i> Criar Grupo
            </button>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card" onclick="filterByStatus('')">
                <div class="stat-number" id="stat-total"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card" onclick="filterByStatus('active')">
                <div class="stat-number" id="stat-active"><?php echo $stats['active']; ?></div>
                <div class="stat-label">Ativos</div>
            </div>
            <div class="stat-card" onclick="filterByStatus('inactive')">
                <div class="stat-number" id="stat-inactive"><?php echo $stats['inactive']; ?></div>
                <div class="stat-label">Inativos</div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="search-filter-section">
            <div class="search-input-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" 
                       class="search-input" 
                       id="searchInput" 
                       placeholder="Buscar grupos..." 
                       value="<?php echo htmlspecialchars($search_term); ?>"
                       onkeyup="handleSearch()">
            </div>
        </div>
    </div>

    <!-- Groups Grid -->
    <?php if (empty($user_groups)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-users"></i>
            </div>
            <h3>Nenhum grupo encontrado</h3>
            <p>Crie seu primeiro grupo para organizar seus pacientes</p>
        </div>
    <?php else: ?>
        <div class="user-groups-grid">
            <?php foreach ($user_groups as $group): ?>
                <div class="user-group-card" data-status="<?php echo $group['status']; ?>">
                    <div class="group-card-header">
                        <div style="flex: 1; min-width: 0;">
                            <h3 class="group-name"><?php echo htmlspecialchars($group['name']); ?></h3>
                            <?php if (!empty($group['description'])): ?>
                                <p class="group-description"><?php echo htmlspecialchars($group['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="toggle-switch-wrapper">
                            <label class="toggle-switch-label <?php echo $group['status']; ?>" id="toggle-label-<?php echo $group['id']; ?>">
                                <?php echo $group['status'] === 'active' ? 'Ativo' : 'Inativo'; ?>
                            </label>
                            <label class="toggle-switch">
                                <input type="checkbox" 
                                       <?php echo $group['status'] === 'active' ? 'checked' : ''; ?>
                                       onchange="toggleGroupStatus(<?php echo $group['id']; ?>, '<?php echo $group['status']; ?>', this)">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="group-info">
                        <div class="group-info-item">
                            <i class="fas fa-users"></i>
                            <span><?php echo $group['member_count']; ?> membros</span>
                        </div>
                        <div class="group-info-item">
                            <i class="fas fa-calendar"></i>
                            <span>Criado em <?php echo date('d/m/Y', strtotime($group['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="group-card-actions" onclick="event.stopPropagation()">
                        <button class="btn-action btn-view" onclick="viewGroupMembers(<?php echo $group['id']; ?>)" title="Ver Membros">
                            <i class="fas fa-users"></i>
                            <span>Membros</span>
                        </button>
                        <button class="btn-action btn-goals" onclick="manageGroupGoals(<?php echo $group['id']; ?>)" title="Definir Metas">
                            <i class="fas fa-bullseye"></i>
                            <span>Metas</span>
                        </button>
                        <button class="btn-action btn-patients" onclick="viewGroupPatients(<?php echo $group['id']; ?>)" title="Ver Pacientes">
                            <i class="fas fa-user-md"></i>
                            <span>Pacientes</span>
                        </button>
                        <button class="btn-action btn-edit" onclick="editGroup(<?php echo $group['id']; ?>)">
                            <i class="fas fa-edit"></i>
                            <span>Editar</span>
                        </button>
                        <button class="btn-action btn-delete" onclick="deleteGroup(<?php echo $group['id']; ?>)">
                            <i class="fas fa-trash"></i>
                            <span>Excluir</span>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_term); ?>&status=<?php echo urlencode($status_filter); ?>">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>&status=<?php echo urlencode($status_filter); ?>" 
                       class="<?php echo $i === $page ? 'current' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_term); ?>&status=<?php echo urlencode($status_filter); ?>">
                        Próxima <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal Create/Edit Group - Estilo igual challenge_groups.php -->
<div id="groupModal" class="challenge-edit-modal">
    <div class="challenge-edit-overlay" onclick="closeGroupModal()"></div>
    <div class="challenge-edit-content">
        <button class="sleep-modal-close" onclick="closeGroupModal()" type="button">
            <i class="fas fa-times"></i>
        </button>
        <div class="challenge-edit-header">
            <h3 id="modalTitle">Criar Novo Grupo</h3>
        </div>
        
        <div class="challenge-edit-body">
            <form id="groupForm">
                <input type="hidden" id="groupId" name="group_id" value="">
                
                <!-- Nome do Grupo -->
                <div class="challenge-form-group">
                    <label for="groupName">Nome do Grupo</label>
                    <input type="text" id="groupName" name="name" class="challenge-form-input" required 
                           placeholder="Ex: Grupo Premium">
                </div>
                
                <!-- Descrição -->
                <div class="challenge-form-group">
                    <label for="groupDescription">Descrição</label>
                    <textarea id="groupDescription" name="description" class="challenge-form-textarea" 
                              placeholder="Descreva o propósito do grupo"></textarea>
                </div>
                
                <!-- Status será definido automaticamente -->
                <input type="hidden" id="groupStatus" name="status" value="active">
                
                <!-- Participantes -->
                <div class="challenge-section">
                    <h3 class="challenge-section-title">
                        <i class="fas fa-users"></i> Participantes
                    </h3>
                    <div class="participants-search">
                        <input type="text" class="challenge-form-input" id="participantSearch" 
                               placeholder="Buscar pacientes..." 
                               onkeyup="filterParticipants()">
                    </div>
                    <div class="participants-list" id="participantsList">
                        <?php foreach ($users as $user): ?>
                            <div class="participant-tag" 
                                 data-user-id="<?php echo $user['id']; ?>"
                                 data-name="<?php echo strtolower(htmlspecialchars($user['name'])); ?>"
                                 data-email="<?php echo strtolower(htmlspecialchars($user['email'])); ?>">
                                <?php
                                $has_photo = false;
                                $avatar_url = '';
                                $bgColor = 'rgba(255, 107, 0, 0.1)';

                                if (!empty($user['profile_image_filename'])) {
                                    $original_path_on_server = APP_ROOT_PATH . '/assets/images/users/' . $user['profile_image_filename'];
                                    if (file_exists($original_path_on_server)) {
                                        $avatar_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($user['profile_image_filename']);
                                        $has_photo = true;
                                    } else {
                                        $thumb_filename = 'thumb_' . $user['profile_image_filename'];
                                        $thumb_path_on_server = APP_ROOT_PATH . '/assets/images/users/' . $thumb_filename;
                                        if (file_exists($thumb_path_on_server)) {
                                            $avatar_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($thumb_filename);
                                            $has_photo = true;
                                        }
                                    }
                                }

                                if (!$has_photo) {
                                    $name_parts = explode(' ', trim($user['name']));
                                    $initials = '';
                                    if (count($name_parts) > 1) {
                                        $initials = strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1));
                                    } elseif (!empty($name_parts[0])) {
                                        $initials = strtoupper(substr($name_parts[0], 0, 2));
                                    } else {
                                        $initials = '??';
                                    }
                                    $hash = md5($user['name']);
                                    $r = hexdec(substr($hash, 0, 2)) % 156 + 50;
                                    $g = hexdec(substr($hash, 2, 2)) % 156 + 50;
                                    $b = hexdec(substr($hash, 4, 2)) % 156 + 50;
                                    $max = max($r, $g, $b);
                                    if ($max > 180) {
                                        $r = (int)($r * 0.7);
                                        $g = (int)($g * 0.7);
                                        $b = (int)($b * 0.7);
                                    }
                                    $bgColor = sprintf('#%02x%02x%02x', $r, $g, $b);
                                }
                                ?>
                                <div class="participant-avatar" style="background-color: <?php echo $has_photo ? 'transparent' : $bgColor; ?>; color: <?php echo $has_photo ? 'var(--accent-orange)' : 'white'; ?>;">
                                    <?php if ($has_photo): ?>
                                        <img src="<?php echo $avatar_url; ?>" alt="Foto de <?php echo htmlspecialchars($user['name']); ?>">
                                    <?php else: ?>
                                        <?php echo $initials; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="participant-info">
                                    <div class="participant-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                    <div class="participant-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                                <input type="hidden" name="participants[]" value="<?php echo $user['id']; ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </div>

        <div class="challenge-edit-footer">
            <button type="button" class="btn-cancel" onclick="closeGroupModal()">Cancelar</button>
            <button type="button" class="btn-save" onclick="saveGroup()">
                <i class="fas fa-save"></i> Salvar Grupo
            </button>
        </div>
    </div>
</div>

<!-- Modal de Membros do Grupo -->
<div id="membersModal" class="challenge-edit-modal">
    <div class="challenge-edit-overlay" onclick="closeMembersModal()"></div>
    <div class="challenge-edit-content" style="max-width: 900px;">
        <button class="sleep-modal-close" onclick="closeMembersModal()" type="button">
            <i class="fas fa-times"></i>
        </button>
        <div class="challenge-edit-header">
            <h3 id="membersTitle">Membros do Grupo</h3>
        </div>
        <div class="challenge-edit-body" id="membersContent">
            <!-- Conteúdo será carregado via JavaScript -->
        </div>
    </div>
</div>

<!-- Modal de Metas do Grupo -->
<div id="goalsModal" class="challenge-edit-modal">
    <div class="challenge-edit-overlay" onclick="closeGoalsModal()"></div>
    <div class="challenge-edit-content" style="max-width: 700px;">
        <button class="sleep-modal-close" onclick="closeGoalsModal()" type="button">
            <i class="fas fa-times"></i>
        </button>
        <div class="challenge-edit-header">
            <h3 id="goalsTitle">Metas do Grupo</h3>
        </div>
        <div class="challenge-edit-body">
            <form id="goalsForm">
                <input type="hidden" id="goalsGroupId" name="group_id">
                
                <!-- Metas Nutricionais -->
                <div class="challenge-section">
                    <h3 class="challenge-section-title">
                        <i class="fas fa-utensils"></i> Metas Nutricionais
                    </h3>
                    <div class="challenge-form-row">
                        <div class="challenge-form-group">
                            <label for="targetKcal">Calorias (kcal/dia)</label>
                            <input type="number" id="targetKcal" name="target_kcal" class="challenge-form-input" min="0" step="1" placeholder="Ex: 2000">
                        </div>
                        <div class="challenge-form-group">
                            <label for="targetWater">Água (ml/dia)</label>
                            <input type="number" id="targetWater" name="target_water_ml" class="challenge-form-input" min="0" step="50" placeholder="Ex: 2000">
                        </div>
                    </div>
                    <div class="challenge-form-row">
                        <div class="challenge-form-group">
                            <label for="targetProtein">Proteínas (g/dia)</label>
                            <input type="number" id="targetProtein" name="target_protein_g" class="challenge-form-input" min="0" step="0.1" placeholder="Ex: 150">
                        </div>
                        <div class="challenge-form-group">
                            <label for="targetCarbs">Carboidratos (g/dia)</label>
                            <input type="number" id="targetCarbs" name="target_carbs_g" class="challenge-form-input" min="0" step="0.1" placeholder="Ex: 250">
                        </div>
                    </div>
                    <div class="challenge-form-group">
                        <label for="targetFat">Gorduras (g/dia)</label>
                        <input type="number" id="targetFat" name="target_fat_g" class="challenge-form-input" min="0" step="0.1" placeholder="Ex: 65">
                    </div>
                </div>
                
                <!-- Metas de Atividade -->
                <div class="challenge-section">
                    <h3 class="challenge-section-title">
                        <i class="fas fa-dumbbell"></i> Metas de Atividade
                    </h3>
                    <div class="challenge-form-row">
                        <div class="challenge-form-group">
                            <label for="targetSteps">Passos (passos/dia)</label>
                            <input type="number" id="targetSteps" name="target_steps_daily" class="challenge-form-input" min="0" step="100" placeholder="Ex: 10000">
                        </div>
                        <div class="challenge-form-group">
                            <label for="targetExercise">Exercício (min/dia)</label>
                            <input type="number" id="targetExercise" name="target_exercise_minutes" class="challenge-form-input" min="0" step="5" placeholder="Ex: 30">
                        </div>
                    </div>
                </div>
                
                <!-- Metas de Sono -->
                <div class="challenge-section">
                    <h3 class="challenge-section-title">
                        <i class="fas fa-bed"></i> Metas de Sono
                    </h3>
                    <div class="challenge-form-group">
                        <label for="targetSleep">Sono (horas/dia)</label>
                        <input type="number" id="targetSleep" name="target_sleep_hours" class="challenge-form-input" min="0" max="24" step="0.5" placeholder="Ex: 8">
                    </div>
                </div>
            </form>
        </div>
        <div class="challenge-edit-footer">
            <button type="button" class="btn-cancel" onclick="closeGoalsModal()">Cancelar</button>
            <button type="button" class="btn-save" onclick="saveGroupGoals()">
                <i class="fas fa-save"></i> Salvar Metas
            </button>
            <button type="button" class="btn-action btn-patients" onclick="applyGoalsToMembers()" style="margin-left: auto;">
                <i class="fas fa-users"></i> Aplicar aos Membros
            </button>
        </div>
    </div>
</div>

<script>
// Variáveis globais
let selectedParticipants = new Set();

// Função para filtrar por status
function filterByStatus(status) {
    const url = new URL(window.location.href);
    if (status) {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

// Função de busca
function handleSearch() {
    const searchInput = document.getElementById('searchInput');
    const searchTerm = searchInput.value.trim();
    const url = new URL(window.location.href);
    
    if (searchTerm) {
        url.searchParams.set('search', searchTerm);
    } else {
        url.searchParams.delete('search');
    }
    url.searchParams.delete('page');
    
    // Debounce
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => {
        window.location.href = url.toString();
    }, 500);
}

// Abrir modal de criar grupo
function openCreateGroupModal() {
    document.getElementById('modalTitle').textContent = 'Criar Novo Grupo';
    document.getElementById('groupForm').reset();
    document.getElementById('groupId').value = '';
    selectedParticipants.clear();
    document.querySelectorAll('.participant-tag').forEach(tag => {
        tag.classList.remove('selected');
    });
    document.getElementById('groupModal').classList.add('active');
}

// Fechar modal de grupo
function closeGroupModal() {
    document.getElementById('groupModal').classList.remove('active');
}

// Editar grupo
function editGroup(groupId) {
    fetch('ajax_user_groups.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get',
            group_id: groupId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const group = data.group;
            document.getElementById('modalTitle').textContent = 'Editar Grupo';
            document.getElementById('groupId').value = group.id;
            document.getElementById('groupName').value = group.name;
            document.getElementById('groupDescription').value = group.description || '';
            document.getElementById('groupStatus').value = group.status;
            
            // Selecionar participantes
            selectedParticipants.clear();
            if (group.members && Array.isArray(group.members)) {
                group.members.forEach(memberId => {
                    selectedParticipants.add(String(memberId));
                });
            }
            
            // Atualizar visual
            document.querySelectorAll('.participant-tag').forEach(tag => {
                const userId = tag.getAttribute('data-user-id');
                if (selectedParticipants.has(userId)) {
                    tag.classList.add('selected');
                } else {
                    tag.classList.remove('selected');
                }
            });
            
            document.getElementById('groupModal').classList.add('active');
        } else {
            alert('Erro ao carregar grupo: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao carregar grupo. Tente novamente.');
    });
}

// Salvar grupo
function saveGroup() {
    const groupId = document.getElementById('groupId').value;
    const name = document.getElementById('groupName').value.trim();
    const description = document.getElementById('groupDescription').value.trim();
    const status = document.getElementById('groupStatus').value;
    
    if (!name) {
        alert('Nome do grupo é obrigatório');
        return;
    }
    
    const members = Array.from(selectedParticipants).map(id => parseInt(id));
    
    fetch('ajax_user_groups.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'save',
            group_id: groupId || 0,
            name: name,
            description: description,
            status: status,
            members: members
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Grupo salvo com sucesso!');
            location.reload();
        } else {
            alert('Erro ao salvar grupo: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar grupo. Tente novamente.');
    });
}

// Excluir grupo
function deleteGroup(groupId) {
    if (!confirm('Tem certeza que deseja excluir este grupo? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    fetch('ajax_user_groups.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'delete',
            group_id: groupId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Grupo excluído com sucesso!');
            location.reload();
        } else {
            alert('Erro ao excluir grupo: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao excluir grupo. Tente novamente.');
    });
}

// Toggle status do grupo
function toggleGroupStatus(groupId, currentStatus, toggleElement) {
    const newStatus = toggleElement.checked ? 'active' : 'inactive';
    const label = document.getElementById('toggle-label-' + groupId);
    
    // Atualizar visual imediatamente
    label.textContent = newStatus === 'active' ? 'Ativo' : 'Inativo';
    label.className = 'toggle-switch-label ' + newStatus;
    label.style.color = newStatus === 'active' ? '#10b981' : '#ef4444';
    label.style.fontWeight = '600';
    
    fetch('ajax_user_groups.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'toggle_status',
            group_id: groupId,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Atualizar estatísticas
            updateStats();
            // Atualizar data-status do card
            const card = toggleElement.closest('.user-group-card');
            if (card) {
                card.setAttribute('data-status', newStatus);
            }
        } else {
            // Reverter toggle
            toggleElement.checked = currentStatus === 'active';
            label.textContent = currentStatus === 'active' ? 'Ativo' : 'Inativo';
            label.className = 'toggle-switch-label ' + currentStatus;
            label.style.color = currentStatus === 'active' ? '#10b981' : '#ef4444';
            alert('Erro ao atualizar status: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        // Reverter toggle
        toggleElement.checked = currentStatus === 'active';
        label.textContent = currentStatus === 'active' ? 'Ativo' : 'Inativo';
        label.className = 'toggle-switch-label ' + currentStatus;
        label.style.color = currentStatus === 'active' ? '#10b981' : '#ef4444';
        alert('Erro ao atualizar status. Tente novamente.');
    });
}

// Atualizar estatísticas
function updateStats() {
    fetch('ajax_user_groups.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_stats'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.stats) {
            document.getElementById('stat-total').textContent = data.stats.total || 0;
            document.getElementById('stat-active').textContent = data.stats.active || 0;
            document.getElementById('stat-inactive').textContent = data.stats.inactive || 0;
        }
    })
    .catch(error => {
        console.error('Erro ao atualizar estatísticas:', error);
    });
}

// Filtrar participantes
function filterParticipants() {
    const searchTerm = document.getElementById('participantSearch').value.toLowerCase();
    const participants = document.querySelectorAll('.participant-tag');
    
    participants.forEach(participant => {
        const name = participant.getAttribute('data-name');
        const email = participant.getAttribute('data-email');
        
        if (name.includes(searchTerm) || email.includes(searchTerm)) {
            participant.style.display = 'flex';
        } else {
            participant.style.display = 'none';
        }
    });
}

// Selecionar/desselecionar participante
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.participant-tag').forEach(tag => {
        tag.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            
            if (selectedParticipants.has(userId)) {
                selectedParticipants.delete(userId);
                this.classList.remove('selected');
            } else {
                selectedParticipants.add(userId);
                this.classList.add('selected');
            }
        });
    });
});

// Visualizar membros do grupo
function viewGroupMembers(groupId) {
    fetch('ajax_user_groups.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_members',
            group_id: groupId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const members = data.members || [];
            const modal = document.getElementById('membersModal');
            const content = document.getElementById('membersContent');
            const title = document.getElementById('membersTitle');
            
            if (members.length === 0) {
                content.innerHTML = '<p>Nenhum membro neste grupo.</p>';
            } else {
                let html = '<div class="participants-list">';
                members.forEach(member => {
                    const hasPhoto = member.profile_image && member.profile_image.trim() !== '';
                    const avatarUrl = hasPhoto ? '<?php echo BASE_ASSET_URL; ?>/assets/images/users/' + encodeURIComponent(member.profile_image) : '';
                    
                    let initials = '??';
                    let bgColor = '#666';
                    if (member.name) {
                        const nameParts = member.name.split(' ');
                        if (nameParts.length > 1) {
                            initials = (nameParts[0][0] + nameParts[nameParts.length - 1][0]).toUpperCase();
                        } else if (nameParts[0].length >= 2) {
                            initials = nameParts[0].substring(0, 2).toUpperCase();
                        }
                        const hash = md5(member.name);
                        const r = parseInt(hash.substring(0, 2), 16) % 156 + 50;
                        const g = parseInt(hash.substring(2, 4), 16) % 156 + 50;
                        const b = parseInt(hash.substring(4, 6), 16) % 156 + 50;
                        bgColor = `rgb(${r}, ${g}, ${b})`;
                    }
                    
                    html += `
                        <div class="participant-tag" style="cursor: pointer;" onclick="window.location.href='view_user.php?id=${member.id}'">
                            <div class="participant-avatar" style="background-color: ${hasPhoto ? 'transparent' : bgColor}; color: ${hasPhoto ? 'var(--accent-orange)' : 'white'};">
                                ${hasPhoto ? `<img src="${avatarUrl}" alt="${member.name}">` : initials}
                            </div>
                            <div class="participant-info">
                                <div class="participant-name">${member.name || 'Sem nome'}</div>
                                <div class="participant-email">${member.email || 'Sem email'}</div>
                            </div>
                            <i class="fas fa-chevron-right" style="margin-left: auto; color: var(--text-secondary);"></i>
                        </div>
                    `;
                });
                html += '</div>';
                content.innerHTML = html;
            }
            
            modal.classList.add('active');
        } else {
            alert('Erro ao carregar membros: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao carregar membros. Tente novamente.');
    });
}

// Fechar modal de membros
function closeMembersModal() {
    document.getElementById('membersModal').classList.remove('active');
}

// Gerenciar metas do grupo
function manageGroupGoals(groupId) {
    document.getElementById('goalsGroupId').value = groupId;
    document.getElementById('goalsTitle').textContent = 'Metas do Grupo';
    
    // Buscar metas existentes
    fetch('ajax_user_groups.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_goals',
            group_id: groupId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.goals) {
            const goals = data.goals;
            document.getElementById('targetKcal').value = goals.target_kcal || '';
            document.getElementById('targetWater').value = goals.target_water_ml || '';
            document.getElementById('targetProtein').value = goals.target_protein_g || '';
            document.getElementById('targetCarbs').value = goals.target_carbs_g || '';
            document.getElementById('targetFat').value = goals.target_fat_g || '';
            document.getElementById('targetSteps').value = goals.target_steps_daily || '';
            document.getElementById('targetExercise').value = goals.target_exercise_minutes || '';
            document.getElementById('targetSleep').value = goals.target_sleep_hours || '';
        } else {
            // Limpar campos se não houver metas
            document.getElementById('goalsForm').reset();
            document.getElementById('goalsGroupId').value = groupId;
        }
        document.getElementById('goalsModal').classList.add('active');
    })
    .catch(error => {
        console.error('Erro:', error);
        // Abrir modal mesmo se houver erro (para criar novas metas)
        document.getElementById('goalsForm').reset();
        document.getElementById('goalsGroupId').value = groupId;
        document.getElementById('goalsModal').classList.add('active');
    });
}

// Fechar modal de metas
function closeGoalsModal() {
    document.getElementById('goalsModal').classList.remove('active');
}

// Salvar metas do grupo
function saveGroupGoals() {
    const groupId = document.getElementById('goalsGroupId').value;
    const form = document.getElementById('goalsForm');
    const formData = new FormData(form);
    
    const goals = {
        group_id: groupId,
        target_kcal: formData.get('target_kcal') || null,
        target_water_ml: formData.get('target_water_ml') || null,
        target_protein_g: formData.get('target_protein_g') || null,
        target_carbs_g: formData.get('target_carbs_g') || null,
        target_fat_g: formData.get('target_fat_g') || null,
        target_steps_daily: formData.get('target_steps_daily') || null,
        target_exercise_minutes: formData.get('target_exercise_minutes') || null,
        target_sleep_hours: formData.get('target_sleep_hours') || null
    };
    
    fetch('ajax_user_groups.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'save_goals',
            ...goals
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Metas salvas com sucesso!');
            closeGoalsModal();
        } else {
            alert('Erro ao salvar metas: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar metas. Tente novamente.');
    });
}

// Aplicar metas aos membros do grupo
function applyGoalsToMembers() {
    const groupId = document.getElementById('goalsGroupId').value;
    
    if (!confirm('Deseja aplicar essas metas a todos os membros deste grupo? Isso irá sobrescrever as metas atuais de cada membro.')) {
        return;
    }
    
    fetch('ajax_user_groups.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'apply_goals_to_members',
            group_id: groupId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Metas aplicadas com sucesso aos membros!');
        } else {
            alert('Erro ao aplicar metas: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao aplicar metas. Tente novamente.');
    });
}

// Ver pacientes do grupo (link para users.php com filtro)
function viewGroupPatients(groupId) {
    window.location.href = 'users.php?group=' + groupId;
}

// Função MD5 simples (para gerar cores)
function md5(string) {
    function md5_RotateLeft(lValue, iShiftBits) {
        return (lValue<<iShiftBits) | (lValue>>>(32-iShiftBits));
    }
    function md5_AddUnsigned(lX,lY) {
        var lX4,lY4,lX8,lY8,lResult;
        lX8 = (lX & 0x80000000);
        lY8 = (lY & 0x80000000);
        lX4 = (lX & 0x40000000);
        lY4 = (lY & 0x40000000);
        lResult = (lX & 0x3FFFFFFF)+(lY & 0x3FFFFFFF);
        if (lX4 & lY4) {
            return (lResult ^ 0x80000000 ^ lX8 ^ lY8);
        }
        if (lX4 | lY4) {
            if (lResult & 0x40000000) {
                return (lResult ^ 0xC0000000 ^ lX8 ^ lY8);
            } else {
                return (lResult ^ 0x40000000 ^ lX8 ^ lY8);
            }
        } else {
            return (lResult ^ lX8 ^ lY8);
        }
    }
    function md5_F(x,y,z) { return (x & y) | ((~x) & z); }
    function md5_G(x,y,z) { return (x & z) | (y & (~z)); }
    function md5_H(x,y,z) { return (x ^ y ^ z); }
    function md5_I(x,y,z) { return (y ^ (x | (~z))); }
    function md5_FF(a,b,c,d,x,s,ac) {
        a = md5_AddUnsigned(a, md5_AddUnsigned(md5_AddUnsigned(md5_F(b, c, d), x), ac));
        return md5_AddUnsigned(md5_RotateLeft(a, s), b);
    }
    function md5_GG(a,b,c,d,x,s,ac) {
        a = md5_AddUnsigned(a, md5_AddUnsigned(md5_AddUnsigned(md5_G(b, c, d), x), ac));
        return md5_AddUnsigned(md5_RotateLeft(a, s), b);
    }
    function md5_HH(a,b,c,d,x,s,ac) {
        a = md5_AddUnsigned(a, md5_AddUnsigned(md5_AddUnsigned(md5_H(b, c, d), x), ac));
        return md5_AddUnsigned(md5_RotateLeft(a, s), b);
    }
    function md5_II(a,b,c,d,x,s,ac) {
        a = md5_AddUnsigned(a, md5_AddUnsigned(md5_AddUnsigned(md5_I(b, c, d), x), ac));
        return md5_AddUnsigned(md5_RotateLeft(a, s), b);
    }
    function md5_ConvertToWordArray(string) {
        var lWordCount;
        var lMessageLength = string.length;
        var lNumberOfWords_temp1=lMessageLength + 8;
        var lNumberOfWords_temp2=(lNumberOfWords_temp1-(lNumberOfWords_temp1 % 64))/64;
        var lNumberOfWords = (lNumberOfWords_temp2+1)*16;
        var lWordArray=Array(lNumberOfWords-1);
        var lBytePosition = 0;
        var lByteCount = 0;
        while ( lByteCount < lMessageLength ) {
            lWordCount = (lByteCount-(lByteCount % 4))/4;
            lBytePosition = (lByteCount % 4)*8;
            lWordArray[lWordCount] = (lWordArray[lWordCount] | (string.charCodeAt(lByteCount)<<lBytePosition));
            lByteCount++;
        }
        lWordCount = (lByteCount-(lByteCount % 4))/4;
        lBytePosition = (lByteCount % 4)*8;
        lWordArray[lWordCount] = lWordArray[lWordCount] | (0x80<<lBytePosition);
        lWordArray[lNumberOfWords-2] = lMessageLength<<3;
        lWordArray[lNumberOfWords-1] = lMessageLength>>>29;
        return lWordArray;
    }
    function md5_WordToHex(lValue) {
        var WordToHexValue="",WordToHexValue_temp="",lByte,lCount;
        for (lCount = 0;lCount<=3;lCount++) {
            lByte = (lValue>>>(lCount*8)) & 255;
            WordToHexValue_temp = "0" + lByte.toString(16);
            WordToHexValue = WordToHexValue + WordToHexValue_temp.substr(WordToHexValue_temp.length-2,2);
        }
        return WordToHexValue;
    }
    function md5_Utf8Encode(string) {
        string = string.replace(/\r\n/g,"\n");
        var utftext = "";
        for (var n = 0; n < string.length; n++) {
            var c = string.charCodeAt(n);
            if (c < 128) {
                utftext += String.fromCharCode(c);
            }
            else if((c > 127) && (c < 2048)) {
                utftext += String.fromCharCode((c >> 6) | 192);
                utftext += String.fromCharCode((c & 63) | 128);
            }
            else {
                utftext += String.fromCharCode((c >> 12) | 224);
                utftext += String.fromCharCode(((c >> 6) & 63) | 128);
                utftext += String.fromCharCode((c & 63) | 128);
            }
        }
        return utftext;
    }
    var x=Array();
    var k,AA,BB,CC,DD,a,b,c,d;
    var S11=7, S12=12, S13=17, S14=22;
    var S21=5, S22=9 , S23=14, S24=20;
    var S31=4, S32=11, S33=16, S34=23;
    var S41=6, S42=10, S43=15, S44=21;
    string = md5_Utf8Encode(string);
    x = md5_ConvertToWordArray(string);
    a = 0x67452301; b = 0xEFCDAB89; c = 0x98BADCFE; d = 0x10325476;
    for (k=0;k<x.length;k+=16) {
        AA=a; BB=b; CC=c; DD=d;
        a=md5_FF(a,b,c,d,x[k+0], S11,0xD76AA478);
        d=md5_FF(d,a,b,c,x[k+1], S12,0xE8C7B756);
        c=md5_FF(c,d,a,b,x[k+2], S13,0x242070DB);
        b=md5_FF(b,c,d,a,x[k+3], S14,0xC1BDCEEE);
        a=md5_FF(a,b,c,d,x[k+4], S11,0xF57C0FAF);
        d=md5_FF(d,a,b,c,x[k+5], S12,0x4787C62A);
        c=md5_FF(c,d,a,b,x[k+6], S13,0xA8304613);
        b=md5_FF(b,c,d,a,x[k+7], S14,0xFD469501);
        a=md5_FF(a,b,c,d,x[k+8], S11,0x698098D8);
        d=md5_FF(d,a,b,c,x[k+9], S12,0x8B44F7AF);
        c=md5_FF(c,d,a,b,x[k+10],S13,0xFFFF5BB1);
        b=md5_FF(b,c,d,a,x[k+11],S14,0x895CD7BE);
        a=md5_FF(a,b,c,d,x[k+12],S11,0x6B901122);
        d=md5_FF(d,a,b,c,x[k+13],S12,0xFD987193);
        c=md5_FF(c,d,a,b,x[k+14],S13,0xA679438E);
        b=md5_FF(b,c,d,a,x[k+15],S14,0x49B40821);
        a=md5_GG(a,b,c,d,x[k+1], S21,0xF61E2562);
        d=md5_GG(d,a,b,c,x[k+6], S22,0xC040B340);
        c=md5_GG(c,d,a,b,x[k+11],S23,0x265E5A51);
        b=md5_GG(b,c,d,a,x[k+0], S24,0xE9B6C7AA);
        a=md5_GG(a,b,c,d,x[k+5], S21,0xD62F105D);
        d=md5_GG(d,a,b,c,x[k+10],S22,0x2441453);
        c=md5_GG(c,d,a,b,x[k+15],S23,0xD8A1E681);
        b=md5_GG(b,c,d,a,x[k+4], S24,0xE7D3FBC8);
        a=md5_GG(a,b,c,d,x[k+9], S21,0x21E1CDE6);
        d=md5_GG(d,a,b,c,x[k+14],S22,0xC33707D6);
        c=md5_GG(c,d,a,b,x[k+3], S23,0xF4D50D87);
        b=md5_GG(b,c,d,a,x[k+8], S24,0x455A14ED);
        a=md5_GG(a,b,c,d,x[k+13],S21,0xA9E3E905);
        d=md5_GG(d,a,b,c,x[k+2], S22,0xFCEFA3F8);
        c=md5_GG(c,d,a,b,x[k+7], S23,0x676F02D9);
        b=md5_GG(b,c,d,a,x[k+12],S24,0x8D2A4C8A);
        a=md5_HH(a,b,c,d,x[k+5], S31,0xFFFA3942);
        d=md5_HH(d,a,b,c,x[k+8], S32,0x8771F681);
        c=md5_HH(c,d,a,b,x[k+11],S33,0x6D9D6122);
        b=md5_HH(b,c,d,a,x[k+14],S34,0xFDE5380C);
        a=md5_HH(a,b,c,d,x[k+1], S31,0xA4BEEA44);
        d=md5_HH(d,a,b,c,x[k+4], S32,0x4BDECFA9);
        c=md5_HH(c,d,a,b,x[k+7], S33,0xF6BB4B60);
        b=md5_HH(b,c,d,a,x[k+10],S34,0xBEBFBC70);
        a=md5_HH(a,b,c,d,x[k+13],S31,0x289B7EC6);
        d=md5_HH(d,a,b,c,x[k+0], S32,0xEAA127FA);
        c=md5_HH(c,d,a,b,x[k+3], S33,0xD4EF3085);
        b=md5_HH(b,c,d,a,x[k+6], S34,0x4881D05);
        a=md5_HH(a,b,c,d,x[k+9], S31,0xD9D4D039);
        d=md5_HH(d,a,b,c,x[k+12],S32,0xE6DB99E5);
        c=md5_HH(c,d,a,b,x[k+15],S33,0x1FA27CF8);
        b=md5_HH(b,c,d,a,x[k+2], S34,0xC4AC5665);
        a=md5_II(a,b,c,d,x[k+0], S41,0xF4292244);
        d=md5_II(d,a,b,c,x[k+7], S42,0x432AFF97);
        c=md5_II(c,d,a,b,x[k+14],S43,0xAB9423A7);
        b=md5_II(b,c,d,a,x[k+5], S44,0xFC93A039);
        a=md5_II(a,b,c,d,x[k+12],S41,0x655B59C3);
        d=md5_II(d,a,b,c,x[k+3], S42,0x8F0CCC92);
        c=md5_II(c,d,a,b,x[k+10],S43,0xFFEFF47D);
        b=md5_II(b,c,d,a,x[k+1], S44,0x85845DD1);
        a=md5_II(a,b,c,d,x[k+8], S41,0x6FA87E4F);
        d=md5_II(d,a,b,c,x[k+15],S42,0xFE2CE6E0);
        c=md5_II(c,d,a,b,x[k+6], S43,0xA3014314);
        b=md5_II(b,c,d,a,x[k+13],S44,0x4E0811A1);
        a=md5_II(a,b,c,d,x[k+4], S41,0xF7537E82);
        d=md5_II(d,a,b,c,x[k+11],S42,0xBD3AF235);
        c=md5_II(c,d,a,b,x[k+2], S43,0x2AD7D2BB);
        b=md5_II(b,c,d,a,x[k+9], S44,0xEB86D391);
        a=md5_AddUnsigned(a,AA);
        b=md5_AddUnsigned(b,BB);
        c=md5_AddUnsigned(c,CC);
        d=md5_AddUnsigned(d,DD);
    }
    return (md5_WordToHex(a)+md5_WordToHex(b)+md5_WordToHex(c)+md5_WordToHex(d)).toLowerCase();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
