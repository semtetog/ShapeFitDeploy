<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$room_id = $_GET['id'] ?? null;

if (!$room_id) {
    header('Location: ' . BASE_APP_URL . '/challenge_rooms.php');
    exit;
}

// === BUSCAR DADOS DA SALA ===
$stmt_room = $conn->prepare("
    SELECT 
        cr.*,
        COUNT(crm.user_id) as member_count,
        CASE WHEN crm.user_id IS NOT NULL THEN 1 ELSE 0 END as is_member,
        crm.is_admin,
        crm.joined_at
    FROM sf_challenge_rooms cr
    LEFT JOIN sf_challenge_room_members crm ON cr.id = crm.challenge_room_id AND crm.user_id = ?
    WHERE cr.id = ?
    GROUP BY cr.id
");
$stmt_room->bind_param("ii", $user_id, $room_id);
$stmt_room->execute();
$room_data = $stmt_room->get_result()->fetch_assoc();
$stmt_room->close();

if (!$room_data) {
    header('Location: ' . BASE_APP_URL . '/challenge_rooms.php');
    exit;
}

// === BUSCAR TAREFAS DA SALA ===
$stmt_tasks = $conn->prepare("
    SELECT * FROM sf_challenge_daily_tasks 
    WHERE challenge_room_id = ? AND is_active = 1
    ORDER BY points DESC, task_name ASC
");
$stmt_tasks->bind_param("i", $room_id);
$stmt_tasks->execute();
$room_tasks = $stmt_tasks->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_tasks->close();

// === BUSCAR RANKING DA SALA ===
$stmt_ranking = $conn->prepare("
    SELECT 
        u.id,
        u.name,
        u.profile_image,
        cr.total_points,
        cr.completed_tasks,
        cr.active_days,
        cr.ranking_position,
        cr.last_activity,
        ROW_NUMBER() OVER (ORDER BY cr.total_points DESC, cr.last_activity DESC) as position
    FROM sf_challenge_rankings cr
    JOIN sf_users u ON cr.user_id = u.id
    WHERE cr.challenge_room_id = ?
    ORDER BY cr.total_points DESC, cr.last_activity DESC
");
$stmt_ranking->bind_param("i", $room_id);
$stmt_ranking->execute();
$room_ranking = $stmt_ranking->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_ranking->close();

// === BUSCAR PROGRESSO DO USUÁRIO ===
$stmt_progress = $conn->prepare("
    SELECT 
        cdt.task_name,
        cdt.task_type,
        cdt.target_value,
        cdt.target_unit,
        cdt.points,
        cp.progress_date,
        cp.actual_value,
        cp.completed,
        cp.points_earned,
        cp.notes
    FROM sf_challenge_daily_tasks cdt
    LEFT JOIN sf_challenge_progress cp ON cdt.id = cp.task_id AND cp.user_id = ? AND cp.progress_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    WHERE cdt.challenge_room_id = ? AND cdt.is_active = 1
    ORDER BY cdt.points DESC, cdt.task_name ASC
");
$stmt_progress->bind_param("ii", $user_id, $room_id);
$stmt_progress->execute();
$user_progress = $stmt_progress->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_progress->close();

// === BUSCAR MENSAGENS DA SALA ===
$stmt_messages = $conn->prepare("
    SELECT 
        cm.*,
        u.name as user_name,
        u.profile_image
    FROM sf_challenge_messages cm
    JOIN sf_users u ON cm.user_id = u.id
    WHERE cm.challenge_room_id = ?
    ORDER BY cm.is_pinned DESC, cm.created_at DESC
    LIMIT 50
");
$stmt_messages->bind_param("i", $room_id);
$stmt_messages->execute();
$room_messages = $stmt_messages->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_messages->close();

// === CALCULAR ESTATÍSTICAS ===
$total_tasks = count($room_tasks);
$completed_tasks = 0;
$total_points = 0;
$active_days = [];

foreach ($user_progress as $progress) {
    if ($progress['completed']) {
        $completed_tasks++;
        $total_points += $progress['points_earned'];
    }
    if ($progress['progress_date']) {
        $active_days[] = $progress['progress_date'];
    }
}

$user_stats = [
    'total_tasks' => $total_tasks,
    'completed_tasks' => $completed_tasks,
    'total_points' => $total_points,
    'active_days' => count(array_unique($active_days)),
    'completion_rate' => $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0
];

$page_title = $room_data['name'];

require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* === CHALLENGE ROOM DETAILS PAGE === */
.room-details-container {
    padding: 0 24px;
    max-width: 100%;
}

.room-details-header {
    display: flex;
    align-items: center;
    padding: 16px 0;
    background: transparent;
    position: sticky;
    top: 0;
    z-index: 100;
    gap: 16px;
    margin-bottom: 20px;
}

.back-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: var(--text-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.back-btn:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.room-title {
    flex: 1;
    text-align: center;
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

/* Informações da Sala */
.room-info-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
}

.room-info-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.room-info h2 {
    color: var(--text-primary);
    font-size: 20px;
    font-weight: 600;
    margin: 0 0 8px 0;
}

.room-info p {
    color: var(--text-secondary);
    font-size: 14px;
    margin: 0;
    line-height: 1.4;
}

.room-status-badge {
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
}

.room-status-badge.active {
    background: rgba(74, 222, 128, 0.2);
    color: #4ade80;
}

.room-status-badge.completed {
    background: rgba(255, 107, 53, 0.2);
    color: var(--accent-orange);
}

.room-meta {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 16px;
}

.meta-item {
    text-align: center;
}

.meta-value {
    color: var(--text-primary);
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 4px;
}

.meta-label {
    color: var(--text-secondary);
    font-size: 12px;
}

.room-progress-bar {
    width: 100%;
    height: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 8px;
}

.room-progress-fill {
    height: 100%;
    background: var(--primary-orange-gradient);
    border-radius: 4px;
    transition: width 0.3s ease;
}

.room-progress-text {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: var(--text-secondary);
}

/* Estatísticas do Usuário */
.user-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.user-stat-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    padding: 16px;
    text-align: center;
}

.stat-icon {
    font-size: 24px;
    color: var(--accent-orange);
    margin-bottom: 8px;
}

.stat-value {
    color: var(--text-primary);
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 4px;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 12px;
}

/* Tarefas */
.tasks-section {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
}

.section-title {
    color: var(--text-primary);
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.tasks-grid {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.task-item {
    background: rgba(255, 255, 255, 0.06);
    border-radius: 12px;
    padding: 16px;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.task-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.task-info h4 {
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 4px 0;
}

.task-info p {
    color: var(--text-secondary);
    font-size: 12px;
    margin: 0;
}

.task-points {
    background: var(--primary-orange-gradient);
    color: var(--text-primary);
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
}

.task-target {
    color: var(--text-secondary);
    font-size: 12px;
    margin-bottom: 8px;
}

.task-progress {
    display: flex;
    align-items: center;
    gap: 8px;
}

.progress-checkbox {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.progress-checkbox.completed {
    background: #4ade80;
    border-color: #4ade80;
    color: white;
}

.progress-input {
    flex: 1;
    padding: 6px 8px;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-size: 12px;
}

.progress-input:focus {
    outline: none;
    border-color: var(--accent-orange);
}

/* Ranking */
.ranking-section {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
}

.ranking-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 300px;
    overflow-y: auto;
}

.ranking-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px;
    border-radius: 8px;
    transition: background 0.2s ease;
}

.ranking-item:hover {
    background: rgba(255, 255, 255, 0.05);
}

.ranking-item.current-user {
    background: rgba(255, 107, 53, 0.1);
    border: 1px solid rgba(255, 107, 53, 0.3);
}

.rank-position {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
}

.rank-position.top-3 {
    color: var(--text-primary);
}

.rank-position.top-3:nth-child(1) {
    background: linear-gradient(135deg, #ffd700, #ffed4e);
}

.rank-position.top-3:nth-child(2) {
    background: linear-gradient(135deg, #c0c0c0, #e5e5e5);
}

.rank-position.top-3:nth-child(3) {
    background: linear-gradient(135deg, #cd7f32, #daa520);
}

.rank-position.regular {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-secondary);
}

.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--primary-orange-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-primary);
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
}

.user-details {
    flex: 1;
    min-width: 0;
}

.user-details h5 {
    color: var(--text-primary);
    font-size: 12px;
    font-weight: 600;
    margin: 0 0 2px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-details p {
    color: var(--text-secondary);
    font-size: 10px;
    margin: 0;
}

.rank-score {
    color: var(--accent-orange);
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
}

/* Chat */
.chat-section {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
}

.chat-messages {
    max-height: 300px;
    overflow-y: auto;
    margin-bottom: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.chat-message {
    display: flex;
    gap: 12px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.06);
    border-radius: 12px;
}

.message-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--primary-orange-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-primary);
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
}

.message-content h6 {
    color: var(--text-primary);
    font-size: 12px;
    font-weight: 600;
    margin: 0 0 4px 0;
}

.message-content p {
    color: var(--text-secondary);
    font-size: 12px;
    margin: 0 0 4px 0;
    line-height: 1.4;
}

.message-time {
    color: var(--text-secondary);
    font-size: 10px;
}

.chat-input {
    display: flex;
    gap: 8px;
}

.chat-input input {
    flex: 1;
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-size: 12px;
}

.chat-input input:focus {
    outline: none;
    border-color: var(--accent-orange);
}

.chat-send-btn {
    padding: 8px 12px;
    border-radius: 8px;
    background: var(--primary-orange-gradient);
    border: none;
    color: var(--text-primary);
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.chat-send-btn:hover {
    filter: brightness(1.1);
}

/* Botões de Ação */
.room-actions {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
}

.action-btn {
    flex: 1;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.action-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--accent-orange);
}

.action-btn.primary {
    background: var(--primary-orange-gradient);
    border: none;
    color: var(--text-primary);
}

.action-btn.primary:hover {
    filter: brightness(1.1);
}

.action-btn.danger {
    background: rgba(248, 113, 113, 0.2);
    border-color: #f87171;
    color: #f87171;
}

/* Responsive */
@media (max-width: 480px) {
    .user-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .room-meta {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .room-actions {
        flex-direction: column;
    }
}
</style>

<div class="app-container">
    <div class="room-details-container">
        <!-- Header -->
        <div class="room-details-header">
            <button class="back-btn" onclick="history.back()">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h1 class="room-title"><?php echo htmlspecialchars($room_data['name']); ?></h1>
        </div>

        <!-- Informações da Sala -->
        <div class="room-info-card">
            <div class="room-info-header">
                <div class="room-info">
                    <h2><?php echo htmlspecialchars($room_data['name']); ?></h2>
                    <p><?php echo htmlspecialchars($room_data['description']); ?></p>
                </div>
                <div class="room-status-badge <?php echo $room_data['status']; ?>">
                    <i class="fas fa-<?php echo $room_data['status'] === 'active' ? 'play' : 'check'; ?>"></i>
                    <?php echo ucfirst($room_data['status']); ?>
                </div>
            </div>

            <div class="room-meta">
                <div class="meta-item">
                    <div class="meta-value"><?php echo $room_data['member_count']; ?></div>
                    <div class="meta-label">Membros</div>
                </div>
                <div class="meta-item">
                    <div class="meta-value"><?php echo $room_data['duration_days']; ?></div>
                    <div class="meta-label">Dias</div>
                </div>
                <div class="meta-item">
                    <div class="meta-value"><?php echo date('d/m', strtotime($room_data['end_date'])); ?></div>
                    <div class="meta-label">Termina</div>
                </div>
            </div>

            <div class="room-progress-bar">
                <div class="room-progress-fill" style="width: <?php echo ($room_data['member_count'] / $room_data['max_members']) * 100; ?>%"></div>
            </div>
            <div class="room-progress-text">
                <span>Progresso da Sala</span>
                <span><?php echo round(($room_data['member_count'] / $room_data['max_members']) * 100); ?>% completo</span>
            </div>
        </div>

        <!-- Estatísticas do Usuário -->
        <div class="user-stats-grid">
            <div class="user-stat-card">
                <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                <div class="stat-value"><?php echo $user_stats['total_points']; ?></div>
                <div class="stat-label">Pontos</div>
            </div>
            <div class="user-stat-card">
                <div class="stat-icon"><i class="fas fa-tasks"></i></div>
                <div class="stat-value"><?php echo $user_stats['completed_tasks']; ?>/<?php echo $user_stats['total_tasks']; ?></div>
                <div class="stat-label">Tarefas</div>
            </div>
            <div class="user-stat-card">
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                <div class="stat-value"><?php echo $user_stats['completion_rate']; ?>%</div>
                <div class="stat-label">Taxa</div>
            </div>
            <div class="user-stat-card">
                <div class="stat-icon"><i class="fas fa-calendar"></i></div>
                <div class="stat-value"><?php echo $user_stats['active_days']; ?></div>
                <div class="stat-label">Dias Ativos</div>
            </div>
        </div>

        <!-- Tarefas -->
        <div class="tasks-section">
            <h3 class="section-title">
                <i class="fas fa-list-check"></i>
                Tarefas Diárias
            </h3>
            <div class="tasks-grid">
                <?php foreach ($room_tasks as $task): ?>
                    <div class="task-item">
                        <div class="task-header">
                            <div class="task-info">
                                <h4><?php echo htmlspecialchars($task['task_name']); ?></h4>
                                <p><?php echo htmlspecialchars($task['task_description']); ?></p>
                            </div>
                            <div class="task-points">+<?php echo $task['points']; ?></div>
                        </div>
                        <div class="task-target">
                            Meta: <?php echo $task['target_value']; ?> <?php echo $task['target_unit']; ?>
                        </div>
                        <div class="task-progress">
                            <div class="progress-checkbox" onclick="toggleTaskCompletion(<?php echo $task['id']; ?>)">
                                <i class="fas fa-check" style="display: none;"></i>
                            </div>
                            <input type="number" class="progress-input" placeholder="Valor alcançado" 
                                   onchange="updateTaskProgress(<?php echo $task['id']; ?>, this.value)">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Ranking -->
        <div class="ranking-section">
            <h3 class="section-title">
                <i class="fas fa-trophy"></i>
                Ranking da Sala
            </h3>
            <div class="ranking-list">
                <?php foreach (array_slice($room_ranking, 0, 10) as $index => $user): ?>
                    <div class="ranking-item <?php echo $user['id'] == $user_id ? 'current-user' : ''; ?>">
                        <div class="rank-position <?php echo $index < 3 ? 'top-3' : 'regular'; ?>">
                            <?php echo $index + 1; ?>
                        </div>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                        </div>
                        <div class="user-details">
                            <h5><?php echo htmlspecialchars($user['name']); ?></h5>
                            <p><?php echo $user['completed_tasks']; ?> tarefas completadas</p>
                        </div>
                        <div class="rank-score">
                            <?php echo number_format($user['total_points'], 0, ',', '.'); ?> pts
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Chat -->
        <div class="chat-section">
            <h3 class="section-title">
                <i class="fas fa-comments"></i>
                Chat da Sala
            </h3>
            <div class="chat-messages" id="chatMessages">
                <?php if (empty($room_messages)): ?>
                    <div class="chat-message">
                        <div class="message-avatar">?</div>
                        <div class="message-content">
                            <h6>Sistema</h6>
                            <p>Nenhuma mensagem ainda. Seja o primeiro a comentar!</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($room_messages as $message): ?>
                        <div class="chat-message">
                            <div class="message-avatar">
                                <?php echo strtoupper(substr($message['user_name'], 0, 2)); ?>
                            </div>
                            <div class="message-content">
                                <h6><?php echo htmlspecialchars($message['user_name']); ?></h6>
                                <p><?php echo htmlspecialchars($message['message']); ?></p>
                                <div class="message-time"><?php echo date('d/m H:i', strtotime($message['created_at'])); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="chat-input">
                <input type="text" id="messageInput" placeholder="Digite sua mensagem..." onkeypress="handleMessageKeypress(event)">
                <button class="chat-send-btn" onclick="sendMessage()">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>

        <!-- Botões de Ação -->
        <div class="room-actions">
            <?php if ($room_data['is_member']): ?>
                <button class="action-btn" onclick="leaveRoom(<?php echo $room_id; ?>)">
                    <i class="fas fa-sign-out-alt"></i>
                    Sair da Sala
                </button>
            <?php else: ?>
                <button class="action-btn primary" onclick="joinRoom(<?php echo $room_id; ?>)">
                    <i class="fas fa-plus"></i>
                    Participar
                </button>
            <?php endif; ?>
            
            <a href="<?php echo BASE_APP_URL; ?>/challenge_rooms.php" class="action-btn">
                <i class="fas fa-arrow-left"></i>
                Voltar
            </a>
        </div>
    </div>
</div>

<script>
// === TASK FUNCTIONS ===
function toggleTaskCompletion(taskId) {
    const checkbox = event.target.closest('.progress-checkbox');
    const isCompleted = checkbox.classList.contains('completed');
    
    if (isCompleted) {
        checkbox.classList.remove('completed');
        checkbox.querySelector('i').style.display = 'none';
    } else {
        checkbox.classList.add('completed');
        checkbox.querySelector('i').style.display = 'block';
    }
    
    // Aqui você implementaria a atualização via AJAX
    console.log('Task completion toggled:', taskId, !isCompleted);
}

function updateTaskProgress(taskId, value) {
    // Aqui você implementaria a atualização do progresso via AJAX
    console.log('Task progress updated:', taskId, value);
}

// === CHAT FUNCTIONS ===
function handleMessageKeypress(event) {
    if (event.key === 'Enter') {
        sendMessage();
    }
}

function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if (!message) return;
    
    // Aqui você implementaria o envio da mensagem via AJAX
    console.log('Sending message:', message);
    
    // Simular envio
    addMessageToChat('Você', message, true);
    input.value = '';
}

function addMessageToChat(userName, message, isCurrentUser = false) {
    const chatMessages = document.getElementById('chatMessages');
    const messageDiv = document.createElement('div');
    messageDiv.className = 'chat-message';
    
    const now = new Date();
    const timeStr = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
    
    messageDiv.innerHTML = `
        <div class="message-avatar">${userName.substring(0, 2).toUpperCase()}</div>
        <div class="message-content">
            <h6>${userName}</h6>
            <p>${message}</p>
            <div class="message-time">${timeStr}</div>
        </div>
    `;
    
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// === ROOM FUNCTIONS ===
function joinRoom(roomId) {
    if (confirm('Deseja participar desta sala de desafio?')) {
        // Aqui você implementaria a entrada na sala via AJAX
        console.log('Joining room:', roomId);
        
        showNotification('Você entrou na sala!', 'success');
        
        // Recarregar página
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }
}

function leaveRoom(roomId) {
    if (confirm('Deseja sair desta sala de desafio?')) {
        // Aqui você implementaria a saída da sala via AJAX
        console.log('Leaving room:', roomId);
        
        showNotification('Você saiu da sala.', 'info');
        
        // Redirecionar para salas
        setTimeout(() => {
            window.location.href = '<?php echo BASE_APP_URL; ?>/challenge_rooms.php';
        }, 1000);
    }
}

// === NOTIFICATION SYSTEM ===
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    Object.assign(notification.style, {
        position: 'fixed',
        top: '20px',
        right: '20px',
        padding: '12px 16px',
        borderRadius: '8px',
        color: 'white',
        fontWeight: '600',
        zIndex: '10000',
        opacity: '0',
        transform: 'translateX(100%)',
        transition: 'all 0.3s ease'
    });
    
    if (type === 'success') {
        notification.style.background = '#4ade80';
    } else if (type === 'error') {
        notification.style.background = '#f87171';
    } else {
        notification.style.background = '#22d3ee';
    }
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}
</script>

<?php require_once APP_ROOT_PATH . '/includes/layout_footer.php'; ?>





