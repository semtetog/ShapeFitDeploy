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
        a.full_name as author_name
                  FROM sf_member_content mc
                  LEFT JOIN sf_admins a ON mc.admin_id = a.id
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

    $sql .= " ORDER BY mc.created_at DESC LIMIT ? OFFSET ?";
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
    min-height: 320px;
}

.content-card:hover {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: var(--accent-orange) !important;
}

/* Header do card - igual group-card-header */
.content-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
    flex-wrap: wrap;
    width: 100%;
    box-sizing: border-box;
    min-width: 0;
}

.content-header-with-icon {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    flex: 1;
    min-width: 0;
}

.content-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    flex: 1;
    min-width: 0;
    word-wrap: break-word;
    overflow-wrap: break-word;
    hyphens: auto;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.content-type-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: rgba(255, 107, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-orange);
    font-size: 1.25rem;
    flex-shrink: 0;
}

/* Body do card */
.content-card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0;
    min-width: 0;
    min-height: 0;
}

/* Descrição com altura fixa para manter alinhamento */
.content-description {
    font-size: 0.875rem;
    color: var(--text-secondary);
    line-height: 1.5;
    margin: 0 0 0.75rem 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    height: 2.625rem; /* Altura fixa: 2 linhas * 1.5 line-height * 0.875rem */
    word-wrap: break-word;
    overflow-wrap: break-word;
    flex-shrink: 0;
}

/* Spacer para empurrar autor/data para baixo */
.content-body-spacer {
    flex: 1;
    min-height: 0;
}

/* Container para informações do autor e data - sempre na mesma posição */
.content-meta-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    flex-shrink: 0;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    margin-bottom: 0.75rem;
}

.content-info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8125rem;
    color: var(--text-secondary);
    flex-wrap: nowrap;
    height: 1.25rem;
    flex-shrink: 0;
}

.content-info-item i {
    font-size: 0.75rem;
    color: var(--accent-orange);
    flex-shrink: 0;
    width: 14px;
    text-align: center;
}

.content-info-item span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
    flex: 1;
}

/* Outras informações (categorias, target, status) - opcionais, acima do spacer */
.content-extra-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    flex-shrink: 0;
    margin-bottom: 0.75rem;
}

/* Actions - igual group-card-actions */
.content-card-actions {
    display: flex;
    gap: 0.5rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    align-items: center;
    flex-wrap: wrap;
    width: 100%;
    box-sizing: border-box;
    min-width: 0;
    flex-shrink: 0;
    margin-top: auto;
}

.content-card-actions .btn-action {
    padding: 0.625rem 0.75rem;
    border-radius: 10px;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    white-space: nowrap;
    flex: 1 1 auto;
    min-width: 0;
    box-sizing: border-box;
    border: 1px solid var(--glass-border);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
}

.content-card-actions .btn-action:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--accent-orange);
    transform: translateY(-2px);
}

.content-card-actions .btn-action i {
    font-size: 0.875rem;
    flex-shrink: 0;
}

.content-card-actions .btn-action span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
}

.content-card-actions .btn-action.btn-view {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.3);
    color: #3B82F6;
}

.content-card-actions .btn-action.btn-view:hover {
    background: rgba(59, 130, 246, 0.2);
    border-color: #3B82F6;
    color: #3B82F6;
}

.content-card-actions .btn-action.btn-edit {
    background: rgba(255, 107, 0, 0.1);
    color: var(--accent-orange);
    border: 1px solid rgba(255, 107, 0, 0.2);
}

.content-card-actions .btn-action.btn-edit:hover {
    background: rgba(255, 107, 0, 0.2);
    border-color: var(--accent-orange);
}

.content-card-actions .btn-action.btn-delete {
    background: rgba(239, 68, 68, 0.1);
    color: #EF4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.content-card-actions .btn-action.btn-delete:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: #EF4444;
}

/* Status badge */
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    display: inline-block;
}

.status-badge.active {
    background: rgba(16, 185, 129, 0.2);
    color: #10B981;
}

.status-badge.inactive {
    background: rgba(107, 114, 128, 0.2);
    color: #6B7280;
}

.status-badge.draft {
    background: rgba(251, 191, 36, 0.2);
    color: #FBBF24;
}

/* Responsive */
@media (max-width: 1024px) {
    .content-card-actions {
        gap: 0.75rem;
    }
    
    .content-card-actions .btn-action {
        font-size: 0.75rem;
        padding: 0.5rem 0.5rem;
    }
}

@media (max-width: 768px) {
    .content-card {
        padding: 0.875rem !important;
        gap: 0.625rem !important;
    }
    
    .content-name {
        font-size: 1rem;
    }
    
    .content-card-actions {
        flex-direction: row;
        flex-wrap: wrap;
    }
    
    .content-card-actions .btn-action {
        flex: 1 1 calc(50% - 0.25rem);
        min-width: 0;
        max-width: calc(50% - 0.25rem);
        font-size: 0.75rem;
        padding: 0.5rem 0.5rem;
    }
}

@media (max-width: 480px) {
    .content-card {
        padding: 0.875rem !important;
        gap: 0.625rem !important;
    }
    
    .content-name {
        font-size: 1rem;
    }
    
    .content-card-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .content-card-actions .btn-action {
        flex: 1;
        width: 100%;
        min-width: 0;
        max-width: 100%;
        padding: 0.5rem 0.75rem;
    }
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
    min-height: 100px;
}

/* Custom Select - Estilo igual recipes */
.custom-select-wrapper {
    position: relative;
    width: 100%;
}

.custom-select {
    position: relative;
}

.custom-select-trigger {
    width: 100%;
    padding: 0.625rem 0.875rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
    box-sizing: border-box;
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
    position: absolute;
    top: calc(100% + 0.5rem);
    left: 0;
    right: 0;
    background: rgba(26, 26, 26, 0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
    z-index: 10001;
    max-height: 300px;
    overflow-y: auto;
    overflow-x: hidden;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    pointer-events: none;
    -webkit-overflow-scrolling: touch;
}

.challenge-edit-modal .custom-select-options {
    z-index: 10001;
}

.custom-select.active .custom-select-options {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
    pointer-events: auto;
}

.custom-select-option {
    padding: 0.875rem 1.25rem;
    font-size: 0.875rem;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: 'Montserrat', sans-serif;
    font-weight: 600;
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

.challenge-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

/* Toggle Switch - Igual user_groups.php */
.toggle-switch-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.toggle-switch-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-secondary);
    min-width: 50px;
    text-align: left;
    transition: color 0.3s ease;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
    cursor: pointer;
    flex-shrink: 0;
}

.toggle-switch-input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-switch-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #EF4444; /* Vermelho quando desativado */
    transition: all 0.3s ease;
    border-radius: 26px;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle-switch-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: all 0.3s ease;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* Quando está ativo (checked) - Verde */
.toggle-switch-input:checked + .toggle-switch-slider {
    background-color: #22C55E; /* Verde quando ativado */
    box-shadow: 0 0 8px rgba(34, 197, 94, 0.4);
}

.toggle-switch-input:checked + .toggle-switch-slider:before {
    transform: translateX(24px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
}

/* Hover effect */
.toggle-switch:hover .toggle-switch-slider {
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2), 0 0 12px rgba(255, 255, 255, 0.1);
}

.toggle-switch-input:checked:hover + .toggle-switch-slider {
    box-shadow: 0 0 12px rgba(34, 197, 94, 0.6);
}

.toggle-switch-input:not(:checked):hover + .toggle-switch-slider {
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2), 0 0 12px rgba(239, 68, 68, 0.3);
}

/* Atualizar label quando está ativo */
.toggle-switch-input:checked ~ .toggle-switch-label,
.toggle-switch-wrapper:has(.toggle-switch-input:checked) .toggle-switch-label {
    color: #22C55E;
    font-weight: 700;
}

.toggle-switch-wrapper:has(.toggle-switch-input:not(:checked)) .toggle-switch-label {
    color: #EF4444;
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
                <div class="content-card" data-type="<?php echo $content['content_type']; ?>" data-status="<?php echo $content['status'] ?? 'active'; ?>">
                    <div class="content-card-header">
                        <div class="content-header-with-icon">
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
                            <h3 class="content-name"><?php echo htmlspecialchars($content['title']); ?></h3>
                        </div>
                        <div class="toggle-switch-wrapper" onclick="event.stopPropagation()">
                            <?php
                            $is_active = ($content['status'] ?? 'active') === 'active';
                            ?>
                            <label class="toggle-switch">
                                <input type="checkbox" 
                                       class="toggle-switch-input" 
                                       <?php echo $is_active ? 'checked' : ''; ?>
                                       onchange="toggleContentStatus(<?php echo $content['id']; ?>, '<?php echo $content['status'] ?? 'active'; ?>', this)"
                                       data-content-id="<?php echo $content['id']; ?>"
                                       data-current-status="<?php echo $content['status'] ?? 'active'; ?>">
                                <span class="toggle-switch-slider"></span>
                            </label>
                            <span class="toggle-switch-label" id="toggle-label-<?php echo $content['id']; ?>" style="color: <?php echo $is_active ? '#22C55E' : '#EF4444'; ?>; font-weight: <?php echo $is_active ? '700' : '600'; ?>;"><?php echo $is_active ? 'Ativo' : 'Inativo'; ?></span>
                        </div>
                    </div>
                    
                    <div class="content-card-body">
                        <!-- Descrição - altura fixa sempre -->
                        <p class="content-description">
                            <?php echo !empty($content['description']) ? htmlspecialchars($content['description']) : 'Sem descrição'; ?>
                        </p>
                        
                        <!-- Informações extras (opcionais) - aparecem antes do spacer -->
                        <?php if (isset($content['target_type']) && $content['target_type'] !== 'all'): ?>
                            <div class="content-extra-info">
                                <div class="content-info-item">
                                    <i class="fas fa-users"></i>
                                    <span>
                                        <?php
                                        switch($content['target_type']) {
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
                        <?php endif; ?>
                        
                        <!-- Spacer para empurrar autor/data para baixo -->
                        <div class="content-body-spacer"></div>
                        
                        <!-- Informações principais (autor e data) - sempre na mesma posição -->
                        <div class="content-meta-info">
                            <div class="content-info-item">
                                <i class="fas fa-user"></i>
                                <span><?php echo htmlspecialchars($content['author_name'] ?? 'Admin'); ?></span>
                            </div>
                            
                            <div class="content-info-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo date('d/m/Y', strtotime($content['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="content-card-actions" onclick="event.stopPropagation()">
                        <button class="btn-action btn-view" onclick="viewContent(<?php echo $content['id']; ?>)" title="Visualizar">
                            <i class="fas fa-eye"></i>
                            <span>Visualizar</span>
                        </button>
                        <button class="btn-action btn-edit" onclick="editContent(<?php echo $content['id']; ?>)" title="Editar">
                            <i class="fas fa-edit"></i>
                            <span>Editar</span>
                        </button>
                        <button class="btn-action btn-delete" onclick="deleteContent(<?php echo $content['id']; ?>)" title="Excluir">
                            <i class="fas fa-trash"></i>
                            <span>Excluir</span>
                        </button>
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
                        <input type="hidden" id="contentType" name="content_type" value="">
                        <div class="custom-select-wrapper">
                            <div class="custom-select" id="contentTypeSelect">
                                <div class="custom-select-trigger">
                                    <span class="custom-select-value">Selecione...</span>
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                                <div class="custom-select-options">
                                    <div class="custom-select-option" data-value="">Selecione...</div>
                                    <div class="custom-select-option" data-value="chef">Chef</div>
                                    <div class="custom-select-option" data-value="supplements">Suplementos</div>
                                    <div class="custom-select-option" data-value="videos">Vídeos</div>
                                    <div class="custom-select-option" data-value="articles">Artigos</div>
                                    <div class="custom-select-option" data-value="pdf">PDF</div>
                                </div>
                            </div>
                        </div>
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
                        <input type="hidden" id="targetType" name="target_type" value="">
                        <div class="custom-select-wrapper">
                            <div class="custom-select" id="targetTypeSelect">
                                <div class="custom-select-trigger">
                                    <span class="custom-select-value">Selecione...</span>
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                                <div class="custom-select-options">
                                    <div class="custom-select-option" data-value="">Selecione...</div>
                                    <div class="custom-select-option" data-value="all">Todos os usuários</div>
                                    <div class="custom-select-option" data-value="user">Usuário específico</div>
                                    <div class="custom-select-option" data-value="group">Grupo específico</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="challenge-form-group" id="targetIdGroup" style="display: none;">
                        <label for="targetId">Selecionar</label>
                        <input type="hidden" id="targetId" name="target_id" value="">
                        <div class="custom-select-wrapper">
                            <div class="custom-select" id="targetIdSelect">
                                <div class="custom-select-trigger">
                                    <span class="custom-select-value">Selecione...</span>
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                                <div class="custom-select-options" id="targetIdOptions">
                                    <div class="custom-select-option" data-value="">Selecione...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Status será sempre 'active' por padrão, mas pode ser alterado via toggle no card -->
                <input type="hidden" id="contentStatus" name="status" value="active">
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

<!-- Modal de Alerta -->
<div id="alertModal" class="challenge-edit-modal">
    <div class="challenge-edit-overlay" onclick="closeAlertModal()"></div>
    <div class="challenge-edit-content" style="max-width: 450px;">
        <button class="sleep-modal-close" onclick="closeAlertModal()" type="button">
            <i class="fas fa-times"></i>
        </button>
        <div class="challenge-edit-header">
            <h3 id="alertTitle">Alerta</h3>
        </div>
        <div class="challenge-edit-body">
            <p id="alertMessage" style="color: var(--text-primary); margin: 0; line-height: 1.6;"></p>
        </div>
        <div class="challenge-edit-footer">
            <button type="button" class="btn-save" onclick="closeAlertModal()">OK</button>
        </div>
    </div>
</div>

<!-- Modal de Confirmação -->
<div id="confirmModal" class="challenge-edit-modal">
    <div class="challenge-edit-overlay" onclick="closeConfirmModal()"></div>
    <div class="challenge-edit-content" style="max-width: 450px;">
        <button class="sleep-modal-close" onclick="closeConfirmModal()" type="button">
            <i class="fas fa-times"></i>
        </button>
        <div class="challenge-edit-header">
            <h3 id="confirmTitle">Confirmar</h3>
        </div>
        <div class="challenge-edit-body">
            <p id="confirmMessage" style="color: var(--text-primary); margin: 0; line-height: 1.6;"></p>
        </div>
        <div class="challenge-edit-footer">
            <button type="button" class="btn-cancel" onclick="closeConfirmModal()">Cancelar</button>
            <button type="button" class="btn-save" id="confirmButton">Confirmar</button>
        </div>
    </div>
</div>

<script>
// Dados para JavaScript
const users = <?php echo json_encode($users); ?>;
const groups = <?php echo json_encode($groups); ?>;

// Armazenar handlers para poder removê-los depois
const customSelectHandlers = new Map();

// Inicializar custom selects
function initCustomSelect(selectId, hiddenInputId, onChangeCallback) {
    const customSelect = document.getElementById(selectId);
    if (!customSelect) return;
    
    // Remover handlers anteriores se existirem
    if (customSelectHandlers.has(selectId)) {
        const handlers = customSelectHandlers.get(selectId);
        if (handlers.triggerHandler) {
            handlers.trigger.removeEventListener('click', handlers.triggerHandler);
        }
        handlers.optionHandlers.forEach(({ option, handler }) => {
            option.removeEventListener('click', handler);
        });
    }
    
    const hiddenInput = document.getElementById(hiddenInputId);
    const trigger = customSelect.querySelector('.custom-select-trigger');
    const options = customSelect.querySelectorAll('.custom-select-option');
    const valueDisplay = customSelect.querySelector('.custom-select-value');
    
    // Handler para o trigger
    const triggerHandler = function(e) {
        e.stopPropagation();
        
        // Fechar outros selects
        document.querySelectorAll('.custom-select').forEach(s => {
            if (s.id !== selectId) {
                s.classList.remove('active');
            }
        });
        
        customSelect.classList.toggle('active');
    };
    
    trigger.addEventListener('click', triggerHandler);
    
    // Handlers para as opções
    const optionHandlers = [];
    options.forEach(option => {
        const optionHandler = function(e) {
            e.stopPropagation();
            
            const value = this.getAttribute('data-value');
            const text = this.textContent;
            
            // Atualiza o valor do input escondido
            hiddenInput.value = value;
            // Atualiza o texto visível
            valueDisplay.textContent = text;
            
            // Remove a classe 'selected' de todos e adiciona na clicada
            options.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            
            // Fecha o dropdown
            customSelect.classList.remove('active');
            
            // Chama callback se fornecido
            if (onChangeCallback) {
                onChangeCallback(value);
            }
        };
        
        option.addEventListener('click', optionHandler);
        optionHandlers.push({ option, handler: optionHandler });
    });
    
    // Armazenar handlers para possível remoção
    customSelectHandlers.set(selectId, {
        trigger,
        triggerHandler,
        optionHandlers
    });
}

// Inicializar todos os custom selects quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    // Tipo de conteúdo
    initCustomSelect('contentTypeSelect', 'contentType', function(value) {
        toggleContentFields();
    });
    
    // Público-alvo
    initCustomSelect('targetTypeSelect', 'targetType', function(value) {
        toggleTargetFields();
    });
    
    // Target ID (será populado dinamicamente)
    initCustomSelect('targetIdSelect', 'targetId', null);
    
    // Status
    initCustomSelect('contentStatusSelect', 'contentStatus', null);
});

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

// Função para resetar custom select
function resetCustomSelect(selectId, hiddenInputId, defaultValue = '', defaultText = 'Selecione...') {
    const customSelect = document.getElementById(selectId);
    const hiddenInput = document.getElementById(hiddenInputId);
    const valueDisplay = customSelect.querySelector('.custom-select-value');
    const options = customSelect.querySelectorAll('.custom-select-option');
    
    hiddenInput.value = defaultValue;
    valueDisplay.textContent = defaultText;
    options.forEach(opt => {
        opt.classList.remove('selected');
        if (opt.getAttribute('data-value') === defaultValue) {
            opt.classList.add('selected');
        }
    });
}

// Função para definir valor do custom select
function setCustomSelectValue(selectId, hiddenInputId, value) {
    const customSelect = document.getElementById(selectId);
    const hiddenInput = document.getElementById(hiddenInputId);
    const valueDisplay = customSelect.querySelector('.custom-select-value');
    const options = customSelect.querySelectorAll('.custom-select-option');
    
    hiddenInput.value = value;
    
    options.forEach(opt => {
        opt.classList.remove('selected');
        if (opt.getAttribute('data-value') === value) {
            opt.classList.add('selected');
            valueDisplay.textContent = opt.textContent;
        }
    });
}

// Função para abrir modal de criar conteúdo
function openCreateContentModal() {
    document.getElementById('modalTitle').textContent = 'Criar Conteúdo';
    document.getElementById('contentForm').reset();
    document.getElementById('contentId').value = '';
    document.getElementById('fileUploadGroup').style.display = 'block';
    document.getElementById('contentTextGroup').style.display = 'none';
    document.getElementById('targetIdGroup').style.display = 'none';
    
    // Resetar custom selects
    resetCustomSelect('contentTypeSelect', 'contentType', '', 'Selecione...');
    resetCustomSelect('targetTypeSelect', 'targetType', '', 'Selecione...');
    // Status sempre será 'active' por padrão (definido no hidden input)
    
    const modal = document.getElementById('contentModal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Toggle status do conteúdo - Igual user_groups.php
function toggleContentStatus(contentId, currentStatus, toggleElement) {
    const toggle = toggleElement || document.querySelector(`.toggle-switch-input[data-content-id="${contentId}"]`);
    if (!toggle) return;
    
    // IMPORTANTE: O checkbox já foi alterado pelo evento onchange
    // Então toggle.checked já reflete o NOVO estado (não o antigo)
    const isChecked = toggle.checked;
    const newStatus = isChecked ? 'active' : 'inactive';
    const wrapper = toggle.closest('.toggle-switch-wrapper');
    const label = wrapper ? wrapper.querySelector('.toggle-switch-label') : null;
    
    // Atualizar label IMEDIATAMENTE baseado no estado atual do checkbox
    if (label) {
        const newText = isChecked ? 'Ativo' : 'Inativo';
        const newColor = isChecked ? '#22C55E' : '#EF4444';
        const newWeight = isChecked ? '700' : '600';
        
        // Atualizar diretamente
        label.textContent = newText;
        label.style.color = newColor;
        label.style.fontWeight = newWeight;
        
        // Forçar reflow para garantir que a atualização seja visível
        label.offsetHeight;
    }
    
    fetch('ajax_content_management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'toggle_status',
            content_id: contentId,
            status: newStatus
        })
    })
    .then(async response => {
        const text = await response.text();
        if (!response.ok) {
            throw new Error(text || `Erro HTTP ${response.status}`);
        }
        if (!text || text.trim() === '') {
            throw new Error('Resposta vazia do servidor');
        }
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error('Resposta inválida do servidor');
        }
    })
    .then(data => {
        if (data.success) {
            // Atualizar estatísticas
            updateContentStats();
            // Atualizar data-status do card
            const card = toggle.closest('.content-card');
            if (card) {
                card.setAttribute('data-status', newStatus);
            }
        } else {
            // Reverter toggle
            toggle.checked = !isChecked;
            if (label) {
                label.textContent = isChecked ? 'Inativo' : 'Ativo';
                label.style.color = isChecked ? '#EF4444' : '#22C55E';
                label.style.fontWeight = isChecked ? '600' : '700';
            }
            alert('Erro ao atualizar status: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        // Reverter toggle
        toggle.checked = !isChecked;
        if (label) {
            label.textContent = isChecked ? 'Inativo' : 'Ativo';
            label.style.color = isChecked ? '#EF4444' : '#22C55E';
            label.style.fontWeight = isChecked ? '600' : '700';
        }
        alert('Erro ao atualizar status. Tente novamente.');
    });
}

// Atualizar estatísticas de conteúdo
function updateContentStats() {
    fetch('ajax_content_management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_stats'
        })
    })
    .then(async response => {
        const text = await response.text();
        if (!response.ok || !text) return null;
        try {
            return JSON.parse(text);
        } catch (e) {
            return null;
        }
    })
    .then(data => {
        if (data && data.success && data.stats) {
            const statTotal = document.getElementById('stat-total');
            const statActive = document.getElementById('stat-active');
            const statInactive = document.getElementById('stat-inactive');
            if (statTotal) statTotal.textContent = data.stats.total || 0;
            if (statActive) statActive.textContent = data.stats.active || 0;
            if (statInactive) statInactive.textContent = data.stats.inactive || 0;
        }
    })
    .catch(error => {
        console.error('Erro ao atualizar estatísticas:', error);
    });
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
            document.getElementById('contentText').value = content.content_text || '';
            
            // Definir valores dos custom selects
            setCustomSelectValue('contentTypeSelect', 'contentType', content.content_type || '');
            setCustomSelectValue('targetTypeSelect', 'targetType', content.target_type || 'all');
            // Status não é editado no modal, apenas via toggle no card
            
            // Toggle campos baseado no tipo
            toggleContentFields();
            
            // Toggle campos baseado no público-alvo
            toggleTargetFields();
            
            // Selecionar target_id se houver (após popular as opções)
            if (content.target_id && content.target_type !== 'all') {
                setTimeout(() => {
                    setCustomSelectValue('targetIdSelect', 'targetId', content.target_id);
                }, 200);
            }
            
            
            // Abrir modal
            const modal = document.getElementById('contentModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        } else {
            showAlert('Erro', 'Erro ao carregar conteúdo: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showAlert('Erro', 'Erro ao carregar conteúdo. Tente novamente.');
    });
}

// Função para visualizar conteúdo
function viewContent(contentId) {
    // Redirecionar para página de visualização
    window.open(`view_content.php?id=${contentId}`, '_blank');
}

// Função para excluir conteúdo
function deleteContent(contentId) {
    showConfirm('Confirmar Exclusão', 'Tem certeza que deseja excluir este conteúdo? Esta ação não pode ser desfeita.', function() {
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
                showAlert('Sucesso', 'Conteúdo deletado com sucesso!');
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showAlert('Erro', 'Erro ao deletar conteúdo: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showAlert('Erro', 'Erro ao deletar conteúdo. Tente novamente.');
        });
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
        showAlert('Validação', 'Título é obrigatório');
        return;
    }
    
    if (!contentType) {
        showAlert('Validação', 'Tipo de conteúdo é obrigatório');
        return;
    }
    
    // Validar se há arquivo ou texto para artigos
    if (contentType === 'articles') {
        const contentText = document.getElementById('contentText').value.trim();
        if (!contentText) {
            showAlert('Validação', 'Conteúdo do artigo é obrigatório');
            return;
        }
    } else {
        const fileInput = document.getElementById('contentFile');
        const contentId = document.getElementById('contentId').value;
        if (!fileInput.files[0] && !contentId) {
            showAlert('Validação', 'Arquivo é obrigatório para este tipo de conteúdo');
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
    .then(async response => {
        // Ler o texto da resposta uma única vez
        const text = await response.text();
        
        // Verificar se a resposta é válida
        if (!response.ok) {
            let errorMsg = 'Erro ao salvar conteúdo';
            try {
                const json = JSON.parse(text);
                errorMsg = json.error || errorMsg;
            } catch (e) {
                errorMsg = text || `Erro HTTP ${response.status}`;
            }
            throw new Error(errorMsg);
        }
        
        // Verificar se a resposta está vazia
        if (!text || text.trim() === '') {
            throw new Error('Resposta vazia do servidor');
        }
        
        // Verificar Content-Type
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Resposta inválida do servidor: ' + (text.substring(0, 100) || 'Resposta não é JSON'));
        }
        
        // Tentar parsear JSON
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Erro ao parsear JSON:', text);
            throw new Error('Resposta inválida do servidor. Verifique o console para mais detalhes.');
        }
    })
    .then(data => {
        if (data.success) {
            showAlert('Sucesso', data.message || 'Conteúdo salvo com sucesso!');
            closeContentModal();
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert('Erro', 'Erro ao salvar conteúdo: ' + (data.error || 'Erro desconhecido'));
            saveButton.innerHTML = originalText;
            saveButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showAlert('Erro', 'Erro ao salvar conteúdo: ' + error.message);
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
    const targetIdSelect = document.getElementById('targetIdSelect');
    const targetIdOptions = document.getElementById('targetIdOptions');
    const targetIdHidden = document.getElementById('targetId');
    
    if (targetType === 'all') {
        targetIdGroup.style.display = 'none';
        targetIdHidden.removeAttribute('required');
        targetIdHidden.value = '';
    } else {
        targetIdGroup.style.display = 'block';
        targetIdHidden.setAttribute('required', 'required');
        
        // Limpar opções existentes (exceto a primeira)
        const firstOption = targetIdOptions.querySelector('.custom-select-option[data-value=""]');
        targetIdOptions.innerHTML = '';
        if (firstOption) {
            targetIdOptions.appendChild(firstOption);
        } else {
            const defaultOption = document.createElement('div');
            defaultOption.className = 'custom-select-option';
            defaultOption.setAttribute('data-value', '');
            defaultOption.textContent = 'Selecione...';
            targetIdOptions.appendChild(defaultOption);
        }
        
        // Adicionar opções baseadas no tipo usando custom select
        if (targetType === 'user') {
            users.forEach(user => {
                const option = document.createElement('div');
                option.className = 'custom-select-option';
                option.setAttribute('data-value', user.id);
                option.textContent = user.name;
                targetIdOptions.appendChild(option);
            });
        } else if (targetType === 'group') {
            groups.forEach(group => {
                const option = document.createElement('div');
                option.className = 'custom-select-option';
                option.setAttribute('data-value', group.id);
                option.textContent = group.name;
                targetIdOptions.appendChild(option);
            });
        }
        
        // Re-inicializar o custom select com as novas opções
        initCustomSelect('targetIdSelect', 'targetId', null);
        
        // Resetar o valor
        resetCustomSelect('targetIdSelect', 'targetId', '', 'Selecione...');
    }
}

// Funções para modais de alerta
function showAlert(title, message) {
    document.getElementById('alertTitle').textContent = title;
    document.getElementById('alertMessage').textContent = message;
    document.getElementById('alertModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeAlertModal() {
    document.getElementById('alertModal').classList.remove('active');
    document.body.style.overflow = '';
}

function showConfirm(title, message, onConfirm) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    const confirmButton = document.getElementById('confirmButton');
    confirmButton.onclick = function() {
        closeConfirmModal();
        if (onConfirm) onConfirm();
    };
    document.getElementById('confirmModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Fechar modal ao clicar no overlay
document.addEventListener('click', function(event) {
    const contentModal = document.getElementById('contentModal');
    if (contentModal && event.target === contentModal.querySelector('.challenge-edit-overlay')) {
        closeContentModal();
    }
    
    const alertModal = document.getElementById('alertModal');
    if (alertModal && event.target === alertModal.querySelector('.challenge-edit-overlay')) {
        closeAlertModal();
    }
    
    const confirmModal = document.getElementById('confirmModal');
    if (confirmModal && event.target === confirmModal.querySelector('.challenge-edit-overlay')) {
        closeConfirmModal();
    }
});

// Fechar modal ao pressionar ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeContentModal();
        closeAlertModal();
        closeConfirmModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
