<?php
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Salas de Desafio';
$page_slug = 'challenge_groups';

// Executar SQL das tabelas se necessário
$conn = require __DIR__ . '/../includes/db.php';
$sql_file = __DIR__ . '/sql/challenge_rooms_system.sql';
if (file_exists($sql_file)) {
    $sql = file_get_contents($sql_file);
    $conn->multi_query($sql);
    while ($conn->next_result()) {;} // Consumir todos os resultados
}

// Buscar salas de desafio
$admin_id = $_SESSION['admin_id'];
$rooms_query = "SELECT 
    cr.*,
    COUNT(crm.user_id) as member_count,
    a.full_name as admin_name
FROM sf_challenge_rooms cr
LEFT JOIN sf_challenge_room_members crm ON cr.id = crm.challenge_room_id
LEFT JOIN sf_admins a ON cr.created_by = a.id
WHERE cr.created_by = ?
GROUP BY cr.id
ORDER BY cr.created_at DESC";
$stmt_rooms = $conn->prepare($rooms_query);
$stmt_rooms->bind_param("i", $admin_id);
$stmt_rooms->execute();
$challenge_rooms = $stmt_rooms->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_rooms->close();

// Buscar usuários para adicionar aos grupos
$users_query = "SELECT u.id, u.name, u.email, up.profile_image_filename 
                FROM sf_users u 
                LEFT JOIN sf_user_profiles up ON u.id = up.user_id 
                WHERE u.status = 'active'
                ORDER BY u.name";
$users_result = $conn->query($users_query);
$users = $users_result->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-wrapper">
    <div class="main-content">
        
        <div class="main-header">
            <div class="header-content">
                <h1><i class="fas fa-trophy"></i> Salas de Desafio</h1>
                <p class="header-subtitle">Crie e gerencie grupos de desafio para motivar seus pacientes</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openCreateRoomModal()">
                    <i class="fas fa-plus"></i> Criar Sala de Desafio
                </button>
            </div>
        </div>

        <!-- Estatísticas Gerais -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo count($challenge_rooms); ?></h3>
                    <p>Salas Criadas</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo array_sum(array_column($challenge_rooms, 'member_count')); ?></h3>
                    <p>Total de Participantes</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo count(array_filter($challenge_rooms, function($room) { return $room['status'] === 'active'; })); ?></h3>
                    <p>Salas Ativas</p>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filtros</h3>
            </div>
            <div class="filters-grid">
                <div class="filter-group">
                    <label>Status</label>
                    <select id="statusFilter" class="form-control" onchange="filterRooms()">
                        <option value="">Todos</option>
                        <option value="active">Ativas</option>
                        <option value="inactive">Inativas</option>
                        <option value="completed">Concluídas</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Buscar</label>
                    <input type="text" id="searchFilter" class="form-control" placeholder="Nome da sala..." onkeyup="filterRooms()">
                </div>
            </div>
        </div>

        <!-- Grid de Salas -->
        <div class="rooms-grid" id="roomsGrid">
            <?php if (empty($challenge_rooms)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3>Nenhuma sala de desafio criada</h3>
                    <p>Crie sua primeira sala para começar a motivar seus pacientes</p>
                    <button class="btn btn-primary" onclick="openCreateRoomModal()">
                        <i class="fas fa-plus"></i> Criar Primeira Sala
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($challenge_rooms as $room): ?>
                    <div class="room-card" data-status="<?php echo $room['status']; ?>">
                        <div class="room-header">
                            <div class="room-title">
                                <h3><?php echo htmlspecialchars($room['name']); ?></h3>
                                <span class="room-status <?php echo $room['status']; ?>">
                                    <i class="fas fa-circle"></i>
                                    <?php echo ucfirst($room['status']); ?>
                                </span>
                            </div>
                            <div class="room-actions">
                                <button class="btn-icon" onclick="editRoom(<?php echo $room['id']; ?>)" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon" onclick="manageMembers(<?php echo $room['id']; ?>)" title="Gerenciar Membros">
                                    <i class="fas fa-users"></i>
                                </button>
                                <button class="btn-icon" onclick="viewRoomDetails(<?php echo $room['id']; ?>)" title="Ver Detalhes">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="room-body">
                            <p class="room-description"><?php echo htmlspecialchars($room['description']); ?></p>
                            
                            <div class="room-stats">
                                <div class="stat">
                                    <i class="fas fa-users"></i>
                                    <span><?php echo $room['member_count']; ?> participantes</span>
                                </div>
                                <div class="stat">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('d/m/Y', strtotime($room['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($room['end_date'])); ?></span>
                                </div>
                                <div class="stat">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo $room['status'] === 'active' ? 'Em andamento' : 'Finalizada'; ?></span>
                                </div>
                            </div>
                            
                            <?php if ($room['goals']): ?>
                                <div class="room-goals">
                                    <h4>Metas do Desafio:</h4>
                                    <div class="goals-list">
                                        <?php 
                                        $goals = json_decode($room['goals'], true);
                                        foreach ($goals as $goal_type => $goal_value): 
                                        ?>
                                            <div class="goal-item">
                                                <i class="fas fa-<?php echo getGoalIcon($goal_type); ?>"></i>
                                                <span><?php echo getGoalLabel($goal_type); ?>: <?php echo $goal_value; ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de Criar/Editar Sala -->
<div class="modal-overlay" id="roomModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Criar Sala de Desafio</h3>
            <button class="modal-close" onclick="closeRoomModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="roomForm" onsubmit="saveRoom(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label for="roomName">Nome da Sala</label>
                    <input type="text" id="roomName" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="roomDescription">Descrição</label>
                    <textarea id="roomDescription" name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="startDate">Data de Início</label>
                        <input type="date" id="startDate" name="start_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="endDate">Data de Fim</label>
                        <input type="date" id="endDate" name="end_date" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="maxParticipants">Máximo de Participantes</label>
                    <input type="number" id="maxParticipants" name="max_participants" class="form-control" value="50" min="2" max="100">
                </div>
                
                <div class="form-group">
                    <label>Metas do Desafio</label>
                    <div class="goals-config">
                        <div class="goal-item-config">
                            <label>
                                <input type="checkbox" name="goals[steps]" value="1">
                                <i class="fas fa-walking"></i> Passos Diários
                            </label>
                            <input type="number" name="goals_steps_value" placeholder="Ex: 10000" class="form-control">
                        </div>
                        <div class="goal-item-config">
                            <label>
                                <input type="checkbox" name="goals[exercise]" value="1">
                                <i class="fas fa-dumbbell"></i> Minutos de Exercício
                            </label>
                            <input type="number" name="goals_exercise_value" placeholder="Ex: 30" class="form-control">
                        </div>
                        <div class="goal-item-config">
                            <label>
                                <input type="checkbox" name="goals[water]" value="1">
                                <i class="fas fa-tint"></i> Copos de Água
                            </label>
                            <input type="number" name="goals_water_value" placeholder="Ex: 8" class="form-control">
                        </div>
                        <div class="goal-item-config">
                            <label>
                                <input type="checkbox" name="goals[calories]" value="1">
                                <i class="fas fa-fire"></i> Calorias Consumidas
                            </label>
                            <input type="number" name="goals_calories_value" placeholder="Ex: 2000" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeRoomModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar Sala</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Gerenciar Membros -->
<div class="modal-overlay" id="membersModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Gerenciar Membros</h3>
            <button class="modal-close" onclick="closeMembersModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="members-section">
                <h4>Adicionar Membros</h4>
                <div class="search-box">
                    <input type="text" id="userSearch" placeholder="Buscar usuários..." class="form-control">
                </div>
                <div class="users-list" id="usersList">
                    <!-- Lista de usuários será carregada aqui -->
                </div>
            </div>
            
            <div class="members-section">
                <h4>Membros Atuais</h4>
                <div class="current-members" id="currentMembers">
                    <!-- Membros atuais serão carregados aqui -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Funções JavaScript para gerenciar salas de desafio
function openCreateRoomModal() {
    document.getElementById('modalTitle').textContent = 'Criar Sala de Desafio';
    document.getElementById('roomForm').reset();
    document.getElementById('roomModal').classList.add('active');
}

function closeRoomModal() {
    document.getElementById('roomModal').classList.remove('active');
}

function saveRoom(event) {
    event.preventDefault();
    // Implementar salvamento da sala
    console.log('Salvando sala...');
}

function manageMembers(roomId) {
    // Implementar gerenciamento de membros
    console.log('Gerenciando membros da sala:', roomId);
}

function viewRoomDetails(roomId) {
    // Implementar visualização de detalhes
    console.log('Ver detalhes da sala:', roomId);
}

function filterRooms() {
    const statusFilter = document.getElementById('statusFilter').value;
    const searchFilter = document.getElementById('searchFilter').value.toLowerCase();
    const rooms = document.querySelectorAll('.room-card');
    
    rooms.forEach(room => {
        const status = room.dataset.status;
        const title = room.querySelector('h3').textContent.toLowerCase();
        
        const statusMatch = !statusFilter || status === statusFilter;
        const searchMatch = !searchFilter || title.includes(searchFilter);
        
        room.style.display = (statusMatch && searchMatch) ? 'block' : 'none';
    });
}

// Funções auxiliares para metas
function getGoalIcon(goalType) {
    const icons = {
        'steps': 'walking',
        'exercise': 'dumbbell',
        'water': 'tint',
        'calories': 'fire'
    };
    return icons[goalType] || 'target';
}

function getGoalLabel(goalType) {
    const labels = {
        'steps': 'Passos',
        'exercise': 'Exercício (min)',
        'water': 'Água (copos)',
        'calories': 'Calorias'
    };
    return labels[goalType] || goalType;
}
</script>

<style>
/* Estilos para o sistema de salas de desafio */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--surface-color);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    border: 1px solid var(--border-color);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary-orange-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.stat-content h3 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-primary);
}

.stat-content p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.rooms-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.room-card {
    background: var(--surface-color);
    border-radius: 16px;
    border: 1px solid var(--border-color);
    overflow: hidden;
    transition: all 0.3s ease;
}

.room-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
}

.room-header {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.room-title h3 {
    margin: 0 0 8px 0;
    color: var(--text-primary);
    font-size: 1.2rem;
}

.room-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.room-status.active {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
}

.room-status.inactive {
    background: rgba(156, 163, 175, 0.1);
    color: #9ca3af;
}

.room-status.completed {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.room-actions {
    display: flex;
    gap: 8px;
}

.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--surface-color);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.btn-icon:hover {
    background: var(--accent-orange);
    color: white;
    border-color: var(--accent-orange);
}

.room-body {
    padding: 20px;
}

.room-description {
    color: var(--text-secondary);
    margin-bottom: 20px;
    line-height: 1.5;
}

.room-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.stat i {
    color: var(--accent-orange);
    width: 16px;
}

.room-goals h4 {
    margin: 0 0 12px 0;
    color: var(--text-primary);
    font-size: 1rem;
}

.goals-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.goal-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: rgba(255, 107, 53, 0.05);
    border-radius: 8px;
    font-size: 0.9rem;
}

.goal-item i {
    color: var(--accent-orange);
    width: 16px;
}

.goals-config {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.goal-item-config {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
}

.goal-item-config label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    flex: 1;
}

.goal-item-config input[type="checkbox"] {
    margin: 0;
}

.goal-item-config input[type="number"] {
    width: 100px;
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
}

.empty-icon {
    font-size: 4rem;
    color: var(--text-secondary);
    margin-bottom: 20px;
}

.empty-state h3 {
    margin: 0 0 12px 0;
    color: var(--text-primary);
}

.empty-state p {
    color: var(--text-secondary);
    margin-bottom: 24px;
}

@media (max-width: 768px) {
    .rooms-grid {
        grid-template-columns: 1fr;
    }
    
    .room-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .room-actions {
        align-self: flex-end;
    }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
