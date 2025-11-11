<?php
// admin/content_management.php - Gerenciamento de Conteúdo - Design Profissional

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'content_management';
$page_title = 'Gerenciamento de Conteúdo';

$admin_id = $_SESSION['admin_id'] ?? 1;

// --- Lógica de busca e filtro ---
$search_term = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// --- Verificar se a tabela existe e tem as colunas necessárias ---
$table_exists = false;
$has_status_column = false;

try {
    // Verificar se a tabela existe
    $check_table = $conn->query("SHOW TABLES LIKE 'sf_member_content'");
    if ($check_table && $check_table->num_rows > 0) {
        $table_exists = true;
        
        // Verificar se a coluna status existe
        $check_status = $conn->query("SHOW COLUMNS FROM sf_member_content LIKE 'status'");
        if ($check_status && $check_status->num_rows > 0) {
            $has_status_column = true;
        }
    }
} catch (Exception $e) {
    // Tabela não existe ou erro
    $table_exists = false;
    $has_status_column = false;
}

// --- Estatísticas gerais ---
$stats = [];

if ($table_exists) {
    // Total de conteúdos
    try {
        $total_result = $conn->query("SELECT COUNT(*) as count FROM sf_member_content WHERE admin_id = $admin_id");
        if ($total_result) {
            $stats['total'] = $total_result->fetch_assoc()['count'];
        } else {
            $stats['total'] = 0;
        }
    } catch (Exception $e) {
        $stats['total'] = 0;
    }

    // Por status (só se a coluna existir)
    if ($has_status_column) {
        try {
            $stats_query = "SELECT status, COUNT(*) as count 
                            FROM sf_member_content 
                            WHERE admin_id = $admin_id
                            GROUP BY status";
            $stats_result = $conn->query($stats_query);
            $stats_by_status = ['active' => 0, 'inactive' => 0, 'draft' => 0];
            if ($stats_result) {
                while ($row = $stats_result->fetch_assoc()) {
                    if (isset($stats_by_status[$row['status']])) {
                        $stats_by_status[$row['status']] = $row['count'];
                    }
                }
            }
            $stats['active'] = $stats_by_status['active'];
            $stats['inactive'] = $stats_by_status['inactive'];
            $stats['draft'] = $stats_by_status['draft'];
        } catch (Exception $e) {
            $stats['active'] = 0;
            $stats['inactive'] = 0;
            $stats['draft'] = 0;
        }
    } else {
        // Se não tem coluna status, assume que todos são ativos
        $stats['active'] = $stats['total'];
        $stats['inactive'] = 0;
        $stats['draft'] = 0;
    }
} else {
    // Tabela não existe
    $stats['total'] = 0;
    $stats['active'] = 0;
    $stats['inactive'] = 0;
    $stats['draft'] = 0;
}

// --- Construir query de busca ---
$contents = [];

if ($table_exists) {
    $sql = "SELECT 
        mc.*,
        a.full_name as author_name,
        GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as categories
                      FROM sf_member_content mc
                      LEFT JOIN sf_admins a ON mc.admin_id = a.id
        LEFT JOIN sf_content_category_relations ccr ON mc.id = ccr.content_id
        LEFT JOIN sf_categories c ON ccr.category_id = c.id
        WHERE mc.admin_id = ?";
    $conditions = [];
    $params = [$admin_id];
    $types = 'i';

    if (!empty($search_term)) {
        $conditions[] = "(mc.title LIKE ? OR mc.description LIKE ?)";
        $params[] = '%' . $search_term . '%';
        $params[] = '%' . $search_term . '%';
        $types .= 'ss';
    }

    if (!empty($status_filter) && $has_status_column) {
        $conditions[] = "mc.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }

    if (!empty($type_filter)) {
        $conditions[] = "mc.content_type = ?";
        $params[] = $type_filter;
        $types .= 's';
    }

    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }

    $sql .= " GROUP BY mc.id ORDER BY mc.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    $types .= 'ii';

    // Executar query
    try {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $contents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    } catch (Exception $e) {
        $contents = [];
    }
}

// Buscar categorias (usando sf_categories existente)
$categories_query = "SELECT * FROM sf_categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Buscar usuários para conteúdo específico
$users_query = "SELECT id, name, email FROM sf_users ORDER BY name";
$users_result = $conn->query($users_query);
$users = $users_result->fetch_all(MYSQLI_ASSOC);

// Buscar grupos para conteúdo específico
$groups_query = "SELECT id, group_name as name FROM sf_user_groups WHERE is_active = 1 ORDER BY group_name";
$groups_result = $conn->query($groups_query);
$groups = $groups_result->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ========================================================================= */
/*       CONTENT MANAGEMENT PAGE - DESIGN MODERNO (IGUAL CHALLENGE_GROUPS)   */
/* ========================================================================= */

.content-management-page {
    padding: 1.5rem 2rem;
    min-height: 100vh;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

/* Forçar remoção de sombras e efeitos de todos os cards */
.content-management-page * {
    box-shadow: none !important;
}

.content-management-page .dashboard-card,
.content-management-page .content-card,
.content-management-page [class*="card"] {
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

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-top: 1.5rem;
}

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
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
    box-shadow: none !important;
}

.stat-card:hover {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: var(--accent-orange) !important;
}

.stat-number {
    font-size: 1.5rem !important;
    font-weight: 700 !important;
    color: var(--accent-orange) !important;
    margin: 0 !important;
    line-height: 1.2 !important;
}

.stat-label {
    font-size: 0.75rem !important;
    color: var(--text-secondary) !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    font-weight: 600 !important;
    margin: 0.5rem 0 0 0 !important;
}

/* Filter Card */
.filter-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 1.25rem !important;
    margin-bottom: 2rem !important;
    box-shadow: none !important;
}

.filter-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.search-input {
    flex: 1;
    min-width: 250px;
    padding: 0.875rem 1.25rem;
    font-size: 0.95rem;
    color: var(--text-primary);
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    outline: none;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
}

.search-input:focus {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

/* Custom Select */
.custom-select-wrapper {
    position: relative;
    min-width: 180px;
    z-index: 1;
}

.custom-select-wrapper.active {
    z-index: 9999;
}

.custom-select-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.875rem 1.25rem;
    font-size: 0.95rem;
    color: var(--text-primary);
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.custom-select-trigger:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.custom-select.active .custom-select-trigger {
    border-color: var(--accent-orange);
}

.custom-select-options {
    display: none;
    position: absolute;
    top: calc(100% + 0.5rem);
    left: 0;
    right: 0;
    z-index: 9999;
    background: rgb(28, 28, 28);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    max-height: 300px;
    overflow-y: auto;
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
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
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

/* Button Create */
.btn-create-content {
    width: 64px;
    height: 64px;
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

.btn-create-content:hover {
    background: rgba(255, 107, 0, 0.15);
    border-color: var(--accent-orange);
    transform: scale(1.05);
}

.btn-create-content i {
    font-size: 1.5rem;
}

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 380px), 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.content-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 12px !important;
    padding: 1rem !important;
    transition: all 0.3s ease !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 0.75rem !important;
    box-shadow: none !important;
    cursor: pointer;
}

.content-card:hover {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: var(--accent-orange) !important;
}

.content-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
}

.content-type-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(255, 107, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-orange);
    font-size: 1.5rem;
    flex-shrink: 0;
}

.content-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-action {
    padding: 0.5rem;
    border-radius: 8px;
    border: 1px solid var(--glass-border);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
}

.btn-action:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--accent-orange);
}

.content-body h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.content-body p {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin: 0 0 0.75rem 0;
    line-height: 1.5;
}

.content-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8125rem;
    color: var(--text-secondary);
}

.meta-item i {
    color: var(--accent-orange);
    width: 16px;
}

.content-target {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8125rem;
    color: var(--text-secondary);
    padding: 0.5rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 8px;
}

.content-target i {
    color: var(--accent-orange);
}

.content-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin-top: auto;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.active {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.status-badge.inactive {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.status-badge.draft {
    background: rgba(156, 163, 175, 0.2);
    color: #9ca3af;
}

.content-stats {
    display: flex;
    gap: 1rem;
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.content-stats span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Empty State */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3rem 1rem;
}

.empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(255, 107, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--accent-orange);
    font-size: 2rem;
}

.empty-state h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.empty-state p {
    font-size: 1rem;
    color: var(--text-secondary);
    margin: 0 0 1.5rem 0;
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
}

.challenge-edit-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    flex-wrap: wrap;
    align-items: center;
}

.challenge-edit-footer button {
    padding: 0.625rem 1.25rem !important;
    border-radius: 10px !important;
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.5rem !important;
    white-space: nowrap;
    min-width: 160px !important;
    width: 160px !important;
    height: 42px !important;
    box-sizing: border-box !important;
    flex-shrink: 0;
    text-align: center;
}

.challenge-edit-footer .btn-cancel {
    background: rgba(255, 255, 255, 0.05) !important;
    color: var(--text-secondary) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
}

.challenge-edit-footer .btn-cancel:hover {
    background: rgba(255, 255, 255, 0.08) !important;
    color: var(--text-primary) !important;
}

.challenge-edit-footer .btn-save {
    background: linear-gradient(135deg, #FF6600, #FF8533) !important;
    color: white !important;
    border: none !important;
}

.challenge-edit-footer .btn-save:hover {
    background: linear-gradient(135deg, #FF8533, #FF6600) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 102, 0, 0.3);
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
.challenge-form-group label:has(+ textarea[required])::after,
.challenge-form-group label:has(+ select[required])::after {
    content: ' *';
    color: var(--accent-orange);
    margin-left: 0.25rem;
}

.challenge-form-input,
.challenge-form-textarea,
.challenge-form-select {
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
.challenge-form-textarea:focus,
.challenge-form-select:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

.challenge-form-textarea {
    resize: vertical;
    min-height: 100px;
}

.challenge-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.categories-selection {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.category-item label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
}

.category-item label:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.category-item input[type="checkbox"] {
    display: none;
}

.category-item input[type="checkbox"]:checked + i {
    color: var(--accent-orange);
}

.category-item input[type="checkbox"]:checked ~ span {
    color: var(--accent-orange);
}

/* File Input */
.challenge-form-input[type="file"] {
    padding: 0.75rem;
    cursor: pointer;
}

.challenge-form-input[type="file"]::file-selector-button {
    padding: 0.5rem 1rem;
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.3);
    border-radius: 8px;
    color: var(--accent-orange);
    cursor: pointer;
    font-weight: 600;
    margin-right: 1rem;
    transition: all 0.3s ease;
}

.challenge-form-input[type="file"]::file-selector-button:hover {
    background: rgba(255, 107, 0, 0.2);
    border-color: var(--accent-orange);
}

/* Responsive */
@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 340px), 1fr));
        gap: 1.25rem;
    }
}

@media (max-width: 768px) {
    .content-management-page {
        padding: 1rem;
    }
    
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .challenge-form-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .header-title {
        flex-direction: column;
    }
    
    .filter-row {
        flex-direction: column;
    }
    
    .search-input {
        width: 100%;
    }
}
</style>

<div class="content-management-page">
    <!-- Header Card -->
    <div class="header-card">
        <div class="header-title">
            <div>
                <h2><i class="fas fa-file-alt"></i> Gerenciamento de Conteúdo</h2>
                <p>Crie e gerencie conteúdos para a área de membros dos seus pacientes</p>
            </div>
            <button class="btn-create-content" onclick="openCreateContentModal()" title="Criar Novo Conteúdo">
                <i class="fas fa-plus"></i>
                </button>
            </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card" onclick="filterByStatus('')">
                <div class="stat-number" id="statTotal"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card" onclick="filterByStatus('active')">
                <div class="stat-number" id="statActive"><?php echo $stats['active']; ?></div>
                <div class="stat-label">Ativos</div>
            </div>
            <div class="stat-card" onclick="filterByStatus('inactive')">
                <div class="stat-number" id="statInactive"><?php echo $stats['inactive']; ?></div>
                <div class="stat-label">Inativos</div>
            </div>
            <div class="stat-card" onclick="filterByStatus('draft')">
                <div class="stat-number" id="statDraft"><?php echo $stats['draft']; ?></div>
                <div class="stat-label">Rascunhos</div>
            </div>
        </div>
        </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <div class="filter-row">
            <input type="text" class="search-input" placeholder="Buscar por título ou descrição..." 
                   value="<?php echo htmlspecialchars($search_term); ?>" 
                   onkeyup="handleSearch(event)" id="searchInput">
            
            <div class="custom-select-wrapper" id="typeSelectWrapper">
                <div class="custom-select" id="typeSelect">
                    <div class="custom-select-trigger" onclick="toggleSelect('typeSelect')">
                        <span><?php echo $type_filter ? ucfirst($type_filter) : 'Todos os tipos'; ?></span>
                        <i class="fas fa-chevron-down"></i>
            </div>
                    <div class="custom-select-options">
                        <div class="custom-select-option <?php echo !$type_filter ? 'selected' : ''; ?>" 
                             onclick="selectType('')">Todos os tipos</div>
                        <div class="custom-select-option <?php echo $type_filter === 'chef' ? 'selected' : ''; ?>" 
                             onclick="selectType('chef')">Chef</div>
                        <div class="custom-select-option <?php echo $type_filter === 'supplements' ? 'selected' : ''; ?>" 
                             onclick="selectType('supplements')">Suplementos</div>
                        <div class="custom-select-option <?php echo $type_filter === 'videos' ? 'selected' : ''; ?>" 
                             onclick="selectType('videos')">Vídeos</div>
                        <div class="custom-select-option <?php echo $type_filter === 'articles' ? 'selected' : ''; ?>" 
                             onclick="selectType('articles')">Artigos</div>
                        <div class="custom-select-option <?php echo $type_filter === 'pdf' ? 'selected' : ''; ?>" 
                             onclick="selectType('pdf')">PDF</div>
                </div>
                </div>
                </div>
            
            <div class="custom-select-wrapper" id="statusSelectWrapper">
                <div class="custom-select" id="statusSelect">
                    <div class="custom-select-trigger" onclick="toggleSelect('statusSelect')">
                        <span><?php echo $status_filter ? ucfirst($status_filter) : 'Todos os status'; ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="custom-select-options">
                        <div class="custom-select-option <?php echo !$status_filter ? 'selected' : ''; ?>" 
                             onclick="selectStatus('')">Todos os status</div>
                        <div class="custom-select-option <?php echo $status_filter === 'active' ? 'selected' : ''; ?>" 
                             onclick="selectStatus('active')">Ativo</div>
                        <div class="custom-select-option <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>" 
                             onclick="selectStatus('inactive')">Inativo</div>
                        <div class="custom-select-option <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>" 
                             onclick="selectStatus('draft')">Rascunho</div>
                    </div>
                </div>
            </div>
            </div>
        </div>

    <!-- Content Grid -->
        <div class="content-grid" id="contentGrid">
            <?php if (empty($contents)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                    <i class="fas fa-file-alt"></i>
                    </div>
                    <h3>Nenhum conteúdo encontrado</h3>
                <p>Crie seu primeiro conteúdo para a área de membros dos seus pacientes</p>
                </div>
            <?php else: ?>
            <?php foreach ($contents as $content): ?>
                <div class="content-card" data-type="<?php echo $content['content_type']; ?>" data-status="<?php echo $content['status']; ?>">
                    <div class="content-header">
                        <div class="content-type-icon">
                            <?php
                            $icon = 'fas fa-file';
                            switch($content['content_type']) {
                                case 'chef':
                                    $icon = 'fas fa-utensils';
                                    break;
                                case 'supplements':
                                    $icon = 'fas fa-pills';
                                    break;
                                case 'videos':
                                    $icon = 'fas fa-play';
                                    break;
                                case 'articles':
                                    $icon = 'fas fa-file-alt';
                                    break;
                                case 'pdf':
                                    $icon = 'fas fa-file-pdf';
                                    break;
                            }
                            ?>
                            <i class="<?php echo $icon; ?>"></i>
                        </div>
                        <div class="content-actions">
                            <button class="btn-action" onclick="viewContent(<?php echo $content['id']; ?>)" title="Visualizar">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn-action" onclick="editContent(<?php echo $content['id']; ?>)" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-action" onclick="deleteContent(<?php echo $content['id']; ?>)" title="Excluir" style="color: #ef4444;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="content-body">
                        <h3><?php echo htmlspecialchars($content['title']); ?></h3>
                        <p><?php echo htmlspecialchars($content['description'] ? substr($content['description'], 0, 100) . '...' : 'Sem descrição'); ?></p>
                        
                        <div class="content-meta">
                            <div class="meta-item">
                                <i class="fas fa-user"></i>
                                <span><?php echo htmlspecialchars($content['author_name'] ?? 'Admin'); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo date('d/m/Y', strtotime($content['created_at'])); ?></span>
                            </div>
                            <?php if (!empty($content['categories'])): ?>
                            <div class="meta-item">
                                <i class="fas fa-tag"></i>
                                <span><?php echo htmlspecialchars($content['categories']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="content-target">
                            <i class="fas fa-users"></i>
                            <span>
                                <?php
                                switch($content['target_type'] ?? 'all') {
                                    case 'all':
                                        echo 'Todos os usuários';
                                        break;
                                    case 'user':
                                        echo 'Usuário específico';
                                        break;
                                    case 'group':
                                        echo 'Grupo específico';
                                        break;
                                    default:
                                        echo 'Todos os usuários';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="content-footer">
                        <div class="content-status">
                            <span class="status-badge <?php echo $content['status'] ?? 'draft'; ?>">
                                <?php echo ucfirst($content['status'] ?? 'draft'); ?>
                            </span>
                        </div>
                        <div class="content-stats">
                            <?php if ($content['file_path']): ?>
                                <span><i class="fas fa-file"></i> <?php echo $content['content_type'] === 'videos' ? 'Vídeo' : ($content['content_type'] === 'pdf' ? 'PDF' : 'Arquivo'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
    </div>
</div>

<!-- Modal de Criar/Editar Conteúdo -->
<div id="contentModal" class="challenge-edit-modal">
    <div class="challenge-edit-overlay" onclick="closeContentModal()"></div>
    <div class="challenge-edit-content">
        <button class="sleep-modal-close" onclick="closeContentModal()" type="button">
            <i class="fas fa-times"></i>
        </button>
        <div class="challenge-edit-header">
            <h3 id="modalTitle">Criar Conteúdo</h3>
        </div>
        <div class="challenge-edit-body">
            <form id="contentForm" enctype="multipart/form-data">
                <input type="hidden" id="contentId" name="content_id">
                
                <div class="challenge-form-row">
                    <div class="challenge-form-group">
                        <label for="contentTitle">Título *</label>
                        <input type="text" id="contentTitle" name="title" class="challenge-form-input" required placeholder="Ex: Receita de Salada Fit">
                    </div>
                    <div class="challenge-form-group">
                        <label for="contentType">Tipo de Conteúdo *</label>
                        <select id="contentType" name="content_type" class="challenge-form-select" required onchange="toggleContentFields()">
                            <option value="">Selecione...</option>
                            <option value="chef">Chef</option>
                            <option value="supplements">Suplementos</option>
                            <option value="videos">Vídeos</option>
                            <option value="articles">Artigos</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                </div>
                
                <div class="challenge-form-group">
                    <label for="contentDescription">Descrição</label>
                    <textarea id="contentDescription" name="description" class="challenge-form-textarea" rows="3" placeholder="Descreva o conteúdo"></textarea>
                </div>
                
                <div class="challenge-form-group" id="fileUploadGroup">
                    <label for="contentFile">Arquivo</label>
                    <input type="file" id="contentFile" name="file" class="challenge-form-input" accept="image/*,video/mp4,video/quicktime,video/x-msvideo,video/webm,.pdf">
                    <small style="color: var(--text-secondary); font-size: 0.75rem; margin-top: 0.5rem; display: block;">Formatos aceitos: Imagens (JPG, PNG, GIF, WebP), Vídeos (MP4, MOV, AVI, WebM), PDF. Máximo: 100MB para vídeos, 10MB para outros.</small>
                </div>
                
                <div class="challenge-form-group" id="contentTextGroup" style="display: none;">
                    <label for="contentText">Conteúdo do Artigo</label>
                    <textarea id="contentText" name="content_text" class="challenge-form-textarea" rows="10" placeholder="Digite o conteúdo do artigo aqui..."></textarea>
                </div>
                
                <div class="challenge-form-row">
                    <div class="challenge-form-group">
                        <label for="targetType">Público-Alvo *</label>
                        <select id="targetType" name="target_type" class="challenge-form-select" required onchange="toggleTargetFields()">
                            <option value="">Selecione...</option>
                            <option value="all">Todos os usuários</option>
                            <option value="user">Usuário específico</option>
                            <option value="group">Grupo específico</option>
                        </select>
                    </div>
                    <div class="challenge-form-group" id="targetIdGroup" style="display: none;">
                        <label for="targetId">Selecionar</label>
                        <select id="targetId" name="target_id" class="challenge-form-select">
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                </div>
                
                <div class="challenge-form-group">
                    <label>Categorias</label>
                    <div class="categories-selection">
                        <?php foreach ($categories as $category): ?>
                            <div class="category-item">
                                <label>
                                    <input type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>">
                                    <i class="fas fa-tag"></i>
                                    <span><?php echo htmlspecialchars($category['name']); ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="challenge-form-group">
                    <label for="contentStatus">Status</label>
                    <select id="contentStatus" name="status" class="challenge-form-select">
                        <option value="draft">Rascunho</option>
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="challenge-edit-footer">
            <button type="button" class="btn-cancel" onclick="closeContentModal()">Cancelar</button>
            <button type="button" class="btn-save" onclick="saveContent()">
                <i class="fas fa-save"></i> Salvar Conteúdo
            </button>
        </div>
    </div>
</div>

<script>
// Dados para JavaScript
const users = <?php echo json_encode($users); ?>;
const groups = <?php echo json_encode($groups); ?>;

// Toggle custom select
function toggleSelect(selectId) {
    const select = document.getElementById(selectId);
    const wrapper = select.closest('.custom-select-wrapper');
    const allSelects = document.querySelectorAll('.custom-select');
    const allWrappers = document.querySelectorAll('.custom-select-wrapper');
    
    // Close all other selects
    allSelects.forEach(s => {
        if (s.id !== selectId) {
            s.classList.remove('active');
        }
    });
    allWrappers.forEach(w => {
        if (w.id !== wrapper.id) {
            w.classList.remove('active');
        }
    });
    
    // Toggle current select
    select.classList.toggle('active');
    wrapper.classList.toggle('active');
}

// Select type filter
function selectType(type) {
    const url = new URL(window.location);
    if (type) {
        url.searchParams.set('type', type);
    } else {
        url.searchParams.delete('type');
    }
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

// Select status filter
function selectStatus(status) {
    const url = new URL(window.location);
    if (status) {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

// Filter by status from stat card
function filterByStatus(status) {
    selectStatus(status);
}

// Handle search
function handleSearch(event) {
    if (event.key === 'Enter') {
        const searchTerm = event.target.value;
        const url = new URL(window.location);
        if (searchTerm) {
            url.searchParams.set('search', searchTerm);
        } else {
            url.searchParams.delete('search');
        }
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }
}

// Close selects when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.custom-select-wrapper')) {
        document.querySelectorAll('.custom-select').forEach(select => {
            select.classList.remove('active');
        });
        document.querySelectorAll('.custom-select-wrapper').forEach(wrapper => {
            wrapper.classList.remove('active');
        });
    }
});

// Função para abrir modal de criar conteúdo
function openCreateContentModal() {
    document.getElementById('modalTitle').textContent = 'Criar Conteúdo';
    document.getElementById('contentForm').reset();
    document.getElementById('contentId').value = '';
    document.getElementById('fileUploadGroup').style.display = 'block';
    document.getElementById('contentTextGroup').style.display = 'none';
    document.getElementById('targetIdGroup').style.display = 'none';
    const modal = document.getElementById('contentModal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Função para fechar modal
function closeContentModal() {
    const modal = document.getElementById('contentModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

// Função para editar conteúdo
function editContent(contentId) {
    fetch('ajax_content_management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_content&content_id=${contentId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.content) {
            const content = data.content;
            document.getElementById('modalTitle').textContent = 'Editar Conteúdo';
            document.getElementById('contentId').value = content.id;
            document.getElementById('contentTitle').value = content.title || '';
            document.getElementById('contentDescription').value = content.description || '';
            document.getElementById('contentType').value = content.content_type || '';
            document.getElementById('contentStatus').value = content.status || 'draft';
            document.getElementById('targetType').value = content.target_type || 'all';
            document.getElementById('contentText').value = content.content_text || '';
            
            // Toggle campos baseado no tipo
            toggleContentFields();
            
            // Toggle campos baseado no público-alvo
            toggleTargetFields();
            
            // Selecionar target_id se houver
            if (content.target_id && content.target_type !== 'all') {
                setTimeout(() => {
                    document.getElementById('targetId').value = content.target_id;
                }, 100);
            }
            
            // Selecionar categorias
            if (content.categories && Array.isArray(content.categories)) {
                content.categories.forEach(catId => {
                    const checkbox = document.querySelector(`input[name="categories[]"][value="${catId}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
            }
            
            // Abrir modal
            const modal = document.getElementById('contentModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        } else {
            alert('Erro ao carregar conteúdo: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao carregar conteúdo. Tente novamente.');
    });
}

// Função para visualizar conteúdo
function viewContent(contentId) {
    // Redirecionar para página de visualização
    window.open(`view_content.php?id=${contentId}`, '_blank');
}

// Função para excluir conteúdo
function deleteContent(contentId) {
    if (!confirm('Tem certeza que deseja excluir este conteúdo?')) {
        return;
    }
    
    fetch('ajax_content_management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete_content&content_id=${contentId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Conteúdo deletado com sucesso!');
            location.reload();
        } else {
            alert('Erro ao deletar conteúdo: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao deletar conteúdo. Tente novamente.');
    });
}

// Função para salvar conteúdo
function saveContent() {
    const form = document.getElementById('contentForm');
    const formData = new FormData(form);
    formData.append('action', 'save_content');
    
    // Validação básica
    const title = document.getElementById('contentTitle').value.trim();
    const contentType = document.getElementById('contentType').value;
    
    if (!title) {
        alert('Título é obrigatório');
        return;
    }
    
    if (!contentType) {
        alert('Tipo de conteúdo é obrigatório');
        return;
    }
    
    // Validar se há arquivo ou texto para artigos
    if (contentType === 'articles') {
        const contentText = document.getElementById('contentText').value.trim();
        if (!contentText) {
            alert('Conteúdo do artigo é obrigatório');
            return;
        }
    } else {
        const fileInput = document.getElementById('contentFile');
        const contentId = document.getElementById('contentId').value;
        if (!fileInput.files[0] && !contentId) {
            alert('Arquivo é obrigatório para este tipo de conteúdo');
            return;
        }
    }
    
    // Mostrar loading
    const saveButton = document.querySelector('.btn-save');
    const originalText = saveButton.innerHTML;
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
    saveButton.disabled = true;
    
    fetch('ajax_content_management.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Conteúdo salvo com sucesso!');
            closeContentModal();
            location.reload();
        } else {
            alert('Erro ao salvar conteúdo: ' + (data.error || 'Erro desconhecido'));
            saveButton.innerHTML = originalText;
            saveButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar conteúdo. Tente novamente.');
        saveButton.innerHTML = originalText;
        saveButton.disabled = false;
    });
}

// Função para alternar campos baseado no tipo de conteúdo
function toggleContentFields() {
    const contentType = document.getElementById('contentType').value;
    const fileUploadGroup = document.getElementById('fileUploadGroup');
    const contentTextGroup = document.getElementById('contentTextGroup');
    const fileInput = document.getElementById('contentFile');
    
    if (contentType === 'articles') {
        fileUploadGroup.style.display = 'none';
        contentTextGroup.style.display = 'block';
        fileInput.removeAttribute('required');
    } else {
        fileUploadGroup.style.display = 'block';
        contentTextGroup.style.display = 'none';
        const contentId = document.getElementById('contentId').value;
        if (!contentId) {
            fileInput.setAttribute('required', 'required');
        }
    }
    
    // Atualizar accept do input baseado no tipo
    if (contentType === 'videos') {
        fileInput.setAttribute('accept', 'video/mp4,video/quicktime,video/x-msvideo,video/webm');
    } else if (contentType === 'pdf') {
        fileInput.setAttribute('accept', '.pdf');
    } else if (contentType === 'chef' || contentType === 'supplements') {
        fileInput.setAttribute('accept', 'image/*,video/*,.pdf');
    }
}

// Função para alternar campos baseado no público-alvo
function toggleTargetFields() {
    const targetType = document.getElementById('targetType').value;
    const targetIdGroup = document.getElementById('targetIdGroup');
    const targetId = document.getElementById('targetId');
    
    if (targetType === 'all') {
        targetIdGroup.style.display = 'none';
        targetId.removeAttribute('required');
        targetId.value = '';
    } else {
        targetIdGroup.style.display = 'block';
        targetId.setAttribute('required', 'required');
        
        // Limpar opções
        targetId.innerHTML = '<option value="">Selecione...</option>';
        
        // Adicionar opções baseadas no tipo
        if (targetType === 'user') {
            users.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = user.name;
                targetId.appendChild(option);
            });
        } else if (targetType === 'group') {
            groups.forEach(group => {
                const option = document.createElement('option');
                option.value = group.id;
                option.textContent = group.name;
                targetId.appendChild(option);
            });
        }
    }
}

// Fechar modal ao clicar no overlay
document.addEventListener('click', function(event) {
    const modal = document.getElementById('contentModal');
    if (event.target === modal.querySelector('.challenge-edit-overlay')) {
        closeContentModal();
    }
});

// Fechar modal ao pressionar ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeContentModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
