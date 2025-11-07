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

// Buscar usuários para o modal
$users_query = "SELECT u.id, u.name, u.email, up.profile_image_filename 
                FROM sf_users u 
                LEFT JOIN sf_user_profiles up ON u.id = up.user_id 
                WHERE u.status = 'active'
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

/* Header Card */
.header-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    margin-bottom: 2rem;
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
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
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
}

.stat-card:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-2px);
    border-color: var(--accent-orange);
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--accent-orange);
    margin: 0;
    line-height: 1.2;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-top: 0.5rem;
}

/* Filter Card */
.filter-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
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
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
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
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
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
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.5);
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
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.challenge-group-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    cursor: pointer;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.challenge-group-card:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
    border-color: var(--accent-orange);
}

.group-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
}

.group-name {
    font-size: 1.25rem;
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
    padding: 4rem 2rem;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 4rem;
    color: var(--text-secondary);
    opacity: 0.5;
    margin-bottom: 1rem;
}

.empty-state h3 {
    font-size: 1.5rem;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.empty-state p {
    font-size: 1rem;
    margin: 0 0 2rem 0;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
    align-items: center;
    justify-content: center;
    padding: 2rem;
    box-sizing: border-box;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: linear-gradient(135deg, rgba(30, 30, 30, 0.98) 0%, rgba(20, 20, 20, 0.98) 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 0;
    max-width: 800px;
    width: 100%;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
}

.modal-header {
    padding: 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

.modal-body {
    padding: 2rem;
    overflow-y: auto;
    flex: 1;
}

.modal-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 0.875rem 1.25rem;
    font-size: 0.95rem;
    color: var(--text-primary);
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    outline: none;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
    box-sizing: border-box;
}

.form-group input:focus,
.form-group textarea:focus {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.goals-section {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.goals-section h3 {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
}

.goals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.goal-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.goal-item:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: var(--accent-orange);
}

.goal-item input[type="checkbox"] {
    width: auto;
    margin: 0;
    cursor: pointer;
}

.goal-item input[type="number"] {
    width: 100px;
    margin-left: auto;
    display: none;
}

.goal-item.active input[type="number"] {
    display: block;
}

.goal-item label {
    margin: 0;
    cursor: pointer;
    flex: 1;
    font-weight: 500;
}

.participants-section {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.participants-section h3 {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
}

.participants-search {
    margin-bottom: 1rem;
}

.participants-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1rem;
}

.participant-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    border-radius: 8px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.participant-item:hover {
    background: rgba(255, 255, 255, 0.05);
}

.participant-item input[type="checkbox"] {
    width: auto;
    margin: 0;
    cursor: pointer;
}

.participant-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 107, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-orange);
    font-weight: 600;
    flex-shrink: 0;
}

.participant-info {
    flex: 1;
}

.participant-name {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.participant-email {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.btn-primary {
    padding: 0.875rem 1.5rem;
    background: var(--accent-orange);
    border: none;
    border-radius: 12px;
    color: #FFFFFF;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
}

.btn-primary:hover {
    background: #e65c00;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 107, 0, 0.4);
}

.btn-secondary {
    padding: 0.875rem 1.5rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    color: var(--text-primary);
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
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
            <button class="btn-primary" onclick="openCreateChallengeModal()">
                <i class="fas fa-plus"></i> Criar Primeiro Desafio
            </button>
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

<!-- Modal Create/Edit Challenge -->
<div id="challengeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Criar Novo Desafio</h2>
            <button class="modal-close" onclick="closeChallengeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="challengeForm">
                <input type="hidden" id="challengeId" name="challenge_id">
                
                <div class="form-group">
                    <label for="challengeName">Nome do Desafio *</label>
                    <input type="text" id="challengeName" name="name" required 
                           placeholder="Ex: Desafio de Verão 2025">
                </div>
                
                <div class="form-group">
                    <label for="challengeDescription">Descrição</label>
                    <textarea id="challengeDescription" name="description" 
                              placeholder="Descreva o objetivo e regras do desafio"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="startDate">Data de Início *</label>
                        <input type="date" id="startDate" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label for="endDate">Data de Fim *</label>
                        <input type="date" id="endDate" name="end_date" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="challengeStatus">Status</label>
                    <select id="challengeStatus" name="status" class="form-group input">
                        <option value="scheduled">Agendado</option>
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                        <option value="completed">Concluído</option>
                    </select>
                </div>
                
                <!-- Goals Section -->
                <div class="goals-section">
                    <h3><i class="fas fa-bullseye"></i> Metas do Desafio</h3>
                    <div class="goals-grid">
                        <div class="goal-item" data-goal="calories">
                            <input type="checkbox" id="goal_calories" name="goals[]" value="calories">
                            <input type="number" id="goal_calories_value" name="goal_calories_value" 
                                   placeholder="kcal" min="0" step="1">
                            <label for="goal_calories">
                                <i class="fas fa-fire"></i> Calorias (kcal/dia)
                            </label>
                        </div>
                        <div class="goal-item" data-goal="water">
                            <input type="checkbox" id="goal_water" name="goals[]" value="water">
                            <input type="number" id="goal_water_value" name="goal_water_value" 
                                   placeholder="ml" min="0" step="50">
                            <label for="goal_water">
                                <i class="fas fa-tint"></i> Água (ml/dia)
                            </label>
                        </div>
                        <div class="goal-item" data-goal="exercise">
                            <input type="checkbox" id="goal_exercise" name="goals[]" value="exercise">
                            <input type="number" id="goal_exercise_value" name="goal_exercise_value" 
                                   placeholder="min" min="0" step="5">
                            <label for="goal_exercise">
                                <i class="fas fa-dumbbell"></i> Exercício (min/dia)
                            </label>
                        </div>
                        <div class="goal-item" data-goal="sleep">
                            <input type="checkbox" id="goal_sleep" name="goals[]" value="sleep">
                            <input type="number" id="goal_sleep_value" name="goal_sleep_value" 
                                   placeholder="horas" min="0" max="24" step="0.5">
                            <label for="goal_sleep">
                                <i class="fas fa-bed"></i> Sono (horas/dia)
                            </label>
                        </div>
                        <div class="goal-item" data-goal="steps">
                            <input type="checkbox" id="goal_steps" name="goals[]" value="steps">
                            <input type="number" id="goal_steps_value" name="goal_steps_value" 
                                   placeholder="passos" min="0" step="100">
                            <label for="goal_steps">
                                <i class="fas fa-walking"></i> Passos (passos/dia)
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Participants Section -->
                <div class="participants-section">
                    <h3><i class="fas fa-users"></i> Participantes</h3>
                    <div class="participants-search">
                        <input type="text" class="search-input" id="participantSearch" 
                               placeholder="Buscar pacientes..." 
                               onkeyup="filterParticipants()">
                    </div>
                    <div class="participants-list" id="participantsList">
                        <?php foreach ($users as $user): ?>
                            <div class="participant-item" 
                                 data-name="<?php echo strtolower(htmlspecialchars($user['name'])); ?>"
                                 data-email="<?php echo strtolower(htmlspecialchars($user['email'])); ?>">
                                <input type="checkbox" name="participants[]" 
                                       value="<?php echo $user['id']; ?>" 
                                       id="participant_<?php echo $user['id']; ?>">
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
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeChallengeModal()">Cancelar</button>
            <button type="button" class="btn-primary" onclick="saveChallenge()">
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
    const items = document.querySelectorAll('.participant-item');
    
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

// Toggle goal input visibility
document.addEventListener('DOMContentLoaded', function() {
    const goalItems = document.querySelectorAll('.goal-item');
    goalItems.forEach(item => {
        const checkbox = item.querySelector('input[type="checkbox"]');
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                item.classList.add('active');
                item.querySelector('input[type="number"]').required = true;
            } else {
                item.classList.remove('active');
                item.querySelector('input[type="number"]').required = false;
                item.querySelector('input[type="number"]').value = '';
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
    
    // Reset goals
    document.querySelectorAll('.goal-item').forEach(item => {
        item.classList.remove('active');
        item.querySelector('input[type="checkbox"]').checked = false;
        item.querySelector('input[type="number"]').value = '';
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

// Close modal when clicking outside
document.getElementById('challengeModal')?.addEventListener('click', function(event) {
    if (event.target === this) {
        closeChallengeModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
