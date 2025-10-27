<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$room_id = $_GET['id'] ?? null;

if (!$room_id) {
    header("Location: " . BASE_APP_URL . "/challenge_rooms_new.php");
    exit();
}

// Verificar se o usuário é membro da sala
$stmt_check = $conn->prepare("
    SELECT crm.*, cr.* 
    FROM sf_challenge_room_members crm
    INNER JOIN sf_challenge_rooms cr ON crm.challenge_room_id = cr.id
    WHERE crm.challenge_room_id = ? AND crm.user_id = ?
");
$stmt_check->bind_param("ii", $room_id, $user_id);
$stmt_check->execute();
$room_data = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

if (!$room_data) {
    header("Location: " . BASE_APP_URL . "/challenge_rooms_new.php");
    exit();
}

// Buscar ranking da sala
$stmt_ranking = $conn->prepare("
    SELECT 
        u.id,
        u.name,
        up.profile_image_filename,
        crm.total_points,
        crm.joined_at,
        ROW_NUMBER() OVER (ORDER BY crm.total_points DESC) as position
    FROM sf_challenge_room_members crm
    INNER JOIN sf_users u ON crm.user_id = u.id
    LEFT JOIN sf_user_profiles up ON u.id = up.user_id
    WHERE crm.challenge_room_id = ? AND crm.status = 'active'
    ORDER BY crm.total_points DESC
");
$stmt_ranking->bind_param("i", $room_id);
$stmt_ranking->execute();
$ranking = $stmt_ranking->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_ranking->close();

// Encontrar posição do usuário
$user_position = null;
foreach ($ranking as $index => $user) {
    if ($user['id'] == $user_id) {
        $user_position = $index + 1;
        break;
    }
}

// Buscar progresso diário do usuário (últimos 7 dias)
$stmt_progress = $conn->prepare("
    SELECT 
        date,
        steps_count,
        exercise_minutes,
        water_cups,
        calories_consumed,
        points_earned
    FROM sf_challenge_daily_progress
    WHERE challenge_room_id = ? AND user_id = ?
    ORDER BY date DESC
    LIMIT 7
");
$stmt_progress->bind_param("ii", $room_id, $user_id);
$stmt_progress->execute();
$daily_progress = $stmt_progress->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_progress->close();

$page_title = $room_data['name'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* Estilos para detalhes da sala de desafio */
.room-details-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.room-header {
    background: var(--surface-color);
    border-radius: 20px;
    padding: 32px;
    margin-bottom: 32px;
    border: 1px solid var(--border-color);
    text-align: center;
}

.room-title {
    font-size: 2.2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 12px 0;
    background: var(--primary-orange-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.room-description {
    color: var(--text-secondary);
    font-size: 1.1rem;
    line-height: 1.6;
    margin: 0 0 24px 0;
}

.room-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.meta-item {
    text-align: center;
    padding: 20px;
    background: rgba(255, 107, 53, 0.05);
    border-radius: 16px;
    border: 1px solid rgba(255, 107, 53, 0.1);
}

.meta-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--accent-orange);
    margin: 0 0 8px 0;
}

.meta-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin: 0;
}

.content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 32px;
    margin-bottom: 32px;
}

.content-card {
    background: var(--surface-color);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.card-header {
    padding: 24px;
    border-bottom: 1px solid var(--border-color);
    background: rgba(255, 107, 53, 0.05);
}

.card-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.card-title i {
    color: var(--accent-orange);
}

.ranking-list {
    max-height: 400px;
    overflow-y: auto;
}

.ranking-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 24px;
    border-bottom: 1px solid var(--border-color);
    transition: all 0.2s ease;
}

.ranking-item:hover {
    background: rgba(255, 107, 53, 0.05);
}

.ranking-item:last-child {
    border-bottom: none;
}

.ranking-position {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
}

.ranking-position.first {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: white;
}

.ranking-position.second {
    background: linear-gradient(135deg, #C0C0C0, #A0A0A0);
    color: white;
}

.ranking-position.third {
    background: linear-gradient(135deg, #CD7F32, #B8860B);
    color: white;
}

.ranking-position.other {
    background: var(--border-color);
    color: var(--text-secondary);
}

.user-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-avatar i {
    color: var(--accent-orange);
    font-size: 1.2rem;
}

.user-info {
    flex: 1;
}

.user-name {
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 4px 0;
}

.user-points {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin: 0;
}

.progress-chart {
    padding: 24px;
}

.chart-container {
    height: 200px;
    display: flex;
    align-items: end;
    gap: 8px;
    margin-bottom: 16px;
}

.chart-bar {
    flex: 1;
    background: var(--primary-orange-gradient);
    border-radius: 4px 4px 0 0;
    min-height: 4px;
    transition: all 0.3s ease;
}

.chart-bar:hover {
    filter: brightness(1.1);
}

.chart-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.goals-section {
    padding: 24px;
}

.goals-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 16px 0;
}

.goals-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.goal-card {
    padding: 16px;
    background: rgba(255, 107, 53, 0.05);
    border-radius: 12px;
    border: 1px solid rgba(255, 107, 53, 0.1);
    text-align: center;
}

.goal-icon {
    font-size: 1.5rem;
    color: var(--accent-orange);
    margin-bottom: 8px;
}

.goal-value {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 4px 0;
}

.goal-label {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .room-details-container {
        padding: 16px;
    }
    
    .room-meta {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    
    .content-grid {
        grid-template-columns: 1fr;
        gap: 24px;
    }
    
    .goals-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="app-container">
    <div class="room-details-container">
        <!-- Header da Sala -->
        <div class="room-header">
            <h1 class="room-title"><?php echo htmlspecialchars($room_data['name']); ?></h1>
            <p class="room-description"><?php echo htmlspecialchars($room_data['description']); ?></p>
            
            <div class="room-meta">
                <div class="meta-item">
                    <div class="meta-value"><?php echo number_format($room_data['total_points']); ?></div>
                    <div class="meta-label">Seus Pontos</div>
                </div>
                <div class="meta-item">
                    <div class="meta-value"><?php echo $user_position; ?>º</div>
                    <div class="meta-label">Sua Posição</div>
                </div>
                <div class="meta-item">
                    <div class="meta-value"><?php echo count($ranking); ?></div>
                    <div class="meta-label">Participantes</div>
                </div>
                <div class="meta-item">
                    <div class="meta-value"><?php echo date('d/m', strtotime($room_data['end_date'])); ?></div>
                    <div class="meta-label">Data Final</div>
                </div>
            </div>
        </div>

        <!-- Grid de Conteúdo -->
        <div class="content-grid">
            <!-- Ranking -->
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-trophy"></i>
                        Ranking da Sala
                    </h3>
                </div>
                <div class="ranking-list">
                    <?php foreach (array_slice($ranking, 0, 10) as $index => $user): ?>
                        <div class="ranking-item">
                            <div class="ranking-position <?php echo $index < 3 ? ['first', 'second', 'third'][$index] : 'other'; ?>">
                                <?php echo $index + 1; ?>
                            </div>
                            
                            <div class="user-avatar">
                                <?php if (!empty($user['profile_image_filename'])): ?>
                                    <img src="<?php echo BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($user['profile_image_filename']); ?>" alt="Avatar">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            
                            <div class="user-info">
                                <h4 class="user-name"><?php echo htmlspecialchars($user['name']); ?></h4>
                                <p class="user-points"><?php echo number_format($user['total_points']); ?> pontos</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Progresso Diário -->
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-line"></i>
                        Seu Progresso (7 dias)
                    </h3>
                </div>
                <div class="progress-chart">
                    <?php if (!empty($daily_progress)): ?>
                        <div class="chart-container">
                            <?php 
                            $max_points = max(array_column($daily_progress, 'points_earned'));
                            foreach (array_reverse($daily_progress) as $day): 
                                $height = $max_points > 0 ? ($day['points_earned'] / $max_points) * 100 : 0;
                            ?>
                                <div class="chart-bar" style="height: <?php echo $height; ?>%" title="<?php echo $day['points_earned']; ?> pontos em <?php echo date('d/m', strtotime($day['date'])); ?>"></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="chart-labels">
                            <span><?php echo date('d/m', strtotime($daily_progress[count($daily_progress)-1]['date'])); ?></span>
                            <span><?php echo date('d/m', strtotime($daily_progress[0]['date'])); ?></span>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                            <i class="fas fa-chart-line" style="font-size: 2rem; margin-bottom: 16px;"></i>
                            <p>Nenhum progresso registrado ainda</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Metas do Desafio -->
        <?php if ($room_data['goals']): ?>
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-target"></i>
                        Metas do Desafio
                    </h3>
                </div>
                <div class="goals-section">
                    <div class="goals-grid">
                        <?php 
                        $goals = json_decode($room_data['goals'], true);
                        foreach ($goals as $goal_type => $goal_value): 
                        ?>
                            <div class="goal-card">
                                <div class="goal-icon">
                                    <i class="fas fa-<?php echo getGoalIcon($goal_type); ?>"></i>
                                </div>
                                <div class="goal-value"><?php echo $goal_value; ?></div>
                                <div class="goal-label"><?php echo getGoalLabel($goal_type); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Funções auxiliares
function getGoalIcon($goalType) {
    $icons = [
        'steps' => 'walking',
        'exercise' => 'dumbbell',
        'water' => 'tint',
        'calories' => 'fire'
    ];
    return $icons[$goalType] ?? 'target';
}

function getGoalLabel($goalType) {
    $labels = [
        'steps' => 'Passos',
        'exercise' => 'Exercício (min)',
        'water' => 'Água (copos)',
        'calories' => 'Calorias'
    ];
    return $labels[$goalType] ?? $goalType;
}
?>

<?php
require_once APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>
