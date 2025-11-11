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

/* Stats Grid - Estilo igual user_groups.php */
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
    box-shadow: none !important;
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
    transform: scale(1.1);
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

/* Remover asterisco automático se já tiver um span com asterisco */
.challenge-form-group label:has(span[style*="accent-orange"])::after {
    content: none;
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

/* Estilos para preview do arquivo atual no modal */
#currentFilePreview {
    transition: all 0.3s ease;
}

#currentFilePreview:hover {
    transform: scale(1.02);
    border-color: var(--accent-orange);
}

#currentFilePreview:hover #currentFilePlayOverlay {
    background: rgba(0, 0, 0, 0.8);
    transform: translate(-50%, -50%) scale(1.1);
}

#currentFilePreview button[onclick*="removeCurrentFile"]:hover {
    background: rgba(239, 68, 68, 0.2) !important;
    border-color: #EF4444 !important;
    transform: scale(1.15);
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
            </div>
        </div>

    <!-- Search Bar -->
    <div class="filter-card">
        <div class="filter-row">
            <input type="text" class="search-input" placeholder="Buscar por título ou descrição..." 
                   value="<?php echo htmlspecialchars($search_term); ?>" 
                   onkeyup="handleSearch(event)" id="searchInput">
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
                                    case 'videos':
                                        $icon = 'fas fa-play';
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
    
    <?php 
    // Se for requisição AJAX, parar aqui (retornar apenas o grid)
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        exit;
    }
    ?>
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
                
                    <div class="challenge-form-group">
                    <label for="contentTitle" style="display: flex; align-items: center; gap: 0.25rem;">Título <span style="color: var(--accent-orange);">*</span></label>
                        <input type="text" id="contentTitle" name="title" class="challenge-form-input" required placeholder="Ex: Receita de Salada Fit">
                    </div>
                
                <!-- Tipo de conteúdo será detectado automaticamente -->
                        <input type="hidden" id="contentType" name="content_type" value="">
                
                <div class="challenge-form-group">
                    <label for="contentDescription">Descrição</label>
                    <textarea id="contentDescription" name="description" class="challenge-form-textarea" rows="3" placeholder="Descreva o conteúdo"></textarea>
                                </div>
                
                <div class="challenge-form-group" id="fileUploadGroup">
                    <label for="contentFile">Arquivo <span style="color: var(--accent-orange);">*</span></label>
                    <input type="file" id="contentFile" name="file" class="challenge-form-input" accept="video/mp4,video/quicktime,video/x-msvideo,video/webm,.pdf" onchange="handleFileSelect(event)">
                    <small style="color: var(--text-secondary); font-size: 0.75rem; margin-top: 0.5rem; display: block;">Formatos aceitos: Vídeos (MP4, MOV, AVI, WebM) ou PDF. Máximo: 100MB para vídeos, 10MB para PDF.</small>
                    
                    <!-- Preview do arquivo selecionado -->
                    <div id="filePreview" style="margin-top: 1rem; display: none;">
                        <div style="position: relative; border-radius: 12px; overflow: hidden; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--glass-border); max-width: 400px;">
                            <div id="videoPreview" style="display: none;">
                                <video id="previewVideo" style="width: 100%; max-height: 200px; display: block;" controls></video>
                                </div>
                            <div id="pdfPreview" style="display: none; padding: 1.5rem; text-align: center;">
                                <i class="fas fa-file-pdf" style="font-size: 2.5rem; color: var(--accent-orange); margin-bottom: 0.75rem;"></i>
                                <p style="color: var(--text-primary); font-weight: 600; margin: 0; font-size: 0.875rem;" id="pdfFileName"></p>
                            </div>
                            <button type="button" onclick="clearFilePreview()" style="position: absolute; top: 0.5rem; right: 0.5rem; background: rgba(0, 0, 0, 0.8); border: none; color: white; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; z-index: 10;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <!-- Thumbnail - Extração automática de frames do vídeo (abaixo do vídeo) -->
                        <div class="challenge-form-group" id="thumbnailGroup" style="display: none; margin-top: 1rem;">
                            <label>Thumbnail (Opcional)</label>
                            <small style="color: var(--text-secondary); font-size: 0.75rem; margin-top: 0.5rem; display: block; margin-bottom: 1rem;">Selecione um frame do vídeo como thumbnail.</small>
                            
                            <!-- Galeria de frames do vídeo -->
                            <div id="videoFramesGallery" style="display: none; margin-bottom: 1rem;">
                                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; min-height: 120px;">
                                    <!-- Frames serão inseridos aqui via JavaScript -->
                                </div>
                            </div>
                
                            <!-- Input hidden para armazenar o frame selecionado -->
                            <input type="hidden" id="selectedThumbnailData" name="thumbnail_data" data-file-id="">
                            <button type="button" onclick="regenerateVideoFrames()" style="margin-top: 1rem; padding: 0.5rem 1rem; background: var(--accent-orange); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease;">
                                <i class="fas fa-sync-alt"></i> Gerar novos frames
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Campo de mini título para vídeos (movido para fora do fileUploadGroup) -->
                <div id="videoTitleGroup" class="challenge-form-group" style="display: none;">
                    <label for="videoTitle">Título do Arquivo <span style="color: var(--accent-orange);">*</span></label>
                    <input type="text" id="videoTitle" name="video_title" class="challenge-form-input" placeholder="Ex: Preparo da receita, Dicas finais, etc." required oninput="updateVideoTitleDisplay()">
                    <small style="color: var(--text-secondary); font-size: 0.75rem; margin-top: 0.5rem; display: block;">Obrigatório para vídeos e PDFs.</small>
                </div>
                
                <!-- Arquivos salvos (ao editar) - mais abaixo -->
                <div id="currentFilesInfo" class="challenge-form-group" style="margin-top: 1.5rem; display: none;">
                    <label style="margin-bottom: 0.75rem; display: block; font-size: 0.8125rem; font-weight: 600; color: var(--text-primary);">Arquivos Salvos</label>
                    <div id="currentFilesList" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
                        <!-- Arquivos serão inseridos aqui via JavaScript -->
                    </div>
                </div>
                
                
                <div class="challenge-form-row">
                    <div class="challenge-form-group">
                        <label for="targetType">Público-Alvo <span style="color: var(--accent-orange);">*</span></label>
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
        
        const wrapper = customSelect.closest('.custom-select-wrapper');
        
        // Fechar outros selects
        document.querySelectorAll('.custom-select').forEach(s => {
            if (s.id !== selectId) {
                s.classList.remove('active');
            }
        });
        document.querySelectorAll('.custom-select-wrapper').forEach(w => {
            if (w !== wrapper) {
                w.classList.remove('active');
            }
        });
        
        customSelect.classList.toggle('active');
        if (wrapper) {
            wrapper.classList.toggle('active');
        }
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
            const wrapper = customSelect.closest('.custom-select-wrapper');
            if (wrapper) {
                wrapper.classList.remove('active');
            }
            
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
    // Tipo de conteúdo é detectado automaticamente, não precisa de select
    
    // Público-alvo
    initCustomSelect('targetTypeSelect', 'targetType', function(value) {
        toggleTargetFields();
    });
    
    // Target ID (será populado dinamicamente)
    initCustomSelect('targetIdSelect', 'targetId', null);
    
});

// Close select when clicking outside
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

// Filter by status from stat card
function filterByStatus(status) {
    const url = new URL(window.location);
    if (status) {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    url.searchParams.delete('page');
    window.location.href = url.toString();
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
    document.getElementById('targetIdGroup').style.display = 'none';
    
    // Ocultar e limpar arquivos salvos ao criar novo conteúdo
    const currentFilesInfo = document.getElementById('currentFilesInfo');
    const currentFilesList = document.getElementById('currentFilesList');
    if (currentFilesInfo) {
        currentFilesInfo.style.display = 'none';
    }
    if (currentFilesList) {
        currentFilesList.innerHTML = '';
    }
    
    // Ocultar currentFileInfo apenas ao criar novo (não ao editar)
    const currentFileInfo = document.getElementById('currentFileInfo');
    if (currentFileInfo) {
        currentFileInfo.style.display = 'none';
    }
    
    clearFilePreview();
    clearThumbnailPreview();
    
    // Limpar campo de título do vídeo
    const videoTitleInput = document.getElementById('videoTitle');
    if (videoTitleInput) {
        videoTitleInput.value = '';
    }
    const videoTitleGroup = document.getElementById('videoTitleGroup');
    if (videoTitleGroup) {
        videoTitleGroup.style.display = 'none';
    }
    
    // Resetar tipo de conteúdo
    document.getElementById('contentType').value = '';
    
    // Resetar custom selects
    resetCustomSelect('targetTypeSelect', 'targetType', '', 'Selecione...');
    // Status sempre será 'active' por padrão (definido no hidden input)
    
    const modal = document.getElementById('contentModal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Função para atualizar lista de conteúdos via AJAX
function updateContentList() {
    const searchTerm = document.getElementById('searchInput')?.value || '';
    const urlParams = new URLSearchParams(window.location.search);
    const statusFilter = urlParams.get('status') || '';
    
    // Construir URL
    let url = window.location.pathname + '?ajax=1';
    if (searchTerm) {
        url += '&search=' + encodeURIComponent(searchTerm);
    }
    if (statusFilter) {
        url += '&status=' + statusFilter;
    }
    
    // Buscar conteúdos atualizados
    fetch(url)
        .then(response => response.text())
        .then(html => {
            // Extrair apenas o grid de conteúdos
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newGrid = doc.getElementById('contentGrid');
            if (newGrid) {
                const currentGrid = document.getElementById('contentGrid');
                if (currentGrid) {
                    currentGrid.innerHTML = newGrid.innerHTML;
                }
            }
        })
        .catch(error => {
            console.error('Erro ao atualizar lista:', error);
            // Fallback: recarregar página se AJAX falhar
            setTimeout(() => location.reload(), 2000);
        });
}

// Função para atualizar estatísticas via AJAX
function updateStats() {
    fetch('ajax_content_management.php?action=get_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.stats) {
                const stats = data.stats;
                const statTotal = document.getElementById('statTotal');
                const statActive = document.getElementById('statActive');
                const statInactive = document.getElementById('statInactive');
                
                if (statTotal) statTotal.textContent = stats.total || 0;
                if (statActive) statActive.textContent = stats.active || 0;
                if (statInactive) statInactive.textContent = stats.inactive || 0;
            }
        })
        .catch(error => {
            console.error('Erro ao atualizar estatísticas:', error);
        });
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
function editContent(contentId, preserveNewFilePreview = false) {
    fetch('ajax_content_management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_content&content_id=${contentId}`
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
            throw new Error('Resposta inválida do servidor: ' + text.substring(0, 100));
        }
    })
    .then(data => {
        if (data.success && data.content) {
            const content = data.content;
            document.getElementById('modalTitle').textContent = 'Editar Conteúdo';
            document.getElementById('contentId').value = content.id;
            document.getElementById('contentTitle').value = content.title || '';
            document.getElementById('contentDescription').value = content.description || '';
            
            // Definir tipo de conteúdo (detecção automática, não precisa de select)
            document.getElementById('contentType').value = content.content_type || '';
            
            // Definir valores dos custom selects
            setCustomSelectValue('targetTypeSelect', 'targetType', content.target_type || 'all');
            // Status não é editado no modal, apenas via toggle no card
            
            // Mostrar arquivos salvos se existirem
            const currentFilesInfo = document.getElementById('currentFilesInfo');
            const currentFilesList = document.getElementById('currentFilesList');
            
            // Usar array de arquivos se disponível, senão usar método antigo (compatibilidade)
            const files = content.files && Array.isArray(content.files) ? content.files : 
                         (content.file_path ? [{
                             id: null,
                             file_path: content.file_path,
                             file_name: content.file_name,
                             thumbnail_url: content.thumbnail_url,
                             video_title: content.video_title,
                             mime_type: content.mime_type || (content.content_type === 'videos' ? 'video/mp4' : 'application/pdf')
                         }] : []);
            
            if (files.length > 0 && currentFilesList) {
                currentFilesList.innerHTML = '';
                
                files.forEach((file, index) => {
                    // Construir URL do arquivo
                    let fileUrl = file.file_path;
                    if (!fileUrl.startsWith('http') && !fileUrl.startsWith('/')) {
                        fileUrl = '/' + fileUrl;
                    }
                    
                    // Determinar tipo de conteúdo
                    const isVideo = file.mime_type && file.mime_type.startsWith('video/') || 
                                   content.content_type === 'videos' ||
                                   (file.file_path && /\.(mp4|mov|avi|webm)$/i.test(file.file_path));
                    const isPDF = file.mime_type === 'application/pdf' || 
                                 content.content_type === 'pdf' ||
                                 (file.file_path && /\.pdf$/i.test(file.file_path));
                    
                    // Criar container para arquivo + título
                    const fileContainer = document.createElement('div');
                    fileContainer.style.cssText = 'display: flex; flex-direction: column; width: 100%; max-width: 300px;';
                    
                    // Criar elemento do arquivo
                    const fileItem = document.createElement('div');
                    fileItem.style.cssText = 'position: relative; width: 100%; border-radius: 12px; overflow: hidden; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--glass-border); cursor: pointer;';
                    fileItem.dataset.fileUrl = fileUrl;
                    fileItem.dataset.fileId = file.id || '';
                    fileItem.onclick = () => window.open(fileUrl, '_blank');
                    
                    if (isVideo) {
                        const video = document.createElement('video');
                        video.style.cssText = 'width: 100%; height: auto; max-height: 150px; display: block; object-fit: cover;';
                        video.src = fileUrl;
                        video.muted = true;
                        if (file.thumbnail_url) {
                            let thumbUrl = file.thumbnail_url;
                            if (!thumbUrl.startsWith('http') && !thumbUrl.startsWith('/')) {
                                thumbUrl = '/' + thumbUrl;
                            }
                            video.poster = thumbUrl;
                        }
                        fileItem.appendChild(video);
                        
                        // Overlay de play
                        const playOverlay = document.createElement('div');
                        playOverlay.style.cssText = 'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0, 0, 0, 0.6); border-radius: 50%; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.25rem; pointer-events: none;';
                        playOverlay.innerHTML = '<i class="fas fa-play"></i>';
                        fileItem.appendChild(playOverlay);
                    } else if (isPDF) {
                        const pdfIcon = document.createElement('div');
                        pdfIcon.style.cssText = 'width: 100%; height: 150px; background: rgba(255, 107, 0, 0.1); display: flex; align-items: center; justify-content: center;';
                        pdfIcon.innerHTML = '<i class="fas fa-file-pdf" style="font-size: 3rem; color: var(--accent-orange);"></i>';
                        fileItem.appendChild(pdfIcon);
                    } else if (file.thumbnail_url) {
                        const img = document.createElement('img');
                        let thumbUrl = file.thumbnail_url;
                        if (!thumbUrl.startsWith('http') && !thumbUrl.startsWith('/')) {
                            thumbUrl = '/' + thumbUrl;
                        }
                        img.src = thumbUrl;
                        img.style.cssText = 'width: 100%; height: auto; max-height: 150px; display: block; object-fit: cover;';
                        img.alt = 'Preview do arquivo';
                        fileItem.appendChild(img);
                    }
                    
                    // Container para botões de ação (editar e excluir)
                    const actionButtonsContainer = document.createElement('div');
                    actionButtonsContainer.style.cssText = 'position: absolute; top: 0.5rem; right: 0.5rem; display: flex; gap: 0.5rem; z-index: 10;';
                    
                    // Botão de editar (lápis) - apenas para vídeos
                    if (isVideo) {
                        const editBtn = document.createElement('button');
                        editBtn.type = 'button';
                        editBtn.onclick = (e) => {
                            e.stopPropagation();
                            e.preventDefault();
                            console.log('Botão de editar clicado:', { fileId: file.id, contentId: content.id, fileUrl });
                            editFileThumbnail(file.id || null, content.id, fileUrl);
                        };
                        editBtn.style.cssText = 'width: 36px; height: 36px; padding: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3); background: rgba(255, 107, 0, 0.1); border: 1px solid rgba(255, 107, 0, 0.3); color: var(--accent-orange); cursor: pointer; transition: all 0.3s ease;';
                        editBtn.innerHTML = '<i class="fas fa-pencil-alt"></i>';
                        
                        // Adicionar hover com zoom
                        editBtn.addEventListener('mouseenter', function() {
                            this.style.transform = 'scale(1.15)';
                            this.style.background = 'rgba(255, 107, 0, 0.2)';
                            this.style.borderColor = 'var(--accent-orange)';
                        });
                        editBtn.addEventListener('mouseleave', function() {
                            this.style.transform = 'scale(1)';
                            this.style.background = 'rgba(255, 107, 0, 0.1)';
                            this.style.borderColor = 'rgba(255, 107, 0, 0.3)';
                        });
                        
                        actionButtonsContainer.appendChild(editBtn);
                    }
                    
                    // Botão de lixeira
                    const deleteBtn = document.createElement('button');
                    deleteBtn.type = 'button';
                    deleteBtn.onclick = (e) => {
                        e.stopPropagation();
                        removeCurrentFile(file.id || null, content.id);
                    };
                    deleteBtn.style.cssText = 'width: 36px; height: 36px; padding: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3); background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #EF4444; cursor: pointer; transition: all 0.3s ease;';
                    deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
                    
                    // Adicionar hover com zoom
                    deleteBtn.addEventListener('mouseenter', function() {
                        this.style.transform = 'scale(1.15)';
                        this.style.background = 'rgba(239, 68, 68, 0.2)';
                        this.style.borderColor = '#EF4444';
                    });
                    deleteBtn.addEventListener('mouseleave', function() {
                        this.style.transform = 'scale(1)';
                        this.style.background = 'rgba(239, 68, 68, 0.1)';
                        this.style.borderColor = 'rgba(239, 68, 68, 0.3)';
                    });
                    
                    actionButtonsContainer.appendChild(deleteBtn);
                    fileItem.appendChild(actionButtonsContainer);
                    
                    // Adicionar arquivo ao container
                    fileContainer.appendChild(fileItem);
                    
                    // Título do arquivo (vídeo ou PDF) - diretamente abaixo do arquivo - EDITÁVEL
                    const titleDiv = document.createElement('div');
                    titleDiv.style.cssText = 'margin-top: 0.5rem; padding: 0.375rem 0.625rem; background: rgba(255, 107, 0, 0.06); border-radius: 6px; border: 1px solid rgba(255, 107, 0, 0.15); cursor: pointer; transition: all 0.2s ease;';
                    titleDiv.dataset.fileId = file.id || '';
                    titleDiv.dataset.contentId = content.id;
                    titleDiv.dataset.originalTitle = file.video_title || '';
                    
                    const titleText = file.video_title && file.video_title.trim() !== '' ? file.video_title : 'Sem título';
                    titleDiv.innerHTML = `<p style="margin: 0; color: var(--accent-orange); font-weight: 500; font-size: 0.75rem; line-height: 1.4; text-align: center; user-select: none;">${titleText}</p>`;
                    
                    // Anexar event listeners usando addEventListener para garantir que funcionem
                    titleDiv.addEventListener('click', (e) => {
                        e.stopPropagation();
                        editVideoTitle(titleDiv, file.id || null, content.id);
                    });
                    titleDiv.addEventListener('mouseenter', () => {
                        titleDiv.style.background = 'rgba(255, 107, 0, 0.1)';
                    });
                    titleDiv.addEventListener('mouseleave', () => {
                        titleDiv.style.background = 'rgba(255, 107, 0, 0.06)';
                    });
                    
                    fileContainer.appendChild(titleDiv);
                    
                    // Adicionar container completo à lista
                    currentFilesList.appendChild(fileContainer);
                });
                
                currentFilesInfo.style.display = 'block';
            } else {
                // Se não há arquivos salvos, ocultar apenas se não estiver preservando preview de novo arquivo
                if (!preserveNewFilePreview) {
                    if (currentFilesInfo) currentFilesInfo.style.display = 'none';
                }
            }
            
            // Carregar mini título se existir
            const videoTitleInput = document.getElementById('videoTitle');
            const videoTitleGroup = document.getElementById('videoTitleGroup');
            const currentVideoTitleDisplay = document.getElementById('currentVideoTitleDisplay');
            const currentVideoTitleDisplayText = document.getElementById('currentVideoTitleDisplayText');
            
            if (videoTitleInput) {
                if (content.video_title) {
                    videoTitleInput.value = content.video_title;
                } else {
                    videoTitleInput.value = '';
                }
            }
            
            // Mostrar título abaixo do vídeo atual (sempre verificar se é vídeo e se tem título)
            if (currentVideoTitleDisplay && currentVideoTitleDisplayText) {
                if (content.content_type === 'videos' && content.video_title && content.video_title.trim() !== '') {
                    currentVideoTitleDisplayText.textContent = content.video_title;
                    currentVideoTitleDisplay.style.display = 'block';
                } else {
                    currentVideoTitleDisplay.style.display = 'none';
                }
            }
            
            // Mostrar campo de título para vídeos e PDFs
            if ((content.content_type === 'videos' || content.content_type === 'pdf') && videoTitleGroup) {
                videoTitleGroup.style.display = 'block';
            } else if (videoTitleGroup) {
                videoTitleGroup.style.display = 'none';
            }
            
            // Limpar previews de novos arquivos apenas se não estiver preservando
            if (!preserveNewFilePreview) {
                clearFilePreview();
                clearThumbnailPreview();
            }
            // Se preserveNewFilePreview for true, o preview do novo arquivo já está visível
            // e não deve ser limpo
            
            // Se for vídeo e tiver arquivo atual, mostrar opção de gerar frames para trocar thumbnail
            if (content.content_type === 'videos' && files.length > 0) {
                const thumbnailGroup = document.getElementById('thumbnailGroup');
                if (thumbnailGroup) {
                    thumbnailGroup.style.display = 'block';
                }
                
                // Para cada vídeo salvo, carregar e gerar frames para permitir trocar thumbnail
                files.forEach((file) => {
                    if (file.mime_type && file.mime_type.startsWith('video/')) {
                        const fileUrl = file.file_path.startsWith('http') || file.file_path.startsWith('/') 
                            ? file.file_path 
                            : '/' + file.file_path;
                        
                        // Criar vídeo temporário para gerar frames
                        const tempVideo = document.createElement('video');
                        tempVideo.src = fileUrl;
                        tempVideo.muted = true;
                        tempVideo.preload = 'metadata';
                        tempVideo.crossOrigin = 'anonymous';
                        
                        tempVideo.onloadedmetadata = function() {
                            // Gerar frames para este vídeo específico
                            generateVideoFramesForExistingVideo(tempVideo, file.id || null);
                        };
                        
                        tempVideo.onerror = function() {
                            console.error('Erro ao carregar vídeo para gerar frames:', fileUrl);
                        };
                        
                        tempVideo.load();
                    }
                });
            }
            
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

// Função para editar título do vídeo inline
function editVideoTitle(titleDiv, fileId, contentId) {
    // Prevenir múltiplas edições simultâneas
    if (titleDiv.dataset.editing === 'true') {
        return;
    }
    
    const currentText = titleDiv.dataset.originalTitle || '';
    
    // Marcar como editando
    titleDiv.dataset.editing = 'true';
    
    // Criar input
    const input = document.createElement('input');
    input.type = 'text';
    input.value = currentText;
    input.style.cssText = 'width: 100%; padding: 0.375rem 0.625rem; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 107, 0, 0.3); border-radius: 6px; color: var(--accent-orange); font-weight: 500; font-size: 0.75rem; text-align: center; outline: none;';
    input.maxLength = 255;
    input.dataset.fileId = fileId || '';
    input.dataset.contentId = contentId || '';
    
    // Substituir texto por input
    titleDiv.innerHTML = '';
    titleDiv.appendChild(input);
    input.focus();
    input.select();
    
    const restoreTitle = (text) => {
        const displayText = text && text.trim() !== '' ? text : 'Sem título';
        titleDiv.innerHTML = `<p style="margin: 0; color: var(--accent-orange); font-weight: 500; font-size: 0.75rem; line-height: 1.4; text-align: center; user-select: none;">${displayText}</p>`;
        
        // Atualizar dataset com o valor atual do input (caso tenha sido editado)
        if (input && input.value.trim() !== currentText) {
            titleDiv.dataset.originalTitle = input.value.trim();
        }
        
        // Remover flag de edição
        titleDiv.dataset.editing = 'false';
        
        // Re-adicionar event listeners usando addEventListener
        const clickHandler = (e) => {
            e.stopPropagation();
            editVideoTitle(titleDiv, fileId, contentId);
        };
        titleDiv.addEventListener('click', clickHandler);
        
        titleDiv.addEventListener('mouseenter', () => {
            titleDiv.style.background = 'rgba(255, 107, 0, 0.1)';
        });
        
        titleDiv.addEventListener('mouseleave', () => {
            titleDiv.style.background = 'rgba(255, 107, 0, 0.06)';
        });
    };
    
    // Ao perder foco, apenas atualizar o dataset mas não salvar ainda
    input.addEventListener('blur', () => {
        const newTitle = input.value.trim();
        titleDiv.dataset.originalTitle = newTitle;
        restoreTitle(newTitle);
    });
    
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            input.blur(); // Isso vai restaurar o título
        } else if (e.key === 'Escape') {
            e.preventDefault();
            restoreTitle(currentText);
        }
    });
    
    // Prevenir que o blur seja acionado quando clicar no próprio titleDiv
    titleDiv.addEventListener('mousedown', (e) => {
        e.stopPropagation();
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
    const contentId = document.getElementById('contentId').value;
    const fileInput = document.getElementById('contentFile');
    
    if (!title) {
        showAlert('Validação', 'Título é obrigatório');
        return;
    }
    
    // Detectar tipo de conteúdo automaticamente se não estiver definido
    if (!contentType) {
        if (fileInput.files[0]) {
            const detectedType = detectContentType(fileInput.files[0]);
            if (detectedType) {
                document.getElementById('contentType').value = detectedType;
                contentType = detectedType;
            } else {
                showAlert('Validação', 'Tipo de arquivo não suportado. Use apenas vídeos (MP4, MOV, AVI, WebM) ou PDF.');
        return;
    }
        } else if (contentId) {
            // Ao editar, se não há arquivo novo, manter o tipo existente
            // O tipo já deve estar definido no hidden input
        } else {
            showAlert('Validação', 'Selecione um arquivo (vídeo ou PDF)');
            return;
        }
    }
    
    // Validar se há arquivo (obrigatório para vídeos e PDF)
    if (!fileInput.files[0] && !contentId) {
        showAlert('Validação', 'Arquivo é obrigatório para este tipo de conteúdo');
        return;
    }
    
    // Validar título do arquivo (obrigatório para vídeos e PDFs)
    const videoTitleInput = document.getElementById('videoTitle');
    if (videoTitleInput && videoTitleInput.offsetParent !== null) { // Se o campo está visível
        const videoTitle = videoTitleInput.value.trim();
        if (!videoTitle) {
            showAlert('Validação', 'Título do arquivo é obrigatório');
            videoTitleInput.focus();
            return;
        }
    }
    
    // Se arquivo foi removido, adicionar flag
    if (fileRemoved) {
        formData.append('remove_file', '1');
    }
    
    // Se houver thumbnail selecionada de um frame do vídeo, converter e adicionar
    const selectedThumbnailData = document.getElementById('selectedThumbnailData');
    const thumbnailData = selectedThumbnailData ? selectedThumbnailData.value : '';
    const fileIdForThumbnail = selectedThumbnailData ? (selectedThumbnailData.dataset.fileId || '') : '';
    
    if (thumbnailData && !formData.has('thumbnail')) {
        // Converter data URL para blob e adicionar ao FormData
        fetch(thumbnailData)
            .then(res => res.blob())
            .then(blob => {
                const file = new File([blob], 'thumbnail.jpg', { type: 'image/jpeg' });
                formData.append('thumbnail', file);
                if (fileIdForThumbnail) {
                    formData.append('thumbnail_file_id', fileIdForThumbnail);
                }
                submitFormData(formData);
            })
            .catch(() => {
                // Se falhar, tentar enviar sem thumbnail
                submitFormData(formData);
            });
        return;
    }
    
    // Se não houver thumbnail selecionada mas há vídeo novo, gerar automaticamente do primeiro frame
    if (fileInput && fileInput.files[0] && fileInput.files[0].type.startsWith('video/') && !thumbnailData) {
        const previewVideo = document.getElementById('previewVideo');
        if (previewVideo && previewVideo.readyState >= 2) {
            // Extrair primeiro frame automaticamente
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = previewVideo.videoWidth;
            canvas.height = previewVideo.videoHeight;
            
            // Ir para o primeiro frame (0.1 segundos)
            previewVideo.currentTime = 0.1;
            previewVideo.onseeked = function() {
                ctx.drawImage(previewVideo, 0, 0, canvas.width, canvas.height);
                const frameDataUrl = canvas.toDataURL('image/jpeg', 0.85);
                
                // Converter para blob e adicionar
                fetch(frameDataUrl)
                    .then(res => res.blob())
                    .then(blob => {
                        const file = new File([blob], 'thumbnail_auto.jpg', { type: 'image/jpeg' });
                        formData.append('thumbnail', file);
                        submitFormData(formData);
                    })
                    .catch(() => {
                        submitFormData(formData);
                    });
            };
            previewVideo.load();
            return;
        }
    }
    
    submitFormData(formData);
}

// Função auxiliar para enviar FormData
function submitFormData(formData) {
    // Coletar todos os títulos editados dos arquivos salvos
    const titleDivs = document.querySelectorAll('[data-file-id][data-content-id]');
    const titlesToUpdate = [];
    
    titleDivs.forEach(titleDiv => {
        const fileId = titleDiv.dataset.fileId;
        const contentId = titleDiv.dataset.contentId;
        
        if (!fileId || !contentId) return;
        
        // Verificar se há um input ativo (em edição)
        const input = titleDiv.querySelector('input');
        if (input) {
            const newTitle = input.value.trim();
            titlesToUpdate.push({
                file_id: fileId,
                content_id: contentId,
                video_title: newTitle
            });
        } else {
            // Se não há input, usar o valor do dataset (já foi atualizado no blur)
            const currentTitle = titleDiv.dataset.originalTitle || '';
            if (currentTitle) {
                titlesToUpdate.push({
                    file_id: fileId,
                    content_id: contentId,
                    video_title: currentTitle
                });
            }
        }
    });
    
    // Salvar títulos primeiro, depois salvar o conteúdo
    if (titlesToUpdate.length > 0) {
        const updatePromises = titlesToUpdate.map(titleData => {
            const titleFormData = new FormData();
            titleFormData.append('action', 'update_video_title');
            titleFormData.append('file_id', titleData.file_id);
            titleFormData.append('content_id', titleData.content_id);
            titleFormData.append('video_title', titleData.video_title);
            
            return fetch('ajax_content_management.php', {
                method: 'POST',
                body: titleFormData
            }).then(response => response.json());
        });
        
        Promise.all(updatePromises)
            .then(() => {
                // Após salvar todos os títulos, salvar o conteúdo
                sendFormData(formData);
            })
            .catch(error => {
                console.error('Erro ao salvar títulos:', error);
                // Mesmo com erro nos títulos, tentar salvar o conteúdo
                sendFormData(formData);
            });
    } else {
        // Se não há títulos para atualizar, salvar o conteúdo diretamente
        sendFormData(formData);
    }
}

// Função para enviar o FormData do conteúdo
function sendFormData(formData) {
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
            
            // Atualizar ID do conteúdo se foi criado
            let contentId = document.getElementById('contentId').value;
            if (data.content_id) {
                contentId = data.content_id;
                document.getElementById('contentId').value = contentId;
            }
            
            // Limpar preview do novo arquivo após salvar (sempre limpar)
            clearFilePreview();
            clearThumbnailPreview();
            
            // Limpar input de arquivo
            const fileInput = document.getElementById('contentFile');
            if (fileInput) {
                fileInput.value = '';
            }
            
            // Recarregar dados do conteúdo para mostrar o arquivo salvo (mantém modal aberto)
            if (contentId) {
                editContent(contentId, false);
            }
            
            // Atualizar lista de conteúdos via AJAX (sem recarregar página)
            updateContentList();
            
            // Atualizar estatísticas
            updateStats();
            
            // Restaurar botão
            saveButton.innerHTML = originalText;
            saveButton.disabled = false;
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
    const fileInput = document.getElementById('contentFile');
    
        const contentId = document.getElementById('contentId').value;
        if (!contentId) {
            fileInput.setAttribute('required', 'required');
        }
    
    // Aceitar vídeos e PDFs (detecção automática)
    fileInput.setAttribute('accept', 'video/mp4,video/quicktime,video/x-msvideo,video/webm,.pdf');
}

// Variável global para armazenar o vídeo e frames
let currentVideoFile = null;
let videoFramesGenerated = false;

// Variável global para controlar se arquivo foi removido
let fileRemoved = false;

// Variável global para armazenar dados do arquivo atual (para remoção)
let currentFileData = null;

// Função para detectar tipo de conteúdo automaticamente
function detectContentType(file) {
    if (!file) return '';
    
    // Verificar por MIME type primeiro
    if (file.type.startsWith('video/')) {
        return 'videos';
    } else if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
        return 'pdf';
    }
    
    // Verificar por extensão como fallback
    const extension = file.name.toLowerCase().split('.').pop();
    const videoExtensions = ['mp4', 'mov', 'avi', 'webm', 'mkv', 'flv', 'wmv'];
    if (videoExtensions.includes(extension)) {
        return 'videos';
    } else if (extension === 'pdf') {
        return 'pdf';
    }
    
    return '';
}

// Função para lidar com seleção de arquivo
function handleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Detectar tipo de conteúdo automaticamente
    const detectedType = detectContentType(file);
    if (detectedType) {
        document.getElementById('contentType').value = detectedType;
        // Atualizar visual do tipo (se houver display)
        const contentTypeDisplay = document.querySelector('[data-content-type-display]');
        if (contentTypeDisplay) {
            contentTypeDisplay.textContent = detectedType === 'videos' ? 'Vídeo' : 'PDF';
        }
        
        // Mostrar campo de título para vídeos e PDFs
        const videoTitleGroup = document.getElementById('videoTitleGroup');
        if (videoTitleGroup) {
            videoTitleGroup.style.display = 'block';
        }
    }
    
    const filePreview = document.getElementById('filePreview');
    const videoPreview = document.getElementById('videoPreview');
    const pdfPreview = document.getElementById('pdfPreview');
    const previewVideo = document.getElementById('previewVideo');
    const pdfFileName = document.getElementById('pdfFileName');
    const thumbnailGroup = document.getElementById('thumbnailGroup');
    const videoFramesGallery = document.getElementById('videoFramesGallery');
    const currentFileInfo = document.getElementById('currentFileInfo');
    
    // NÃO ocultar arquivo atual - manter visível para referência
    // O usuário pode ver o arquivo antigo enquanto seleciona o novo
    
    // Ocultar previews de novos arquivos (serão mostrados quando arquivo for selecionado)
    videoPreview.style.display = 'none';
    pdfPreview.style.display = 'none';
    filePreview.style.display = 'none';
    videoFramesGallery.style.display = 'none';
    thumbnailGroup.style.display = 'none';
    clearThumbnailPreview();
    videoFramesGenerated = false;
    
    // Verificar tipo de arquivo
    if (file.type.startsWith('video/') || detectedType === 'videos') {
        currentVideoFile = file;
        const videoURL = URL.createObjectURL(file);
        previewVideo.src = videoURL;
        videoPreview.style.display = 'block';
        filePreview.style.display = 'block';
        
        // Mostrar campo de título e grupo de thumbnail
        const videoTitleGroup = document.getElementById('videoTitleGroup');
        if (videoTitleGroup) {
            videoTitleGroup.style.display = 'block';
        }
        thumbnailGroup.style.display = 'block';
        
        // Aguardar o vídeo carregar para extrair frames
        previewVideo.onloadedmetadata = function() {
            generateVideoFrames(previewVideo);
        };
    } else if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf') || detectedType === 'pdf') {
        currentVideoFile = null;
        pdfFileName.textContent = file.name;
        pdfPreview.style.display = 'block';
        filePreview.style.display = 'block';
        
        // Mostrar campo de título para PDF também
        const videoTitleGroup = document.getElementById('videoTitleGroup');
        if (videoTitleGroup) {
            videoTitleGroup.style.display = 'block';
        }
        // Ocultar apenas thumbnails para PDF
        thumbnailGroup.style.display = 'none';
    }
}

// Função para remover arquivo atual (ao editar) - remoção imediata
function removeCurrentFile(fileId = null, contentId = null) {
    // Se contentId não foi fornecido, tentar pegar do formulário
    if (!contentId) {
        contentId = document.getElementById('contentId')?.value;
    }
    
    if (!contentId || contentId <= 0) {
        // Se não está editando, apenas limpar visualmente
        const currentFilesInfo = document.getElementById('currentFilesInfo');
        const fileInput = document.getElementById('contentFile');
        
        if (currentFilesInfo) {
            currentFilesInfo.style.display = 'none';
        }
        
        if (fileInput) {
            fileInput.value = '';
            fileInput.setAttribute('required', 'required');
        }
        
        clearFilePreview();
        clearThumbnailPreview();
        document.getElementById('contentType').value = '';
        currentFileData = null;
        return;
    }
    
    // Mostrar modal de confirmação
    showConfirm(
        'Remover Arquivo',
        'Tem certeza que deseja remover este arquivo permanentemente? Esta ação não pode ser desfeita.',
        function() {
            // Remover imediatamente via AJAX
            const formData = new FormData();
            formData.append('action', 'remove_file');
            formData.append('content_id', contentId);
            if (fileId) {
                formData.append('file_id', fileId);
            }
            
            // Mostrar loading
            const currentFileInfo = document.getElementById('currentFileInfo');
            const currentFilePreview = document.getElementById('currentFilePreview');
            const loadingOverlay = document.createElement('div');
            loadingOverlay.style.cssText = 'position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 20; border-radius: 12px;';
            loadingOverlay.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: white;"></i>';
            if (currentFilePreview) {
                currentFilePreview.style.position = 'relative';
                currentFilePreview.appendChild(loadingOverlay);
            }
            
            fetch('ajax_content_management.php', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const text = await response.text();
                if (loadingOverlay && loadingOverlay.parentNode) {
                    loadingOverlay.remove();
                }
                
                if (!response.ok) {
                    let errorMsg = 'Erro ao remover arquivo';
                    try {
                        const json = JSON.parse(text);
                        errorMsg = json.error || errorMsg;
                    } catch (e) {
                        errorMsg = text || `Erro HTTP ${response.status}`;
                    }
                    throw new Error(errorMsg);
                }
                
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Resposta inválida do servidor');
                }
            })
            .then(data => {
                if (data.success) {
                    // Ocultar arquivo atual
                    if (currentFileInfo) {
                        currentFileInfo.style.display = 'none';
                    }
                    
                    // Limpar input de arquivo
                    const fileInput = document.getElementById('contentFile');
                    if (fileInput) {
                        fileInput.value = '';
                        fileInput.setAttribute('required', 'required');
                    }
                    
                    // Limpar previews
                    clearFilePreview();
                    clearThumbnailPreview();
                    
                    // Resetar tipo de conteúdo
                    document.getElementById('contentType').value = '';
                    
                    // Limpar dados do arquivo atual
                    currentFileData = null;
                    fileRemoved = false; // Já foi removido no servidor
                    
                    showAlert('Sucesso', 'Arquivo removido com sucesso!');
                    
                    // Recarregar dados do conteúdo para atualizar a interface
                    const contentIdValue = document.getElementById('contentId').value;
                    if (contentIdValue) {
                        editContent(contentIdValue);
                    }
                } else {
                    throw new Error(data.error || 'Erro ao remover arquivo');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showAlert('Erro', error.message || 'Erro ao remover arquivo. Tente novamente.');
            });
        }
    );
}

// Função para gerar frames do vídeo
function generateVideoFrames(video) {
    if (!video || video.readyState < 2) {
        // Se o vídeo ainda não carregou, tentar novamente
        setTimeout(() => generateVideoFrames(video), 500);
        return;
    }
    
    const gallery = document.getElementById('videoFramesGallery');
    const framesContainer = gallery.querySelector('div');
    if (!framesContainer) return;
    
    // Limpar frames anteriores
    framesContainer.innerHTML = '';
    videoFramesGenerated = false;
    
    // Mostrar loading
    const loadingDiv = document.createElement('div');
    loadingDiv.style.cssText = 'text-align: center; padding: 2rem; color: var(--text-secondary);';
    loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando frames...';
    framesContainer.appendChild(loadingDiv);
    
    const duration = video.duration;
    const numFrames = 4; // 4 frames como no YouTube
    
    // Criar canvas para extrair frames
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    let framesExtracted = 0;
    const frameTimes = [];
    
    // Calcular tempos dos frames com variação aleatória para evitar sempre os mesmos frames
    // Usar timestamp atual para variar os tempos
    const seed = Date.now() % 1000; // Seed baseado no tempo atual
    const baseInterval = duration / (numFrames + 1);
    
    for (let i = 1; i <= numFrames; i++) {
        // Adicionar variação aleatória baseada no seed
        const variation = (seed * i) % (baseInterval * 0.3); // Variação de até 30% do intervalo
        const frameTime = baseInterval * i + variation;
        // Garantir que está dentro dos limites do vídeo
        frameTimes.push(Math.max(0.5, Math.min(duration - 0.5, frameTime)));
    }
    
    // Ordenar os tempos para garantir ordem crescente
    frameTimes.sort((a, b) => a - b);
    
    // Criar um vídeo temporário para extrair frames (não interfere com o preview)
    const tempVideo = document.createElement('video');
    tempVideo.src = video.src;
    tempVideo.muted = true;
    tempVideo.preload = 'metadata';
    
    let currentFrameIndex = 0;
    
    function extractNextFrame() {
        if (currentFrameIndex >= frameTimes.length) {
            // Todos os frames foram extraídos
            videoFramesGenerated = true;
            gallery.style.display = 'block';
            return;
        }
        
        const time = frameTimes[currentFrameIndex];
        tempVideo.currentTime = time;
    }
    
    tempVideo.onseeked = function() {
        // Desenhar frame no canvas
        ctx.drawImage(tempVideo, 0, 0, canvas.width, canvas.height);
        
        // Converter canvas para imagem
        const frameDataUrl = canvas.toDataURL('image/jpeg', 0.85);
        
        // Criar elemento de frame
        const frameDiv = document.createElement('div');
        frameDiv.style.cssText = 'position: relative; cursor: pointer; border-radius: 8px; overflow: hidden; border: 2px solid transparent; transition: all 0.3s ease; background: rgba(255, 255, 255, 0.05);';
        frameDiv.className = 'video-frame-thumb';
        frameDiv.dataset.frameIndex = currentFrameIndex;
        
        // Hover effect
        frameDiv.addEventListener('mouseenter', function() {
            if (!this.querySelector('.frame-check-icon').style.display || this.querySelector('.frame-check-icon').style.display === 'none') {
                this.style.borderColor = 'rgba(255, 107, 0, 0.5)';
            }
        });
        frameDiv.addEventListener('mouseleave', function() {
            if (!this.querySelector('.frame-check-icon').style.display || this.querySelector('.frame-check-icon').style.display === 'none') {
                this.style.borderColor = 'transparent';
            }
        });
        
        const frameImg = document.createElement('img');
        frameImg.src = frameDataUrl;
        frameImg.style.cssText = 'width: 100%; height: 120px; object-fit: cover; display: block;';
        frameImg.alt = `Frame ${currentFrameIndex + 1}`;
        
        const checkIcon = document.createElement('div');
        checkIcon.style.cssText = 'position: absolute; top: 0.5rem; right: 0.5rem; background: var(--accent-orange); color: white; width: 24px; height: 24px; border-radius: 50%; display: none; align-items: center; justify-content: center; font-size: 0.75rem; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);';
        checkIcon.innerHTML = '<i class="fas fa-check"></i>';
        checkIcon.className = 'frame-check-icon';
        
        frameDiv.appendChild(frameImg);
        frameDiv.appendChild(checkIcon);
        
        // Adicionar evento de clique
        frameDiv.addEventListener('click', function() {
            selectVideoFrame(frameDataUrl, frameDiv);
        });
        
        // Remover loading se for o primeiro frame
        if (currentFrameIndex === 0) {
            loadingDiv.remove();
        }
        
        framesContainer.appendChild(frameDiv);
        
        framesExtracted++;
        currentFrameIndex++;
        
        // Extrair próximo frame
        extractNextFrame();
    };
    
    tempVideo.onerror = function() {
        loadingDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Erro ao gerar frames';
        loadingDiv.style.color = '#EF4444';
    };
    
    // Iniciar extração
    tempVideo.load();
    extractNextFrame();
}

// Função para regenerar frames
function regenerateVideoFrames() {
    const previewVideo = document.getElementById('previewVideo');
    if (previewVideo && previewVideo.src) {
        generateVideoFrames(previewVideo);
    }
}

// Função para selecionar um frame como thumbnail
function selectVideoFrame(frameDataUrl, frameElement) {
    // Remover seleção anterior
    document.querySelectorAll('.video-frame-thumb').forEach(frame => {
        frame.style.borderColor = 'transparent';
        const checkIcon = frame.querySelector('.frame-check-icon');
        if (checkIcon) checkIcon.style.display = 'none';
    });
    
    // Marcar frame selecionado
    frameElement.style.borderColor = 'var(--accent-orange)';
    const checkIcon = frameElement.querySelector('.frame-check-icon');
    if (checkIcon) checkIcon.style.display = 'flex';
    
    // Atualizar poster do vídeo diretamente
    const previewVideo = document.getElementById('previewVideo');
    if (previewVideo) {
        previewVideo.poster = frameDataUrl;
    }
    
    // Salvar no hidden input
    const selectedThumbnailData = document.getElementById('selectedThumbnailData');
    if (selectedThumbnailData) {
        selectedThumbnailData.value = frameDataUrl;
        // Limpar fileId se for para novo vídeo
        selectedThumbnailData.dataset.fileId = '';
    }
}

// Função para gerar frames de um vídeo existente (ao editar)
function generateVideoFramesForExistingVideo(video, fileId) {
    console.log('generateVideoFramesForExistingVideo chamado:', { video: !!video, readyState: video?.readyState, fileId });
    
    if (!video) {
        console.error('Vídeo não fornecido');
        return;
    }
    
    if (video.readyState < 2) {
        console.log('Vídeo ainda não carregado, aguardando...', video.readyState);
        setTimeout(() => generateVideoFramesForExistingVideo(video, fileId), 500);
        return;
    }
    
    const gallery = document.getElementById('videoFramesGallery');
    if (!gallery) {
        console.error('videoFramesGallery não encontrado');
        return;
    }
    
    const framesContainer = gallery.querySelector('div');
    if (!framesContainer) {
        console.error('framesContainer não encontrado dentro de videoFramesGallery');
        return;
    }
    
    console.log('Gerando frames para vídeo:', { duration: video.duration, width: video.videoWidth, height: video.videoHeight });
    
    const duration = video.duration;
    const numFrames = 4;
    
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    let framesExtracted = 0;
    const frameTimes = [];
    const seed = Date.now() % 1000;
    const baseInterval = duration / (numFrames + 1);
    
    for (let i = 1; i <= numFrames; i++) {
        const variation = (seed * i) % (baseInterval * 0.3);
        const frameTime = baseInterval * i + variation;
        frameTimes.push(Math.max(0.5, Math.min(duration - 0.5, frameTime)));
    }
    
    frameTimes.sort((a, b) => a - b);
    
    const tempVideo = document.createElement('video');
    tempVideo.src = video.src;
    tempVideo.muted = true;
    tempVideo.preload = 'metadata';
    tempVideo.crossOrigin = 'anonymous';
    
    let currentFrameIndex = 0;
    
    function extractNextFrame() {
        if (currentFrameIndex >= frameTimes.length) {
            console.log('Todos os frames foram extraídos');
            console.log('Total de frames no container:', framesContainer.children.length);
            console.log('Estado da galeria antes:', { display: gallery.style.display, offsetParent: gallery.offsetParent });
            
            // Garantir que o thumbnailGroup está visível
            const thumbnailGroup = document.getElementById('thumbnailGroup');
            if (thumbnailGroup) {
                thumbnailGroup.style.display = 'block';
                console.log('thumbnailGroup exibido');
            }
            
            // Exibir a galeria
            gallery.style.display = 'block';
            console.log('Galeria exibida:', gallery.style.display, 'Visível:', gallery.offsetParent !== null);
            console.log('Estado da galeria depois:', { display: gallery.style.display, offsetParent: gallery.offsetParent, offsetHeight: gallery.offsetHeight });
            
            // Scroll até a galeria
            setTimeout(() => {
                gallery.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                console.log('Scroll executado');
            }, 100);
            return;
        }
        
        const time = frameTimes[currentFrameIndex];
        console.log(`Extraindo frame ${currentFrameIndex + 1}/${frameTimes.length} no tempo ${time.toFixed(2)}s`);
        tempVideo.currentTime = time;
    }
    
    tempVideo.onseeked = function() {
        ctx.drawImage(tempVideo, 0, 0, canvas.width, canvas.height);
        const frameDataUrl = canvas.toDataURL('image/jpeg', 0.85);
        
        const frameDiv = document.createElement('div');
        frameDiv.style.cssText = 'position: relative; cursor: pointer; border-radius: 8px; overflow: hidden; border: 2px solid transparent; transition: all 0.3s ease; background: rgba(255, 255, 255, 0.05);';
        frameDiv.className = 'video-frame-thumb';
        frameDiv.dataset.frameIndex = currentFrameIndex;
        frameDiv.dataset.fileId = fileId || '';
        
        frameDiv.addEventListener('mouseenter', function() {
            const checkIcon = this.querySelector('.frame-check-icon');
            if (!checkIcon || checkIcon.style.display === 'none') {
                this.style.borderColor = 'rgba(255, 107, 0, 0.5)';
            }
        });
        frameDiv.addEventListener('mouseleave', function() {
            const checkIcon = this.querySelector('.frame-check-icon');
            if (!checkIcon || checkIcon.style.display === 'none') {
                this.style.borderColor = 'transparent';
            }
        });
        
        const frameImg = document.createElement('img');
        frameImg.src = frameDataUrl;
        frameImg.style.cssText = 'width: 100%; height: 120px; object-fit: cover; display: block;';
        frameImg.alt = `Frame ${currentFrameIndex + 1}`;
        
        const checkIcon = document.createElement('div');
        checkIcon.style.cssText = 'position: absolute; top: 0.5rem; right: 0.5rem; background: var(--accent-orange); color: white; width: 24px; height: 24px; border-radius: 50%; display: none; align-items: center; justify-content: center; font-size: 0.75rem; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);';
        checkIcon.innerHTML = '<i class="fas fa-check"></i>';
        checkIcon.className = 'frame-check-icon';
        
        frameDiv.appendChild(frameImg);
        frameDiv.appendChild(checkIcon);
        
        frameDiv.addEventListener('click', function() {
            selectVideoFrameForExisting(fileId, frameDataUrl, frameDiv);
        });
        
        framesContainer.appendChild(frameDiv);
        console.log('Frame adicionado ao DOM:', currentFrameIndex + 1, frameDiv);
        
        framesExtracted++;
        currentFrameIndex++;
        extractNextFrame();
    };
    
    tempVideo.onerror = function(error) {
        console.error('Erro ao gerar frames do vídeo existente:', error);
    };
    
    tempVideo.onloadedmetadata = function() {
        console.log('Vídeo temporário carregado, iniciando extração de frames');
    };
    
    try {
        tempVideo.load();
        extractNextFrame();
    } catch (error) {
        console.error('Erro ao iniciar extração de frames:', error);
    }
}

// Função para selecionar frame de vídeo existente (com fileId)
function selectVideoFrameForExisting(fileId, frameDataUrl, frameElement) {
    // Remover seleção anterior
    document.querySelectorAll('.video-frame-thumb').forEach(frame => {
        frame.style.borderColor = 'transparent';
        const checkIcon = frame.querySelector('.frame-check-icon');
        if (checkIcon) checkIcon.style.display = 'none';
    });
    
    // Marcar frame selecionado
    frameElement.style.borderColor = 'var(--accent-orange)';
    const checkIcon = frameElement.querySelector('.frame-check-icon');
    if (checkIcon) checkIcon.style.display = 'flex';
    
    // Salvar no hidden input com fileId
    const selectedThumbnailData = document.getElementById('selectedThumbnailData');
    if (selectedThumbnailData) {
        selectedThumbnailData.value = frameDataUrl;
        selectedThumbnailData.dataset.fileId = fileId || '';
    }
    
    // Salvar automaticamente a thumbnail selecionada
    if (fileId && frameDataUrl) {
        saveFileThumbnail(fileId, frameDataUrl);
    }
}

// Função para editar thumbnail de um arquivo específico
function editFileThumbnail(fileId, contentId, fileUrl) {
    console.log('editFileThumbnail chamado:', { fileId, contentId, fileUrl });
    
    if (!fileId || !contentId || !fileUrl) {
        console.error('Parâmetros inválidos:', { fileId, contentId, fileUrl });
        showAlert('Erro', 'Parâmetros inválidos para editar thumbnail');
        return;
    }
    
    // Mostrar grupo de thumbnail
    const thumbnailGroup = document.getElementById('thumbnailGroup');
    const videoFramesGallery = document.getElementById('videoFramesGallery');
    const framesContainer = videoFramesGallery ? videoFramesGallery.querySelector('div') : null;
    
    console.log('Elementos encontrados:', { 
        thumbnailGroup: !!thumbnailGroup, 
        videoFramesGallery: !!videoFramesGallery, 
        framesContainer: !!framesContainer 
    });
    
    if (!thumbnailGroup || !videoFramesGallery || !framesContainer) {
        console.error('Elementos não encontrados:', { thumbnailGroup, videoFramesGallery, framesContainer });
        showAlert('Erro', 'Elementos do modal não encontrados. Certifique-se de que o modal está aberto.');
        return;
    }
    
    // Limpar frames anteriores
    framesContainer.innerHTML = '';
    
    // Mostrar grupo de thumbnail
    thumbnailGroup.style.display = 'block';
    // A galeria será exibida quando os frames forem gerados
    videoFramesGallery.style.display = 'none'; // Inicialmente escondida, será mostrada quando os frames estiverem prontos
    
    // Adicionar loading
    const loadingDiv = document.createElement('div');
    loadingDiv.style.cssText = 'text-align: center; padding: 2rem; color: var(--text-secondary);';
    loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Carregando vídeo...';
    framesContainer.appendChild(loadingDiv);
    
    // Criar vídeo temporário para gerar frames
    const tempVideo = document.createElement('video');
    tempVideo.src = fileUrl;
    tempVideo.muted = true;
    tempVideo.preload = 'metadata';
    tempVideo.crossOrigin = 'anonymous';
    
    tempVideo.onloadedmetadata = function() {
        loadingDiv.remove();
        generateVideoFramesForExistingVideo(tempVideo, fileId);
    };
    
    tempVideo.onerror = function() {
        loadingDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Erro ao carregar vídeo';
        loadingDiv.style.color = '#EF4444';
    };
    
    tempVideo.load();
    
    // Scroll suave até a galeria de thumbnails
    setTimeout(() => {
        thumbnailGroup.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 100);
}

// Função para salvar thumbnail de um arquivo específico
function saveFileThumbnail(fileId, frameDataUrl) {
    if (!fileId || !frameDataUrl) return;
    
    // Converter data URL para blob
    fetch(frameDataUrl)
        .then(res => res.blob())
        .then(blob => {
            const file = new File([blob], 'thumbnail.jpg', { type: 'image/jpeg' });
            const formData = new FormData();
            formData.append('action', 'save_content');
            formData.append('content_id', document.getElementById('contentId').value);
            formData.append('thumbnail', file);
            formData.append('thumbnail_file_id', fileId);
            
            return fetch('ajax_content_management.php', {
                method: 'POST',
                body: formData
            });
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Atualizar preview do arquivo na lista
                const fileItem = document.querySelector(`[data-file-id="${fileId}"]`);
                if (fileItem) {
                    const video = fileItem.querySelector('video');
                    if (video) {
                        video.poster = frameDataUrl;
                    }
                }
                
                // Mostrar mensagem de sucesso
                showAlert('Sucesso', 'Thumbnail atualizada com sucesso!');
                
                // Recarregar conteúdo para atualizar a lista
                const contentId = document.getElementById('contentId').value;
                if (contentId) {
                    setTimeout(() => {
                        editContent(contentId, true);
                    }, 1000);
                }
            } else {
                showAlert('Erro', data.error || 'Erro ao salvar thumbnail');
            }
        })
        .catch(error => {
            console.error('Erro ao salvar thumbnail:', error);
            showAlert('Erro', 'Erro ao salvar thumbnail. Tente novamente.');
        });
}

// Função para abrir arquivo atual ao clicar
function openCurrentFile() {
    const currentFilePreview = document.getElementById('currentFilePreview');
    if (!currentFilePreview || !currentFilePreview.dataset.fileUrl) return;
    
    const fileUrl = currentFilePreview.dataset.fileUrl;
    window.open(fileUrl, '_blank');
}

// Função para atualizar display do título do vídeo (não usado no preview, só após salvar)
function updateVideoTitleDisplay() {
    // Não fazer nada - título só aparece após salvar
}

// Função para limpar preview do arquivo
function clearFilePreview() {
    const fileInput = document.getElementById('contentFile');
    const filePreview = document.getElementById('filePreview');
    const previewVideo = document.getElementById('previewVideo');
    const thumbnailGroup = document.getElementById('thumbnailGroup');
    const videoFramesGallery = document.getElementById('videoFramesGallery');
    const videoTitleGroup = document.getElementById('videoTitleGroup');
    
    if (fileInput) fileInput.value = '';
    if (filePreview) filePreview.style.display = 'none';
    if (thumbnailGroup) thumbnailGroup.style.display = 'none';
    if (videoFramesGallery) videoFramesGallery.style.display = 'none';
    if (videoTitleGroup) videoTitleGroup.style.display = 'none';
    if (previewVideo && previewVideo.src) {
        // Verificar se é blob URL antes de revogar
        if (previewVideo.src.startsWith('blob:')) {
            URL.revokeObjectURL(previewVideo.src);
        }
        previewVideo.src = '';
    }
    
    currentVideoFile = null;
    videoFramesGenerated = false;
    
    // NÃO ocultar currentFileInfo - manter arquivo antigo visível
}

// Função para limpar preview da thumbnail (apenas limpar o poster do vídeo)
function clearThumbnailPreview() {
    const previewVideo = document.getElementById('previewVideo');
    if (previewVideo) {
        previewVideo.poster = '';
    }
    document.getElementById('selectedThumbnailData').value = '';
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
