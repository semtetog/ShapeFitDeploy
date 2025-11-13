<?php
// public_html/challenges.php - Página para usuários visualizarem seus desafios

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/challenge_status_helper.php';

$user_id = $_SESSION['user_id'];
$current_date = date('Y-m-d');

// Atualizar status dos desafios automaticamente baseado nas datas
updateChallengeStatusAutomatically($conn);

// Verificar se usuário completou onboarding
$user_profile_data = getUserProfileData($conn, $user_id);
if (!$user_profile_data || !$user_profile_data['onboarding_complete']) {
    header("Location: " . BASE_APP_URL . "/onboarding/onboarding.php");
    exit();
}

// Buscar desafio específico se ID for fornecido
$challenge_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($challenge_id > 0) {
    // Buscar desafio específico (apenas se não estiver inativo)
    $stmt = $conn->prepare("
        SELECT 
            cg.*,
            COUNT(DISTINCT cgm.user_id) as total_participants
        FROM sf_challenge_groups cg
        INNER JOIN sf_challenge_group_members cgm ON cg.id = cgm.group_id
        WHERE cg.id = ? AND cgm.user_id = ? AND cg.status != 'inactive'
        GROUP BY cg.id
    ");
    $stmt->bind_param("ii", $challenge_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        // Desafio não encontrado ou usuário não participa
        header("Location: " . BASE_APP_URL . "/challenges.php");
        exit();
    }
    
    $challenge = $result->fetch_assoc();
    $stmt->close();
    
    // Decodificar goals JSON
    $challenge['goals'] = json_decode($challenge['goals'] ?? '[]', true);
    
    // Buscar progresso do usuário no desafio (hoje)
    $stmt_progress = $conn->prepare("
        SELECT * FROM sf_challenge_group_daily_progress
        WHERE challenge_group_id = ? AND user_id = ? AND date = ?
    ");
    $stmt_progress->bind_param("iis", $challenge_id, $user_id, $current_date);
    $stmt_progress->execute();
    $progress_result = $stmt_progress->get_result();
    $daily_progress = $progress_result->fetch_assoc();
    $stmt_progress->close();
    
    // Buscar estatísticas do usuário no desafio
    $user_total_points = getChallengeGroupTotalPoints($conn, $challenge_id, $user_id);
    
    // Buscar posição do usuário no ranking
    $stmt_user_rank = $conn->prepare("
        SELECT 
            u.id,
            RANK() OVER (ORDER BY COALESCE(SUM(cgdp.points_earned), 0) DESC) as user_rank
        FROM sf_challenge_group_members cgm
        INNER JOIN sf_users u ON cgm.user_id = u.id
        LEFT JOIN sf_challenge_group_daily_progress cgdp ON cgdp.user_id = u.id AND cgdp.challenge_group_id = ?
        WHERE cgm.group_id = ? AND u.id = ?
        GROUP BY u.id
    ");
    $stmt_user_rank->bind_param("iii", $challenge_id, $challenge_id, $user_id);
    $stmt_user_rank->execute();
    $user_rank_result = $stmt_user_rank->get_result();
    $user_rank_data = $user_rank_result->fetch_assoc();
    $user_rank = $user_rank_data['user_rank'] ?? 0;
    $stmt_user_rank->close();
    
    // Buscar progresso médio por meta
    $stmt_avg = $conn->prepare("
        SELECT 
            AVG(calories_consumed) as avg_calories,
            AVG(water_ml) as avg_water,
            AVG(exercise_minutes) as avg_exercise,
            AVG(sleep_hours) as avg_sleep,
            COUNT(*) as days_active
        FROM sf_challenge_group_daily_progress
        WHERE challenge_group_id = ? AND user_id = ?
    ");
    $stmt_avg->bind_param("ii", $challenge_id, $user_id);
    $stmt_avg->execute();
    $avg_result = $stmt_avg->get_result();
    $avg_stats = $avg_result->fetch_assoc();
    $stmt_avg->close();
    
    // Sincronizar progresso antes de buscar ranking
    syncChallengeGroupProgress($conn, $user_id, $current_date);
    
    $stmt_participants = $conn->prepare("
        SELECT 
            u.id,
            u.name,
            up.profile_image_filename,
            up.gender,
            COALESCE(SUM(cgdp.points_earned), 0) as challenge_points
        FROM sf_challenge_group_members cgm
        INNER JOIN sf_users u ON cgm.user_id = u.id
        LEFT JOIN sf_user_profiles up ON u.id = up.user_id
        LEFT JOIN sf_challenge_group_daily_progress cgdp ON cgdp.user_id = u.id AND cgdp.challenge_group_id = ?
        WHERE cgm.group_id = ?
        GROUP BY u.id, u.name, up.profile_image_filename, up.gender
        ORDER BY challenge_points DESC, u.name ASC
        LIMIT 10
    ");
    $stmt_participants->bind_param("ii", $challenge_id, $challenge_id);
    $stmt_participants->execute();
    $participants_result = $stmt_participants->get_result();
    $participants = [];
    while ($row = $participants_result->fetch_assoc()) {
        $participants[] = $row;
    }
    $stmt_participants->close();
    
    $page_title = htmlspecialchars($challenge['name']);
} else {
    // Buscar todos os desafios do usuário (apenas ativos)
    $stmt = $conn->prepare("
        SELECT 
            cg.*,
            COUNT(DISTINCT cgm.user_id) as total_participants
        FROM sf_challenge_groups cg
        INNER JOIN sf_challenge_group_members cgm ON cg.id = cgm.group_id
        WHERE cgm.user_id = ? AND cg.status != 'inactive'
        GROUP BY cg.id
        ORDER BY cg.start_date DESC, cg.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $challenges = [];
    while ($row = $result->fetch_assoc()) {
        $row['goals'] = json_decode($row['goals'] ?? '[]', true);
        $challenges[] = $row;
    }
    $stmt->close();
    
    $page_title = "Meus Desafios";
}

require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* Estilos para a página de desafios */
body {
    background-color: var(--bg-color);
    color: var(--text-primary);
}

.app-container {
    max-width: 600px;
    margin: 0 auto;
    padding: calc(env(safe-area-inset-top, 0px) + 20px) 24px 24px;
}

.page-header {
    display: flex;
    align-items: center;
    margin-bottom: 24px;
    gap: 1rem;
    justify-content: flex-start;
}

/* Quando há título, centralizar e espaçar */
.page-header.has-title {
    justify-content: space-between;
}

.page-header.has-title .page-title {
    flex: 1;
    justify-content: center;
}

.page-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-title i {
    color: var(--accent-orange);
}

.back-button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.back-button:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--accent-orange);
    color: var(--accent-orange);
    transform: translateX(-2px);
}

/* Card de desafio */
.challenge-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.challenge-card:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
    transform: translateY(-2px);
}

.challenge-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.challenge-card-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    flex: 1;
}

.challenge-status {
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
    white-space: nowrap;
}

.challenge-status.active {
    background: rgba(34, 197, 94, 0.15);
    color: #22C55E;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.challenge-status.scheduled {
    background: rgba(255, 193, 7, 0.15);
    color: #FFC107;
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.challenge-status.completed {
    background: rgba(156, 163, 175, 0.15);
    color: #9CA3AF;
    border: 1px solid rgba(156, 163, 175, 0.3);
}

.challenge-description {
    font-size: 1rem;
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 20px;
}

.challenge-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 20px;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.challenge-meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.challenge-meta-item i {
    color: var(--accent-orange);
}

.challenge-progress {
    margin-bottom: 20px;
}

.challenge-progress-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.progress-bar {
    width: 100%;
    height: 8px;
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(135deg, #FF6600, #FF8533);
    border-radius: 4px;
    transition: width 0.5s ease-in-out;
}

.challenge-goals {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 20px;
}

.goal-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 16px;
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.2);
    font-size: 0.85rem;
    color: var(--text-primary);
    font-weight: 500;
}

.goal-badge i {
    color: var(--accent-orange);
}

/* Participantes */
.participants-section {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.participants-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 16px;
}

.participants-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.participant-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
}

.participant-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
}

.participant-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.participant-avatar i {
    color: var(--accent-orange);
    font-size: 1.2rem;
}

.participant-info {
    flex: 1;
    min-width: 0;
}

.participant-name {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.participant-points {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.participant-rank {
    font-weight: 700;
    color: var(--accent-orange);
    font-size: 1rem;
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state-icon {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: rgba(255, 107, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px;
    position: relative;
}

.empty-state-icon i {
    font-size: 3rem;
    color: var(--accent-orange);
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    line-height: 1;
}

.empty-state h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 12px;
}

.empty-state p {
    font-size: 1rem;
    color: var(--text-secondary);
    margin: 0;
    line-height: 1.6;
}

/* Dashboard de Progresso */
.progress-dashboard-section {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.dashboard-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.dashboard-title i {
    color: var(--accent-orange);
}

.progress-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 24px;
}

.progress-stat-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stat-icon i {
    font-size: 1.5rem;
}

.stat-content {
    flex: 1;
    min-width: 0;
}

.stat-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.2;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-top: 4px;
}

.section-subtitle {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 16px;
}

.goals-progress-section {
    margin-bottom: 24px;
}

.goal-progress-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
}

.goal-progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.goal-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.goal-info i {
    color: var(--accent-orange);
    font-size: 1.1rem;
}

.goal-name {
    font-weight: 600;
    color: var(--text-primary);
}

.goal-points {
    display: flex;
    align-items: center;
    gap: 8px;
}

.multiplier-badge {
    background: rgba(255, 107, 0, 0.2);
    color: var(--accent-orange);
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
}

.points-value {
    font-weight: 700;
    color: var(--accent-orange);
    font-size: 0.9rem;
}

.goal-progress-bar-container {
    margin-top: 8px;
}

.goal-progress-bar {
    width: 100%;
    height: 8px;
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 8px;
}

.goal-progress-fill {
    height: 100%;
    background: linear-gradient(135deg, #FF6600, #FF8533);
    border-radius: 4px;
    transition: width 0.5s ease-in-out;
}

.goal-progress-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.goal-consumed {
    font-weight: 600;
    color: var(--text-primary);
}

.goal-percentage {
    font-weight: 600;
    color: var(--accent-orange);
}

@media (max-width: 480px) {
    .progress-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="app-container">
    <div class="page-header <?php echo $challenge_id == 0 ? 'has-title' : ''; ?>">
        <a href="javascript:history.back()" class="back-button" aria-label="Voltar">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php if ($challenge_id == 0): ?>
            <!-- Mostrar título apenas na lista de desafios -->
            <h1 class="page-title">
                <i class="fas fa-trophy"></i>
                <?php echo htmlspecialchars($page_title); ?>
            </h1>
        <?php endif; ?>
    </div>

    <?php if ($challenge_id > 0 && isset($challenge)): ?>
        <!-- Detalhes do desafio específico -->
        <div class="challenge-card">
            <div class="challenge-card-header">
                <h2 class="challenge-card-title"><?php echo htmlspecialchars($challenge['name']); ?></h2>
                <?php
                $start_date = new DateTime($challenge['start_date']);
                $end_date = new DateTime($challenge['end_date']);
                $today = new DateTime();
                $current_status = $challenge['status'];
                
                if ($today < $start_date) {
                    $status_class = 'scheduled';
                    $status_text = 'Agendado';
                } elseif ($today >= $start_date && $today <= $end_date) {
                    $status_class = 'active';
                    $status_text = 'Em andamento';
                } else {
                    $status_class = 'completed';
                    $status_text = 'Concluído';
                }
                ?>
                <span class="challenge-status <?php echo $status_class; ?>">
                    <?php echo $status_text; ?>
                </span>
            </div>
            
            <?php if (!empty($challenge['description'])): ?>
                <p class="challenge-description"><?php echo nl2br(htmlspecialchars($challenge['description'])); ?></p>
            <?php endif; ?>
            
            <div class="challenge-meta">
                <div class="challenge-meta-item">
                    <i class="fas fa-calendar"></i>
                    <span><?php echo $start_date->format('d/m/Y'); ?> - <?php echo $end_date->format('d/m/Y'); ?></span>
                </div>
                <div class="challenge-meta-item">
                    <i class="fas fa-users"></i>
                    <span><?php echo $challenge['total_participants']; ?> participante<?php echo $challenge['total_participants'] > 1 ? 's' : ''; ?></span>
                </div>
            </div>
            
            <?php if ($status_class === 'active'): ?>
                <?php
                $total_days = $start_date->diff($end_date)->days + 1;
                $days_passed = $today > $start_date ? $start_date->diff($today)->days : 0;
                $days_remaining = max(0, $end_date->diff($today)->days);
                $progress_percentage = $total_days > 0 ? min(100, round(($days_passed / $total_days) * 100)) : 0;
                ?>
                <div class="challenge-progress">
                    <div class="challenge-progress-info">
                        <span><?php echo $days_remaining; ?> dia<?php echo $days_remaining > 1 ? 's' : ''; ?> restante<?php echo $days_remaining > 1 ? 's' : ''; ?></span>
                        <span><?php echo $progress_percentage; ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-bar-fill" style="width: <?php echo $progress_percentage; ?>%;"></div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($challenge['goals'])): ?>
                <div class="challenge-goals">
                    <?php foreach ($challenge['goals'] as $goal): ?>
                        <?php
                        $goal_icons = [
                            'calories' => 'fas fa-fire',
                            'water' => 'fas fa-tint',
                            'exercise' => 'fas fa-dumbbell',
                            'sleep' => 'fas fa-bed'
                        ];
                        $goal_labels = [
                            'calories' => 'Calorias',
                            'water' => 'Água',
                            'exercise' => 'Exercício',
                            'sleep' => 'Sono'
                        ];
                        $icon = $goal_icons[$goal['type']] ?? 'fas fa-bullseye';
                        $label = $goal_labels[$goal['type']] ?? ucfirst($goal['type']);
                        ?>
                        <span class="goal-badge">
                            <i class="<?php echo $icon; ?>"></i>
                            <span><?php echo $label; ?></span>
                            <?php if (isset($goal['value'])): ?>
                                <span><?php echo $goal['value']; ?>
                                <?php
                                if ($goal['type'] === 'calories') echo 'kcal';
                                elseif ($goal['type'] === 'water') echo 'ml';
                                elseif ($goal['type'] === 'exercise') echo 'min';
                                elseif ($goal['type'] === 'sleep') echo 'h';
                                ?>
                                </span>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Dashboard de Progresso do Usuário -->
            <div class="progress-dashboard-section">
                <h3 class="dashboard-title">
                    <i class="fas fa-chart-line"></i>
                    Meu Progresso
                </h3>
                
                <!-- Estatísticas Gerais -->
                <div class="progress-stats-grid">
                    <div class="progress-stat-card">
                        <div class="stat-icon" style="background: rgba(255, 107, 0, 0.1);">
                            <i class="fas fa-trophy" style="color: var(--accent-orange);"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($user_total_points, 0, ',', '.'); ?></div>
                            <div class="stat-label">Pontos Totais</div>
                        </div>
                    </div>
                    <div class="progress-stat-card">
                        <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1);">
                            <i class="fas fa-medal" style="color: #22C55E;"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value">#<?php echo $user_rank; ?></div>
                            <div class="stat-label">Posição no Ranking</div>
                        </div>
                    </div>
                    <div class="progress-stat-card">
                        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1);">
                            <i class="fas fa-calendar-check" style="color: #3B82F6;"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $avg_stats['days_active'] ?? 0; ?></div>
                            <div class="stat-label">Dias Ativos</div>
                        </div>
                    </div>
                </div>
                
                <!-- Progresso por Meta (Hoje) -->
                <?php if ($daily_progress && !empty($challenge['goals'])): ?>
                    <div class="goals-progress-section">
                        <h4 class="section-subtitle">Progresso de Hoje</h4>
                        <?php
                        $points_config = getChallengePointsConfig();
                        $multiplier = getChallengePointsMultiplier($current_date);
                        $breakdown = json_decode($daily_progress['points_breakdown'] ?? '{}', true);
                        
                        $goal_icons = [
                            'calories' => 'fas fa-fire',
                            'water' => 'fas fa-tint',
                            'exercise' => 'fas fa-dumbbell',
                            'sleep' => 'fas fa-bed'
                        ];
                        ?>
                        <?php foreach ($challenge['goals'] as $goal): ?>
                            <?php
                            $goal_type = $goal['type'] ?? '';
                            $goal_value = (float)($goal['value'] ?? 0);
                            $config = $points_config[$goal_type] ?? null;
                            
                            if (!$config || $goal_value <= 0) continue;
                            
                            // Obter valores consumidos
                            $consumed = 0;
                            $unit = '';
                            switch ($goal_type) {
                                case 'calories':
                                    $consumed = (float)($daily_progress['calories_consumed'] ?? 0);
                                    $unit = 'kcal';
                                    break;
                                case 'water':
                                    $consumed = (float)($daily_progress['water_ml'] ?? 0);
                                    $unit = 'ml';
                                    break;
                                case 'exercise':
                                    $consumed = (int)($daily_progress['exercise_minutes'] ?? 0);
                                    $unit = 'min';
                                    break;
                                case 'sleep':
                                    $consumed = (float)($daily_progress['sleep_hours'] ?? 0);
                                    $unit = 'h';
                                    break;
                            }
                            
                            $percentage = min(100, ($consumed / $goal_value) * 100);
                            $goal_breakdown = $breakdown[$goal_type] ?? null;
                            $points_today = $goal_breakdown['final_points'] ?? 0;
                            ?>
                            <div class="goal-progress-card">
                                <div class="goal-progress-header">
                                    <div class="goal-info">
                                        <i class="<?php echo $goal_icons[$goal_type] ?? 'fas fa-bullseye'; ?>"></i>
                                        <span class="goal-name"><?php echo $config['label']; ?></span>
                                    </div>
                                    <div class="goal-points">
                                        <?php if ($multiplier > 1): ?>
                                            <span class="multiplier-badge"><?php echo $multiplier; ?>x</span>
                                        <?php endif; ?>
                                        <span class="points-value">+<?php echo $points_today; ?> pts</span>
                                    </div>
                                </div>
                                <div class="goal-progress-bar-container">
                                    <div class="goal-progress-bar">
                                        <div class="goal-progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                    </div>
                                    <div class="goal-progress-info">
                                        <span class="goal-consumed"><?php echo number_format($consumed, $goal_type === 'calories' || $goal_type === 'water' ? 0 : ($goal_type === 'sleep' ? 1 : 0), ',', '.'); ?> <?php echo $unit; ?></span>
                                        <span class="goal-target">/ <?php echo number_format($goal_value, $goal_type === 'calories' || $goal_type === 'water' ? 0 : ($goal_type === 'sleep' ? 1 : 0), ',', '.'); ?> <?php echo $unit; ?></span>
                                        <span class="goal-percentage"><?php echo round($percentage, 1); ?>%</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($participants)): ?>
                <div class="participants-section">
                    <h3 class="participants-title">Participantes</h3>
                    <div class="participants-list">
                        <?php foreach ($participants as $index => $participant): ?>
                            <?php
                            $rank = $index + 1;
                            $has_photo = !empty($participant['profile_image_filename']);
                            $avatar_url = $has_photo 
                                : '';
                            
                            // Verificar se arquivo existe antes de usar
                            $avatar_url = '';
                            if (!empty($participant['profile_image_filename'])) {
                                $image_path = APP_ROOT_PATH . '/assets/images/users/' . $participant['profile_image_filename'];
                                if (file_exists($image_path)) {
                                    $avatar_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($participant['profile_image_filename']);
                                } else {
                                    // Tentar thumbnail
                                    $thumb_filename = 'thumb_' . $participant['profile_image_filename'];
                                    $thumb_path = APP_ROOT_PATH . '/assets/images/users/' . $thumb_filename;
                                    if (file_exists($thumb_path)) {
                                        $avatar_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($thumb_filename);
                                    }
                                }
                            }
                            
                            // Gerar iniciais
                            $initials = '';
                            $name_parts = explode(' ', $participant['name']);
                            if (count($name_parts) >= 2) {
                                $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[count($name_parts) - 1], 0, 1));
                            } else {
                                $initials = strtoupper(substr($participant['name'], 0, 2));
                            }
                            
                            // Cor de fundo baseada no nome
                            $colors = ['#FF6B00', '#3B82F6', '#22C55E', '#A855F7', '#EC4899', '#F59E0B'];
                            $colorIndex = crc32($participant['name']) % count($colors);
                            $bgColor = $colors[$colorIndex];
                            ?>
                            <div class="participant-item">
                                <div class="participant-rank">#<?php echo $rank; ?></div>
                                <div class="participant-avatar" style="background-color: <?php echo $has_photo ? 'transparent' : $bgColor; ?>; color: <?php echo $has_photo ? 'var(--accent-orange)' : 'white'; ?>;">
                                    <?php if ($has_photo): ?>
                                        <img src="<?php echo $avatar_url; ?>" alt="Foto de <?php echo htmlspecialchars($participant['name']); ?>">
                                    <?php else: ?>
                                        <?php echo $initials; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="participant-info">
                                    <div class="participant-name"><?php echo htmlspecialchars($participant['name']); ?></div>
                                    <div class="participant-points"><?php echo number_format($participant['challenge_points'], 0, ',', '.'); ?> pontos</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
    <?php else: ?>
        <!-- Lista de desafios -->
        <?php if (empty($challenges)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <h3>Nenhum desafio encontrado</h3>
                <p>Você não foi adicionado(a) em nenhum grupo de desafios. Consulte seu nutricionista para mais informações.</p>
            </div>
        <?php else: ?>
            <?php foreach ($challenges as $challenge): ?>
                <?php
                $start_date = new DateTime($challenge['start_date']);
                $end_date = new DateTime($challenge['end_date']);
                $today = new DateTime();
                
                if ($today < $start_date) {
                    $status_class = 'scheduled';
                    $status_text = 'Agendado';
                } elseif ($today >= $start_date && $today <= $end_date) {
                    $status_class = 'active';
                    $status_text = 'Em andamento';
                } else {
                    $status_class = 'completed';
                    $status_text = 'Concluído';
                }
                
                $total_days = $start_date->diff($end_date)->days + 1;
                $days_passed = $today > $start_date ? $start_date->diff($today)->days : 0;
                $days_remaining = max(0, $end_date->diff($today)->days);
                $progress_percentage = $total_days > 0 ? min(100, round(($days_passed / $total_days) * 100)) : 0;
                ?>
                <a href="<?php echo BASE_APP_URL; ?>/challenges.php?id=<?php echo $challenge['id']; ?>" class="challenge-card" style="text-decoration: none; color: inherit; display: block;">
                    <div class="challenge-card-header">
                        <h2 class="challenge-card-title"><?php echo htmlspecialchars($challenge['name']); ?></h2>
                        <span class="challenge-status <?php echo $status_class; ?>">
                            <?php echo $status_text; ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($challenge['description'])): ?>
                        <p class="challenge-description"><?php echo htmlspecialchars(substr($challenge['description'], 0, 150)); ?><?php echo strlen($challenge['description']) > 150 ? '...' : ''; ?></p>
                    <?php endif; ?>
                    
                    <div class="challenge-meta">
                        <div class="challenge-meta-item">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo $start_date->format('d/m/Y'); ?> - <?php echo $end_date->format('d/m/Y'); ?></span>
                        </div>
                        <div class="challenge-meta-item">
                            <i class="fas fa-users"></i>
                            <span><?php echo $challenge['total_participants']; ?> participante<?php echo $challenge['total_participants'] > 1 ? 's' : ''; ?></span>
                        </div>
                    </div>
                    
                    <?php if ($status_class === 'active'): ?>
                        <div class="challenge-progress">
                            <div class="challenge-progress-info">
                                <span><?php echo $days_remaining; ?> dia<?php echo $days_remaining > 1 ? 's' : ''; ?> restante<?php echo $days_remaining > 1 ? 's' : ''; ?></span>
                                <span><?php echo $progress_percentage; ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-bar-fill" style="width: <?php echo $progress_percentage; ?>%;"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($challenge['goals'])): ?>
                        <div class="challenge-goals">
                            <?php foreach ($challenge['goals'] as $goal): ?>
                                <?php
                                $goal_icons = [
                                    'calories' => 'fas fa-fire',
                                    'water' => 'fas fa-tint',
                                    'exercise' => 'fas fa-dumbbell',
                                    'sleep' => 'fas fa-bed'
                                ];
                                $goal_labels = [
                                    'calories' => 'Calorias',
                                    'water' => 'Água',
                                    'exercise' => 'Exercício',
                                    'sleep' => 'Sono'
                                ];
                                $icon = $goal_icons[$goal['type']] ?? 'fas fa-bullseye';
                                $label = $goal_labels[$goal['type']] ?? ucfirst($goal['type']);
                                ?>
                                <span class="goal-badge">
                                    <i class="<?php echo $icon; ?>"></i>
                                    <span><?php echo $label; ?></span>
                                    <?php if (isset($goal['value'])): ?>
                                        <span><?php echo $goal['value']; ?>
                                        <?php
                                        if ($goal['type'] === 'calories') echo 'kcal';
                                        elseif ($goal['type'] === 'water') echo 'ml';
                                        elseif ($goal['type'] === 'exercise') echo 'min';
                                        elseif ($goal['type'] === 'sleep') echo 'h';
                                        ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once APP_ROOT_PATH . '/includes/layout_footer.php'; ?>

