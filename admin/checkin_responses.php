<?php
// admin/checkin_responses.php - Visualizar respostas dos check-ins

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'checkin';
$page_title = 'Respostas do Check-in';

$admin_id = $_SESSION['admin_id'] ?? 1;
$checkin_id = (int)($_GET['id'] ?? 0);

if ($checkin_id <= 0) {
    header("Location: checkin.php");
    exit;
}

// Buscar check-in
$stmt = $conn->prepare("SELECT * FROM sf_checkin_configs WHERE id = ? AND admin_id = ?");
$stmt->bind_param("ii", $checkin_id, $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: checkin.php");
    exit;
}

$checkin = $result->fetch_assoc();
$stmt->close();

// Buscar perguntas
$stmt = $conn->prepare("SELECT * FROM sf_checkin_questions WHERE config_id = ? ORDER BY order_index ASC");
$stmt->bind_param("i", $checkin_id);
$stmt->execute();
$questions_result = $stmt->get_result();
$questions = [];
while ($row = $questions_result->fetch_assoc()) {
    $row['options'] = !empty($row['options']) ? json_decode($row['options'], true) : null;
    $questions[$row['id']] = $row;
}
$stmt->close();

// Processar filtro de datas
$date_filter = $_GET['date_filter'] ?? 'all';
$date_condition = "";

switch ($date_filter) {
    case 'last_7_days':
        $date_condition = "AND DATE(cr.submitted_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'this_week':
        $date_condition = "AND YEARWEEK(cr.submitted_at, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'last_week':
        $date_condition = "AND YEARWEEK(cr.submitted_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 7 DAY), 1)";
        break;
    case 'this_month':
        $date_condition = "AND YEAR(cr.submitted_at) = YEAR(CURDATE()) AND MONTH(cr.submitted_at) = MONTH(CURDATE())";
        break;
    case 'last_month':
        $date_condition = "AND YEAR(cr.submitted_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(cr.submitted_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        break;
    default:
        $date_condition = "";
}

// Buscar usuários que responderam
// IMPORTANTE: Buscar respostas diretamente da tabela sf_checkin_responses
// sem depender de is_completed, para manter histórico completo mesmo após reset
$responses_query = "
    SELECT DISTINCT 
        u.id as user_id,
        u.name as user_name,
        u.email,
        up.profile_image_filename,
        DATE(cr.submitted_at) as response_date,
        MAX(cr.submitted_at) as completed_at
    FROM sf_checkin_responses cr
    INNER JOIN sf_users u ON cr.user_id = u.id
    LEFT JOIN sf_user_profiles up ON u.id = up.user_id
    WHERE cr.config_id = ?
    $date_condition
    GROUP BY u.id, DATE(cr.submitted_at)
    ORDER BY completed_at DESC
";

$stmt = $conn->prepare($responses_query);
$stmt->bind_param("i", $checkin_id);
$stmt->execute();
$users_result = $stmt->get_result();
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $user_id = $row['user_id'];
    $response_date = $row['response_date'];
    $key = $user_id . '_' . $response_date;
    
    if (!isset($users[$key])) {
        $users[$key] = $row;
        $users[$key]['responses'] = [];
        
        // Buscar primeira resposta para preview
        $first_resp_stmt = $conn->prepare("
            SELECT response_text, response_value
            FROM sf_checkin_responses
            WHERE config_id = ? AND user_id = ? AND DATE(submitted_at) = ?
            ORDER BY submitted_at ASC
            LIMIT 1
        ");
        $first_resp_stmt->bind_param("iis", $checkin_id, $user_id, $response_date);
        $first_resp_stmt->execute();
        $first_resp_result = $first_resp_stmt->get_result();
        if ($first_resp = $first_resp_result->fetch_assoc()) {
            $users[$key]['first_response'] = !empty($first_resp['response_text']) 
                ? $first_resp['response_text'] 
                : ($first_resp['response_value'] ?? '');
        }
        $first_resp_stmt->close();
    }
    
    // Buscar todas as respostas deste usuário para esta data
    $resp_stmt = $conn->prepare("
        SELECT question_id, response_text, response_value, submitted_at
        FROM sf_checkin_responses
        WHERE config_id = ? AND user_id = ? AND DATE(submitted_at) = ?
        ORDER BY submitted_at ASC
    ");
    $resp_stmt->bind_param("iis", $checkin_id, $user_id, $response_date);
    $resp_stmt->execute();
    $resp_result = $resp_stmt->get_result();
    while ($resp = $resp_result->fetch_assoc()) {
        $users[$key]['responses'][$resp['question_id']] = $resp;
    }
    $resp_stmt->close();
}
$stmt->close();

// Contar total de respostas
$total_count = count($users);

require_once __DIR__ . '/includes/header.php';
?>

<style>
.checkin-responses-page {
    padding: 1.5rem 2rem;
    min-height: 100vh;
}

.checkin-responses-page * {
    box-shadow: none !important;
}

.header-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 1.5rem !important;
    margin-bottom: 2rem !important;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--accent-orange);
    text-decoration: none;
    margin-bottom: 1rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.back-link:hover {
    color: #e55a00;
    transform: translateX(-4px);
}

.header-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 1rem;
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

/* Filtros */
.filters-section {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 16px !important;
    padding: 1.25rem !important;
    margin-bottom: 1.5rem !important;
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-group label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-secondary);
    white-space: nowrap;
}

.custom-select-wrapper {
    position: relative;
    min-width: 180px;
}

.custom-select {
    position: relative;
}

.custom-select-trigger {
    width: 100%;
    padding: 0.625rem 0.875rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.875rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
}

.custom-select-trigger:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.custom-select-trigger.active {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.custom-select-options {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: rgba(30, 30, 30, 0.98);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    overflow: hidden;
    z-index: 1000;
    display: none;
    max-height: 300px;
    overflow-y: auto;
}

.custom-select-options.active {
    display: block;
}

.custom-select-option {
    padding: 0.75rem 0.875rem;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.custom-select-option:last-child {
    border-bottom: none;
}

.custom-select-option:hover {
    background: rgba(255, 107, 0, 0.1);
    color: var(--accent-orange);
}

.custom-select-option.selected {
    background: rgba(255, 107, 0, 0.15);
    color: var(--accent-orange);
    font-weight: 600;
}

.submissions-count {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.2);
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--accent-orange);
}

.submissions-count .badge {
    background: var(--accent-orange);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 700;
}

/* Tabela com scroll horizontal */
.table-container {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 16px !important;
    overflow: hidden;
    margin-bottom: 2rem;
}

.table-wrapper {
    overflow-x: auto;
    overflow-y: visible;
    width: 100%;
}

.responses-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

.responses-table thead {
    background: rgba(255, 255, 255, 0.05);
    position: sticky;
    top: 0;
    z-index: 10;
}

.responses-table th {
    padding: 1rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-secondary);
    border-bottom: 1px solid var(--glass-border);
    white-space: nowrap;
}

.responses-table th:first-child {
    width: 40px;
    padding-left: 1.5rem;
}

.responses-table th:nth-child(2) {
    width: 180px;
}

.responses-table th:nth-child(3) {
    width: 250px;
}

.responses-table th:nth-child(4) {
    min-width: 300px;
}

.responses-table tbody tr {
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    cursor: pointer;
    transition: all 0.2s ease;
}

.responses-table tbody tr:hover {
    background: rgba(255, 107, 0, 0.05);
}

.responses-table td {
    padding: 1rem;
    font-size: 0.875rem;
    color: var(--text-primary);
    vertical-align: middle;
}

.responses-table td:first-child {
    padding-left: 1.5rem;
}

.table-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--accent-orange);
}

.table-date {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.table-date i {
    font-size: 0.75rem;
    opacity: 0.6;
}

.table-user {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.table-user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-orange);
    font-weight: 700;
    font-size: 0.75rem;
    flex-shrink: 0;
}

.table-user-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.table-user-name {
    font-weight: 600;
    color: var(--text-primary);
}

.table-preview {
    color: var(--text-secondary);
    font-size: 0.875rem;
    line-height: 1.5;
    max-width: 400px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

/* Modal de Chat */
.chat-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(10px);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.chat-modal.active {
    display: flex;
}

.chat-modal-content {
    background: rgba(30, 30, 30, 0.98);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.chat-modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--glass-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.chat-modal-header h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.chat-modal-close {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    font-size: 1.25rem;
    line-height: 1;
}

.chat-modal-close:hover {
    background: rgba(255, 107, 0, 0.1);
    border-color: var(--accent-orange);
    color: var(--accent-orange);
}

.chat-modal-body {
    padding: 1.5rem;
    overflow-y: auto;
    flex: 1;
}

.chat-message {
    margin-bottom: 1.5rem;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.chat-message.bot {
    display: flex;
    gap: 0.75rem;
}

.chat-message.bot .message-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255, 107, 0, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-orange);
    font-size: 0.875rem;
    font-weight: 700;
    flex-shrink: 0;
}

.chat-message.bot .message-content {
    flex: 1;
}

.chat-message.bot .message-bubble {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 1rem;
    color: var(--text-primary);
    font-size: 0.875rem;
    line-height: 1.6;
}

.chat-message.user {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.chat-message.user .message-content {
    max-width: 80%;
}

.chat-message.user .message-bubble {
    background: rgba(255, 107, 0, 0.15);
    border: 1px solid rgba(255, 107, 0, 0.3);
    border-radius: 12px;
    padding: 1rem;
    color: var(--text-primary);
    font-size: 0.875rem;
    line-height: 1.6;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.chat-message.user .message-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255, 107, 0, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-orange);
    font-size: 0.75rem;
    font-weight: 700;
    flex-shrink: 0;
}

.chat-message.user .message-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.chat-message-time {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-top: 0.5rem;
    padding-left: 0.5rem;
}

.chat-message.user .chat-message-time {
    text-align: right;
    padding-right: 0.5rem;
    padding-left: 0;
}
</style>

<div class="checkin-responses-page">
    <a href="checkin.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Voltar para Check-ins
    </a>

    <div class="header-card">
        <div class="header-title">
            <div>
                <h2><?php echo htmlspecialchars($checkin['name']); ?></h2>
                <p><?php echo htmlspecialchars($checkin['description'] ?? ''); ?></p>
            </div>
        </div>
    </div>

    <div class="filters-section">
        <div class="filter-group">
            <label for="dateFilter">Filtrar por:</label>
            <div class="custom-select-wrapper">
                <div class="custom-select">
                    <div class="custom-select-trigger" id="dateFilterTrigger">
                        <?php
                        $filter_labels = [
                            'all' => 'Todas as datas',
                            'last_7_days' => 'Últimos 7 dias',
                            'this_week' => 'Esta semana',
                            'last_week' => 'Semana passada',
                            'this_month' => 'Este mês',
                            'last_month' => 'Mês passado'
                        ];
                        echo htmlspecialchars($filter_labels[$date_filter] ?? 'Todas as datas');
                        ?>
                        <i class="fas fa-chevron-down" style="font-size: 0.75rem; margin-left: 0.5rem;"></i>
                    </div>
                    <div class="custom-select-options" id="dateFilterOptions">
                        <div class="custom-select-option <?php echo $date_filter === 'all' ? 'selected' : ''; ?>" data-value="all">Todas as datas</div>
                        <div class="custom-select-option <?php echo $date_filter === 'last_7_days' ? 'selected' : ''; ?>" data-value="last_7_days">Últimos 7 dias</div>
                        <div class="custom-select-option <?php echo $date_filter === 'this_week' ? 'selected' : ''; ?>" data-value="this_week">Esta semana</div>
                        <div class="custom-select-option <?php echo $date_filter === 'last_week' ? 'selected' : ''; ?>" data-value="last_week">Semana passada</div>
                        <div class="custom-select-option <?php echo $date_filter === 'this_month' ? 'selected' : ''; ?>" data-value="this_month">Este mês</div>
                        <div class="custom-select-option <?php echo $date_filter === 'last_month' ? 'selected' : ''; ?>" data-value="last_month">Mês passado</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="submissions-count">
            <span>Submissions</span>
            <span class="badge"><?php echo $total_count; ?></span>
        </div>
    </div>

    <?php if (empty($users)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>Nenhuma resposta ainda</h3>
            <p>Os pacientes ainda não responderam este check-in.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <div class="table-wrapper">
                <table class="responses-table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" class="table-checkbox" id="selectAll">
                            </th>
                            <th>
                                <i class="fas fa-clock"></i> Submitted at
                            </th>
                            <th>
                                <i class="fas fa-user"></i> Nome
                            </th>
                            <th>
                                <i class="fas fa-comment"></i> <?php 
                                $first_question = !empty($questions) ? reset($questions) : null;
                                echo htmlspecialchars($first_question['question_text'] ?? 'Resposta'); 
                                ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $key => $user): ?>
                            <?php
                            $date = new DateTime($user['completed_at']);
                            $formatted_date = $date->format('d/m/Y');
                            $formatted_time = $date->format('H:i');
                            
                            $name_parts = explode(' ', trim($user['user_name']));
                            $initials = count($name_parts) > 1 
                                ? strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1)) 
                                : (!empty($name_parts[0]) ? strtoupper(substr($name_parts[0], 0, 2)) : 'U');
                            
                            $preview = $user['first_response'] ?? '';
                            if (mb_strlen($preview) > 100) {
                                $preview = mb_substr($preview, 0, 100) . '...';
                            }
                            ?>
                            <tr data-user-key="<?php echo htmlspecialchars($key); ?>" onclick="openChatModal('<?php echo htmlspecialchars($key); ?>')">
                                <td onclick="event.stopPropagation();">
                                    <input type="checkbox" class="table-checkbox" data-user-key="<?php echo htmlspecialchars($key); ?>">
                                </td>
                                <td>
                                    <div class="table-date">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo $formatted_date; ?>, <?php echo $formatted_time; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="table-user">
                                        <div class="table-user-avatar">
                                            <?php if (!empty($user['profile_image_filename']) && file_exists(APP_ROOT_PATH . '/assets/images/users/' . $user['profile_image_filename'])): ?>
                                                <img src="<?php echo BASE_APP_URL . '/assets/images/users/' . htmlspecialchars($user['profile_image_filename']); ?>" alt="<?php echo htmlspecialchars($user['user_name']); ?>">
                                            <?php else: ?>
                                                <?php echo $initials; ?>
                                            <?php endif; ?>
                                        </div>
                                        <span class="table-user-name"><?php echo htmlspecialchars($user['user_name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="table-preview"><?php echo htmlspecialchars($preview ?: 'Sem resposta'); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de Chat -->
<div class="chat-modal" id="chatModal">
    <div class="chat-modal-content">
        <div class="chat-modal-header">
            <h3 id="chatModalUserName"></h3>
            <button class="chat-modal-close" onclick="closeChatModal()">&times;</button>
        </div>
        <div class="chat-modal-body" id="chatModalBody">
            <!-- Conteúdo do chat será inserido aqui via JavaScript -->
        </div>
    </div>
</div>

<script>
// Dados dos usuários para o JavaScript
const usersData = <?php echo json_encode($users); ?>;
const questionsData = <?php echo json_encode($questions); ?>;

// Custom Select
document.addEventListener('DOMContentLoaded', function() {
    const trigger = document.getElementById('dateFilterTrigger');
    const options = document.getElementById('dateFilterOptions');
    const optionItems = options.querySelectorAll('.custom-select-option');
    
    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        options.classList.toggle('active');
        trigger.classList.toggle('active');
    });
    
    optionItems.forEach(option => {
        option.addEventListener('click', function() {
            const value = this.getAttribute('data-value');
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('date_filter', value);
            window.location.href = currentUrl.toString();
        });
    });
    
    document.addEventListener('click', function(e) {
        if (!trigger.contains(e.target) && !options.contains(e.target)) {
            options.classList.remove('active');
            trigger.classList.remove('active');
        }
    });
    
    // Select All checkbox
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.table-checkbox[data-user-key]');
    
    selectAll.addEventListener('change', function() {
        checkboxes.forEach(cb => {
            cb.checked = this.checked;
        });
    });
});

function openChatModal(userKey) {
    const user = usersData[userKey];
    if (!user) return;
    
    const modal = document.getElementById('chatModal');
    const modalBody = document.getElementById('chatModalBody');
    const modalUserName = document.getElementById('chatModalUserName');
    
    // Nome do usuário
    modalUserName.textContent = user.user_name;
    
    // Limpar conteúdo anterior
    modalBody.innerHTML = '';
    
    // Criar mensagens do chat
    const questionIds = Object.keys(questionsData).sort((a, b) => {
        return (questionsData[a].order_index || 0) - (questionsData[b].order_index || 0);
    });
    
    questionIds.forEach(questionId => {
        const question = questionsData[questionId];
        const response = user.responses[questionId];
        
        // Mensagem do bot (pergunta)
        const botMessage = document.createElement('div');
        botMessage.className = 'chat-message bot';
        botMessage.innerHTML = `
            <div class="message-avatar">B</div>
            <div class="message-content">
                <div class="message-bubble">${escapeHtml(question.question_text)}</div>
            </div>
        `;
        modalBody.appendChild(botMessage);
        
        // Mensagem do usuário (resposta)
        const userMessage = document.createElement('div');
        userMessage.className = 'chat-message user';
        
        let responseText = 'Sem resposta';
        if (response) {
            if (response.response_text) {
                responseText = response.response_text;
            } else if (response.response_value) {
                responseText = response.response_value;
            }
        }
        
        const userAvatar = user.profile_image_filename && 
            '<?php echo BASE_APP_URL; ?>/assets/images/users/' + escapeHtml(user.profile_image_filename);
        const nameParts = user.user_name.split(' ');
        const initials = nameParts.length > 1 
            ? (nameParts[0][0] + nameParts[nameParts.length - 1][0]).toUpperCase()
            : (nameParts[0]?.substring(0, 2) || 'U').toUpperCase();
        
        userMessage.innerHTML = `
            <div class="message-content">
                <div class="message-bubble">${escapeHtml(responseText)}</div>
                ${response ? `<div class="chat-message-time">${formatDateTime(response.submitted_at)}</div>` : ''}
            </div>
            <div class="message-avatar">
                ${userAvatar ? `<img src="${userAvatar}" alt="${escapeHtml(user.user_name)}">` : initials}
            </div>
        `;
        modalBody.appendChild(userMessage);
    });
    
    // Scroll para o final
    setTimeout(() => {
        modalBody.scrollTop = modalBody.scrollHeight;
    }, 100);
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeChatModal() {
    const modal = document.getElementById('chatModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

// Fechar modal ao clicar fora
document.getElementById('chatModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeChatModal();
    }
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeChatModal();
    }
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
