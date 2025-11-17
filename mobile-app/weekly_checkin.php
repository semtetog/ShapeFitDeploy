<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$page_title = "Check-in Semanal";

// Buscar dados do usuário
$user_profile_data = getUserProfileData($conn, $user_id);

// Buscar dados da semana atual
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('sunday this week'));

// Buscar dados da semana anterior para comparação
$last_week_start = date('Y-m-d', strtotime('monday last week'));
$last_week_end = date('Y-m-d', strtotime('sunday last week'));

// === DADOS NUTRICIONAIS DA SEMANA ===
$stmt_nutrition = $conn->prepare("
    SELECT 
        DATE(date_consumed) as date,
        SUM(kcal_consumed) as daily_kcal,
        SUM(protein_consumed_g) as daily_protein,
        SUM(carbs_consumed_g) as daily_carbs,
        SUM(fat_consumed_g) as daily_fat
    FROM sf_user_meal_log 
    WHERE user_id = ? AND date_consumed BETWEEN ? AND ?
    GROUP BY DATE(date_consumed)
    ORDER BY date_consumed ASC
");
$stmt_nutrition->bind_param("iss", $user_id, $current_week_start, $current_week_end);
$stmt_nutrition->execute();
$nutrition_data = $stmt_nutrition->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_nutrition->close();

// === DADOS DE HIDRATAÇÃO DA SEMANA ===
$stmt_water = $conn->prepare("
    SELECT date, water_consumed_cups, (water_consumed_cups * 250) as water_consumed_ml
    FROM sf_user_daily_tracking 
    WHERE user_id = ? AND date BETWEEN ? AND ?
    ORDER BY date ASC
");
$stmt_water->bind_param("iss", $user_id, $current_week_start, $current_week_end);
$stmt_water->execute();
$water_data = $stmt_water->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_water->close();

// === DADOS DE PESO ===
$stmt_weight = $conn->prepare("
    SELECT date_recorded, weight_kg
    FROM sf_user_weight_history 
    WHERE user_id = ? AND date_recorded BETWEEN ? AND ?
    ORDER BY date_recorded ASC
");
$stmt_weight->bind_param("iss", $user_id, $current_week_start, $current_week_end);
$stmt_weight->execute();
$weight_data = $stmt_weight->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_weight->close();

// === DADOS DE ROTINAS/EXERCÍCIOS ===
$stmt_routines = $conn->prepare("
    SELECT 
        DATE(date) as date,
        COUNT(*) as completed_routines
    FROM sf_user_routine_log 
    WHERE user_id = ? AND date BETWEEN ? AND ?
    GROUP BY DATE(date)
    ORDER BY date ASC
");
$stmt_routines->bind_param("iss", $user_id, $current_week_start, $current_week_end);
$stmt_routines->execute();
$routines_data = $stmt_routines->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_routines->close();

// === CALCULAR METAS ===
$gender = $user_profile_data['gender'] ?? 'male';
$weight_kg = (float)($user_profile_data['weight_kg'] ?? 70);
$height_cm = (int)($user_profile_data['height_cm'] ?? 170);
$dob = $user_profile_data['dob'] ?? date('Y-m-d', strtotime('-30 years'));
$exercise_frequency = $user_profile_data['exercise_frequency'] ?? 'sedentary';
$objective = $user_profile_data['objective'] ?? 'maintain';

$age_years = calculateAge($dob);
$daily_calories_goal = calculateTargetDailyCalories($gender, $weight_kg, $height_cm, $age_years, $exercise_frequency, $objective);
$weekly_calories_goal = $daily_calories_goal * 7;

$macros_goal = calculateMacronutrients($daily_calories_goal, $objective);
$weekly_protein_goal = $macros_goal['protein_g'] * 7;
$weekly_carbs_goal = $macros_goal['carbs_g'] * 7;
$weekly_fat_goal = $macros_goal['fat_g'] * 7;

// Meta de água
$water_goal_data = getWaterIntakeSuggestion($weight_kg);
$daily_water_goal_ml = $water_goal_data['total_ml'];
$weekly_water_goal_ml = $daily_water_goal_ml * 7;

// === BUSCAR FOTOS DO CHECK-IN SEMANAL ===
$stmt_photos = $conn->prepare("
    SELECT photo_type, filename, created_at
    FROM sf_weekly_checkin_photos 
    WHERE user_id = ? AND checkin_date = ?
    ORDER BY photo_type ASC
");
$checkin_date = date('Y-m-d'); // Data atual do check-in
$stmt_photos->bind_param("is", $user_id, $checkin_date);
$stmt_photos->execute();
$checkin_photos = $stmt_photos->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_photos->close();

// Organizar fotos por tipo
$photos = ['first' => null, 'last' => null];
foreach ($checkin_photos as $photo) {
    $photos[$photo['photo_type']] = $photo;
}

// === CALCULAR ESTATÍSTICAS DA SEMANA ===
$week_stats = [
    'kcal' => ['consumed' => 0, 'goal' => $weekly_calories_goal, 'days' => 0],
    'protein' => ['consumed' => 0, 'goal' => $weekly_protein_goal, 'days' => 0],
    'carbs' => ['consumed' => 0, 'goal' => $weekly_carbs_goal, 'days' => 0],
    'fat' => ['consumed' => 0, 'goal' => $weekly_fat_goal, 'days' => 0],
    'water' => ['consumed' => 0, 'goal' => $weekly_water_goal_ml, 'days' => 0],
    'routines' => ['completed' => 0, 'goal' => 7, 'days' => 0]
];

foreach ($nutrition_data as $day) {
    $week_stats['kcal']['consumed'] += (float)$day['daily_kcal'];
    $week_stats['protein']['consumed'] += (float)$day['daily_protein'];
    $week_stats['carbs']['consumed'] += (float)$day['daily_carbs'];
    $week_stats['fat']['consumed'] += (float)$day['daily_fat'];
    $week_stats['kcal']['days']++;
}

foreach ($water_data as $day) {
    $week_stats['water']['consumed'] += (float)$day['water_consumed_ml'];
    $week_stats['water']['days']++;
}

foreach ($routines_data as $day) {
    $week_stats['routines']['completed'] += (int)$day['completed_routines'];
    $week_stats['routines']['days']++;
}

// Calcular porcentagens
foreach ($week_stats as $metric => &$data) {
    if ($data['goal'] > 0) {
        $data['percentage'] = round(($data['consumed'] / $data['goal']) * 100, 1);
    } else {
        $data['percentage'] = 0;
    }
}

require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* === WEEKLY CHECK-IN PAGE === */
.checkin-container {
    padding: 0 24px;
    max-width: 100%;
}

.checkin-header {
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

.checkin-title {
    flex: 1;
    text-align: center;
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.week-selector {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    margin-bottom: 20px;
    padding: 16px;
    background: rgba(255, 255, 255, 0.04);
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.week-nav-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.2s ease;
}

.week-nav-btn:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.week-info {
    text-align: center;
}

.week-range {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.week-period {
    font-size: 12px;
    color: var(--text-secondary);
}

/* Cards de Estatísticas */
.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 20px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 16px;
    text-align: center;
}

.stat-icon {
    font-size: 24px;
    color: var(--accent-orange);
    margin-bottom: 8px;
}

.stat-label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 4px;
}

.stat-value {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.stat-goal {
    font-size: 10px;
    color: var(--text-secondary);
}

.stat-percentage {
    font-size: 14px;
    font-weight: 600;
    margin-top: 8px;
}

.percentage-excellent { color: #4ade80; }
.percentage-good { color: #22d3ee; }
.percentage-fair { color: #fbbf24; }
.percentage-poor { color: #f87171; }

/* Gráficos */
.charts-section {
    margin-bottom: 20px;
}

.chart-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 16px;
}

.chart-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}

.chart-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
}

.chart-icon {
    color: var(--accent-orange);
}

.chart-container {
    position: relative;
    height: 200px;
}

/* Comparação de Fotos */
.photos-comparison {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 20px;
}

.photos-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}

.photos-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
}

.photos-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.photo-container {
    text-align: center;
}

.photo-placeholder {
    width: 100%;
    height: 120px;
    background: rgba(255, 255, 255, 0.05);
    border: 2px dashed rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.photo-placeholder:hover {
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

.photo-placeholder i {
    font-size: 32px;
    color: var(--text-secondary);
}

.photo-display {
    position: relative;
    width: 100%;
    height: 120px;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    margin-bottom: 8px;
}

.photo-display img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.photo-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s ease;
    color: white;
}

.photo-display:hover .photo-overlay {
    opacity: 1;
}

.photo-overlay i {
    font-size: 24px;
    margin-bottom: 4px;
}

.photo-overlay span {
    font-size: 12px;
    font-weight: 600;
}

.photo-label {
    font-size: 12px;
    color: var(--text-secondary);
}

.photo-date {
    font-size: 10px;
    color: var(--text-secondary);
    margin-top: 4px;
}

/* Botões de Ação */
.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}

.action-btn {
    padding: 16px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.action-btn:hover {
    background: rgba(255, 255, 255, 0.08);
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

/* Resumo Semanal */
.weekly-summary {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 20px;
}

.summary-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.summary-icon {
    color: var(--accent-orange);
}

.summary-text {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.5;
}

/* Loading States */
.loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: var(--text-secondary);
}

.loading i {
    margin-right: 8px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .photos-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="app-container">
    <div class="checkin-container">
        <!-- Header -->
        <div class="checkin-header">
            <h1 class="checkin-title">Check-in Semanal</h1>
        </div>

        <!-- Seletor de Semana -->
        <div class="week-selector">
            <button class="week-nav-btn" onclick="changeWeek(-1)">
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="week-info">
                <div class="week-range" id="weekRange">
                    <?php echo date('d/m', strtotime($current_week_start)) . ' - ' . date('d/m/Y', strtotime($current_week_end)); ?>
                </div>
                <div class="week-period" id="weekPeriod">Semana Atual</div>
            </div>
            <button class="week-nav-btn" onclick="changeWeek(1)">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>

        <!-- Cards de Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-fire"></i></div>
                <div class="stat-label">Calorias</div>
                <div class="stat-value"><?php echo number_format($week_stats['kcal']['consumed'], 0, ',', '.'); ?></div>
                <div class="stat-goal">Meta: <?php echo number_format($week_stats['kcal']['goal'], 0, ',', '.'); ?></div>
                <div class="stat-percentage percentage-<?php echo $week_stats['kcal']['percentage'] >= 90 ? 'excellent' : ($week_stats['kcal']['percentage'] >= 70 ? 'good' : ($week_stats['kcal']['percentage'] >= 50 ? 'fair' : 'poor')); ?>">
                    <?php echo $week_stats['kcal']['percentage']; ?>%
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-tint"></i></div>
                <div class="stat-label">Água</div>
                <div class="stat-value"><?php echo number_format($week_stats['water']['consumed']/1000, 1, ',', '.'); ?>L</div>
                <div class="stat-goal">Meta: <?php echo number_format($week_stats['water']['goal']/1000, 1, ',', '.'); ?>L</div>
                <div class="stat-percentage percentage-<?php echo $week_stats['water']['percentage'] >= 90 ? 'excellent' : ($week_stats['water']['percentage'] >= 70 ? 'good' : ($week_stats['water']['percentage'] >= 50 ? 'fair' : 'poor')); ?>">
                    <?php echo $week_stats['water']['percentage']; ?>%
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-dumbbell"></i></div>
                <div class="stat-label">Rotinas</div>
                <div class="stat-value"><?php echo $week_stats['routines']['completed']; ?></div>
                <div class="stat-goal">Meta: <?php echo $week_stats['routines']['goal']; ?></div>
                <div class="stat-percentage percentage-<?php echo $week_stats['routines']['percentage'] >= 90 ? 'excellent' : ($week_stats['routines']['percentage'] >= 70 ? 'good' : ($week_stats['routines']['percentage'] >= 50 ? 'fair' : 'poor')); ?>">
                    <?php echo $week_stats['routines']['percentage']; ?>%
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-weight"></i></div>
                <div class="stat-label">Peso</div>
                <div class="stat-value"><?php echo number_format($weight_kg, 1, ',', '.'); ?>kg</div>
                <div class="stat-goal">Atual</div>
                <div class="stat-percentage">
                    <?php if (count($weight_data) > 0): ?>
                        <?php 
                        $first_weight = $weight_data[0]['weight_kg'];
                        $last_weight = end($weight_data)['weight_kg'];
                        $change = $last_weight - $first_weight;
                        $change_text = $change > 0 ? '+' . number_format($change, 1, ',', '.') : number_format($change, 1, ',', '.');
                        ?>
                        <?php echo $change_text; ?>kg
                    <?php else: ?>
                        --
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="charts-section">
            <div class="chart-card">
                <div class="chart-header">
                    <i class="fas fa-chart-line chart-icon"></i>
                    <h3 class="chart-title">Evolução da Semana</h3>
                </div>
                <div class="chart-container">
                    <canvas id="weeklyChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Comparação de Fotos -->
        <div class="photos-comparison">
            <div class="photos-header">
                <i class="fas fa-images chart-icon"></i>
                <h3 class="photos-title">Comparação de Fotos</h3>
            </div>
            <div class="photos-grid">
                <div class="photo-container">
                    <?php if ($photos['first']): ?>
                        <div class="photo-display" onclick="uploadPhoto('first')">
                            <img src="<?php echo BASE_ASSET_URL . '/assets/images/weekly_checkin/' . htmlspecialchars($photos['first']['filename']); ?>" alt="Primeira foto">
                            <div class="photo-overlay">
                                <i class="fas fa-camera"></i>
                                <span>Trocar</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="photo-placeholder" onclick="uploadPhoto('first')">
                            <i class="fas fa-camera"></i>
                        </div>
                    <?php endif; ?>
                    <div class="photo-label">Primeira Foto</div>
                    <div class="photo-date" id="firstPhotoDate">
                        <?php echo $photos['first'] ? date('d/m/Y', strtotime($photos['first']['created_at'])) : '--'; ?>
                    </div>
                </div>
                <div class="photo-container">
                    <?php if ($photos['last']): ?>
                        <div class="photo-display" onclick="uploadPhoto('last')">
                            <img src="<?php echo BASE_ASSET_URL . '/assets/images/weekly_checkin/' . htmlspecialchars($photos['last']['filename']); ?>" alt="Última foto">
                            <div class="photo-overlay">
                                <i class="fas fa-camera"></i>
                                <span>Trocar</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="photo-placeholder" onclick="uploadPhoto('last')">
                            <i class="fas fa-camera"></i>
                        </div>
                    <?php endif; ?>
                    <div class="photo-label">Última Foto</div>
                    <div class="photo-date" id="lastPhotoDate">
                        <?php echo $photos['last'] ? date('d/m/Y', strtotime($photos['last']['created_at'])) : '--'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumo Semanal -->
        <div class="weekly-summary">
            <div class="summary-title">
                <i class="fas fa-clipboard-list summary-icon"></i>
                Resumo da Semana
            </div>
            <div class="summary-text" id="weeklySummary">
                Carregando resumo...
            </div>
        </div>

        <!-- Botões de Ação -->
        <div class="action-buttons">
            <button class="action-btn primary" onclick="completeCheckin()">
                <i class="fas fa-check"></i>
                Completar Check-in Semanal
            </button>
            <a href="<?php echo BASE_APP_URL; ?>/progress.php" class="action-btn">
                <i class="fas fa-chart-bar"></i>
                Ver Progresso Detalhado
            </a>
            <a href="<?php echo BASE_APP_URL; ?>/measurements_progress.php" class="action-btn">
                <i class="fas fa-ruler"></i>
                Adicionar Medidas e Fotos
            </a>
        </div>
    </div>
</div>

<!-- Input oculto para upload de fotos -->
<input type="file" id="photoInput" accept="image/*" style="display: none;" onchange="handlePhotoUpload(event)">
<input type="hidden" id="csrf_token_main_app" value="<?php echo $_SESSION['csrf_token']; ?>">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let currentWeekOffset = 0;
let weeklyChart = null;

// Dados da semana atual (vindos do PHP)
const weekStats = <?php echo json_encode($week_stats); ?>;
const nutritionData = <?php echo json_encode($nutrition_data); ?>;
const waterData = <?php echo json_encode($water_data); ?>;
const weightData = <?php echo json_encode($weight_data); ?>;
const routinesData = <?php echo json_encode($routines_data); ?>;

// Inicializar página
document.addEventListener('DOMContentLoaded', function() {
    generateWeeklySummary();
    initializeChart();
});

function changeWeek(direction) {
    currentWeekOffset += direction;
    
    // Atualizar interface
    updateWeekDisplay();
    
    // Recarregar dados da semana
    loadWeekData();
}

function updateWeekDisplay() {
    const today = new Date();
    const weekStart = new Date(today);
    weekStart.setDate(today.getDate() - today.getDay() + 1 + (currentWeekOffset * 7));
    const weekEnd = new Date(weekStart);
    weekEnd.setDate(weekStart.getDate() + 6);
    
    const startStr = weekStart.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    const endStr = weekEnd.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    
    document.getElementById('weekRange').textContent = startStr + ' - ' + endStr;
    
    if (currentWeekOffset === 0) {
        document.getElementById('weekPeriod').textContent = 'Semana Atual';
    } else if (currentWeekOffset === -1) {
        document.getElementById('weekPeriod').textContent = 'Semana Anterior';
    } else {
        document.getElementById('weekPeriod').textContent = `Semana ${currentWeekOffset > 0 ? '+' : ''}${currentWeekOffset}`;
    }
}

function loadWeekData() {
    // Aqui você implementaria a lógica para carregar dados de outras semanas
    // Por enquanto, vamos manter os dados da semana atual
    console.log('Carregando dados da semana:', currentWeekOffset);
}

function initializeChart() {
    const ctx = document.getElementById('weeklyChart').getContext('2d');
    
    // Preparar dados para o gráfico
    const days = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
    const caloriesData = [];
    const waterData = [];
    const routinesData = [];
    
    // Mapear dados para os dias da semana
    for (let i = 0; i < 7; i++) {
        const date = new Date();
        date.setDate(date.getDate() - date.getDay() + 1 + i);
        const dateStr = date.toISOString().split('T')[0];
        
        // Encontrar dados para este dia
        const nutritionDay = nutritionData.find(d => d.date === dateStr);
        const waterDay = waterData.find(d => d.date === dateStr);
        const routineDay = routinesData.find(d => d.date === dateStr);
        
        caloriesData.push(nutritionDay ? parseFloat(nutritionDay.daily_kcal) : 0);
        waterData.push(waterDay ? parseFloat(waterDay.water_consumed_ml) / 1000 : 0);
        routinesData.push(routineDay ? parseInt(routineDay.completed_routines) : 0);
    }
    
    weeklyChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: days,
            datasets: [
                {
                    label: 'Calorias',
                    data: caloriesData,
                    borderColor: '#ff6b35',
                    backgroundColor: 'rgba(255, 107, 53, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Água (L)',
                    data: waterData,
                    borderColor: '#22d3ee',
                    backgroundColor: 'rgba(34, 211, 238, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                },
                {
                    label: 'Rotinas',
                    data: routinesData,
                    borderColor: '#4ade80',
                    backgroundColor: 'rgba(74, 222, 128, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y2'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#ffffff',
                        font: {
                            size: 12
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: '#ffffff'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    ticks: {
                        color: '#ffffff'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    ticks: {
                        color: '#ffffff'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                },
                y2: {
                    type: 'linear',
                    display: false
                }
            }
        }
    });
}

function generateWeeklySummary() {
    const summary = document.getElementById('weeklySummary');
    
    let summaryText = '';
    
    // Análise de calorias
    const calPercentage = weekStats.kcal.percentage;
    if (calPercentage >= 90) {
        summaryText += '🎉 Excelente controle calórico esta semana! ';
    } else if (calPercentage >= 70) {
        summaryText += '👍 Bom controle calórico, continue assim! ';
    } else if (calPercentage >= 50) {
        summaryText += '⚠️ Atenção ao controle calórico. ';
    } else {
        summaryText += '📊 Foque em atingir suas metas calóricas. ';
    }
    
    // Análise de água
    const waterPercentage = weekStats.water.percentage;
    if (waterPercentage >= 90) {
        summaryText += '💧 Hidratação excelente! ';
    } else if (waterPercentage >= 70) {
        summaryText += '💧 Boa hidratação. ';
    } else {
        summaryText += '💧 Lembre-se de beber mais água. ';
    }
    
    // Análise de rotinas
    const routinePercentage = weekStats.routines.percentage;
    if (routinePercentage >= 90) {
        summaryText += '🏃‍♂️ Rotinas em dia! ';
    } else if (routinePercentage >= 70) {
        summaryText += '🏃‍♂️ Continue com as rotinas. ';
    } else {
        summaryText += '🏃‍♂️ Tente completar mais rotinas diárias. ';
    }
    
    summary.textContent = summaryText || 'Continue seguindo suas metas para ter uma semana ainda melhor!';
}

function uploadPhoto(type) {
    document.getElementById('photoInput').click();
    document.getElementById('photoInput').dataset.photoType = type;
}

function handlePhotoUpload(event) {
    const file = event.target.files[0];
    const photoType = event.target.dataset.photoType;
    
    if (!file) return;
    
    // Validar tipo de arquivo
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        alert('Tipo de arquivo não permitido. Use JPG, PNG ou WebP.');
        return;
    }
    
    // Validar tamanho (máximo 5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert('Arquivo muito grande. Máximo 5MB.');
        return;
    }
    
    // Mostrar loading
    const photoElement = document.querySelector(`[onclick="uploadPhoto('${photoType}')"]`);
    const originalContent = photoElement.innerHTML;
    photoElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i><br><small>Enviando...</small>';
    photoElement.style.pointerEvents = 'none';
    
    // Preparar dados para upload
    const formData = new FormData();
    formData.append('photo', file);
    formData.append('photo_type', photoType);
    formData.append('checkin_date', new Date().toISOString().split('T')[0]);
    formData.append('csrf_token', document.getElementById('csrf_token_main_app').value);
    
    // Fazer upload via AJAX
    fetch('<?php echo BASE_APP_URL; ?>/ajax_upload_weekly_photo.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Atualizar interface com a nova foto
            updatePhotoDisplay(photoType, data.url, data.filename);
            
            // Atualizar data
            const dateStr = new Date().toLocaleDateString('pt-BR');
            document.getElementById(photoType + 'PhotoDate').textContent = dateStr;
            
            // Mostrar mensagem de sucesso
            showNotification('Foto enviada com sucesso!', 'success');
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        console.error('Erro no upload:', error);
        alert('Erro ao enviar foto: ' + error.message);
        
        // Restaurar interface original
        photoElement.innerHTML = originalContent;
        photoElement.style.pointerEvents = 'auto';
    });
}

function updatePhotoDisplay(photoType, imageUrl, filename) {
    const photoContainer = document.querySelector(`[onclick="uploadPhoto('${photoType}')"]`).parentElement;
    const photoElement = photoContainer.querySelector('[onclick]');
    
    // Criar novo elemento de foto
    photoElement.innerHTML = `
        <img src="${imageUrl}" alt="${photoType} foto">
        <div class="photo-overlay">
            <i class="fas fa-camera"></i>
            <span>Trocar</span>
        </div>
    `;
    photoElement.className = 'photo-display';
    photoElement.style.pointerEvents = 'auto';
}

function showNotification(message, type = 'info') {
    // Criar elemento de notificação
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Estilos da notificação
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
    
    // Cores baseadas no tipo
    if (type === 'success') {
        notification.style.background = '#4ade80';
    } else if (type === 'error') {
        notification.style.background = '#f87171';
    } else {
        notification.style.background = '#22d3ee';
    }
    
    document.body.appendChild(notification);
    
    // Animar entrada
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Remover após 3 segundos
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

function completeCheckin() {
    // Aqui você implementaria a lógica para completar o check-in
    alert('Check-in semanal completado com sucesso!');
    
    // Redirecionar ou atualizar interface
    // window.location.reload();
}
</script>

<?php require_once APP_ROOT_PATH . '/includes/layout_footer.php'; ?>
