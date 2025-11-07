<?php
// admin/challenge_groups.php - Gerenciamento de Grupos de Desafio - Design Profissional

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

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

// --- Construir query de busca ---
$sql = "SELECT 
    cg.*,
    COUNT(DISTINCT cgm.user_id) as member_count,
    COUNT(DISTINCT cgo.id) as goals_count
    FROM sf_challenge_groups cg
    LEFT JOIN sf_challenge_group_members cgm ON cg.id = cgm.group_id
    LEFT JOIN sf_challenge_goals cgo ON cg.id = cgo.challenge_group_id
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

require_once __DIR__ . '/includes/header.php';
?>

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
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow: visible;
    position: relative;
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
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
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
    gap: 1rem;
}

.group-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    flex: 1;
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
}

.group-info {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.group-info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.group-info-item i {
    color: var(--accent-orange);
}

.group-card-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: auto;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-action {
    flex: 1;
    padding: 0.75rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
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
    background: rgba(255, 107, 0, 0.1);
    border-color: rgba(255, 107, 0, 0.3);
}

.participant-tag.selected::after {
    content: '✓';
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    width: 20px;
    height: 20px;
    background: var(--accent-orange);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    line-height: 1;
}

.participant-avatar {
    width: 40px;
    height: 40px;
    min-width: 40px;
    min-height: 40px;
    border-radius: 50%;
    background: rgba(255, 107, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-orange);
    font-weight: 600;
    font-size: 0.875rem;
    overflow: hidden;
}

.participant-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
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
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card" onclick="filterByStatus('active')">
                <div class="stat-number"><?php echo $stats['active']; ?></div>
                <div class="stat-label">Ativos</div>
            </div>
            <div class="stat-card" onclick="filterByStatus('completed')">
                <div class="stat-number"><?php echo $stats['completed']; ?></div>
                <div class="stat-label">Concluídos</div>
            </div>
            <div class="stat-card" onclick="filterByStatus('scheduled')">
                <div class="stat-number"><?php echo $stats['scheduled']; ?></div>
                <div class="stat-label">Agendados</div>
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
                
                // Buscar metas do grupo
                $goals_query = "SELECT goal_type, goal_value, goal_unit 
                               FROM sf_challenge_goals 
                               WHERE challenge_group_id = ?";
                $goals_stmt = $conn->prepare($goals_query);
                $goals_stmt->bind_param("i", $group['id']);
                $goals_stmt->execute();
                $goals = $goals_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $goals_stmt->close();
                ?>
                <div class="challenge-group-card" onclick="viewChallenge(<?php echo $group['id']; ?>)">
                    <div class="group-card-header">
                        <h3 class="group-name"><?php echo htmlspecialchars($group['name']); ?></h3>
                        <span class="group-status <?php echo $status_class; ?>">
                            <?php 
                            echo $status_class === 'active' ? 'Ativo' : 
                                ($status_class === 'completed' ? 'Concluído' : 
                                ($status_class === 'scheduled' ? 'Agendado' : 'Inativo')); 
                            ?>
                                </span>
                            </div>
                    
                    <?php if (!empty($group['description'])): ?>
                        <p class="group-description"><?php echo htmlspecialchars($group['description']); ?></p>
                    <?php endif; ?>
                    
                    <div class="group-info">
                        <div class="group-info-item">
                            <i class="fas fa-users"></i>
                            <span><?php echo $group['member_count']; ?> participantes</span>
                        </div>
                        <div class="group-info-item">
                            <i class="fas fa-bullseye"></i>
                            <span><?php echo count($goals); ?> metas</span>
                        </div>
                        <div class="group-info-item">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo $start_date->format('d/m/Y'); ?> - <?php echo $end_date->format('d/m/Y'); ?></span>
                        </div>
                    </div>
                    
                    <div class="group-card-actions" onclick="event.stopPropagation()">
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
                    <label for="challengeName">Nome do Desafio *</label>
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
                        <label for="startDate">Data de Início *</label>
                        <input type="date" id="startDate" name="start_date" class="challenge-form-input" required>
                    </div>
                    <div class="challenge-form-group">
                        <label for="endDate">Data de Fim *</label>
                        <input type="date" id="endDate" name="end_date" class="challenge-form-input" required>
                    </div>
                </div>
                
                <!-- Status -->
                <div class="challenge-form-group">
                    <label for="challengeStatus">Status</label>
                    <select id="challengeStatus" name="status" class="challenge-form-select">
                        <option value="scheduled">Agendado</option>
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                        <option value="completed">Concluído</option>
                    </select>
                </div>
                
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
                        <span class="goal-tag steps" data-goal="steps">
                            <i class="fas fa-walking"></i>
                            <span>Passos</span>
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
                        <div class="goal-input-wrapper" id="goal_steps_input" style="display: none;">
                            <label>Passos (passos/dia)</label>
                            <input type="number" id="goal_steps_value" name="goal_steps_value" 
                                   class="challenge-form-input" min="0" step="100" placeholder="Ex: 10000">
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
                                <div class="participant-avatar">
                                    <?php 
                                    if (!empty($user['profile_image_filename'])) {
                                        echo '<img src="' . BASE_ASSET_URL . '/uploads/profiles/' . htmlspecialchars($user['profile_image_filename']) . '" alt="">';
                                    } else {
                                        echo strtoupper(substr($user['name'], 0, 1));
                                    }
                                    ?>
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
    document.getElementById('modalTitle').textContent = 'Criar Novo Desafio';
    document.getElementById('challengeForm').reset();
    document.getElementById('challengeId').value = '';
    document.getElementById('challengeModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    
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
            hiddenInput.name = '';
        }
    });
}

function closeChallengeModal() {
    document.getElementById('challengeModal').classList.remove('active');
    document.body.style.overflow = '';
}

function editChallenge(id) {
    // TODO: Implement edit functionality
    alert('Editar desafio: ' + id);
}

function deleteChallenge(id) {
    if (confirm('Tem certeza que deseja excluir este desafio?')) {
        // TODO: Implement delete functionality
        alert('Excluir desafio: ' + id);
    }
}

function viewChallenge(id) {
    // TODO: Implement view functionality
    alert('Ver desafio: ' + id);
}

function saveChallenge() {
    const form = document.getElementById('challengeForm');
    const formData = new FormData(form);
    
    // TODO: Implement save functionality via AJAX
    alert('Salvar desafio');
}

// Close modal when clicking outside (via overlay)
// Handled by onclick on overlay div
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
