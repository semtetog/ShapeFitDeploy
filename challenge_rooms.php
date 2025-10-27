<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$page_title = "Salas de Desafio";

// Buscar dados do usuário
$user_profile_data = getUserProfileData($conn, $user_id);

// === BUSCAR SALAS DE DESAFIO ===
$stmt_rooms = $conn->prepare("
    SELECT 
        cr.*,
        COUNT(crm.user_id) as member_count,
        CASE WHEN crm.user_id IS NOT NULL THEN 1 ELSE 0 END as is_member
    FROM sf_challenge_rooms cr
    LEFT JOIN sf_challenge_room_members crm ON cr.id = crm.challenge_room_id AND crm.user_id = ?
    GROUP BY cr.id
    ORDER BY cr.created_at DESC
");
$stmt_rooms->bind_param("i", $user_id);
$stmt_rooms->execute();
$challenge_rooms = $stmt_rooms->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_rooms->close();

// === BUSCAR RANKING GERAL ===
$stmt_ranking = $conn->prepare("
    SELECT 
        u.id,
        u.name,
        up.profile_image_filename as profile_image,
        COALESCE(SUM(ul.kcal_consumed), 0) as total_kcal,
        COALESCE(SUM(ul.water_consumed_cups) * 250, 0) as total_water,
        COALESCE(COUNT(DISTINCT DATE(ul.date_consumed)), 0) as active_days,
        ROW_NUMBER() OVER (ORDER BY COALESCE(SUM(ul.kcal_consumed), 0) DESC) as position
    FROM sf_users u
    LEFT JOIN sf_user_profiles up ON u.id = up.user_id
    LEFT JOIN sf_user_meal_log ul ON u.id = ul.user_id 
        AND ul.date_consumed >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    WHERE u.status = 'active'
    GROUP BY u.id, up.profile_image_filename
    ORDER BY total_kcal DESC
    LIMIT 50
");
$stmt_ranking->execute();
$global_ranking = $stmt_ranking->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_ranking->close();

// Encontrar posição do usuário atual
$user_position = null;
foreach ($global_ranking as $index => $user) {
    if ($user['id'] == $user_id) {
        $user_position = $index + 1;
        break;
    }
}

require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* === CHALLENGE ROOMS PAGE === */
.challenge-container {
    padding: 0 24px;
    max-width: 100%;
}

.challenge-header {
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

.challenge-title {
    flex: 1;
    text-align: center;
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

/* Cards de Salas */
.rooms-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    margin-bottom: 24px;
}

.room-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.room-card:hover {
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.06);
    transform: translateY(-2px);
}

.room-card.joined {
    border-color: #4ade80;
    background: rgba(74, 222, 128, 0.1);
}

.room-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.room-info h3 {
    color: var(--text-primary);
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 4px 0;
}

.room-info p {
    color: var(--text-secondary);
    font-size: 12px;
    margin: 0;
}

.room-status {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}

.room-status.active {
    background: rgba(74, 222, 128, 0.2);
    color: #4ade80;
}

.room-status.joined {
    background: rgba(74, 222, 128, 0.3);
    color: #4ade80;
}

.room-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}

.stat-item {
    text-align: center;
}

.stat-value {
    color: var(--text-primary);
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 2px;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 10px;
}

.room-progress {
    margin-bottom: 16px;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}

.progress-label span {
    color: var(--text-secondary);
    font-size: 12px;
}

.progress-bar {
    width: 100%;
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--primary-orange-gradient);
    border-radius: 3px;
    transition: width 0.3s ease;
}

.room-actions {
    display: flex;
    gap: 8px;
}

.action-btn {
    flex: 1;
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    text-decoration: none;
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

.action-btn.joined {
    background: rgba(74, 222, 128, 0.2);
    border-color: #4ade80;
    color: #4ade80;
}

/* Seção de Ranking Global */
.ranking-section {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
}

.ranking-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.ranking-title {
    color: var(--text-primary);
    font-size: 16px;
    font-weight: 600;
    margin: 0;
}

.ranking-period {
    color: var(--text-secondary);
    font-size: 12px;
}

.user-position {
    background: rgba(255, 107, 53, 0.2);
    border: 1px solid var(--accent-orange);
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.position-badge {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary-orange-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-primary);
    font-size: 16px;
    font-weight: 700;
    flex-shrink: 0;
}

.user-info h4 {
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 4px 0;
}

.user-info p {
    color: var(--text-secondary);
    font-size: 12px;
    margin: 0;
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
    width: 24px;
    height: 24px;
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

/* Botão de Criar Sala */
.create-room-btn {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--primary-orange-gradient);
    border: none;
    color: var(--text-primary);
    font-size: 24px;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(255, 107, 53, 0.3);
    transition: all 0.2s ease;
    z-index: 1000;
}

.create-room-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(255, 107, 53, 0.4);
}

/* Modal de Criar Sala */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.modal-content {
    background: var(--bg-secondary);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 24px;
    max-width: 400px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    transform: scale(0.9);
    transition: transform 0.3s ease;
}

.modal-overlay.active .modal-content {
    transform: scale(1);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-title {
    color: var(--text-primary);
    font-size: 18px;
    font-weight: 600;
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 20px;
    cursor: pointer;
    padding: 4px;
}

.form-group {
    margin-bottom: 16px;
}

.form-label {
    display: block;
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 6px;
}

.form-input, .form-textarea, .form-select {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-size: 14px;
    transition: border-color 0.2s ease;
}

.form-input:focus, .form-textarea:focus, .form-select:focus {
    outline: none;
    border-color: var(--accent-orange);
}

.form-textarea {
    resize: vertical;
    min-height: 80px;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}

.btn-modal {
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
}

.btn-modal.primary {
    background: var(--primary-orange-gradient);
    border: none;
    color: var(--text-primary);
}

.btn-modal:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--accent-orange);
}

.btn-modal.primary:hover {
    filter: brightness(1.1);
}

/* Responsive */
@media (max-width: 480px) {
    .rooms-grid {
        grid-template-columns: 1fr;
    }
    
    .room-stats {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .create-room-btn {
        bottom: 80px;
        right: 24px;
    }
}
</style>

<div class="app-container">
    <div class="challenge-container">
        <!-- Header -->
        <div class="challenge-header">
            <h1 class="challenge-title">Salas de Desafio</h1>
        </div>

        <!-- Ranking Global -->
        <div class="ranking-section">
            <div class="ranking-header">
                <h3 class="ranking-title">🏆 Ranking Global</h3>
                <span class="ranking-period">Últimos 7 dias</span>
            </div>

            <?php if ($user_position): ?>
                <div class="user-position">
                    <div class="position-badge"><?php echo $user_position; ?>º</div>
                    <div class="user-info">
                        <h4>Sua Posição</h4>
                        <p><?php echo number_format($global_ranking[$user_position - 1]['total_kcal'], 0, ',', '.'); ?> kcal esta semana</p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="ranking-list">
                <?php foreach (array_slice($global_ranking, 0, 10) as $index => $user): ?>
                    <div class="ranking-item <?php echo $user['id'] == $user_id ? 'current-user' : ''; ?>">
                        <div class="rank-position <?php echo $index < 3 ? 'top-3' : 'regular'; ?>">
                            <?php echo $index + 1; ?>
                        </div>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                        </div>
                        <div class="user-details">
                            <h5><?php echo htmlspecialchars($user['name']); ?></h5>
                            <p><?php echo $user['active_days']; ?> dias ativos</p>
                        </div>
                        <div class="rank-score">
                            <?php echo number_format($user['total_kcal'], 0, ',', '.'); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Salas de Desafio -->
        <div class="rooms-grid">
            <?php if (empty($challenge_rooms)): ?>
                <div class="room-card">
                    <div class="room-header">
                        <div class="room-info">
                            <h3>Nenhuma sala encontrada</h3>
                            <p>Crie a primeira sala de desafio!</p>
                        </div>
                    </div>
                    <div class="room-actions">
                        <button class="action-btn primary" onclick="openCreateRoomModal()">
                            <i class="fas fa-plus"></i> Criar Sala
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($challenge_rooms as $room): ?>
                    <div class="room-card <?php echo $room['is_member'] ? 'joined' : ''; ?>" onclick="viewRoomDetails(<?php echo $room['id']; ?>)">
                        <div class="room-header">
                            <div class="room-info">
                                <h3><?php echo htmlspecialchars($room['name']); ?></h3>
                                <p><?php echo htmlspecialchars($room['description']); ?></p>
                            </div>
                            <div class="room-status <?php echo $room['is_member'] ? 'joined' : 'active'; ?>">
                                <i class="fas fa-users"></i>
                                <?php echo $room['is_member'] ? 'Participando' : 'Disponível'; ?>
                            </div>
                        </div>

                        <div class="room-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $room['member_count']; ?></div>
                                <div class="stat-label">Membros</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $room['max_members']; ?></div>
                                <div class="stat-label">Máximo</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo date('d/m', strtotime($room['end_date'])); ?></div>
                                <div class="stat-label">Termina</div>
                            </div>
                        </div>

                        <div class="room-progress">
                            <div class="progress-label">
                                <span>Progresso da Sala</span>
                                <span><?php echo round(($room['member_count'] / $room['max_members']) * 100); ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo ($room['member_count'] / $room['max_members']) * 100; ?>%"></div>
                            </div>
                        </div>

                        <div class="room-actions">
                            <?php if ($room['is_member']): ?>
                                <a href="<?php echo BASE_APP_URL; ?>/challenge_room_details.php?id=<?php echo $room['id']; ?>" class="action-btn joined">
                                    <i class="fas fa-eye"></i> Ver Sala
                                </a>
                                <button class="action-btn" onclick="leaveRoom(<?php echo $room['id']; ?>, event)">
                                    <i class="fas fa-sign-out-alt"></i> Sair
                                </button>
                            <?php else: ?>
                                <button class="action-btn primary" onclick="joinRoom(<?php echo $room['id']; ?>, event)">
                                    <i class="fas fa-plus"></i> Participar
                                </button>
                                <a href="<?php echo BASE_APP_URL; ?>/challenge_room_details.php?id=<?php echo $room['id']; ?>" class="action-btn">
                                    <i class="fas fa-info"></i> Detalhes
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Botão Flutuante -->
<button class="create-room-btn" onclick="openCreateRoomModal()">
    <i class="fas fa-plus"></i>
</button>

<!-- Modal de Criar Sala -->
<div class="modal-overlay" id="createRoomModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Criar Sala de Desafio</h3>
            <button class="modal-close" onclick="closeCreateRoomModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="createRoomForm" onsubmit="createRoom(event)">
            <div class="form-group">
                <label class="form-label" for="roomName">Nome da Sala</label>
                <input type="text" id="roomName" class="form-input" placeholder="Ex: Desafio 30 Dias" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="roomDescription">Descrição</label>
                <textarea id="roomDescription" class="form-textarea" placeholder="Descreva o objetivo da sala..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="maxMembers">Máximo de Membros</label>
                <select id="maxMembers" class="form-select" required>
                    <option value="10">10 membros</option>
                    <option value="20" selected>20 membros</option>
                    <option value="50">50 membros</option>
                    <option value="100">100 membros</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="challengeType">Tipo de Desafio</label>
                <select id="challengeType" class="form-select" required>
                    <option value="weight_loss">Perda de Peso</option>
                    <option value="muscle_gain">Ganho de Massa</option>
                    <option value="fitness">Condicionamento</option>
                    <option value="nutrition">Alimentação Saudável</option>
                    <option value="general">Geral</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="duration">Duração (dias)</label>
                <select id="duration" class="form-select" required>
                    <option value="7">7 dias</option>
                    <option value="14">14 dias</option>
                    <option value="30" selected>30 dias</option>
                    <option value="60">60 dias</option>
                    <option value="90">90 dias</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-modal" onclick="closeCreateRoomModal()">Cancelar</button>
                <button type="submit" class="btn-modal primary">Criar Sala</button>
            </div>
        </form>
    </div>
</div>

<script>
// === MODAL FUNCTIONS ===
function openCreateRoomModal() {
    document.getElementById('createRoomModal').classList.add('active');
}

function closeCreateRoomModal() {
    document.getElementById('createRoomModal').classList.remove('active');
}

// Fechar modal ao clicar fora
document.getElementById('createRoomModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCreateRoomModal();
    }
});

// === ROOM FUNCTIONS ===
function createRoom(event) {
    event.preventDefault();
    
    const formData = {
        name: document.getElementById('roomName').value,
        description: document.getElementById('roomDescription').value,
        max_members: parseInt(document.getElementById('maxMembers').value),
        challenge_type: document.getElementById('challengeType').value,
        duration: parseInt(document.getElementById('duration').value)
    };
    
    // Aqui você implementaria a criação da sala via AJAX
    console.log('Creating room:', formData);
    
    // Simular criação
    showNotification('Sala criada com sucesso!', 'success');
    closeCreateRoomModal();
    
    // Recarregar página após um delay
    setTimeout(() => {
        window.location.reload();
    }, 1500);
}

function joinRoom(roomId, event) {
    event.stopPropagation();
    
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

function leaveRoom(roomId, event) {
    event.stopPropagation();
    
    if (confirm('Deseja sair desta sala de desafio?')) {
        // Aqui você implementaria a saída da sala via AJAX
        console.log('Leaving room:', roomId);
        
        showNotification('Você saiu da sala.', 'info');
        
        // Recarregar página
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }
}

function viewRoomDetails(roomId) {
    window.location.href = `<?php echo BASE_APP_URL; ?>/challenge_room_details.php?id=${roomId}`;
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
