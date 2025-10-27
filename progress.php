<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

// Buscar dados do usuário
$user_profile_data = getUserProfileData($conn, $user_id);

// ===========================
// 1. BUSCAR METAS DO USUÁRIO
// ===========================
$stmt_goals = $conn->prepare("SELECT * FROM sf_user_goals WHERE user_id = ? AND goal_type = 'nutrition'");
$stmt_goals->bind_param("i", $user_id);
$stmt_goals->execute();
$user_goals = $stmt_goals->get_result()->fetch_assoc();
$stmt_goals->close();

// Se não tem metas, criar baseadas no perfil
if (!$user_goals) {
    $age_years = calculateAge($user_profile_data['dob']);
    $calculated_calories = calculateTargetDailyCalories(
        $user_profile_data['gender'], 
        $user_profile_data['weight_kg'], 
        $user_profile_data['height_cm'], 
        $age_years, 
        $user_profile_data['exercise_frequency'], 
        $user_profile_data['objective']
    );
    $calculated_macros = calculateMacronutrients($calculated_calories, $user_profile_data['objective']);
    $calculated_water = getWaterIntakeSuggestion($user_profile_data['weight_kg']);
    
    // Salvar metas no banco
    $stmt_insert_goals = $conn->prepare("
        INSERT INTO sf_user_goals (
            user_id, goal_type, target_kcal, target_protein_g, target_carbs_g, target_fat_g,
            target_water_cups, target_steps_daily, target_steps_weekly,
            target_workout_hours_weekly, target_workout_hours_monthly,
            target_cardio_hours_weekly, target_cardio_hours_monthly,
            target_sleep_hours, user_gender, step_length_cm
        ) VALUES (?, 'nutrition', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $workout_hours_weekly = 0;
    $cardio_hours_weekly = 0;
    switch ($user_profile_data['exercise_frequency']) {
        case '1_2x_week':
            $workout_hours_weekly = 2.0;
            $cardio_hours_weekly = 1.5;
            break;
        case '3_4x_week':
            $workout_hours_weekly = 4.0;
            $cardio_hours_weekly = 2.5;
            break;
        case '5_6x_week':
            $workout_hours_weekly = 6.0;
            $cardio_hours_weekly = 3.5;
            break;
        case '6_7x_week':
            $workout_hours_weekly = 8.0;
            $cardio_hours_weekly = 4.0;
            break;
        case '7plus_week':
            $workout_hours_weekly = 10.0;
            $cardio_hours_weekly = 5.0;
            break;
    }
    
    $step_length = ($user_profile_data['gender'] == 'male') ? 76.0 : 66.0;
    $target_steps_daily = 10000;
    $target_steps_weekly = 70000;
    $workout_hours_monthly = $workout_hours_weekly * 4;
    $cardio_hours_monthly = $cardio_hours_weekly * 4;
    $sleep_hours = 8.0;
    
    $stmt_insert_goals->bind_param("idddiiidddddssd", 
        $user_id,
        $calculated_calories, 
        $calculated_macros['protein_g'], 
        $calculated_macros['carbs_g'], 
        $calculated_macros['fat_g'],
        $calculated_water['cups'], 
        $target_steps_daily, 
        $target_steps_weekly,
        $workout_hours_weekly, 
        $workout_hours_monthly,
        $cardio_hours_weekly, 
        $cardio_hours_monthly,
        $sleep_hours, 
        $user_profile_data['gender'], 
        $step_length
    );
    
    $stmt_insert_goals->execute();
    $stmt_insert_goals->close();
    
    // Buscar metas recém-criadas
    $stmt_goals = $conn->prepare("SELECT * FROM sf_user_goals WHERE user_id = ? AND goal_type = 'nutrition'");
    $stmt_goals->bind_param("i", $user_id);
    $stmt_goals->execute();
    $user_goals = $stmt_goals->get_result()->fetch_assoc();
    $stmt_goals->close();
}

// ===========================
// 2. VERIFICAR SE USUÁRIO TEM EXERCÍCIOS CONFIGURADOS
// ===========================
$user_has_exercises = false;

// Verificar se tem exercícios no perfil
if (!empty($user_profile_data['exercise_frequency']) && $user_profile_data['exercise_frequency'] !== 'sedentary') {
    $user_has_exercises = true;
}

// ===========================
// 3. DADOS DO DIA ATUAL
// ===========================
$stmt_today = $conn->prepare("
    SELECT 
        kcal_consumed, protein_consumed_g, carbs_consumed_g, fat_consumed_g, water_consumed_cups,
        steps_daily, sleep_hours
    FROM sf_user_daily_tracking 
    WHERE user_id = ? AND date = ?
");
$stmt_today->bind_param("is", $user_id, $today);
$stmt_today->execute();
$today_data = $stmt_today->get_result()->fetch_assoc();
$stmt_today->close();

if (!$today_data) {
    $today_data = [
        'kcal_consumed' => 0, 'protein_consumed_g' => 0, 'carbs_consumed_g' => 0, 
        'fat_consumed_g' => 0, 'water_consumed_cups' => 0,
        'steps_daily' => 0, 'sleep_hours' => 0
    ];
}

// Converter copos para ml (250ml por copo)
$today_data['water_consumed_ml'] = $today_data['water_consumed_cups'] * 250;

// ===========================
// 4. DADOS DA SEMANA ATUAL
// ===========================
$stmt_week = $conn->prepare("
    SELECT 
        SUM(kcal_consumed) as total_kcal,
        SUM(protein_consumed_g) as total_protein,
        SUM(carbs_consumed_g) as total_carbs,
        SUM(fat_consumed_g) as total_fat,
        SUM(water_consumed_cups) as total_water,
        SUM(steps_daily) as total_steps,
        AVG(sleep_hours) as avg_sleep,
        COUNT(*) as days_tracked
    FROM sf_user_daily_tracking 
    WHERE user_id = ? AND date BETWEEN ? AND ?
");
$stmt_week->bind_param("iss", $user_id, $week_start, $week_end);
$stmt_week->execute();
$week_data = $stmt_week->get_result()->fetch_assoc();
$stmt_week->close();

// ===========================
// 4.5. DADOS DO MÊS ATUAL (NOVO BLOCO)
// ===========================
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

$stmt_month = $conn->prepare("
    SELECT 
        SUM(kcal_consumed) as total_kcal,
        SUM(protein_consumed_g) as total_protein,
        SUM(carbs_consumed_g) as total_carbs,
        SUM(fat_consumed_g) as total_fat,
        SUM(water_consumed_cups) as total_water,
        SUM(steps_daily) as total_steps,
        AVG(sleep_hours) as avg_sleep,
        SUM(COALESCE(workout_hours, 0)) as total_workout_hours,
        SUM(COALESCE(cardio_hours, 0)) as total_cardio_hours,
        COUNT(*) as days_tracked
    FROM sf_user_daily_tracking 
    WHERE user_id = ? AND date BETWEEN ? AND ?
");
$stmt_month->bind_param("iss", $user_id, $month_start, $month_end);
$stmt_month->execute();
$month_data = $stmt_month->get_result()->fetch_assoc();
$stmt_month->close();

// ===========================
// 5. DADOS DE TREINO DO DIA ATUAL
// ===========================
$today_workout_data = [
    'workout_hours' => 0,
    'cardio_hours' => 0,
    'total_exercise_hours' => 0,
    'completed_routines' => 0
];

// Buscar dados de treino do dia atual (com verificação de colunas)
try {
    $stmt_workout = $conn->prepare("
        SELECT 
            COALESCE(workout_hours, 0) as workout_hours, 
            COALESCE(cardio_hours, 0) as cardio_hours,
            COALESCE(sleep_hours, 0) as sleep_hours,
            (COALESCE(workout_hours, 0) + COALESCE(cardio_hours, 0)) as total_exercise_hours
        FROM sf_user_daily_tracking 
        WHERE user_id = ? AND date = ?
    ");
    $stmt_workout->bind_param("is", $user_id, $today);
    $stmt_workout->execute();
    $workout_result = $stmt_workout->get_result()->fetch_assoc();
    $stmt_workout->close();

    if ($workout_result) {
        $today_workout_data['workout_hours'] = $workout_result['workout_hours'] ?? 0;
        $today_workout_data['cardio_hours'] = $workout_result['cardio_hours'] ?? 0;
        $today_workout_data['total_exercise_hours'] = $workout_result['total_exercise_hours'] ?? 0;
        // Atualizar também os dados de sono se disponível
        if (isset($workout_result['sleep_hours'])) {
            $today_data['sleep_hours'] = $workout_result['sleep_hours'];
        }
    }
} catch (Exception $e) {
    // Se as colunas não existirem, usar valores padrão
    $today_workout_data['workout_hours'] = 0;
    $today_workout_data['cardio_hours'] = 0;
    $today_workout_data['total_exercise_hours'] = 0;
}

// Contar rotinas completadas hoje
if ($user_has_exercises) {
    $stmt_routines = $conn->prepare("
        SELECT COUNT(*) as completed_routines
        FROM sf_user_routine_log 
        WHERE user_id = ? AND DATE(date) = ? AND is_completed = 1
    ");
    $stmt_routines->bind_param("is", $user_id, $today);
    $stmt_routines->execute();
    $result_routines = $stmt_routines->get_result()->fetch_assoc();
    $today_workout_data['completed_routines'] = $result_routines['completed_routines'] ?? 0;
    $stmt_routines->close();
}

$practiced_today = ($today_workout_data['total_exercise_hours'] > 0 || $today_workout_data['completed_routines'] > 0);

// ===========================
// 6. HISTÓRICO DE PESO (30 dias)
// ===========================
$weight_history = [];
$stmt_weight = $conn->prepare("SELECT date_recorded, weight_kg FROM sf_user_weight_history WHERE user_id = ? AND date_recorded >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ORDER BY date_recorded ASC");
$stmt_weight->bind_param("i", $user_id);
$stmt_weight->execute();
$result_weight = $stmt_weight->get_result();
while ($row = $result_weight->fetch_assoc()) {
    $weight_history[] = $row;
}
$stmt_weight->close();

// Calcular mudança de peso
$weight_change = 0;
if (count($weight_history) >= 2) {
    $first_weight = $weight_history[0]['weight_kg'];
    $last_weight = $weight_history[count($weight_history) - 1]['weight_kg'];
    $weight_change = $last_weight - $first_weight;
}

// ===========================
// 7. FUNÇÕES AUXILIARES
// ===========================
function calculateProgressPercentage($current, $target) {
    if ($target <= 0) return 0;
    return min(100, round(($current / $target) * 100));
}

function getProgressColor($percentage) {
    if ($percentage >= 100) return '#22c55e'; // Verde
    if ($percentage >= 80) return '#f59e0b'; // Amarelo
    if ($percentage >= 60) return '#f97316'; // Laranja
    return '#ef4444'; // Vermelho
}

function formatNumber($number, $decimals = 0) {
    return number_format($number, $decimals, ',', '.');
}

function formatHours($hours) {
    if ($hours < 1) {
        $minutes = round($hours * 60);
        return $minutes . 'min';
    } else {
        $whole_hours = floor($hours);
        $remaining_minutes = round(($hours - $whole_hours) * 60);
        
        if ($remaining_minutes == 0) {
            return $whole_hours . 'h';
        } else {
            return $whole_hours . 'h' . $remaining_minutes;
        }
    }
}

// ===========================
// 8. PREPARAR DADOS PARA EXIBIÇÃO
// ===========================
// Calcular meta de água igual ao main_app
$water_goal_data = getWaterIntakeSuggestion($user_profile_data['weight_kg']);
$water_goal_ml = $water_goal_data['total_ml'];

// Calcular meta diária de treino (baseada na meta semanal dividida por 7)
// Só calcular se o usuário tem exercícios configurados
$daily_workout_target = 0;
$daily_cardio_target = 0;
$daily_total_exercise_target = 0;

if ($user_has_exercises && $user_goals) {
    $daily_workout_target = $user_goals['target_workout_hours_weekly'] / 7;
    $daily_cardio_target = $user_goals['target_cardio_hours_weekly'] / 7;
    $daily_total_exercise_target = $daily_workout_target + $daily_cardio_target;
}

$today_progress = [
    'kcal' => calculateProgressPercentage($today_data['kcal_consumed'], $user_goals['target_kcal']),
    'protein' => calculateProgressPercentage($today_data['protein_consumed_g'], $user_goals['target_protein_g']),
    'carbs' => calculateProgressPercentage($today_data['carbs_consumed_g'], $user_goals['target_carbs_g']),
    'fat' => calculateProgressPercentage($today_data['fat_consumed_g'], $user_goals['target_fat_g']),
    'water' => calculateProgressPercentage($today_data['water_consumed_ml'], $water_goal_ml),
    'steps' => calculateProgressPercentage($today_data['steps_daily'], $user_goals['target_steps_daily']),
    'sleep' => calculateProgressPercentage($today_data['sleep_hours'], $user_goals['target_sleep_hours']),
    'exercise' => calculateProgressPercentage($today_workout_data['total_exercise_hours'], $daily_total_exercise_target)
];

$week_progress = [
    'kcal' => calculateProgressPercentage($week_data['total_kcal'] ?? 0, $user_goals['target_kcal'] * 7),
    'protein' => calculateProgressPercentage($week_data['total_protein'] ?? 0, $user_goals['target_protein_g'] * 7),
    'carbs' => calculateProgressPercentage($week_data['total_carbs'] ?? 0, $user_goals['target_carbs_g'] * 7),
    'fat' => calculateProgressPercentage($week_data['total_fat'] ?? 0, $user_goals['target_fat_g'] * 7),
    'water' => calculateProgressPercentage(($week_data['total_water'] ?? 0) * 250, $water_goal_ml * 7),
    'steps' => calculateProgressPercentage($week_data['total_steps'] ?? 0, $user_goals['target_steps_weekly']),
    'sleep' => calculateProgressPercentage($week_data['avg_sleep'] ?? 0, $user_goals['target_sleep_hours'])
];

// --- PREPARAÇÃO PARA O LAYOUT ---
$page_title = "Meu Progresso";
$extra_js = ['script.js'];
$extra_css = ['pages/_progress.css'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* Layout principal */
.progress-page-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 20px 8px 40px 8px;
}

.page-header {
    width: 100%;
    text-align: left;
    margin-bottom: 4px;
}

.page-header h1 {
    font-size: 1.9rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

/* Cards de progresso */
.progress-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    padding: 20px 16px;
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    text-align: center;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    min-height: 140px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.progress-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}

.progress-icon {
    font-size: 2rem;
    margin-bottom: 8px;
    flex-shrink: 0;
}

.progress-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 8px 0;
    flex-shrink: 0;
}

.progress-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 4px 0;
    flex-shrink: 0;
}

.progress-target {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin: 0 0 8px 0;
    flex-shrink: 0;
    line-height: 1.2;
}

.progress-bar {
    width: 100%;
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 8px;
    flex-shrink: 0;
}

.progress-bar-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.progress-percentage {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-primary);
    flex-shrink: 0;
}

/* Card de exercício */
.exercise-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px 16px;
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    text-align: center;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    min-height: 120px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.exercise-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}

.exercise-status {
    font-size: 0.9rem;
    font-weight: 600;
    margin: 8px 0;
}

.exercise-status.practiced {
    color: #22c55e;
}

.exercise-status.not-practiced {
    color: #ef4444;
}

.btn-add-exercise {
    display: inline-block;
    padding: 8px 16px;
    background: var(--accent-orange);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 8px;
}

.btn-add-exercise:hover {
    background: #e55a00;
    transform: translateY(-1px);
}

/* Grid layouts */
.progress-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.nutrition-cards-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-top: 16px;
}

.activity-cards-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-top: 16px;
}

/* Glass card container */
.glass-card {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 20px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: center;
}

.section-subtitle {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin: 0 0 16px 0;
    text-align: center;
}

/* Chart container */
.chart-container {
    position: relative;
    height: 250px;
    width: 100%;
    margin: 10px 0;
}

/* Seletor de Período */
.period-selector {
    margin-top: 20px;
    margin-bottom: 10px;
}

.period-tabs {
    display: flex;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 4px;
    gap: 4px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.period-tab {
    flex: 1;
    padding: 12px 16px;
    background: transparent;
    border: none;
    border-radius: 8px;
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 48px;
}

.period-tab:hover:not(.active) {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
    transform: translateY(-1px);
}

.period-tab.active {
    background: var(--accent-orange);
    color: white;
    transform: translateY(-1px);
}

.period-tab .tab-icon {
    font-size: 1.1rem;
}

.period-tab .tab-text {
    font-size: 0.85rem;
    font-weight: 600;
}

/* Grid Unificado */
.unified-progress-grid {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 20px;
    margin-top: 20px;
    width: 100%;
    grid-auto-rows: auto;
}

/* Força o layout 2x2 em telas maiores */
@media (min-width: 769px) {
    .unified-progress-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        display: grid !important;
    }
}

/* Spinner de Loading */
.loading-spinner {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: var(--text-secondary);
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid rgba(255, 255, 255, 0.1);
    border-top: 4px solid var(--accent-orange);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 16px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Progress Card Unificado */
.unified-progress-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    padding: 20px 16px;
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    text-align: center;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    min-height: 160px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    width: 100%;
    box-sizing: border-box;
}

.unified-progress-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}

.unified-progress-card .progress-icon {
    font-size: 2.2rem;
    margin-bottom: 12px;
    flex-shrink: 0;
}

.unified-progress-card .progress-label {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 8px 0;
    flex-shrink: 0;
}

.unified-progress-card .progress-value {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 6px 0;
    flex-shrink: 0;
}

.unified-progress-card .progress-target {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin: 0 0 12px 0;
    flex-shrink: 0;
    line-height: 1.3;
}

.unified-progress-card .progress-bar {
    width: 100%;
    height: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 12px;
    flex-shrink: 0;
}

.unified-progress-card .progress-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.unified-progress-card .progress-percentage {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    flex-shrink: 0;
}

.unified-progress-card .progress-details {
    font-size: 0.7rem;
    color: var(--text-secondary);
    margin-top: 8px;
    line-height: 1.2;
    flex-shrink: 0;
}

/* Responsividade */
@media (max-width: 768px) {
    .progress-page-grid {
        padding: 20px 6px 30px 6px;
    }
    
    .page-header h1 {
        font-size: 1.7rem;
    }
    
    .progress-summary-grid,
    .nutrition-cards-grid,
    .activity-cards-grid {
        gap: 12px;
    }
    
    .progress-card,
    .exercise-card {
        padding: 16px 12px;
        min-height: 100px;
    }
    
    .progress-icon {
        font-size: 1.8rem;
        margin-bottom: 6px;
    }
    
    .progress-label {
        font-size: 0.85rem;
    }
    
    .progress-value {
        font-size: 1rem;
    }
    
    .chart-container {
        height: 200px;
    }
    
    .glass-card {
        padding: 20px;
    }
    
    .period-tab {
        padding: 10px 12px;
        min-height: 44px;
    }
    
    .period-tab .tab-text {
        font-size: 0.8rem;
    }
    
    .period-tab .tab-icon {
        font-size: 1rem;
    }
    
    .unified-progress-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    
    .unified-progress-card {
        min-height: 120px;
        padding: 12px 8px;
    }
    
    .unified-progress-card .progress-icon {
        font-size: 1.8rem;
        margin-bottom: 8px;
    }
    
    .unified-progress-card .progress-label {
        font-size: 0.8rem;
    }
    
    .unified-progress-card .progress-value {
        font-size: 1rem;
    }
    
    .unified-progress-card .progress-target {
        font-size: 0.7rem;
    }
    
    .unified-progress-card .progress-percentage {
        font-size: 0.8rem;
    }
}
</style>

<div class="app-container">
    <section class="progress-page-grid">
        <!-- Header da página -->
        <header class="page-header">
            <h1>Meu Progresso</h1>
            
            <!-- Seletor de Período -->
            <div class="period-selector">
                <div class="period-tabs">
                    <button class="period-tab active" data-period="today">
                        <span class="tab-icon">📅</span>
                        <span class="tab-text">Hoje</span>
                    </button>
                    <button class="period-tab" data-period="week">
                        <span class="tab-icon">📊</span>
                        <span class="tab-text">Semana</span>
                    </button>
                    <button class="period-tab" data-period="month">
                        <span class="tab-icon">📈</span>
                        <span class="tab-text">Mês</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- CARD UNIFICADO DE PROGRESSO -->
        <div class="glass-card">
            <h3 class="section-title">📊 Meu Progresso</h3>
            <p class="section-subtitle" id="period-subtitle">Consumo vs Meta - Hoje</p>
            
            <div class="unified-progress-grid" id="progress-content">
                <!-- Conteúdo será carregado dinamicamente via JavaScript -->
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Carregando dados...</p>
                </div>
            </div>
        </div>

        <!-- Gráfico de Evolução do Peso -->
        <?php if (count($weight_history) > 1): ?>
        <div class="glass-card">
            <h3 class="section-title">Evolução do Peso</h3>
            <p class="section-subtitle">Últimos 30 dias</p>
            <div class="chart-container">
                <canvas id="weightChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Resumo de Peso -->
        <div class="glass-card">
            <h3 class="section-title">Resumo de Peso</h3>
            <div class="progress-summary-grid">
                <div class="progress-card">
                    <span class="progress-icon">⚖️</span>
                    <h4 class="progress-label">Peso Atual</h4>
                    <p class="progress-value">
                        <?php if (count($weight_history) > 0): ?>
                            <?php echo formatNumber($weight_history[count($weight_history) - 1]['weight_kg'], 1); ?>kg
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </p>
                    <p class="progress-target">
                        <?php if ($weight_change != 0): ?>
                            <?php echo ($weight_change > 0 ? '+' : '') . formatNumber($weight_change, 1); ?>kg em 30 dias
                        <?php else: ?>
                            Sem alteração
                        <?php endif; ?>
                    </p>
                </div>

                <div class="progress-card">
                    <span class="progress-icon">📊</span>
                    <h4 class="progress-label">Registros</h4>
                    <p class="progress-value"><?php echo count($weight_history); ?></p>
                    <p class="progress-target">pesagens em 30 dias</p>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- JavaScript para Controle de Período e Carregamento Dinâmico -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando página de progresso...');
    
    // --- DADOS DO PHP (passados para JavaScript) ---
    const progressData = {
        today: {
            kcal: <?php echo json_encode($today_data['kcal_consumed']); ?>,
            protein: <?php echo json_encode($today_data['protein_consumed_g']); ?>,
            carbs: <?php echo json_encode($today_data['carbs_consumed_g']); ?>,
            fat: <?php echo json_encode($today_data['fat_consumed_g']); ?>,
            water: <?php echo json_encode($today_data['water_consumed_ml']); ?>,
            steps: <?php echo json_encode($today_data['steps_daily']); ?>,
            sleep: <?php echo json_encode($today_data['sleep_hours']); ?>,
            workout: <?php echo json_encode($today_workout_data['workout_hours']); ?>,
            cardio: <?php echo json_encode($today_workout_data['cardio_hours']); ?>
        },
        week: {
            kcal: <?php echo json_encode($week_data['total_kcal'] ?? 0); ?>,
            protein: <?php echo json_encode($week_data['total_protein'] ?? 0); ?>,
            carbs: <?php echo json_encode($week_data['total_carbs'] ?? 0); ?>,
            fat: <?php echo json_encode($week_data['total_fat'] ?? 0); ?>,
            water: <?php echo json_encode(($week_data['total_water'] ?? 0) * 250); ?>,
            steps: <?php echo json_encode($week_data['total_steps'] ?? 0); ?>,
            sleep: <?php echo json_encode($week_data['avg_sleep'] ?? 0); ?>,
            workout: 0, // Precisa ser buscado do DB para a semana toda
            cardio: 0   // Precisa ser buscado do DB para a semana toda
        },
        month: {
            kcal: <?php echo json_encode($month_data['total_kcal'] ?? 0); ?>,
            protein: <?php echo json_encode($month_data['total_protein'] ?? 0); ?>,
            carbs: <?php echo json_encode($month_data['total_carbs'] ?? 0); ?>,
            fat: <?php echo json_encode($month_data['total_fat'] ?? 0); ?>,
            water: <?php echo json_encode(($month_data['total_water'] ?? 0) * 250); ?>,
            steps: <?php echo json_encode($month_data['total_steps'] ?? 0); ?>,
            sleep: <?php echo json_encode($month_data['avg_sleep'] ?? 0); ?>,
            workout: <?php echo json_encode($month_data['total_workout_hours'] ?? 0); ?>,
            cardio: <?php echo json_encode($month_data['total_cardio_hours'] ?? 0); ?>
        },
        goals: {
            kcal: <?php echo json_encode($user_goals['target_kcal']); ?>,
            protein: <?php echo json_encode($user_goals['target_protein_g']); ?>,
            carbs: <?php echo json_encode($user_goals['target_carbs_g']); ?>,
            fat: <?php echo json_encode($user_goals['target_fat_g']); ?>,
            water: <?php echo json_encode($water_goal_ml); ?>,
            steps_daily: <?php echo json_encode($user_goals['target_steps_daily']); ?>,
            steps_weekly: <?php echo json_encode($user_goals['target_steps_weekly']); ?>,
            steps_monthly: <?php echo json_encode($user_goals['target_steps_daily'] * 30); ?>,
            sleep: <?php echo json_encode($user_goals['target_sleep_hours']); ?>,
            workout_weekly: <?php echo json_encode($user_goals['target_workout_hours_weekly']); ?>,
            cardio_weekly: <?php echo json_encode($user_goals['target_cardio_hours_weekly']); ?>
        }
    };

    console.log('📊 Dados carregados:', progressData);

    // --- FUNÇÕES AUXILIARES ---
    function formatNumber(number, decimals = 0) {
        return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(number);
    }
    
    function formatHours(hours) {
        if (!hours || hours < 0) hours = 0;
        if (hours < 1) return Math.round(hours * 60) + 'min';
        const wholeHours = Math.floor(hours);
        const remainingMinutes = Math.round((hours - wholeHours) * 60);
        return remainingMinutes === 0 ? wholeHours + 'h' : wholeHours + 'h' + remainingMinutes;
    }
    
    function getProgressColor(percentage) {
        if (percentage >= 100) return '#22c55e';
        if (percentage >= 80) return '#f59e0b';
        if (percentage >= 60) return '#f97316';
        return '#ef4444';
    }
    
    function calculateProgress(current, target) {
        if (target <= 0) return 0;
        return Math.min(100, Math.round((current / target) * 100));
    }

    // --- FUNÇÕES DE RENDERIZAÇÃO E LÓGICA ---
    function renderProgressCards(period) {
        console.log('🎨 Renderizando cards para período:', period);
        
        const content = document.getElementById('progress-content');
        if (!content) {
            console.error('❌ Elemento progress-content não encontrado!');
            return;
        }
        
        let currentData, goalsData;
        
        switch(period) {
            case 'week':
                currentData = progressData.week;
                goalsData = {
                    kcal: progressData.goals.kcal * 7,
                    protein: progressData.goals.protein * 7,
                    carbs: progressData.goals.carbs * 7,
                    fat: progressData.goals.fat * 7,
                    water: progressData.goals.water * 7,
                    steps: progressData.goals.steps_weekly,
                    sleep: progressData.goals.sleep, // A meta de sono é diária, a média é comparada com a meta diária
                    workout: progressData.goals.workout_weekly,
                    cardio: progressData.goals.cardio_weekly
                };
                break;
            case 'month':
                currentData = progressData.month;
                goalsData = {
                    kcal: progressData.goals.kcal * 30, // Usa uma média de 30 dias para a meta
                    protein: progressData.goals.protein * 30,
                    carbs: progressData.goals.carbs * 30,
                    fat: progressData.goals.fat * 30,
                    water: progressData.goals.water * 30,
                    steps: progressData.goals.steps_monthly,
                    sleep: progressData.goals.sleep, // A média mensal é comparada com a meta diária
                    workout: progressData.goals.workout_weekly * 4, // Aproximação de 4 semanas
                    cardio: progressData.goals.cardio_weekly * 4
                };
                break;
            case 'today':
            default:
                currentData = progressData.today;
                goalsData = {
                    kcal: progressData.goals.kcal,
                    protein: progressData.goals.protein,
                    carbs: progressData.goals.carbs,
                    fat: progressData.goals.fat,
                    water: progressData.goals.water,
                    steps: progressData.goals.steps_daily,
                    sleep: progressData.goals.sleep,
                    workout: progressData.goals.workout_weekly / 7, // Meta diária
                    cardio: progressData.goals.cardio_weekly / 7  // Meta diária
                };
                break;
        }

        const cards = [
            { icon: '🔥', label: 'Calorias', value: currentData.kcal, target: goalsData.kcal, unit: 'kcal' },
            { icon: '🥩', label: 'Proteínas', value: currentData.protein, target: goalsData.protein, unit: 'g' },
            { icon: '🍞', label: 'Carboidratos', value: currentData.carbs, target: goalsData.carbs, unit: 'g' },
            { icon: '🥑', label: 'Gorduras', value: currentData.fat, target: goalsData.fat, unit: 'g' },
            { icon: '💧', label: 'Água', value: currentData.water, target: goalsData.water, unit: 'ml' },
            { icon: '👟', label: 'Passos', value: currentData.steps, target: goalsData.steps, unit: '' },
            { icon: '😴', label: 'Sono', value: currentData.sleep, target: goalsData.sleep, unit: '', isHour: true }
        ];

        // Adicionar cards de exercícios apenas se o usuário tem exercícios configurados
        if (<?php echo json_encode($user_has_exercises); ?> && <?php echo json_encode($daily_total_exercise_target > 0); ?>) {
            cards.push(
                { icon: '🏋️', label: 'Treino', value: currentData.workout, target: goalsData.workout, unit: '', isHour: true },
                { icon: '🏃', label: 'Cardio', value: currentData.cardio, target: goalsData.cardio, unit: '', isHour: true }
            );
        }

        let cardsHTML = cards.map(card => {
            const progress = calculateProgress(card.value, card.target);
            const displayValue = card.isHour ? formatHours(card.value) : formatNumber(card.value, card.unit === 'g' ? 1 : 0);
            const displayTarget = card.isHour ? formatHours(card.target) : formatNumber(card.target, card.unit === 'g' ? 1 : 0);

            return `
                <div class="unified-progress-card">
                    <span class="progress-icon">${card.icon}</span>
                    <h4 class="progress-label">${card.label}</h4>
                    <p class="progress-value">${displayValue} ${card.unit}</p>
                    <p class="progress-target">Meta: ${displayTarget} ${card.unit}</p>
                    <div class="progress-bar">
                        <div class="progress-bar-fill" style="width: ${progress}%; background: ${getProgressColor(progress)};"></div>
                    </div>
                    <p class="progress-percentage">${progress}% • ${displayValue} de ${displayTarget}</p>
                </div>
            `;
        }).join('');
        
        content.innerHTML = cardsHTML;
        console.log('✅ Cards renderizados com sucesso!');
    }

    function changePeriod(period) {
        console.log('🔄 Mudando para período:', period);
        
        // Atualiza a classe 'active' nos botões
        document.querySelectorAll('.period-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.period === period);
        });
        
        // Atualiza o subtítulo
        const subtitles = {
            'today': 'Consumo vs Meta - Hoje',
            'week': 'Total vs Meta - Esta Semana',
            'month': 'Total vs Meta - Este Mês'
        };
        document.getElementById('period-subtitle').textContent = subtitles[period];
        
        // Renderiza os cards para o período selecionado
        renderProgressCards(period);
    }

    // --- INICIALIZAÇÃO E EVENT LISTENERS ---
    
    // Adiciona os "escutadores de clique" nos botões de período.
    // Esta é a parte que faz os botões funcionarem.
    document.querySelectorAll('.period-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            changePeriod(tab.dataset.period);
        });
    });
    
    // **AÇÃO MAIS IMPORTANTE**: Carrega os dados de "Hoje" assim que a página estiver pronta.
    // Isso remove o "spinner" inicial e mostra os dados.
    changePeriod('today');
    
    // --- GRÁFICO DE PESO (seu código original, que está correto) ---
    <?php if (count($weight_history) > 1): ?>
    const weightData = <?php echo json_encode($weight_history); ?>;
    const ctx = document.getElementById('weightChart').getContext('2d');
    const labels = weightData.map(item => new Date(item.date_recorded).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }));
    const weights = weightData.map(item => parseFloat(item.weight_kg));
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Peso (kg)',
                data: weights,
                borderColor: '#FF6B00',
                backgroundColor: 'rgba(255, 107, 0, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#FF6B00',
                pointBorderColor: '#FFFFFF',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: 'white',
                    bodyColor: 'white',
                    borderColor: '#FF6B00',
                    borderWidth: 1,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + ' kg';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    grid: { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false },
                    ticks: {
                        color: '#ffffff',
                        font: { size: 11 },
                        callback: function(value) { return value + ' kg'; }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#ffffff', font: { size: 11 } }
                }
            },
            elements: { point: { hoverBorderWidth: 3 } }
        }
    });
    <?php endif; ?>
    
    console.log('🎉 Página inicializada com sucesso!');
});
</script>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';