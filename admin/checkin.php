<?php
// admin/checkin.php - Gerenciamento de Check-in

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'checkin';
$page_title = 'Check-in';

$admin_id = $_SESSION['admin_id'] ?? 1;

// --- Lógica de busca e filtro ---
$search_term = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// --- Estatísticas gerais ---
$stats = [];

// Total de check-ins
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM sf_checkin_configs WHERE admin_id = $admin_id")->fetch_assoc()['count'];

// Por status
$stats_query = "SELECT is_active, COUNT(*) as count 
                FROM sf_checkin_configs 
                WHERE admin_id = $admin_id
                GROUP BY is_active";
$stats_result = $conn->query($stats_query);
$stats_by_status = [1 => 0, 0 => 0];
while ($row = $stats_result->fetch_assoc()) {
    $stats_by_status[$row['is_active']] = $row['count'];
}
$stats['active'] = $stats_by_status[1];
$stats['inactive'] = $stats_by_status[0];

// Total de respostas
$stats['responses'] = $conn->query("SELECT COUNT(DISTINCT user_id, config_id, DATE(submitted_at)) as count FROM sf_checkin_responses")->fetch_assoc()['count'];

// --- Construir query de busca ---
$sql = "SELECT 
    cc.*,
    COUNT(DISTINCT cd.id) as distribution_count,
    COUNT(DISTINCT cq.id) as questions_count
    FROM sf_checkin_configs cc
    LEFT JOIN sf_checkin_distribution cd ON cc.id = cd.config_id
    LEFT JOIN sf_checkin_questions cq ON cc.id = cq.config_id
    WHERE cc.admin_id = ?";
$conditions = [];
$params = [$admin_id];
$types = 'i';

if (!empty($search_term)) {
    $conditions[] = "cc.name LIKE ?";
    $params[] = '%' . $search_term . '%';
    $types .= 's';
}

if (!empty($status_filter)) {
    $conditions[] = $status_filter === 'active' ? "cc.is_active = 1" : "cc.is_active = 0";
}

if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

$sql .= " GROUP BY cc.id ORDER BY cc.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

// Executar query
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $checkins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $checkins = [];
}

// Contar total para paginação
$count_sql = "SELECT COUNT(*) as count FROM sf_checkin_configs cc WHERE cc.admin_id = ?";
$count_params = [$admin_id];
$count_types = 'i';

if (!empty($search_term)) {
    $count_sql .= " AND cc.name LIKE ?";
    $count_params[] = '%' . $search_term . '%';
    $count_types .= 's';
}

if (!empty($status_filter)) {
    $count_sql .= $status_filter === 'active' ? " AND cc.is_active = 1" : " AND cc.is_active = 0";
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

// Buscar grupos e usuários para o modal
$groups_query = "SELECT id, group_name as name FROM sf_user_groups WHERE admin_id = $admin_id AND is_active = 1 ORDER BY group_name";
$groups_result = $conn->query($groups_query);
$groups = $groups_result->fetch_all(MYSQLI_ASSOC);

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
.checkin-page {
    padding: 1.5rem 2rem;
    min-height: 100vh;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.checkin-page * {
    box-shadow: none !important;
}

.header-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 1.5rem !important;
    margin-bottom: 2rem !important;
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-top: 1.5rem;
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
}

.stat-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 1.25rem !important;
    margin-bottom: 2rem !important;
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

.checkin-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 420px), 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.checkin-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 12px !important;
    padding: 1.25rem !important;
    transition: all 0.3s ease !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 1rem !important;
    min-height: 200px !important;
}

.checkin-card:hover {
    border-color: var(--accent-orange) !important;
    background: rgba(255, 255, 255, 0.08) !important;
    transform: translateY(-2px);
}

.checkin-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
}

.checkin-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    flex: 1;
}

.checkin-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.checkin-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: auto;
}

.btn-action {
    flex: 1;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    border: 1px solid var(--glass-border);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.875rem;
}

.btn-action:hover {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
    color: white;
}

.btn-action.btn-danger:hover {
    background: #ef4444;
    border-color: #ef4444;
}

.btn-primary {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
    color: white;
    padding: 0.875rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
}

.btn-primary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    overflow-y: auto;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: rgba(20, 20, 20, 0.95);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    width: 100%;
    max-width: 800px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 2rem;
    position: relative;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--glass-border);
}

.modal-header h3 {
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
    padding: 0.5rem;
    transition: color 0.3s ease;
}

.modal-close:hover {
    color: var(--accent-orange);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
    font-weight: 600;
    font-size: 0.95rem;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.875rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    color: var(--text-primary);
    font-size: 0.95rem;
    font-family: 'Montserrat', sans-serif;
    outline: none;
    transition: all 0.3s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.questions-list {
    margin-top: 1rem;
}

.question-item {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
    position: relative;
}

.question-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.question-item-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 0.5rem;
    transition: color 0.3s ease;
}

.btn-icon:hover {
    color: var(--accent-orange);
}

.distribution-section {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--glass-border);
}

.distribution-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.distribution-tab {
    padding: 0.75rem 1.5rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
}

.distribution-tab.active {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
    color: white;
}

.distribution-content {
    max-height: 300px;
    overflow-y: auto;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
}

.distribution-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
}

.distribution-item-name {
    color: var(--text-primary);
    font-weight: 500;
}

.distribution-item-remove {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
}

.distribution-item-remove:hover {
    opacity: 0.8;
}

.day-selector {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.day-option {
    padding: 0.75rem;
    text-align: center;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    font-size: 0.875rem;
}

.day-option:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.day-option.selected {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
    color: white;
}
</style>

<div class="checkin-page">
    <div class="header-card">
        <div class="header-title">
            <div>
                <h2>Check-in</h2>
                <p>Gerencie os check-ins semanais dos seus pacientes</p>
            </div>
            <button class="btn-primary" onclick="openCreateCheckinModal()">
                <i class="fas fa-plus"></i> Criar Check-in
            </button>
        </div>

        <div class="stats-grid">
            <div class="stat-card" onclick="filterByStatus('')">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card" onclick="filterByStatus('active')">
                <div class="stat-number"><?php echo $stats['active']; ?></div>
                <div class="stat-label">Ativos</div>
            </div>
            <div class="stat-card" onclick="filterByStatus('inactive')">
                <div class="stat-number"><?php echo $stats['responses']; ?></div>
                <div class="stat-label">Respostas</div>
            </div>
        </div>
    </div>

    <div class="filter-card">
        <div class="filter-row">
            <input type="text" 
                   class="search-input" 
                   placeholder="Buscar check-ins..." 
                   value="<?php echo htmlspecialchars($search_term); ?>"
                   onkeyup="if(event.key === 'Enter') searchCheckins(this.value)">
            <button class="btn-primary" onclick="searchCheckins(document.querySelector('.search-input').value)">
                <i class="fas fa-search"></i> Buscar
            </button>
        </div>
    </div>

    <div class="checkin-grid">
        <?php if (empty($checkins)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--text-secondary);">
                <i class="fas fa-clipboard-check" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <p>Nenhum check-in encontrado. Crie o primeiro!</p>
            </div>
        <?php else: ?>
            <?php foreach ($checkins as $checkin): ?>
                <div class="checkin-card">
                    <div class="checkin-card-header">
                        <div style="flex: 1;">
                            <h3 class="checkin-name"><?php echo htmlspecialchars($checkin['name']); ?></h3>
                            <div class="checkin-meta">
                                <span><i class="fas fa-calendar"></i> 
                                    <?php 
                                    $days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                                    echo $days[$checkin['day_of_week']] ?? 'Não definido';
                                    ?>
                                </span>
                                <span><i class="fas fa-question-circle"></i> <?php echo $checkin['questions_count']; ?> perguntas</span>
                                <span><i class="fas fa-users"></i> <?php echo $checkin['distribution_count']; ?> distribuições</span>
                                <span><i class="fas fa-circle" style="color: <?php echo $checkin['is_active'] ? '#22c55e' : '#ef4444'; ?>; font-size: 0.5rem;"></i> 
                                    <?php echo $checkin['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="checkin-actions">
                        <button class="btn-action" onclick="viewResponses(<?php echo $checkin['id']; ?>)">
                            <i class="fas fa-eye"></i> Ver Respostas
                        </button>
                        <button class="btn-action" onclick="editCheckin(<?php echo $checkin['id']; ?>)">
                            <i class="fas fa-edit"></i> Editar
                        </button>
                        <button class="btn-action btn-danger" onclick="deleteCheckin(<?php echo $checkin['id']; ?>)">
                            <i class="fas fa-trash"></i> Excluir
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para criar/editar check-in -->
<div id="checkinModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Criar Check-in</h3>
            <button class="modal-close" onclick="closeCheckinModal()">&times;</button>
        </div>
        <form id="checkinForm" onsubmit="saveCheckin(event)">
            <input type="hidden" id="checkinId" name="checkin_id" value="0">
            
            <div class="form-group">
                <label>Nome do Check-in *</label>
                <input type="text" id="checkinName" name="name" required placeholder="Ex: Feedback Semanal">
            </div>

            <div class="form-group">
                <label>Descrição</label>
                <textarea id="checkinDescription" name="description" placeholder="Descrição opcional do check-in"></textarea>
            </div>

            <div class="form-group">
                <label>Dia da Semana *</label>
                <div class="day-selector">
                    <?php 
                    $days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                    for ($i = 0; $i < 7; $i++): 
                    ?>
                        <div class="day-option" data-day="<?php echo $i; ?>" onclick="selectDay(<?php echo $i; ?>)">
                            <?php echo $days[$i]; ?>
                        </div>
                    <?php endfor; ?>
                </div>
                <input type="hidden" id="dayOfWeek" name="day_of_week" value="0">
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="checkinActive" name="is_active" checked> 
                    Check-in ativo
                </label>
            </div>

            <div class="questions-list">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <label style="margin: 0;">Perguntas</label>
                    <button type="button" class="btn-primary" onclick="addQuestion()" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                        <i class="fas fa-plus"></i> Adicionar Pergunta
                    </button>
                </div>
                <div id="questionsContainer"></div>
            </div>

            <div class="distribution-section">
                <label>Distribuição</label>
                <div class="distribution-tabs">
                    <div class="distribution-tab active" onclick="switchDistributionTab('groups')">Grupos</div>
                    <div class="distribution-tab" onclick="switchDistributionTab('users')">Usuários</div>
                </div>
                <div id="groupsDistribution" class="distribution-content">
                    <?php foreach ($groups as $group): ?>
                        <div class="distribution-item">
                            <span class="distribution-item-name"><?php echo htmlspecialchars($group['name']); ?></span>
                            <button type="button" class="distribution-item-remove" onclick="toggleDistribution('group', <?php echo $group['id']; ?>, this)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="usersDistribution" class="distribution-content" style="display: none;">
                    <?php foreach ($users as $user): ?>
                        <div class="distribution-item">
                            <span class="distribution-item-name"><?php echo htmlspecialchars($user['name']); ?></span>
                            <button type="button" class="distribution-item-remove" onclick="toggleDistribution('user', <?php echo $user['id']; ?>, this)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn-primary" style="flex: 1;">
                    <i class="fas fa-save"></i> Salvar
                </button>
                <button type="button" class="btn-action" onclick="closeCheckinModal()" style="flex: 1;">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const questions = [];
let selectedDay = 0;
let distributionData = { groups: [], users: [] };

function openCreateCheckinModal() {
    document.getElementById('checkinId').value = '0';
    document.getElementById('checkinName').value = '';
    document.getElementById('checkinDescription').value = '';
    document.getElementById('checkinActive').checked = true;
    document.getElementById('modalTitle').textContent = 'Criar Check-in';
    questions.length = 0;
    distributionData = { groups: [], users: [] };
    selectedDay = 0;
    updateDaySelector();
    renderQuestions();
    updateDistributionUI();
    document.getElementById('checkinModal').classList.add('active');
}

function closeCheckinModal() {
    document.getElementById('checkinModal').classList.remove('active');
}

function selectDay(day) {
    selectedDay = day;
    updateDaySelector();
    document.getElementById('dayOfWeek').value = day;
}

function updateDaySelector() {
    document.querySelectorAll('.day-option').forEach((el, idx) => {
        el.classList.toggle('selected', idx === selectedDay);
    });
}

function addQuestion() {
    questions.push({
        id: null,
        question_text: '',
        question_type: 'text',
        options: null,
        order_index: questions.length,
        is_required: true
    });
    renderQuestions();
}

function removeQuestion(index) {
    questions.splice(index, 1);
    questions.forEach((q, idx) => q.order_index = idx);
    renderQuestions();
}

function renderQuestions() {
    const container = document.getElementById('questionsContainer');
    container.innerHTML = questions.map((q, idx) => `
        <div class="question-item">
            <div class="question-item-header">
                <strong>Pergunta ${idx + 1}</strong>
                <div class="question-item-actions">
                    <button type="button" class="btn-icon" onclick="removeQuestion(${idx})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label>Texto da Pergunta *</label>
                <textarea onchange="questions[${idx}].question_text = this.value" required>${q.question_text}</textarea>
            </div>
            <div class="form-group">
                <label>Tipo</label>
                <select onchange="questions[${idx}].question_type = this.value; updateQuestionType(${idx})">
                    <option value="text" ${q.question_type === 'text' ? 'selected' : ''}>Texto Livre</option>
                    <option value="multiple_choice" ${q.question_type === 'multiple_choice' ? 'selected' : ''}>Múltipla Escolha</option>
                    <option value="scale" ${q.question_type === 'scale' ? 'selected' : ''}>Escala (0-10)</option>
                </select>
            </div>
            <div id="questionOptions${idx}" style="display: ${q.question_type === 'text' ? 'none' : 'block'};">
                <label>Opções (uma por linha)</label>
                <textarea onchange="updateQuestionOptions(${idx}, this.value)" placeholder="Exemplo para escala:&#10;0&#10;2.5&#10;5&#10;7.5&#10;10"></textarea>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" ${q.is_required ? 'checked' : ''} onchange="questions[${idx}].is_required = this.checked">
                    Obrigatória
                </label>
            </div>
        </div>
    `).join('');
}

function updateQuestionType(index) {
    renderQuestions();
}

function updateQuestionOptions(index, value) {
    const lines = value.split('\n').filter(l => l.trim());
    questions[index].options = lines.length > 0 ? JSON.stringify(lines) : null;
}

function switchDistributionTab(tab) {
    document.querySelectorAll('.distribution-tab').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('groupsDistribution').style.display = tab === 'groups' ? 'block' : 'none';
    document.getElementById('usersDistribution').style.display = tab === 'users' ? 'block' : 'none';
}

function toggleDistribution(type, id, button) {
    const key = type === 'group' ? 'groups' : 'users';
    const index = distributionData[key].indexOf(id);
    
    if (index > -1) {
        distributionData[key].splice(index, 1);
        button.innerHTML = '<i class="fas fa-plus"></i>';
        button.style.color = '';
    } else {
        distributionData[key].push(id);
        button.innerHTML = '<i class="fas fa-check"></i>';
        button.style.color = '#22c55e';
    }
}

function updateDistributionUI() {
    // Atualizar UI baseado em distributionData
    document.querySelectorAll('#groupsDistribution .distribution-item').forEach(item => {
        const id = parseInt(item.querySelector('button').onclick.toString().match(/\d+/)[0]);
        const button = item.querySelector('button');
        if (distributionData.groups.includes(id)) {
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.style.color = '#22c55e';
        }
    });
}

function saveCheckin(event) {
    event.preventDefault();
    
    const formData = new FormData();
    formData.append('action', 'save');
    formData.append('checkin_id', document.getElementById('checkinId').value);
    formData.append('name', document.getElementById('checkinName').value);
    formData.append('description', document.getElementById('checkinDescription').value);
    formData.append('day_of_week', selectedDay);
    formData.append('is_active', document.getElementById('checkinActive').checked ? '1' : '0');
    formData.append('questions', JSON.stringify(questions));
    formData.append('distribution', JSON.stringify(distributionData));
    
    fetch('ajax_checkin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Check-in salvo com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar check-in');
    });
}

function editCheckin(id) {
    fetch('ajax_checkin.php?action=get&checkin_id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const checkin = data.checkin;
                document.getElementById('checkinId').value = checkin.id;
                document.getElementById('checkinName').value = checkin.name;
                document.getElementById('checkinDescription').value = checkin.description || '';
                document.getElementById('checkinActive').checked = checkin.is_active == 1;
                selectedDay = checkin.day_of_week;
                updateDaySelector();
                questions.length = 0;
                if (checkin.questions) {
                    questions.push(...checkin.questions);
                }
                renderQuestions();
                distributionData = checkin.distribution || { groups: [], users: [] };
                updateDistributionUI();
                document.getElementById('modalTitle').textContent = 'Editar Check-in';
                document.getElementById('checkinModal').classList.add('active');
            }
        });
}

function deleteCheckin(id) {
    if (!confirm('Tem certeza que deseja excluir este check-in?')) return;
    
    fetch('ajax_checkin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', checkin_id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Check-in excluído com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    });
}

function viewResponses(id) {
    window.location.href = 'checkin_responses.php?id=' + id;
}

function searchCheckins(term) {
    const url = new URL(window.location);
    if (term) {
        url.searchParams.set('search', term);
    } else {
        url.searchParams.delete('search');
    }
    window.location.href = url.toString();
}

function filterByStatus(status) {
    const url = new URL(window.location);
    if (status) {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    window.location.href = url.toString();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

