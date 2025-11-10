<?php
// admin/challenge_groups.php - Gerenciamento de Grupos de Desafio - Design Profissional

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/challenge_status_helper.php';

requireAdminLogin();

// Atualizar status dos desafios automaticamente baseado nas datas
updateChallengeStatusAutomatically($conn);

$page_slug = 'challenge_groups';
$page_title = 'Grupos de Desafio';

$admin_id = $_SESSION['admin_id'] ?? 1;

// --- Lógica de busca e filtro ---
$search_term = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// --- Estatísticas gerais ---
$stats = [];

// Total de grupos
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM sf_challenge_groups WHERE created_by = $admin_id")->fetch_assoc()['count'];

// Por status
$stats_query = "SELECT status, COUNT(*) as count 
                FROM sf_challenge_groups 
                WHERE created_by = $admin_id
                GROUP BY status";
$stats_result = $conn->query($stats_query);
$stats_by_status = ['active' => 0, 'inactive' => 0, 'completed' => 0, 'scheduled' => 0];
while ($row = $stats_result->fetch_assoc()) {
    $stats_by_status[$row['status']] = $row['count'];
}
$stats['active'] = $stats_by_status['active'];
$stats['completed'] = $stats_by_status['completed'];
$stats['scheduled'] = $stats_by_status['scheduled'] ?? 0;
$stats['inactive'] = $stats_by_status['inactive'] ?? 0;

// --- Construir query de busca ---
$sql = "SELECT 
    cg.*,
    COUNT(DISTINCT cgm.user_id) as member_count
    FROM sf_challenge_groups cg
    LEFT JOIN sf_challenge_group_members cgm ON cg.id = cgm.group_id
    WHERE cg.created_by = ?";
$conditions = [];
$params = [$admin_id];
$types = 'i';

if (!empty($search_term)) {
    $conditions[] = "cg.name LIKE ?";
    $params[] = '%' . $search_term . '%';
    $types .= 's';
}

if (!empty($status_filter)) {
    $conditions[] = "cg.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

$sql .= " GROUP BY cg.id ORDER BY cg.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

// Executar query
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $challenge_groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $challenge_groups = [];
}

// Contar total para paginação
$count_sql = "SELECT COUNT(*) as count FROM sf_challenge_groups cg WHERE cg.created_by = ?";
$count_params = [$admin_id];
$count_types = 'i';

if (!empty($search_term)) {
    $count_sql .= " AND cg.name LIKE ?";
    $count_params[] = '%' . $search_term . '%';
    $count_types .= 's';
}

if (!empty($status_filter)) {
    $count_sql .= " AND cg.status = ?";
    $count_params[] = $status_filter;
    $count_types .= 's';
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

// Adicionar Flatpickr CSS e JS para esta página
$extra_css = $extra_css ?? [];
$extra_js = $extra_js ?? [];

require_once __DIR__ . '/includes/header.php';
?>
<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<!-- Flatpickr Locale Português -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script>

<style>
/* ========================================================================= */
/*       CHALLENGE GROUPS PAGE - DESIGN MODERNO                              */
/* ========================================================================= */

.challenge-groups-page {
    padding: 1.5rem 2rem;
    min-height: 100vh;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

/* Forçar remoção de sombras e efeitos de todos os cards */
.challenge-groups-page * {
    box-shadow: none !important;
}

.challenge-groups-page .dashboard-card,
.challenge-groups-page .content-card,
.challenge-groups-page [class*="card"] {
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

/* Stats Grid - Estilo igual foods_management_new.php */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
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

/* Responsividade dos Cards de Desafio */
@media (max-width: 1024px) {
    .challenge-groups-grid {
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
    .challenge-groups-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .challenge-group-card {
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
    .challenge-group-card {
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
    filter: none !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
}

.stat-card:hover {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: var(--accent-orange) !important;
    transform: translateY(-2px) !important;
    z-index: 2 !important;
    box-shadow: none !important;
}

.stat-number {
    font-size: 1.5rem !important;
    font-weight: 700 !important;
    color: var(--accent-orange) !important;
    margin: 0 !important;
    padding: 0 !important;
    line-height: 1.2 !important;
    white-space: nowrap !important;
    text-align: center !important;
    width: 100% !important;
    display: block !important;
    flex-shrink: 0 !important;
}

.stat-label {
    font-size: 0.75rem !important;
    color: var(--text-secondary) !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    font-weight: 600 !important;
    line-height: 1.4 !important;
    margin: 0.5rem 0 0 0 !important;
    padding: 0 !important;
    text-align: center !important;
    width: 100% !important;
    display: block !important;
    word-break: break-word !important;
    hyphens: auto !important;
    flex-shrink: 0 !important;
}

/* Filter Card */
.filter-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 1.25rem !important;
    margin-bottom: 2rem !important;
    box-shadow: none !important;
    filter: none !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
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

/* Custom Select (mesmo estilo do foods_management_new.php) */
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

/* Buttons */
.btn-create-challenge {
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
    text-decoration: none;
    flex-shrink: 0;
}

.btn-create-challenge:hover {
    background: rgba(255, 107, 0, 0.15);
    border-color: var(--accent-orange);
    transform: scale(1.05);
}

.btn-create-challenge i {
    font-size: 1.5rem;
}

/* Groups Grid */
.challenge-groups-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 380px), 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
    width: 100%;
    box-sizing: border-box;
}

.challenge-group-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 12px !important;
    padding: 1rem !important;
    transition: all 0.3s ease !important;
    cursor: pointer !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 0.75rem !important;
    box-shadow: none !important;
    filter: none !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    width: 100% !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
    min-width: 0 !important;
}

.challenge-group-card:hover {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: var(--accent-orange) !important;
    transform: none !important;
    box-shadow: none !important;
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
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    flex: 1;
    min-width: 0;
    word-wrap: break-word;
    overflow-wrap: break-word;
    hyphens: auto;
}

.group-status {
    padding: 0.375rem 0.75rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.group-status.active {
    background: rgba(16, 185, 129, 0.2);
    color: #10B981;
    border: 1px solid #10B981;
}

.group-status.completed {
    background: rgba(59, 130, 246, 0.2);
    color: #3B82F6;
    border: 1px solid #3B82F6;
}

.group-status.inactive {
    background: rgba(107, 114, 128, 0.2);
    color: #6B7280;
    border: 1px solid #6B7280;
}

.group-status.scheduled {
    background: rgba(251, 191, 36, 0.2);
    color: #FBBF24;
    border: 1px solid #FBBF24;
}

.group-description {
    color: var(--text-secondary);
    font-size: 0.9rem;
    line-height: 1.5;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-wrap: break-word;
    overflow-wrap: break-word;
    width: 100%;
    box-sizing: border-box;
}

.group-info {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    width: 100%;
    box-sizing: border-box;
}

.group-info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
    flex-shrink: 0;
    min-width: 0;
    white-space: nowrap;
}

.group-info-item i {
    color: var(--accent-orange);
}

.group-card-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: auto;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    align-items: center;
    flex-wrap: wrap;
    width: 100%;
    box-sizing: border-box;
    min-width: 0;
}

.btn-action {
    flex: 1;
    min-width: 0;
    max-width: 100%;
    padding: 0.625rem 0.75rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 0.8125rem;
    white-space: nowrap;
    overflow: visible;
    text-overflow: clip;
    box-sizing: border-box;
    border: 1px solid;
    background: transparent;
    color: var(--text-primary);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
    position: relative;
    line-height: 1.2;
}

.btn-action i {
    flex-shrink: 0;
    font-size: 0.8125rem;
}

/* Garantir que o texto dos botões não seja cortado */
.btn-action {
    text-align: center;
}

/* Toggle Switch - Interruptor Moderno */
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

.btn-edit {
    background: rgba(255, 107, 0, 0.1);
    color: var(--accent-orange);
    border: 1px solid rgba(255, 107, 0, 0.2);
}

.btn-edit:hover {
    background: rgba(255, 107, 0, 0.2);
    border-color: var(--accent-orange);
}

.btn-delete {
    background: rgba(239, 68, 68, 0.1);
    color: #EF4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.btn-delete:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: #EF4444;
}

.btn-action.btn-view {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.3);
    color: #3B82F6;
}

.btn-action.btn-view:hover {
    background: rgba(59, 130, 246, 0.2);
    border-color: #3B82F6;
    color: #3B82F6;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 3rem;
    color: var(--text-secondary);
    opacity: 0.5;
    margin-bottom: 1rem;
}

.empty-state h3 {
    font-size: 1.25rem;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.empty-state p {
    font-size: 0.95rem;
    margin: 0;
}

/* ========================================================================= */
/*       PROGRESS MODAL - MODAL DE PROGRESSO DOS PARTICIPANTES               */
/* ========================================================================= */

/* Modal de progresso usa a mesma estrutura dos outros modais */
.progress-modal-content {
    /* Herda estilos de .challenge-edit-content */
}

.progress-modal-content-simple {
    position: relative;
    width: 90%;
    max-width: 1200px;
    min-height: 400px;
    background: rgba(18, 18, 23, 0.98);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    margin: auto;
    z-index: 10001;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.progress-modal-body {
    padding: 1.5rem;
    position: relative;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.progress-date {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 500;
    font-family: 'Montserrat', sans-serif;
}

.progress-date i {
    color: var(--accent-orange);
    font-size: 0.875rem;
}

.btn-refresh {
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.3);
    color: var(--accent-orange);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.8125rem;
    font-weight: 600;
    font-family: 'Montserrat', sans-serif;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-refresh:hover {
    background: rgba(255, 107, 0, 0.15);
    border-color: var(--accent-orange);
}

.btn-refresh i {
    font-size: 0.8125rem;
}

.progress-ranking {
    margin-top: 0;
}

.ranking-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Montserrat', sans-serif;
}

.ranking-title i {
    color: var(--accent-orange);
    font-size: 1rem;
}

.participants-ranking-list {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.participant-rank-item {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 1rem;
    display: grid;
    grid-template-columns: 50px 1fr 320px;
    gap: 1rem;
    align-items: start;
    transition: background 0.2s ease;
}

.participant-rank-item:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 107, 0, 0.2);
}

.participant-rank-item.rank-first {
    border-color: rgba(255, 215, 0, 0.4);
    background: rgba(255, 215, 0, 0.05);
}

.participant-rank-item.rank-second {
    border-color: rgba(192, 192, 192, 0.4);
    background: rgba(192, 192, 192, 0.05);
}

.participant-rank-item.rank-third {
    border-color: rgba(205, 127, 50, 0.4);
    background: rgba(205, 127, 50, 0.05);
}

.rank-number {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--accent-orange);
    text-align: center;
    font-family: 'Montserrat', sans-serif;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
}

.rank-number.rank-first {
    color: #FFD700;
}

.rank-number.rank-second {
    color: #C0C0C0;
}

.rank-number.rank-third {
    color: #CD7F32;
}

.participant-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

.participant-avatar {
    width: 45px;
    height: 45px;
    min-width: 45px;
    min-height: 45px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.participant-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-initials {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1rem;
    font-family: 'Montserrat', sans-serif;
}

.participant-details {
    flex: 1;
    min-width: 0;
}

.participant-name {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.375rem;
    font-family: 'Montserrat', sans-serif;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.participant-stats {
    display: flex;
    gap: 1rem;
    font-size: 0.8125rem;
    color: var(--text-secondary);
    font-family: 'Montserrat', sans-serif;
    flex-wrap: wrap;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-weight: 500;
}

.stat-item i {
    color: var(--accent-orange);
    font-size: 0.75rem;
}

.today-progress {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    width: 100%;
}

.today-points {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--accent-orange);
    text-align: left;
    padding: 0.5rem 0.75rem;
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.2);
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.today-points i {
    font-size: 0.875rem;
}

.today-goals {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}

.goal-progress-item {
    display: grid;
    grid-template-columns: 80px 1fr 100px;
    gap: 0.75rem;
    align-items: center;
    padding: 0.5rem;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.goal-label {
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.8125rem;
    font-weight: 600;
    font-family: 'Montserrat', sans-serif;
}

.goal-label i {
    font-size: 0.75rem;
    color: var(--accent-orange);
}

.goal-progress-bar {
    height: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    overflow: hidden;
    position: relative;
}

.goal-progress-fill {
    height: 100%;
    background: var(--accent-orange);
    transition: width 0.3s ease;
    border-radius: 4px;
}

.goal-value {
    color: var(--text-secondary);
    text-align: right;
    font-size: 0.8125rem;
    font-weight: 500;
    font-family: 'Montserrat', sans-serif;
    white-space: nowrap;
}

.loading-spinner-simple {
    position: relative;
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.spinner-dots {
    position: relative;
    width: 80px;
    height: 80px;
    animation: spinner-rotate 1s linear infinite;
    transform-origin: center center;
}

.spinner-dot {
    position: absolute;
    width: 10px;
    height: 10px;
    background: var(--accent-orange);
    border-radius: 50%;
    top: 50%;
    left: 50%;
    margin-left: -5px;
    margin-top: -5px;
}

/* Posicionar cada bolinha em um círculo perfeito */
.spinner-dot:nth-child(1) {
    transform: translate(0, -35px);
    opacity: 1;
}

.spinner-dot:nth-child(2) {
    transform: translate(24.75px, -24.75px);
    opacity: 0.875;
}

.spinner-dot:nth-child(3) {
    transform: translate(35px, 0);
    opacity: 0.75;
}

.spinner-dot:nth-child(4) {
    transform: translate(24.75px, 24.75px);
    opacity: 0.625;
}

.spinner-dot:nth-child(5) {
    transform: translate(0, 35px);
    opacity: 0.5;
}

.spinner-dot:nth-child(6) {
    transform: translate(-24.75px, 24.75px);
    opacity: 0.375;
}

.spinner-dot:nth-child(7) {
    transform: translate(-35px, 0);
    opacity: 0.25;
}

.spinner-dot:nth-child(8) {
    transform: translate(-24.75px, -24.75px);
    opacity: 0.125;
}

@keyframes spinner-rotate {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.error-message {
    text-align: center;
    padding: 2rem;
    color: #EF4444;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--text-secondary);
}

@media (max-width: 968px) {
    .participant-rank-item {
        grid-template-columns: 45px 1fr;
        gap: 0.875rem;
    }
    
    .today-progress {
        grid-column: 1 / -1;
        margin-top: 0.875rem;
        padding-top: 0.875rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .goal-progress-item {
        grid-template-columns: 70px 1fr 90px;
        gap: 0.625rem;
    }
    
    .progress-modal-body {
        padding: 1.25rem;
    }
    
    .progress-header {
        margin-bottom: 1rem;
        padding-bottom: 0.875rem;
    }
}

@media (max-width: 640px) {
    .progress-modal-content {
        width: 95%;
        max-height: 90vh;
    }
    
    .progress-modal-body {
        padding: 1rem;
    }
    
    .participant-rank-item {
        grid-template-columns: 1fr;
        padding: 0.875rem;
        gap: 0.75rem;
    }
    
    .rank-number {
        text-align: left;
        flex-direction: row;
        min-height: auto;
        justify-content: flex-start;
    }
    
    .participant-info {
        gap: 0.625rem;
    }
    
    .today-progress {
        margin-top: 0.75rem;
        padding-top: 0.75rem;
    }
    
    .goal-progress-item {
        grid-template-columns: 1fr;
        gap: 0.5rem;
        padding: 0.5rem;
    }
    
    .goal-value {
        text-align: left;
    }
    
    .progress-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .btn-refresh {
        width: 100%;
        justify-content: center;
    }
}

/* ========================================================================= */
/*       CHALLENGE MODAL - ESTILO FOODS_MANAGEMENT_NEW.PHP                  */
/* ========================================================================= */

/* Modal principal - estilo food-edit-modal */
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

/* Overlay separado - igual foods_management_new para blur mais rápido */
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

/* Botão X - copiado do sleep-modal-close do foods_management_new */
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

/* Form Groups */
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

/* Asteriscos laranja apenas para campos obrigatórios */
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

/* Wrapper para input de data com ícone customizado */
.date-input-wrapper-modern {
    position: relative;
    display: flex;
    align-items: center;
}

.custom-datepicker {
    cursor: pointer;
    position: relative;
    padding-right: 2.75rem !important;
    flex: 1;
}

.custom-datepicker:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
}

/* Botão de ícone de calendário - círculo laranja pequeno */
.date-icon-btn {
    position: absolute;
    right: 0.5rem;
    width: 1.75rem;
    height: 1.75rem;
    min-width: 1.75rem;
    min-height: 1.75rem;
    border-radius: 50%;
    background: rgba(255, 107, 0, 0.15);
    border: 1px solid var(--accent-orange);
    color: var(--accent-orange);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    margin: 0;
    transition: all 0.3s ease;
    font-size: 0.75rem;
    z-index: 10;
}

.date-icon-btn:hover {
    background: rgba(255, 107, 0, 0.25);
    border-color: #FF8533;
    transform: scale(1.1);
    color: #FF8533;
}

.date-icon-btn:active {
    transform: scale(0.95);
}

.date-icon-btn i {
    margin: 0;
    line-height: 1;
}

/* ========================================================================= */
/*       FLATPICKR - GLASSMORPHISM TEMA DARK + LARANJA                       */
/* ========================================================================= */

/* Esconder seta que aponta para o input */
.flatpickr-calendar::before,
.flatpickr-calendar::after,
.flatpickr-calendar.arrowTop::before,
.flatpickr-calendar.arrowTop::after,
.flatpickr-calendar.arrowBottom::before,
.flatpickr-calendar.arrowBottom::after {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    width: 0 !important;
    height: 0 !important;
    border: none !important;
    content: none !important;
}

.flatpickr-calendar {
    background: rgba(20, 20, 20, 0.95) !important;
    backdrop-filter: blur(20px) !important;
    -webkit-backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 12px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4) !important;
    font-family: 'Montserrat', sans-serif !important;
    padding: 1rem !important;
    width: 100% !important;
    max-width: 320px !important;
    overflow: visible !important;
    box-sizing: border-box !important;
}

/* Remover qualquer indicador de seta do Flatpickr */
.flatpickr-calendar.arrowTop,
.flatpickr-calendar.arrowBottom {
    margin-top: 0 !important;
    margin-bottom: 0 !important;
}

/* Garante que o título (mês/ano) fique SEMPRE centralizado */
.flatpickr-months {
    background: transparent !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    padding-bottom: 1rem !important;
    margin-bottom: 1rem !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    position: relative !important;
    width: 100% !important;
}

.flatpickr-month {
    color: var(--text-primary) !important;
    font-family: 'Montserrat', sans-serif !important;
    font-weight: 600 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    position: relative !important;
    width: 100% !important;
    flex: 0 0 auto !important;
}

/* Centraliza corretamente o container de MÊS + ANO */
.flatpickr-current-month {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    width: auto !important;
    padding: 0 !important;
    margin: 0 auto !important;
    color: var(--text-primary) !important;
    font-family: 'Montserrat', sans-serif !important;
    text-align: center !important;
    position: absolute !important;
    left: 50% !important;
    top: 50% !important;
    transform: translate(-50%, -50%) !important;
    z-index: 5 !important;
    gap: 0.25rem !important;
}

/* Ano menor - remover setas do input */
.flatpickr-current-month .cur-year {
    font-size: 0.75rem !important;
    font-weight: 600 !important;
    opacity: 0.6 !important;
    color: var(--text-secondary) !important;
    margin: 0 !important;
    padding: 0 !important;
    width: auto !important;
    text-align: center !important;
    display: block !important;
    line-height: 1.2 !important;
    /* Remover setas do input number */
    -moz-appearance: textfield !important;
    appearance: textfield !important;
}

/* Remover setas do input number do ano */
.flatpickr-current-month .cur-year::-webkit-outer-spin-button,
.flatpickr-current-month .cur-year::-webkit-inner-spin-button {
    -webkit-appearance: none !important;
    margin: 0 !important;
    display: none !important;
}

/* Remover setas dos inputs de número das metas */
.challenge-form-input[type="number"]::-webkit-outer-spin-button,
.challenge-form-input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.challenge-form-input[type="number"],
input[type="number"] {
    -moz-appearance: textfield;
}

/* Mês alinhado ao centro (modo static - texto, não dropdown) */
.flatpickr-current-month .cur-month {
    font-size: 1rem !important;
    font-weight: 700 !important;
    color: var(--text-primary) !important;
    font-family: 'Montserrat', sans-serif !important;
    margin: 0 !important;
    padding: 0 !important;
    text-align: center !important;
    display: block !important;
}

/* Garantir centralização absoluta entre as setas */
.flatpickr-prev-month {
    left: 0 !important;
    position: absolute !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    z-index: 10 !important;
}

.flatpickr-next-month {
    right: 0 !important;
    position: absolute !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    z-index: 10 !important;
}

.flatpickr-prev-month,
.flatpickr-next-month {
    color: var(--accent-orange) !important;
    fill: var(--accent-orange) !important;
    border-radius: 8px !important;
    padding: 0.5rem !important;
    transition: all 0.3s ease !important;
    position: absolute !important;
    top: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 2rem !important;
    height: 2rem !important;
    cursor: pointer !important;
    z-index: 10 !important;
}

.flatpickr-prev-month {
    left: 0 !important;
    transform: translateY(-50%) !important;
}

.flatpickr-next-month {
    right: 0 !important;
    transform: translateY(-50%) !important;
}

.flatpickr-prev-month svg,
.flatpickr-next-month svg {
    width: 1rem !important;
    height: 1rem !important;
    display: block !important;
}

.flatpickr-prev-month:hover {
    background: rgba(255, 107, 0, 0.1) !important;
    transform: translateY(-50%) scale(1.1) !important;
}

.flatpickr-next-month:hover {
    background: rgba(255, 107, 0, 0.1) !important;
    transform: translateY(-50%) scale(1.1) !important;
}

/* Seta do mês anterior desativada quando estiver no mês atual */
.flatpickr-prev-month.flatpickr-disabled {
    color: rgba(255, 255, 255, 0.25) !important;
    fill: rgba(255, 255, 255, 0.25) !important;
    cursor: not-allowed !important;
    pointer-events: none !important;
    background: transparent !important;
    transform: translateY(-50%) !important; /* evita zoom */
}

.flatpickr-prev-month.flatpickr-disabled:hover {
    background: transparent !important;
    transform: translateY(-50%) !important; /* mantém sem zoom */
}

.flatpickr-weekdays {
    background: transparent !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
    padding-bottom: 0.5rem !important;
    margin-bottom: 0.5rem !important;
    display: flex !important;
    width: 100% !important;
    box-sizing: border-box !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

.flatpickr-weekday {
    color: var(--text-secondary) !important;
    font-family: 'Montserrat', sans-serif !important;
    font-weight: 600 !important;
    font-size: 0.75rem !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    flex: 1 1 calc((100% - 0.875rem) / 7) !important;
    text-align: center !important;
    padding: 0 !important;
    min-width: 0 !important;
    max-width: calc((100% - 0.875rem) / 7) !important;
    box-sizing: border-box !important;
    margin: 0 0.0625rem !important;
}

.flatpickr-days {
    padding: 0.5rem 0.25rem !important;
    width: 100% !important;
    box-sizing: border-box !important;
    overflow: visible !important;
}

.dayContainer {
    width: 100% !important;
    min-width: 0 !important;
    display: flex !important;
    flex-wrap: wrap !important;
    padding: 0 !important;
    margin: 0 !important;
    box-sizing: border-box !important;
    gap: 0 !important;
    overflow: visible !important;
}

.flatpickr-day {
    color: var(--text-primary) !important;
    font-family: 'Montserrat', sans-serif !important;
    font-weight: 600 !important;
    font-size: 0.875rem !important;
    border-radius: 8px !important;
    border: 1px solid transparent !important;
    transition: all 0.3s ease !important;
    margin: 0.0625rem !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex: 1 1 calc((100% - 0.875rem) / 7) !important;
    min-width: 0 !important;
    max-width: calc((100% - 0.875rem) / 7) !important;
    height: 2.5rem !important;
    line-height: 1 !important;
    overflow: visible !important;
    text-overflow: ellipsis !important;
    text-align: center !important;
    padding: 0 !important;
    box-sizing: border-box !important;
}

.flatpickr-day:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.2) !important;
    transform: scale(1.05) !important;
}

.flatpickr-day.today {
    border-color: var(--accent-orange) !important;
    background: rgba(255, 107, 0, 0.15) !important;
    color: var(--accent-orange) !important;
    font-weight: 700 !important;
}

.flatpickr-day.selected,
.flatpickr-day.startRange,
.flatpickr-day.endRange {
    background: var(--accent-orange) !important;
    border-color: var(--accent-orange) !important;
    color: white !important;
    font-weight: 700 !important;
    box-shadow: 0 4px 12px rgba(255, 107, 0, 0.4) !important;
}

.flatpickr-day.selected:hover,
.flatpickr-day.startRange:hover,
.flatpickr-day.endRange:hover {
    background: #FF8533 !important;
    border-color: #FF8533 !important;
    transform: scale(1.05) !important;
}

.flatpickr-day.inRange {
    background: rgba(255, 107, 0, 0.2) !important;
    border-color: rgba(255, 107, 0, 0.3) !important;
    color: var(--text-primary) !important;
}

.flatpickr-day.flatpickr-disabled,
.flatpickr-day.prevMonthDay,
.flatpickr-day.nextMonthDay {
    color: rgba(255, 255, 255, 0.2) !important;
    opacity: 0.4 !important;
}

.flatpickr-day.flatpickr-disabled:hover {
    background: transparent !important;
    border-color: transparent !important;
    transform: none !important;
    cursor: not-allowed !important;
}

.flatpickr-time {
    border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
    padding-top: 1rem !important;
    margin-top: 1rem !important;
}

.flatpickr-time input {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 8px !important;
    color: var(--text-primary) !important;
    font-family: 'Montserrat', sans-serif !important;
    font-weight: 600 !important;
}

.flatpickr-time input:hover {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: var(--accent-orange) !important;
}

.flatpickr-time .flatpickr-am-pm {
    color: var(--text-primary) !important;
    font-family: 'Montserrat', sans-serif !important;
    font-weight: 600 !important;
}

.flatpickr-time .flatpickr-am-pm:hover {
    background: rgba(255, 107, 0, 0.1) !important;
    color: var(--accent-orange) !important;
}

.challenge-form-textarea {
    resize: vertical;
    min-height: 80px;
    font-weight: 500;
}

.challenge-form-select {
    cursor: pointer;
}

.challenge-form-input:focus,
.challenge-form-textarea:focus,
.challenge-form-select:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
}

.challenge-form-input::placeholder,
.challenge-form-textarea::placeholder {
    color: var(--text-secondary);
    opacity: 0.5;
}

.challenge-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

/* Sections */
.challenge-section {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.challenge-section:first-of-type {
    margin-top: 0;
    padding-top: 0;
    border-top: none;
}

.challenge-section-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
    font-family: 'Montserrat', sans-serif;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.challenge-section-title i {
    font-size: 1rem;
    color: var(--accent-orange);
}

/* Goals Tags - Estilo igual source-tags */
.challenge-goals-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.goal-tag {
    cursor: pointer;
    transition: all 0.3s ease;
    user-select: none;
    padding: 0.625rem 1rem;
    border-radius: 10px;
    font-size: 0.8125rem;
    font-weight: 600;
    font-family: 'Montserrat', sans-serif;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    color: rgba(255, 255, 255, 0.5) !important;
    opacity: 0.7;
}

.goal-tag:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    opacity: 0.85;
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: rgba(255, 255, 255, 0.2) !important;
    color: rgba(255, 255, 255, 0.7) !important;
}

.goal-tag.active {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    opacity: 1;
    font-weight: 700;
}

/* Cores específicas para cada meta quando ativa */
.goal-tag.active.calories {
    background: rgba(255, 107, 0, 0.15) !important;
    border-color: rgba(255, 107, 0, 0.4) !important;
    color: #FF6B00 !important;
}

.goal-tag.active.water {
    background: rgba(59, 130, 246, 0.15) !important;
    border-color: rgba(59, 130, 246, 0.4) !important;
    color: #3B82F6 !important;
}

.goal-tag.active.exercise {
    background: rgba(34, 197, 94, 0.15) !important;
    border-color: rgba(34, 197, 94, 0.4) !important;
    color: #22C55E !important;
}

.goal-tag.active.sleep {
    background: rgba(168, 85, 247, 0.15) !important;
    border-color: rgba(168, 85, 247, 0.4) !important;
    color: #A855F7 !important;
}

.goal-tag.active.steps {
    background: rgba(236, 72, 153, 0.15) !important;
    border-color: rgba(236, 72, 153, 0.4) !important;
    color: #EC4899 !important;
}

.goal-tag i {
    font-size: 0.875rem;
}

/* Goal Inputs Container */
.challenge-goals-inputs {
    margin-top: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.goal-input-wrapper {
    display: none;
    flex-direction: column;
    gap: 0.5rem;
}

.goal-input-wrapper[style*="flex"],
.goal-input-wrapper.active {
    display: flex !important;
}

.goal-input-wrapper label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin: 0;
}

.goal-input-wrapper input {
    width: 100%;
}

/* Participants Section */
.participants-search {
    margin-bottom: 1rem;
}

.participants-list {
    max-height: 250px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.02);
}

.participant-tag {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border-radius: 10px;
    transition: all 0.3s ease;
    cursor: pointer;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    position: relative;
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

.participant-tag input[type="hidden"] {
    display: none;
}

/* Botões - Estilo view_user */
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
</style>

<div class="challenge-groups-page">
    <!-- Header Card -->
    <div class="header-card">
        <div class="header-title">
            <div>
                <h2><i class="fas fa-trophy"></i> Grupos de Desafio</h2>
                <p>Gerencie desafios e metas para seus pacientes</p>
            </div>
            <button class="btn-create-challenge" onclick="openCreateChallengeModal()" title="Criar Novo Desafio">
                <i class="fas fa-plus"></i>
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
            <div class="stat-card" onclick="filterByStatus('completed')">
                <div class="stat-number" id="stat-completed"><?php echo $stats['completed']; ?></div>
                <div class="stat-label">Concluídos</div>
            </div>
            <div class="stat-card" onclick="filterByStatus('scheduled')">
                <div class="stat-number" id="stat-scheduled"><?php echo $stats['scheduled']; ?></div>
                <div class="stat-label">Agendados</div>
            </div>
            <div class="stat-card" onclick="filterByStatus('inactive')">
                <div class="stat-number" id="stat-inactive"><?php echo $stats['inactive']; ?></div>
                <div class="stat-label">Inativos</div>
            </div>
            </div>
        </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <div class="filter-row">
            <input type="text" 
                   class="search-input" 
                   placeholder="Buscar desafios..." 
                   value="<?php echo htmlspecialchars($search_term); ?>"
                   onkeyup="handleSearch(event)">
            
            <div class="custom-select-wrapper" id="statusSelectWrapper">
                <div class="custom-select" id="statusSelect">
                    <div class="custom-select-trigger" onclick="toggleSelect('statusSelect')">
                        <span class="custom-select-value"><?php 
                            echo $status_filter === 'active' ? 'Ativos' : 
                                ($status_filter === 'completed' ? 'Concluídos' : 
                                ($status_filter === 'scheduled' ? 'Agendados' : 
                                ($status_filter === 'inactive' ? 'Inativos' : 'Todos os Status'))); 
                        ?></span>
                        <i class="fas fa-chevron-down"></i>
            </div>
                    <div class="custom-select-options">
                        <div class="custom-select-option <?php echo $status_filter === '' ? 'selected' : ''; ?>" 
                             onclick="selectStatus('')">Todos os Status</div>
                        <div class="custom-select-option <?php echo $status_filter === 'active' ? 'selected' : ''; ?>" 
                             onclick="selectStatus('active')">Ativos</div>
                        <div class="custom-select-option <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>" 
                             onclick="selectStatus('scheduled')">Agendados</div>
                        <div class="custom-select-option <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>" 
                             onclick="selectStatus('completed')">Concluídos</div>
                        <div class="custom-select-option <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>" 
                             onclick="selectStatus('inactive')">Inativos</div>
                </div>
                </div>
                </div>
            </div>
        </div>

    <!-- Groups Grid -->
    <?php if (empty($challenge_groups)): ?>
                <div class="empty-state">
                        <i class="fas fa-trophy"></i>
            <h3>Nenhum desafio encontrado</h3>
            <p>Crie seu primeiro grupo de desafio para começar a motivar seus pacientes</p>
                </div>
            <?php else: ?>
        <div class="challenge-groups-grid">
            <?php foreach ($challenge_groups as $group): ?>
                <?php
                $start_date = new DateTime($group['start_date']);
                $end_date = new DateTime($group['end_date']);
                $today = new DateTime();
                $status_class = $group['status'];
                
                // Buscar metas do grupo (do campo JSON)
                $goals_json = $group['goals'] ?? '[]';
                $goals = json_decode($goals_json, true) ?: [];
                ?>
                <div class="challenge-group-card" onclick="viewChallenge(<?php echo $group['id']; ?>)">
                    <div class="group-card-header">
                        <h3 class="group-name"><?php echo htmlspecialchars($group['name']); ?></h3>
                        <div class="toggle-switch-wrapper" onclick="event.stopPropagation()">
                            <?php
                            $is_active = $group['status'] === 'active';
                            ?>
                            <label class="toggle-switch">
                                <input type="checkbox" 
                                       class="toggle-switch-input" 
                                       <?php echo $is_active ? 'checked' : ''; ?>
                                       onchange="toggleChallengeStatus(<?php echo $group['id']; ?>, '<?php echo $group['status']; ?>', this)"
                                       data-challenge-id="<?php echo $group['id']; ?>"
                                       data-current-status="<?php echo $group['status']; ?>">
                                <span class="toggle-switch-slider"></span>
                            </label>
                            <span class="toggle-switch-label" style="color: <?php echo $is_active ? '#22C55E' : '#EF4444'; ?>; font-weight: <?php echo $is_active ? '700' : '600'; ?>;"><?php echo $is_active ? 'Ativo' : 'Inativo'; ?></span>
                        </div>
                    </div>
                    
                    <?php if (!empty($group['description'])): ?>
                        <p class="group-description"><?php echo htmlspecialchars($group['description']); ?></p>
                    <?php endif; ?>
                    
                    <div class="group-info">
                        <div class="group-info-item">
                                    <i class="fas fa-users"></i>
                            <span><?php echo $group['member_count']; ?> participantes</span>
                                    </div>
                        <?php if (!empty($goals)): ?>
                        <div class="group-info-item">
                            <i class="fas fa-bullseye"></i>
                            <span><?php echo count($goals); ?> meta(s)</span>
                                </div>
                        <?php endif; ?>
                        <div class="group-info-item">
                            <span><?php echo $start_date->format('d/m/Y'); ?> - <?php echo $end_date->format('d/m/Y'); ?></span>
                                    </div>
                                </div>
                    
                    <div class="group-card-actions" onclick="event.stopPropagation()">
                        <button class="btn-action btn-view" onclick="viewChallengeProgress(<?php echo $group['id']; ?>)" title="Ver Progresso">
                            <i class="fas fa-chart-line"></i> Progresso
                        </button>
                        <button class="btn-action btn-edit" onclick="editChallenge(<?php echo $group['id']; ?>)">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                        <button class="btn-action btn-delete" onclick="deleteChallenge(<?php echo $group['id']; ?>)">
                            <i class="fas fa-trash"></i> Excluir
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

<!-- Modal Create/Edit Challenge - Estilo view_user -->
<div id="challengeModal" class="challenge-edit-modal">
    <div class="challenge-edit-overlay" onclick="closeChallengeModal()"></div>
    <div class="challenge-edit-content">
        <button class="sleep-modal-close" onclick="closeChallengeModal()" type="button">
            <i class="fas fa-times"></i>
        </button>
        <div class="challenge-edit-header">
            <h3 id="modalTitle">Criar Novo Desafio</h3>
        </div>
        
        <div class="challenge-edit-body">
            <form id="challengeForm">
                <input type="hidden" id="challengeId" name="challenge_id" value="">
                
                <!-- Nome do Desafio -->
                <div class="challenge-form-group">
                    <label for="challengeName">Nome do Desafio</label>
                    <input type="text" id="challengeName" name="name" class="challenge-form-input" required 
                           placeholder="Ex: Desafio de Verão 2025">
                </div>
                
                <!-- Descrição -->
                <div class="challenge-form-group">
                    <label for="challengeDescription">Descrição</label>
                    <textarea id="challengeDescription" name="description" class="challenge-form-textarea" 
                              placeholder="Descreva o objetivo e regras do desafio"></textarea>
                </div>
                
                <!-- Datas -->
                <div class="challenge-form-row">
                    <div class="challenge-form-group">
                        <label for="startDate">Data de Início</label>
                        <div class="date-input-wrapper-modern">
                            <input type="text" id="startDate" name="start_date" class="challenge-form-input custom-datepicker" placeholder="dd/mm/aaaa" maxlength="10" required>
                            <button type="button" class="date-icon-btn" onclick="document.getElementById('startDate')._flatpickr?.open();">
                                <i class="fas fa-calendar-alt"></i>
                            </button>
                    </div>
                    </div>
                    <div class="challenge-form-group">
                        <label for="endDate">Data de Fim</label>
                        <div class="date-input-wrapper-modern">
                            <input type="text" id="endDate" name="end_date" class="challenge-form-input custom-datepicker" placeholder="dd/mm/aaaa" maxlength="10" required>
                            <button type="button" class="date-icon-btn" onclick="document.getElementById('endDate')._flatpickr?.open();">
                                <i class="fas fa-calendar-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Status será definido automaticamente pelo sistema -->
                <input type="hidden" id="challengeStatus" name="status" value="scheduled">
                
                <!-- Metas do Desafio -->
                <div class="challenge-section">
                    <h3 class="challenge-section-title">
                        <i class="fas fa-bullseye"></i> Metas do Desafio
                    </h3>
                    <div class="challenge-goals-tags">
                        <span class="goal-tag calories" data-goal="calories">
                            <i class="fas fa-fire"></i>
                            <span>Calorias</span>
                        </span>
                        <span class="goal-tag water" data-goal="water">
                            <i class="fas fa-tint"></i>
                            <span>Água</span>
                        </span>
                        <span class="goal-tag exercise" data-goal="exercise">
                            <i class="fas fa-dumbbell"></i>
                            <span>Exercício</span>
                        </span>
                        <span class="goal-tag sleep" data-goal="sleep">
                            <i class="fas fa-bed"></i>
                            <span>Sono</span>
                        </span>
                </div>
                
                    <!-- Inputs de valores das metas -->
                    <div class="challenge-goals-inputs">
                        <div class="goal-input-wrapper" id="goal_calories_input" style="display: none;">
                            <label>Calorias (kcal/dia)</label>
                            <input type="number" id="goal_calories_value" name="goal_calories_value" 
                                   class="challenge-form-input" min="0" step="1" placeholder="Ex: 2000">
                        </div>
                        <div class="goal-input-wrapper" id="goal_water_input" style="display: none;">
                            <label>Água (ml/dia)</label>
                            <input type="number" id="goal_water_value" name="goal_water_value" 
                                   class="challenge-form-input" min="0" step="50" placeholder="Ex: 2000">
                        </div>
                        <div class="goal-input-wrapper" id="goal_exercise_input" style="display: none;">
                            <label>Exercício (min/dia)</label>
                            <input type="number" id="goal_exercise_value" name="goal_exercise_value" 
                                   class="challenge-form-input" min="0" step="5" placeholder="Ex: 30">
                        </div>
                        <div class="goal-input-wrapper" id="goal_sleep_input" style="display: none;">
                            <label>Sono (horas/dia)</label>
                            <input type="number" id="goal_sleep_value" name="goal_sleep_value" 
                                   class="challenge-form-input" min="0" max="24" step="0.5" placeholder="Ex: 8">
                        </div>
                    </div>
                </div>
                
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
                                $bgColor = 'rgba(255, 107, 0, 0.1)'; // Cor padrão

                                if (!empty($user['profile_image_filename'])) {
                                    // Verificar primeiro a imagem original (prioridade)
                                    $original_path_on_server = APP_ROOT_PATH . '/assets/images/users/' . $user['profile_image_filename'];
                                    if (file_exists($original_path_on_server)) {
                                        $avatar_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($user['profile_image_filename']);
                                        $has_photo = true;
                                    } else {
                                        // Fallback: verificar thumbnail
                                        $thumb_filename = 'thumb_' . $user['profile_image_filename'];
                                        $thumb_path_on_server = APP_ROOT_PATH . '/assets/images/users/' . $thumb_filename;
                                        if (file_exists($thumb_path_on_server)) {
                                            $avatar_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($thumb_filename);
                                            $has_photo = true;
                                        }
                                    }
                                }

                                // SE NÃO TEM FOTO, GERA AS INICIAIS E COR
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
                                    // Gerar cor escura para bom contraste com texto branco
                                    $hash = md5($user['name']);
                                    $r = hexdec(substr($hash, 0, 2)) % 156 + 50;  // 50-205
                                    $g = hexdec(substr($hash, 2, 2)) % 156 + 50;  // 50-205
                                    $b = hexdec(substr($hash, 4, 2)) % 156 + 50;  // 50-205
                                    // Garantir que pelo menos um canal seja escuro
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
            <button type="button" class="btn-cancel" onclick="closeChallengeModal()">Cancelar</button>
            <button type="button" class="btn-save" onclick="saveChallenge()">
                <i class="fas fa-save"></i> Salvar Desafio
            </button>
        </div>
    </div>
</div>

<!-- Modal de Progresso do Desafio -->
<div id="progressModal" class="challenge-edit-modal">
    <div class="challenge-edit-overlay" onclick="closeProgressModal()"></div>
    <div class="progress-modal-content" id="progressModalBody">
        <!-- Conteúdo será inserido dinamicamente -->
    </div>
</div>

<script>
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

// Select status filter
function selectStatus(status) {
    const url = new URL(window.location);
    if (status) {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    url.searchParams.delete('page'); // Reset to page 1
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
        url.searchParams.delete('page'); // Reset to page 1
        window.location.href = url.toString();
    }
}

// Função para aplicar máscara de data (dd/mm/aaaa)
function applyDateMask(input) {
    input.addEventListener('input', function(e) {
        let value = this.value.replace(/\D/g, ''); // Remove tudo que não é número
        const maxLength = 8; // Máximo de 8 dígitos (ddmmyyyy)
        
        // Limitar a 8 dígitos
        if (value.length > maxLength) {
            value = value.substring(0, maxLength);
        }
        
        // Formatar com barras
        let formattedValue = '';
        if (value.length > 0) {
            formattedValue = value.substring(0, 2); // Dia
            if (value.length > 2) {
                formattedValue += '/' + value.substring(2, 4); // Mês
            }
            if (value.length > 4) {
                formattedValue += '/' + value.substring(4, 8); // Ano
            }
        }
        
        this.value = formattedValue;
    });
    
    // Prevenir digitação de caracteres não numéricos (exceto controle)
    input.addEventListener('keydown', function(e) {
        // Permitir teclas de controle (backspace, delete, tab, etc)
        if (e.key === 'Backspace' || e.key === 'Delete' || e.key === 'Tab' || 
            e.key === 'ArrowLeft' || e.key === 'ArrowRight' || 
            e.key === 'ArrowUp' || e.key === 'ArrowDown' ||
            e.ctrlKey || e.metaKey) {
            return;
        }
        
        // Permitir apenas números
        if (!/^[0-9]$/.test(e.key)) {
            e.preventDefault();
        }
    });
    
    // Limitar comprimento máximo no paste
    input.addEventListener('paste', function(e) {
        e.preventDefault();
        const pastedText = (e.clipboardData || window.clipboardData).getData('text');
        const numbersOnly = pastedText.replace(/\D/g, '').substring(0, 8);
        
        // Formatar como data
        let formattedValue = '';
        if (numbersOnly.length > 0) {
            formattedValue = numbersOnly.substring(0, 2);
            if (numbersOnly.length > 2) {
                formattedValue += '/' + numbersOnly.substring(2, 4);
            }
            if (numbersOnly.length > 4) {
                formattedValue += '/' + numbersOnly.substring(4, 8);
            }
        }
        
        this.value = formattedValue;
    });
}

// Inicializar Flatpickr
function initFlatpickr() {
    // Verificar se os inputs existem
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    
    if (!startDateInput || !endDateInput) {
        console.warn('Inputs de data não encontrados. Flatpickr não será inicializado.');
        return;
    }
    
    // Remover instâncias existentes antes de criar novas
    // Abordagem mais defensiva: apenas tentar destruir se realmente existir e tiver o método
    function safeDestroyFlatpickr(input) {
        if (!input) return;
        
        // Verificar se há uma instância do Flatpickr
        if (!input._flatpickr) return;
        
        try {
            const fp = input._flatpickr;
            
            // Tentar destruir apenas se o método existir
            if (fp && typeof fp.destroy === 'function') {
                // Chamar destroy ANTES de limpar a referência
                fp.destroy();
            }
        } catch (e) {
            // Ignorar erros - a instância pode já estar destruída
            console.warn('Erro ao destruir Flatpickr (pode ser ignorado):', e);
        } finally {
            // Limpar referência após tentar destruir
            input._flatpickr = null;
        }
    }
    
    // Destruir instâncias existentes
    safeDestroyFlatpickr(startDateInput);
    safeDestroyFlatpickr(endDateInput);
    
    // Limpar qualquer elemento do calendário que possa ter ficado no DOM
    // Fazer isso imediatamente após destruir as instâncias
    try {
        const existingCalendars = document.querySelectorAll('.flatpickr-calendar');
        existingCalendars.forEach(cal => {
            try {
                // Remover calendários órfãos (que não estão mais anexados a inputs)
                cal.remove();
            } catch (e) {
                // Ignorar erros
            }
        });
    } catch (e) {
        // Ignorar erros ao limpar calendários
    }
    
    // Configuração do Flatpickr
    const flatpickrOptions = {
        locale: 'pt',
        dateFormat: 'd/m/Y', // Formato brasileiro: 10/11/2025
        minDate: 'today',
        allowInput: true, // Permitir digitação manual
        clickOpens: true,
        animate: true,
        monthSelectorType: 'static', // Mês como texto, não dropdown
        defaultDate: null,
        static: false, // Não usar posicionamento estático
        appendTo: document.body, // Anexar ao body para melhor controle de posição
        positionElement: null, // Não usar elemento de posicionamento para evitar seta
        altInput: false, // Não criar input alternativo
        parseDate: function(datestr, format) {
            // Função para parsear datas no formato dd/mm/yyyy
            // Retornar null se não houver valor ou se o valor for muito curto (parcial)
            if (!datestr || typeof datestr !== 'string') return null;
            
            // Limpar espaços
            datestr = datestr.trim();
            
            // Se o valor for muito curto (menos de 8 caracteres), não é uma data válida
            // dd/mm/yyyy = 10 caracteres mínimos
            if (datestr.length < 8) return null;
            
            // Tentar formato d/m/Y
            if (format === 'd/m/Y' || !format) {
                const parts = datestr.split('/');
                // Deve ter exatamente 3 partes
                if (parts.length === 3) {
                    const day = parseInt(parts[0], 10);
                    const month = parseInt(parts[1], 10) - 1; // Mês é 0-indexed
                    const year = parseInt(parts[2], 10);
                    
                    // Validar se todos os valores são números válidos
                    if (isNaN(day) || isNaN(month) || isNaN(year)) return null;
                    
                    // Validar ranges
                    if (day < 1 || day > 31) return null;
                    if (month < 0 || month > 11) return null;
                    if (year < 2020 || year > 2100) return null;
                    
                    // Criar data e validar
                    const date = new Date(year, month, day);
                    if (isNaN(date.getTime())) return null;
                    
                    // Verificar se a data é válida (evitar datas inválidas como 31/02)
                    if (date.getFullYear() !== year || date.getMonth() !== month || date.getDate() !== day) {
                        return null;
                    }
                    
                    return date;
                }
            }
            return null;
        }
    };
    
    // Função para corrigir o header do calendário (ano em cima, mês embaixo)
    function fixHeader(instance) {
        const calendar = instance.calendarContainer;
        const monthContainer = calendar.querySelector('.flatpickr-current-month');
        
        if (!monthContainer) return;
        
        // Garantir ordem correta: ano em cima, mês embaixo
        // No modo static, o mês é .cur-month (texto), não .flatpickr-monthDropdown-months
        const year = monthContainer.querySelector('.cur-year');
        const month = monthContainer.querySelector('.cur-month');
        
        if (year && month) {
            // Limpar container e reorganizar: ano primeiro, mês depois
            monthContainer.innerHTML = "";
            monthContainer.appendChild(year);  // ano primeiro (vai ficar em cima)
            monthContainer.appendChild(month); // mês depois (fica embaixo)
        }
        
        // Aplicar estilos flexbox e centralização absoluta
        monthContainer.style.display = "flex";
        monthContainer.style.flexDirection = "column";
        monthContainer.style.alignItems = "center";
        monthContainer.style.justifyContent = "center";
        monthContainer.style.width = "auto";
        monthContainer.style.position = "absolute";
        monthContainer.style.left = "50%";
        monthContainer.style.top = "50%";
        monthContainer.style.transform = "translate(-50%, -50%)";
        monthContainer.style.gap = "0.25rem";
        
        // Remover setas do calendário
        calendar.classList.remove('arrowTop', 'arrowBottom');
        
        // Remover setas via CSS (pseudo-elements)
        const style = document.createElement('style');
        style.textContent = `
            .flatpickr-calendar.arrowTop::before,
            .flatpickr-calendar.arrowTop::after,
            .flatpickr-calendar.arrowBottom::before,
            .flatpickr-calendar.arrowBottom::after {
                display: none !important;
            }
        `;
        if (!document.head.querySelector('style[data-flatpickr-arrow-remover]')) {
            style.setAttribute('data-flatpickr-arrow-remover', 'true');
            document.head.appendChild(style);
        }
    }
    
    // Função para desativar seta do mês anterior quando estiver no mês atual
    function disablePastMonth(instance) {
        const prevBtn = instance.calendarContainer.querySelector(".flatpickr-prev-month");
        
        if (!prevBtn) return;
        
        const today = new Date();
        const currentMonth = today.getMonth();
        const currentYear = today.getFullYear();
        
        const selectedMonth = instance.currentMonth;
        const selectedYear = instance.currentYear;
        
        if (selectedYear === currentYear && selectedMonth === currentMonth) {
            prevBtn.classList.add("flatpickr-disabled");
        } else {
            prevBtn.classList.remove("flatpickr-disabled");
        }
    }
    
    // Inicializar para data de início
    if (startDateInput) {
        // Limpar valor se estiver parcial ou inválido antes de inicializar
        const startValue = startDateInput.value.trim();
        if (startValue && startValue.length < 8) {
            // Valor muito curto, limpar
            startDateInput.value = '';
        }
        
        // Aplicar máscara de data
        applyDateMask(startDateInput);
        
        const startPicker = flatpickr(startDateInput, {
            ...flatpickrOptions,
            onChange: function(selectedDates, dateStr, instance) {
                // Atualizar minDate do endDate para ser após startDate
                if (endDateInput && selectedDates.length > 0) {
                    const endDatePicker = endDateInput._flatpickr;
                    if (endDatePicker) {
                        const nextDay = new Date(selectedDates[0]);
                        nextDay.setDate(nextDay.getDate() + 1);
                        endDatePicker.set('minDate', nextDay);
                    }
                }
            },
            onValueUpdate: function(selectedDates, dateStr, instance) {
                // Quando o Flatpickr atualiza o valor (do calendário), garantir formato
                if (dateStr && selectedDates.length > 0) {
                    // Já está formatado pelo Flatpickr
                } else if (dateStr && !selectedDates.length) {
                    // Validar quando o usuário digita manualmente
                    const parts = dateStr.split('/');
                    if (parts.length === 3 && parts[0].length === 2 && parts[1].length === 2 && parts[2].length === 4) {
                        const day = parseInt(parts[0], 10);
                        const month = parseInt(parts[1], 10) - 1;
                        const year = parseInt(parts[2], 10);
                        if (day >= 1 && day <= 31 && month >= 0 && month <= 11 && year >= 2020) {
                            const date = new Date(year, month, day);
                            if (!isNaN(date.getTime()) && date.getFullYear() === year && date.getMonth() === month && date.getDate() === day) {
                                instance.setDate(date, false);
                            }
                        }
                    }
                }
            },
            onReady: function(_, __, instance) {
                fixHeader(instance);
                disablePastMonth(instance);
            },
            onOpen: function(_, __, instance) {
                setTimeout(() => {
                    fixHeader(instance);
                    disablePastMonth(instance);
                }, 50);
            },
            onMonthChange: function(_, __, instance) {
                setTimeout(() => {
                    fixHeader(instance);
                    disablePastMonth(instance);
                }, 10);
            }
        });
        
        // Adicionar evento de blur para validar quando o usuário sair do campo
        startDateInput.addEventListener('blur', function() {
            const value = this.value.trim();
            if (value) {
                const parts = value.split('/');
                if (parts.length === 3 && parts[0].length === 2 && parts[1].length === 2 && parts[2].length === 4) {
                    const day = parseInt(parts[0], 10);
                    const month = parseInt(parts[1], 10);
                    const year = parseInt(parts[2], 10);
                    if (day >= 1 && day <= 31 && month >= 1 && month <= 12 && year >= 2020) {
                        const date = new Date(year, month - 1, day);
                        if (!isNaN(date.getTime()) && date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day) {
                            startPicker.setDate(date, false);
                        }
                    }
                }
            }
        });
    }
    
    // Inicializar para data de fim
    if (endDateInput) {
        // Limpar valor se estiver parcial ou inválido antes de inicializar
        const endValue = endDateInput.value.trim();
        if (endValue && endValue.length < 8) {
            // Valor muito curto, limpar
            endDateInput.value = '';
        }
        
        // Aplicar máscara de data
        applyDateMask(endDateInput);
        
        const endPicker = flatpickr(endDateInput, {
            ...flatpickrOptions,
            onValueUpdate: function(selectedDates, dateStr, instance) {
                // Quando o Flatpickr atualiza o valor (do calendário), garantir formato
                if (dateStr && selectedDates.length > 0) {
                    // Já está formatado pelo Flatpickr
                } else if (dateStr && !selectedDates.length) {
                    // Validar quando o usuário digita manualmente
                    const parts = dateStr.split('/');
                    if (parts.length === 3 && parts[0].length === 2 && parts[1].length === 2 && parts[2].length === 4) {
                        const day = parseInt(parts[0], 10);
                        const month = parseInt(parts[1], 10) - 1;
                        const year = parseInt(parts[2], 10);
                        if (day >= 1 && day <= 31 && month >= 0 && month <= 11 && year >= 2020) {
                            const date = new Date(year, month, day);
                            if (!isNaN(date.getTime()) && date.getFullYear() === year && date.getMonth() === month && date.getDate() === day) {
                                instance.setDate(date, false);
                            }
                        }
                    }
                }
            },
            onReady: function(_, __, instance) {
                fixHeader(instance);
                disablePastMonth(instance);
            },
            onOpen: function(_, __, instance) {
                setTimeout(() => {
                    fixHeader(instance);
                    disablePastMonth(instance);
                }, 50);
            },
            onMonthChange: function(_, __, instance) {
                setTimeout(() => {
                    fixHeader(instance);
                    disablePastMonth(instance);
                }, 10);
            }
        });
        
        // Adicionar evento de blur para validar quando o usuário sair do campo
        endDateInput.addEventListener('blur', function() {
            const value = this.value.trim();
            if (value) {
                const parts = value.split('/');
                if (parts.length === 3 && parts[0].length === 2 && parts[1].length === 2 && parts[2].length === 4) {
                    const day = parseInt(parts[0], 10);
                    const month = parseInt(parts[1], 10);
                    const year = parseInt(parts[2], 10);
                    if (day >= 1 && day <= 31 && month >= 1 && month <= 12 && year >= 2020) {
                        const date = new Date(year, month - 1, day);
                        if (!isNaN(date.getTime()) && date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day) {
                            endPicker.setDate(date, false);
                        }
                    }
                }
            }
        });
    }
}

// NÃO inicializar Flatpickr ao carregar a página
// O Flatpickr será inicializado apenas quando o modal for aberto
// document.addEventListener('DOMContentLoaded', function() {
//     setTimeout(initFlatpickr, 100);
// });

// Filter participants
function filterParticipants() {
    const searchTerm = document.getElementById('participantSearch').value.toLowerCase();
    const items = document.querySelectorAll('.participant-tag');
    
    items.forEach(item => {
        const name = item.getAttribute('data-name');
        const email = item.getAttribute('data-email');
        
        if (name.includes(searchTerm) || email.includes(searchTerm)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

// Toggle goal tags
document.addEventListener('DOMContentLoaded', function() {
    // Goal tags click handlers
    const goalTags = document.querySelectorAll('.goal-tag');
    goalTags.forEach(tag => {
        tag.addEventListener('click', function() {
            const goal = this.dataset.goal;
            const inputWrapper = document.getElementById(`goal_${goal}_input`);
            
            // Toggle active class
            this.classList.toggle('active');
            
            // Show/hide input
            if (this.classList.contains('active')) {
                if (inputWrapper) {
                    inputWrapper.style.display = 'flex';
                    const input = inputWrapper.querySelector('input');
                    if (input) {
                        input.required = true;
                    }
                }
            } else {
                if (inputWrapper) {
                    inputWrapper.style.display = 'none';
                    const input = inputWrapper.querySelector('input');
                    if (input) {
                        input.required = false;
                        input.value = '';
                    }
                }
            }
        });
    });
    
    // Participant tags click handlers
    const participantTags = document.querySelectorAll('.participant-tag');
    participantTags.forEach(tag => {
        tag.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('selected');
            
            const hiddenInput = this.querySelector('input[type="hidden"]');
            if (hiddenInput) {
                if (this.classList.contains('selected')) {
                    hiddenInput.name = 'participants[]';
                } else {
                    hiddenInput.name = '';
                }
            }
        });
    });
    
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

// Modal functions
function openCreateChallengeModal() {
    // Limpar formulário para criar novo desafio
    document.getElementById('modalTitle').textContent = 'Criar Novo Desafio';
    document.getElementById('challengeForm').reset();
    document.getElementById('challengeId').value = '';
    
    // Limpar campos de data explicitamente e garantir que estão vazios
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    if (startDateInput) {
        startDateInput.value = '';
        startDateInput.removeAttribute('data-flatpickr');
        // Remover qualquer instância do Flatpickr que possa existir
        if (startDateInput._flatpickr) {
            try {
                startDateInput._flatpickr.destroy();
            } catch (e) {
                // Ignorar erros
            }
            startDateInput._flatpickr = null;
        }
    }
    if (endDateInput) {
        endDateInput.value = '';
        endDateInput.removeAttribute('data-flatpickr');
        // Remover qualquer instância do Flatpickr que possa existir
        if (endDateInput._flatpickr) {
            try {
                endDateInput._flatpickr.destroy();
            } catch (e) {
                // Ignorar erros
            }
            endDateInput._flatpickr = null;
        }
    }
    
    // Limpar qualquer calendário do DOM antes de abrir o modal
    try {
        const existingCalendars = document.querySelectorAll('.flatpickr-calendar');
        existingCalendars.forEach(cal => {
            try {
                cal.remove();
            } catch (e) {
                // Ignorar erros
            }
        });
    } catch (e) {
        // Ignorar erros
    }
    
    // Reset goal tags
    document.querySelectorAll('.goal-tag').forEach(tag => {
        tag.classList.remove('active');
        const goal = tag.dataset.goal;
        const inputWrapper = document.getElementById(`goal_${goal}_input`);
        if (inputWrapper) {
            inputWrapper.style.display = 'none';
            const input = inputWrapper.querySelector('input');
            if (input) {
                input.required = false;
                input.value = '';
            }
        }
    });
    
    // Reset participant tags
    document.querySelectorAll('.participant-tag').forEach(tag => {
        tag.classList.remove('selected');
        const hiddenInput = tag.querySelector('input[type="hidden"]');
        if (hiddenInput) {
            hiddenInput.removeAttribute('name');
        }
    });
    
    // Status padrão: scheduled (já definido no HTML)
    const statusInput = document.getElementById('challengeStatus');
    if (statusInput) {
        statusInput.value = 'scheduled';
    }
    
    // Abrir modal
    openChallengeModal();
}

function openChallengeModal() {
    // Função genérica para abrir o modal (usada tanto para criar quanto editar)
    const modal = document.getElementById('challengeModal');
    if (!modal) {
        console.error('Modal não encontrado');
        return;
    }
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Aguardar um pouco para garantir que o modal está visível antes de inicializar Flatpickr
    setTimeout(function() {
        try {
            initFlatpickr();
        } catch (e) {
            console.error('Erro ao inicializar Flatpickr:', e);
        }
    }, 150);
}

function closeChallengeModal() {
    const modal = document.getElementById('challengeModal');
    if (modal) {
        modal.classList.remove('active');
    }
    document.body.style.overflow = '';
    
    // Destruir instâncias do Flatpickr ao fechar o modal para evitar conflitos
    // Usar a mesma lógica defensiva da função initFlatpickr
    function safeDestroyFlatpickr(input) {
        if (!input) return;
        
        // Verificar se há uma instância do Flatpickr
        if (!input._flatpickr) return;
        
        try {
            const fp = input._flatpickr;
            
            // Tentar destruir apenas se o método existir
            if (fp && typeof fp.destroy === 'function') {
                // Chamar destroy ANTES de limpar a referência
                fp.destroy();
            }
        } catch (e) {
            // Ignorar erros - a instância pode já estar destruída
        } finally {
            // Limpar referência após tentar destruir
            input._flatpickr = null;
        }
    }
    
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    
    safeDestroyFlatpickr(startDateInput);
    safeDestroyFlatpickr(endDateInput);
    
    // Limpar qualquer elemento do calendário que possa ter ficado no DOM
    try {
        const existingCalendars = document.querySelectorAll('.flatpickr-calendar');
        existingCalendars.forEach(cal => {
            try {
                cal.remove();
            } catch (e) {
                // Ignorar erros
            }
        });
    } catch (e) {
        // Ignorar erros ao limpar calendários
    }
}

function editChallenge(id) {
    if (!id) {
        alert('Erro: ID do desafio não fornecido');
        return;
    }
    
    // Buscar dados do desafio via AJAX
    fetch('ajax_challenge_groups.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get',
            challenge_id: id
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success && result.challenge) {
            const challenge = result.challenge;
            
            // Preencher campos do formulário
            document.getElementById('challengeId').value = challenge.id;
            document.getElementById('challengeName').value = challenge.name || '';
            document.getElementById('challengeDescription').value = challenge.description || '';
            
            // IMPORTANTE: Preservar o status atual do desafio ao editar
            const statusInput = document.getElementById('challengeStatus');
            if (statusInput && challenge.status) {
                statusInput.value = challenge.status;
            } else if (statusInput) {
                // Se não houver status, manter o padrão 'scheduled'
                statusInput.value = 'scheduled';
            }
            
            // Converter datas de Y-m-d para d/m/Y
            if (challenge.start_date) {
                const startDate = new Date(challenge.start_date + 'T00:00:00');
                const startDay = String(startDate.getDate()).padStart(2, '0');
                const startMonth = String(startDate.getMonth() + 1).padStart(2, '0');
                const startYear = startDate.getFullYear();
                document.getElementById('startDate').value = `${startDay}/${startMonth}/${startYear}`;
            }
            
            if (challenge.end_date) {
                const endDate = new Date(challenge.end_date + 'T00:00:00');
                const endDay = String(endDate.getDate()).padStart(2, '0');
                const endMonth = String(endDate.getMonth() + 1).padStart(2, '0');
                const endYear = endDate.getFullYear();
                document.getElementById('endDate').value = `${endDay}/${endMonth}/${endYear}`;
            }
            
            // Preencher metas
            const goals = challenge.goals || [];
            document.querySelectorAll('.goal-tag').forEach(tag => {
                tag.classList.remove('active');
                const goalType = tag.dataset.goal;
                const goalInput = document.getElementById(`goal_${goalType}_input`);
                if (goalInput) {
                    goalInput.style.display = 'none';
                }
            });
            
            goals.forEach(goal => {
                const goalTag = document.querySelector(`.goal-tag[data-goal="${goal.type}"]`);
                if (goalTag) {
                    goalTag.classList.add('active');
                    const goalInput = document.getElementById(`goal_${goal.type}_input`);
                    if (goalInput) {
                        goalInput.style.display = 'flex';
                        const valueInput = document.getElementById(`goal_${goal.type}_value`);
                        if (valueInput) {
                            valueInput.value = goal.value || '';
                        }
                    }
                }
            });
            
            // Preencher participantes
            // O backend retorna 'participants', não 'member_ids'
            const participantIds = challenge.participants || challenge.member_ids || [];
            // Converter para strings para comparar corretamente
            const participantIdsStr = participantIds.map(id => String(id));
            
            console.log('Participantes do desafio:', participantIdsStr);
            
            // Primeiro, remover seleção de todos
            document.querySelectorAll('.participant-tag').forEach(tag => {
                tag.classList.remove('selected');
                const hiddenInput = tag.querySelector('input[type="hidden"]');
                if (hiddenInput) {
                    // Remover name para que não seja enviado se não estiver selecionado
                    hiddenInput.removeAttribute('name');
                }
            });
            
            // Depois, selecionar participantes que estão no desafio
            document.querySelectorAll('.participant-tag').forEach(tag => {
                const userId = tag.getAttribute('data-user-id');
                const hiddenInput = tag.querySelector('input[type="hidden"]');
                
                if (userId && participantIdsStr.includes(String(userId))) {
                    // Participante está no desafio - selecionar
                    tag.classList.add('selected');
                    if (hiddenInput) {
                        hiddenInput.setAttribute('name', 'participants[]');
                    }
                    console.log('Participante selecionado:', userId);
                } else {
                    // Participante não está no desafio - não selecionar
                    tag.classList.remove('selected');
                    if (hiddenInput) {
                        hiddenInput.removeAttribute('name');
                    }
                }
            });
            
            // Atualizar título do modal
            document.getElementById('modalTitle').textContent = 'Editar Desafio';
            
            // Abrir modal (já reinicializa o Flatpickr dentro dele)
            openChallengeModal();
            
            // Função auxiliar para definir data no Flatpickr com retry
            function setDateInFlatpickr(input, dateValue, retries = 5) {
                if (!input || !dateValue || retries <= 0) return;
                
                // Verificar se o Flatpickr está inicializado
                if (!input._flatpickr) {
                    // Tentar novamente após um pequeno delay
                    setTimeout(() => setDateInFlatpickr(input, dateValue, retries - 1), 100);
                    return;
                }
                
                try {
                    // Verificar se a instância é válida
                    if (!input._flatpickr || typeof input._flatpickr.setDate !== 'function') {
                        setTimeout(() => setDateInFlatpickr(input, dateValue, retries - 1), 100);
                        return;
                    }
                    
                    // Tentar parsear no formato d/m/Y
                    const parts = dateValue.split('/');
                    if (parts.length === 3) {
                        const day = parseInt(parts[0], 10);
                        const month = parseInt(parts[1], 10) - 1;
                        const year = parseInt(parts[2], 10);
                        
                        if (!isNaN(day) && !isNaN(month) && !isNaN(year) && 
                            day >= 1 && day <= 31 && month >= 0 && month <= 11 && year >= 2020) {
                            const date = new Date(year, month, day);
                            // Verificar se a data é válida (evitar datas inválidas como 31/02)
                            if (!isNaN(date.getTime()) && 
                                date.getFullYear() === year && 
                                date.getMonth() === month && 
                                date.getDate() === day) {
                                // Tentar setar a data
                                input._flatpickr.setDate(date, false);
                            }
                        }
                    }
                } catch (e) {
                    console.warn('Erro ao definir data no Flatpickr:', e);
                    // Tentar novamente se ainda houver retries
                    if (retries > 1) {
                        setTimeout(() => setDateInFlatpickr(input, dateValue, retries - 1), 100);
                    }
                }
            }
            
            // Aguardar que o Flatpickr seja inicializado antes de definir as datas
            // O openChallengeModal chama initFlatpickr após 150ms, então esperamos um pouco mais
            setTimeout(() => {
                const startDateInput = document.getElementById('startDate');
                const endDateInput = document.getElementById('endDate');
                
                // Definir datas usando a função auxiliar com retry
                if (startDateInput && startDateInput.value) {
                    setDateInFlatpickr(startDateInput, startDateInput.value);
                }
                
                if (endDateInput && endDateInput.value) {
                    setDateInFlatpickr(endDateInput, endDateInput.value);
                }
            }, 400);
            
        } else {
            alert('Erro ao carregar desafio: ' + (result.message || 'Desafio não encontrado'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao carregar desafio. Tente novamente.');
    });
}

function deleteChallenge(id) {
    if (!id) {
        alert('Erro: ID do desafio não fornecido');
        return;
    }
    
    if (!confirm('Tem certeza que deseja excluir este desafio? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    // Deletar desafio via AJAX
    fetch('ajax_challenge_groups.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'delete',
            challenge_id: id
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert(result.message || 'Desafio excluído com sucesso!');
            // Recarregar a página para atualizar a lista
            location.reload();
        } else {
            alert('Erro ao excluir desafio: ' + (result.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao excluir desafio. Tente novamente.');
    });
}

function viewChallenge(id) {
    // Para visualizar, vamos apenas abrir no modo de edição (readonly pode ser implementado depois)
    editChallenge(id);
}

function viewChallengeProgress(challengeId) {
    if (!challengeId) {
        alert('Erro: ID do desafio não fornecido');
        return;
    }
    
    // Abrir modal
    const modal = document.getElementById('progressModal');
    const modalBody = document.getElementById('progressModalBody');
    
    modal.classList.add('active');
    
    // Garantir que o overlay existe
    let overlay = modal.querySelector('.challenge-edit-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'challenge-edit-overlay';
        overlay.onclick = closeProgressModal;
        modal.insertBefore(overlay, modalBody);
    }
    
    // Mostrar loading com a mesma estrutura do modal de progresso completo
    modalBody.innerHTML = `
        <button class="sleep-modal-close" onclick="closeProgressModal()" type="button">
            <i class="fas fa-times"></i>
        </button>
        <div class="challenge-edit-header">
            <h3>Carregando Progresso...</h3>
        </div>
        <div class="challenge-edit-body" style="display: flex; align-items: center; justify-content: center; min-height: 400px;">
            <div class="loading-spinner-simple">
                <div class="spinner-dots">
                    <div class="spinner-dot"></div>
                    <div class="spinner-dot"></div>
                    <div class="spinner-dot"></div>
                    <div class="spinner-dot"></div>
                    <div class="spinner-dot"></div>
                    <div class="spinner-dot"></div>
                    <div class="spinner-dot"></div>
                    <div class="spinner-dot"></div>
                </div>
            </div>
        </div>
    `;
    
    // Buscar progresso via AJAX
    fetch('ajax_challenge_groups.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_progress',
            challenge_id: challengeId
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Erro na resposta do servidor: ' + response.status);
        }
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Resposta não é JSON:', text);
                throw new Error('Resposta do servidor não é JSON válido');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data && data.success) {
            displayChallengeProgress(data);
        } else {
            const errorMsg = data && data.message ? data.message : 'Erro desconhecido';
            modalBody.innerHTML = `<div class="error-message"><i class="fas fa-exclamation-circle"></i> ${errorMsg}</div>`;
        }
    })
    .catch(error => {
        console.error('Erro ao buscar progresso:', error);
        modalBody.innerHTML = '<div class="error-message"><i class="fas fa-exclamation-circle"></i> Erro ao carregar progresso. Tente novamente.</div>';
    });
}

function displayChallengeProgress(data) {
    const modal = document.getElementById('progressModal');
    const modalBody = document.getElementById('progressModalBody');
    const challenge = data.challenge;
    const participants = data.participants || [];
    const currentDate = data.current_date;
    const baseAssetUrl = '<?php echo BASE_ASSET_URL; ?>';
    
    // Garantir que o overlay existe (já deve existir, mas verificar)
    let overlay = modal.querySelector('.challenge-edit-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'challenge-edit-overlay';
        overlay.onclick = closeProgressModal;
        modal.insertBefore(overlay, modalBody);
    }
    
    // Formatar data
    const dateObj = new Date(currentDate + 'T00:00:00');
    const formattedDate = dateObj.toLocaleDateString('pt-BR');
    
    // Preparar metas para exibição
    const goalsMap = {};
    challenge.goals.forEach(goal => {
        goalsMap[goal.type] = goal.value;
    });
    
    let html = `
        <button class="sleep-modal-close" onclick="closeProgressModal()" type="button">
            <i class="fas fa-times"></i>
        </button>
        <div class="challenge-edit-header">
            <h3>Progresso: ${challenge.name}</h3>
        </div>
        <div class="progress-modal-body">
            <div class="progress-header">
                <div class="progress-date">
                    <i class="fas fa-calendar"></i>
                    Progresso de ${formattedDate}
                </div>
                <button class="btn-refresh" onclick="viewChallengeProgress(${challenge.id})" title="Atualizar">
                    <i class="fas fa-sync-alt"></i> Atualizar
                </button>
            </div>
            
            <div class="progress-ranking">
                <h4 class="ranking-title">
                    <i class="fas fa-trophy"></i> Ranking de Participantes
                </h4>
                <div class="participants-ranking-list">
    `;
    
    if (participants.length === 0) {
        html += '<div class="empty-state"><p>Nenhum participante encontrado ou sem progresso registrado.</p></div>';
    } else {
        participants.forEach(participant => {
            const rankClass = participant.rank === 1 ? 'rank-first' : participant.rank === 2 ? 'rank-second' : participant.rank === 3 ? 'rank-third' : '';
            
            // Avatar
            let avatarHtml = '';
            if (participant.profile_image) {
                avatarHtml = `<img src="${baseAssetUrl}/assets/images/users/${participant.profile_image}" alt="${participant.name}">`;
            } else {
                const nameParts = participant.name.split(' ');
                const initials = nameParts.length > 1 
                    ? (nameParts[0][0] + nameParts[nameParts.length - 1][0]).toUpperCase()
                    : participant.name.substring(0, 2).toUpperCase();
                const hash = participant.name.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
                const r = (hash % 156) + 50;
                const g = ((hash * 2) % 156) + 50;
                const b = ((hash * 3) % 156) + 50;
                const bgColor = `rgb(${r}, ${g}, ${b})`;
                avatarHtml = `<div class="avatar-initials" style="background-color: ${bgColor}">${initials}</div>`;
            }
            
            // Adicionar classe ao rank-number baseado na posição
            const rankNumberClass = participant.rank === 1 ? 'rank-first' : participant.rank === 2 ? 'rank-second' : participant.rank === 3 ? 'rank-third' : '';
            
            html += `
                <div class="participant-rank-item ${rankClass}">
                    <div class="rank-number ${rankNumberClass}">
                        #${participant.rank}
                    </div>
                    <div class="participant-info">
                        <div class="participant-avatar">${avatarHtml}</div>
                        <div class="participant-details">
                            <div class="participant-name">${participant.name}</div>
                            <div class="participant-stats">
                                <span class="stat-item">
                                    <i class="fas fa-star"></i> ${participant.total_points.toLocaleString('pt-BR')} pts
                                </span>
                                <span class="stat-item">
                                    <i class="fas fa-calendar-check"></i> ${participant.active_days} ${participant.active_days === 1 ? 'dia' : 'dias'}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="today-progress">
                        <div class="today-points">
                            <i class="fas fa-bolt"></i> ${participant.today.points} pts hoje
                        </div>
                        <div class="today-goals">
            `;
            
            // Progresso de hoje para cada meta
            if (goalsMap.calories) {
                const percentage = Math.min(100, (participant.today.calories / goalsMap.calories) * 100);
                html += `
                    <div class="goal-progress-item">
                        <span class="goal-label"><i class="fas fa-fire"></i> Calorias</span>
                        <div class="goal-progress-bar">
                            <div class="goal-progress-fill" style="width: ${percentage.toFixed(1)}%"></div>
                        </div>
                        <span class="goal-value">${Math.round(participant.today.calories)} / ${goalsMap.calories} kcal</span>
                    </div>
                `;
            }
            
            if (goalsMap.water) {
                const percentage = Math.min(100, (participant.today.water / goalsMap.water) * 100);
                html += `
                    <div class="goal-progress-item">
                        <span class="goal-label"><i class="fas fa-tint"></i> Água</span>
                        <div class="goal-progress-bar">
                            <div class="goal-progress-fill" style="width: ${percentage.toFixed(1)}%"></div>
                        </div>
                        <span class="goal-value">${Math.round(participant.today.water)} / ${goalsMap.water} ml</span>
                    </div>
                `;
            }
            
            if (goalsMap.exercise) {
                const percentage = Math.min(100, (participant.today.exercise / goalsMap.exercise) * 100);
                html += `
                    <div class="goal-progress-item">
                        <span class="goal-label"><i class="fas fa-dumbbell"></i> Exercício</span>
                        <div class="goal-progress-bar">
                            <div class="goal-progress-fill" style="width: ${percentage.toFixed(1)}%"></div>
                        </div>
                        <span class="goal-value">${Math.round(participant.today.exercise)} / ${goalsMap.exercise} min</span>
                    </div>
                `;
            }
            
            if (goalsMap.sleep) {
                const percentage = Math.min(100, (participant.today.sleep / goalsMap.sleep) * 100);
                html += `
                    <div class="goal-progress-item">
                        <span class="goal-label"><i class="fas fa-bed"></i> Sono</span>
                        <div class="goal-progress-bar">
                            <div class="goal-progress-fill" style="width: ${percentage.toFixed(1)}%"></div>
                        </div>
                        <span class="goal-value">${participant.today.sleep.toFixed(1)} / ${goalsMap.sleep} h</span>
                    </div>
                `;
            }
            
            html += `
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    html += `
                </div>
            </div>
        </div>
    `;
    
    modalBody.innerHTML = html;
}

function closeProgressModal() {
    const modal = document.getElementById('progressModal');
    modal.classList.remove('active');
}

function toggleChallengeStatus(id, currentStatus, toggleElement) {
    if (!id) {
        alert('Erro: ID do desafio não fornecido');
        // Reverter o toggle
        if (toggleElement) {
            toggleElement.checked = currentStatus === 'active';
            updateToggleLabel(toggleElement);
        }
        return;
    }
    
    // Usar o elemento passado ou encontrar
    const toggle = toggleElement || document.querySelector(`.toggle-switch-input[data-challenge-id="${id}"]`);
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
    
    // Atualizar status via AJAX (sem recarregar a página)
    fetch('ajax_challenge_groups.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'toggle_status',
            challenge_id: id,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Atualizar o atributo data-current-status para próximas mudanças
            toggle.setAttribute('data-current-status', newStatus);
            
            // Atualizar os stats no topo da página
            updateStats();
            
            // Não recarregar a página - tudo já foi atualizado visualmente
        } else {
            // Reverter o toggle em caso de erro
            toggle.checked = !isChecked;
            updateToggleLabel(toggle);
            alert('Erro ao atualizar status: ' + (result.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        // Reverter o toggle em caso de erro
        toggle.checked = !isChecked;
        updateToggleLabel(toggle);
        alert('Erro ao atualizar status. Tente novamente.');
    });
}

function updateToggleLabel(toggle) {
    const wrapper = toggle.closest('.toggle-switch-wrapper');
    const label = wrapper ? wrapper.querySelector('.toggle-switch-label') : null;
    if (label) {
        const isActive = toggle.checked;
        label.textContent = isActive ? 'Ativo' : 'Inativo';
        label.style.color = isActive ? '#22C55E' : '#EF4444';
        label.style.fontWeight = isActive ? '700' : '600';
    }
}

function updateStats() {
    // Buscar stats atualizados via AJAX
    fetch('ajax_challenge_groups.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_stats'
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success && result.stats) {
            // Atualizar os números dos stats
            const statTotal = document.getElementById('stat-total');
            const statActive = document.getElementById('stat-active');
            const statCompleted = document.getElementById('stat-completed');
            const statScheduled = document.getElementById('stat-scheduled');
            
            const statInactive = document.getElementById('stat-inactive');
            
            if (statTotal) statTotal.textContent = result.stats.total || 0;
            if (statActive) statActive.textContent = result.stats.active || 0;
            if (statCompleted) statCompleted.textContent = result.stats.completed || 0;
            if (statScheduled) statScheduled.textContent = result.stats.scheduled || 0;
            if (statInactive) statInactive.textContent = result.stats.inactive || 0;
        }
    })
    .catch(error => {
        console.error('Erro ao atualizar stats:', error);
        // Não mostrar erro para o usuário, é apenas uma atualização de UI
    });
}

// Salvar desafio
function saveChallenge() {
    const form = document.getElementById('challengeForm');
    if (!form) {
        alert('Erro: Formulário não encontrado');
        return;
    }
    
    const formData = new FormData(form);
    
    // Validar campos obrigatórios
    const name = formData.get('name');
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    
    // Obter valores do Flatpickr ou do input diretamente
    let startDateStr = '';
    let endDateStr = '';
    
    // Função auxiliar para converter data de d/m/Y para Y-m-d
    function parseDateToYMD(dateStr) {
        if (!dateStr || !dateStr.trim()) {
            return null;
        }
        
        // Remover espaços
        dateStr = dateStr.trim();
        
        // Tentar formato d/m/Y
        const parts = dateStr.split('/');
        if (parts.length === 3) {
            const day = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10);
            const year = parseInt(parts[2], 10);
            
            // Validar valores
            if (isNaN(day) || isNaN(month) || isNaN(year)) {
                return null;
            }
            
            if (day < 1 || day > 31 || month < 1 || month > 12 || year < 2020 || year > 2100) {
                return null;
            }
            
            // Verificar se a data é válida
            const date = new Date(year, month - 1, day);
            if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
                return null;
            }
            
            // Formatar para Y-m-d
            return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        }
        
        // Tentar formato Y-m-d (já está no formato correto)
        if (dateStr.match(/^\d{4}-\d{2}-\d{2}$/)) {
            return dateStr;
        }
        
        return null;
    }
    
    // Obter datas
    if (startDateInput) {
        let dateValue = '';
        
        // Tentar obter do Flatpickr primeiro
        if (startDateInput._flatpickr && startDateInput._flatpickr.selectedDates && startDateInput._flatpickr.selectedDates.length > 0) {
            try {
                const selectedDate = startDateInput._flatpickr.selectedDates[0];
                if (selectedDate && selectedDate instanceof Date && !isNaN(selectedDate.getTime())) {
                    const year = selectedDate.getFullYear();
                    const month = String(selectedDate.getMonth() + 1).padStart(2, '0');
                    const day = String(selectedDate.getDate()).padStart(2, '0');
                    startDateStr = `${year}-${month}-${day}`;
                }
            } catch (e) {
                console.warn('Erro ao obter data do Flatpickr:', e);
            }
        }
        
        // Se não conseguiu do Flatpickr, tentar do input
        if (!startDateStr && startDateInput.value) {
            startDateStr = parseDateToYMD(startDateInput.value);
        }
    }
    
    if (endDateInput) {
        let dateValue = '';
        
        // Tentar obter do Flatpickr primeiro
        if (endDateInput._flatpickr && endDateInput._flatpickr.selectedDates && endDateInput._flatpickr.selectedDates.length > 0) {
            try {
                const selectedDate = endDateInput._flatpickr.selectedDates[0];
                if (selectedDate && selectedDate instanceof Date && !isNaN(selectedDate.getTime())) {
                    const year = selectedDate.getFullYear();
                    const month = String(selectedDate.getMonth() + 1).padStart(2, '0');
                    const day = String(selectedDate.getDate()).padStart(2, '0');
                    endDateStr = `${year}-${month}-${day}`;
                }
            } catch (e) {
                console.warn('Erro ao obter data do Flatpickr:', e);
            }
        }
        
        // Se não conseguiu do Flatpickr, tentar do input
        if (!endDateStr && endDateInput.value) {
            endDateStr = parseDateToYMD(endDateInput.value);
        }
    }
    
    // Validar campos obrigatórios
    if (!name || !name.trim()) {
        alert('Por favor, preencha o nome do desafio');
        return;
    }
    
    if (!startDateStr) {
        alert('Por favor, preencha a data de início no formato dd/mm/aaaa');
        if (startDateInput) startDateInput.focus();
        return;
    }
    
    if (!endDateStr) {
        alert('Por favor, preencha a data de fim no formato dd/mm/aaaa');
        if (endDateInput) endDateInput.focus();
        return;
    }
    
    // Validar datas (criar objetos Date para validação)
    const startDateObj = new Date(startDateStr);
    const endDateObj = new Date(endDateStr);
    
    if (isNaN(startDateObj.getTime()) || isNaN(endDateObj.getTime())) {
        alert('Por favor, insira datas válidas no formato dd/mm/aaaa');
        return;
    }
    
    if (startDateObj > endDateObj) {
        alert('A data de início deve ser anterior à data de fim');
        return;
    }
    
    // Coletar metas selecionadas
    const goals = [];
    const goalTags = document.querySelectorAll('.goal-tag.active');
    goalTags.forEach(tag => {
        const goalType = tag.dataset.goal;
        const valueInput = document.getElementById(`goal_${goalType}_value`);
        if (valueInput && valueInput.value) {
            goals.push({
                type: goalType,
                value: valueInput.value
            });
        }
    });
    
    // Coletar participantes selecionados
    const participants = [];
    const participantTags = document.querySelectorAll('.participant-tag.selected');
    participantTags.forEach(tag => {
        const hiddenInput = tag.querySelector('input[type="hidden"]');
        if (hiddenInput && hiddenInput.name === 'participants[]') {
            participants.push(hiddenInput.value);
        }
    });
    
    // Preparar dados para envio
    const data = {
        action: 'save',
        challenge_id: formData.get('challenge_id') || null,
        name: name,
        description: formData.get('description') || '',
        start_date: startDateStr,
        end_date: endDateStr,
        status: formData.get('status') || 'scheduled',
        goals: goals,
        participants: participants
    };
    
    // Enviar via AJAX
    fetch('ajax_challenge_groups.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            closeChallengeModal();
            window.location.reload();
        } else {
            alert('Erro ao salvar desafio: ' + (result.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar desafio: ' + error.message);
    });
}

// Close modal when clicking outside (via overlay)
// Handled by onclick on overlay div
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
