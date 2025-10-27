<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Buscar dados do usuário
$user_profile_data = getUserProfileData($conn, $user_id);

// ===========================
// CALCULAR METAS BASEADAS NO PERFIL DO USUÁRIO
// ===========================
$gender = $user_profile_data['gender'] ?? 'male';
$weight_kg = (float)($user_profile_data['weight_kg'] ?? 70);
$height_cm = (int)($user_profile_data['height_cm'] ?? 170);
$dob = $user_profile_data['dob'] ?? date('Y-m-d', strtotime('-30 years'));
$exercise_frequency = $user_profile_data['exercise_frequency'] ?? 'sedentary';
$objective = $user_profile_data['objective'] ?? 'maintain_weight';

$age_years = calculateAge($dob);
$total_daily_calories_goal = calculateTargetDailyCalories($gender, $weight_kg, $height_cm, $age_years, $exercise_frequency, $objective);
$macros_goal = calculateMacronutrients($total_daily_calories_goal, $objective);
$water_goal_data = getWaterIntakeSuggestion($weight_kg);

// ===========================
// BUSCAR METAS PERSONALIZADAS (META ATIVA MAIS RECENTE)
// ===========================
$user_goals = [];
$stmt_goals = $conn->prepare("SELECT * FROM sf_user_goals WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
$stmt_goals->bind_param("i", $user_id);
$stmt_goals->execute();
$result_goals = $stmt_goals->get_result();
if ($row = $result_goals->fetch_assoc()) {
    $user_goals = $row;
}
$stmt_goals->close();

// Metas finais: personalizadas OU calculadas
$goals = [
    'kcal' => $user_goals['target_kcal'] ?? $total_daily_calories_goal,
    'protein' => $user_goals['target_protein_g'] ?? $macros_goal['protein_g'],
    'carbs' => $user_goals['target_carbs_g'] ?? $macros_goal['carbs_g'],
    'fat' => $user_goals['target_fat_g'] ?? $macros_goal['fat_g'],
    'water' => $user_goals['target_water_cups'] ?? $water_goal_data['cups'],
    'steps_daily' => $user_goals['target_steps_daily'] ?? 10000,
    'steps_weekly' => $user_goals['target_steps_weekly'] ?? 70000,
    'workout_weekly' => $user_goals['target_workout_hours_weekly'] ?? 3.0,
    'workout_monthly' => $user_goals['target_workout_hours_monthly'] ?? 12.0,
    'cardio_weekly' => $user_goals['target_cardio_hours_weekly'] ?? 2.5,
    'cardio_monthly' => $user_goals['target_cardio_hours_monthly'] ?? 10.0,
    'sleep' => $user_goals['target_sleep_hours'] ?? 8.0,
    'step_length' => $user_goals['step_length_cm'] ?? ($gender == 'male' ? 76.0 : 66.0),
    'gender' => $gender
];

// ===========================
// DADOS DO DIA (HOJE)
// ===========================
$today_data = [
    'kcal' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 
    'water' => 0, 'steps' => 0, 'workout' => 0, 'cardio' => 0, 'sleep' => 0
];

$stmt_today = $conn->prepare("SELECT * FROM sf_user_daily_tracking WHERE user_id = ? AND date = ?");
$stmt_today->bind_param("is", $user_id, $today);
$stmt_today->execute();
$result_today = $stmt_today->get_result();
if ($row = $result_today->fetch_assoc()) {
    $today_data = [
        'kcal' => (float)($row['kcal_consumed'] ?? 0),
        'protein' => (float)($row['protein_consumed_g'] ?? 0),
        'carbs' => (float)($row['carbs_consumed_g'] ?? 0),
        'fat' => (float)($row['fat_consumed_g'] ?? 0),
        'water' => (int)($row['water_consumed_cups'] ?? 0),
        'steps' => (int)($row['steps_daily'] ?? 0),
        'workout' => (float)($row['workout_hours'] ?? 0),
        'cardio' => (float)($row['cardio_hours'] ?? 0),
        'sleep' => (float)($row['sleep_hours'] ?? 0)
    ];
}
$stmt_today->close();

// ===========================
// DADOS DA SEMANA (últimos 7 dias)
// ===========================
$week_start = date('Y-m-d', strtotime('-6 days'));
$week_data = [
    'kcal' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0,
    'water' => 0, 'steps' => 0, 'workout' => 0, 'cardio' => 0, 'sleep' => 0,
    'days' => 0
];

$stmt_week = $conn->prepare("SELECT * FROM sf_user_daily_tracking WHERE user_id = ? AND date BETWEEN ? AND ?");
$stmt_week->bind_param("iss", $user_id, $week_start, $today);
$stmt_week->execute();
$result_week = $stmt_week->get_result();

while ($row = $result_week->fetch_assoc()) {
    $week_data['kcal'] += (float)($row['kcal_consumed'] ?? 0);
    $week_data['protein'] += (float)($row['protein_consumed_g'] ?? 0);
    $week_data['carbs'] += (float)($row['carbs_consumed_g'] ?? 0);
    $week_data['fat'] += (float)($row['fat_consumed_g'] ?? 0);
    $week_data['water'] += (int)($row['water_consumed_cups'] ?? 0);
    $week_data['steps'] += (int)($row['steps_daily'] ?? 0);
    $week_data['workout'] += (float)($row['workout_hours'] ?? 0);
    $week_data['cardio'] += (float)($row['cardio_hours'] ?? 0);
    $week_data['sleep'] += (float)($row['sleep_hours'] ?? 0);
    $week_data['days']++;
}
$stmt_week->close();

// Calcular médias semanais
$week_avg = [];
if ($week_data['days'] > 0) {
    $week_avg = [
        'kcal' => round($week_data['kcal'] / $week_data['days']),
        'protein' => round($week_data['protein'] / $week_data['days'], 1),
        'carbs' => round($week_data['carbs'] / $week_data['days'], 1),
        'fat' => round($week_data['fat'] / $week_data['days'], 1),
        'water' => round($week_data['water'] / $week_data['days'], 1),
        'sleep' => round($week_data['sleep'] / $week_data['days'], 1)
    ];
} else {
    $week_avg = ['kcal' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'water' => 0, 'sleep' => 0];
}

// ===========================
// DADOS DO MÊS (últimos 30 dias)
// ===========================
$month_start = date('Y-m-d', strtotime('-29 days'));
$month_data = [
    'kcal' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0,
    'water' => 0, 'steps' => 0, 'workout' => 0, 'cardio' => 0, 'sleep' => 0,
    'days' => 0
];

$stmt_month = $conn->prepare("SELECT * FROM sf_user_daily_tracking WHERE user_id = ? AND date BETWEEN ? AND ?");
$stmt_month->bind_param("iss", $user_id, $month_start, $today);
$stmt_month->execute();
$result_month = $stmt_month->get_result();

while ($row = $result_month->fetch_assoc()) {
    $month_data['kcal'] += (float)($row['kcal_consumed'] ?? 0);
    $month_data['protein'] += (float)($row['protein_consumed_g'] ?? 0);
    $month_data['carbs'] += (float)($row['carbs_consumed_g'] ?? 0);
    $month_data['fat'] += (float)($row['fat_consumed_g'] ?? 0);
    $month_data['water'] += (int)($row['water_consumed_cups'] ?? 0);
    $month_data['steps'] += (int)($row['steps_daily'] ?? 0);
    $month_data['workout'] += (float)($row['workout_hours'] ?? 0);
    $month_data['cardio'] += (float)($row['cardio_hours'] ?? 0);
    $month_data['sleep'] += (float)($row['sleep_hours'] ?? 0);
    $month_data['days']++;
}
$stmt_month->close();

// Calcular médias mensais
$month_avg = [];
if ($month_data['days'] > 0) {
    $month_avg = [
        'kcal' => round($month_data['kcal'] / $month_data['days']),
        'protein' => round($month_data['protein'] / $month_data['days'], 1),
        'carbs' => round($month_data['carbs'] / $month_data['days'], 1),
        'fat' => round($month_data['fat'] / $month_data['days'], 1),
        'water' => round($month_data['water'] / $month_data['days'], 1),
        'sleep' => round($month_data['sleep'] / $month_data['days'], 1)
    ];
} else {
    $month_avg = ['kcal' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'water' => 0, 'sleep' => 0];
}

// ===========================
// CÁLCULOS DE DISTÂNCIA (PASSOS)
// ===========================
function calcularDistancia($passos, $comprimento_passo_cm) {
    // Distância em quilômetros
    return round(($passos * $comprimento_passo_cm) / 100000, 2);
}

$steps_distance_today = calcularDistancia($today_data['steps'], $goals['step_length']);
$steps_distance_week = calcularDistancia($week_data['steps'], $goals['step_length']);
$steps_distance_month = calcularDistancia($month_data['steps'], $goals['step_length']);

$steps_avg_weekly = $week_data['days'] > 0 ? round($week_data['steps'] / $week_data['days']) : 0;
$steps_avg_monthly = $month_data['days'] > 0 ? round($month_data['steps'] / $month_data['days']) : 0;

// ===========================
// FREQUÊNCIA DE TREINO
// ===========================
// Contar quantos dias teve treino (workout > 0)
$stmt_workout_freq = $conn->prepare("SELECT COUNT(*) as workout_days FROM sf_user_daily_tracking WHERE user_id = ? AND date BETWEEN ? AND ? AND workout_hours > 0");
$stmt_workout_freq->bind_param("iss", $user_id, $week_start, $today);
$stmt_workout_freq->execute();
$result_workout_freq = $stmt_workout_freq->get_result();
$workout_freq_week = $result_workout_freq->fetch_assoc()['workout_days'];
$stmt_workout_freq->close();

$stmt_workout_freq_month = $conn->prepare("SELECT COUNT(*) as workout_days FROM sf_user_daily_tracking WHERE user_id = ? AND date BETWEEN ? AND ? AND workout_hours > 0");
$stmt_workout_freq_month->bind_param("iss", $user_id, $month_start, $today);
$stmt_workout_freq_month->execute();
$result_workout_freq_month = $stmt_workout_freq_month->get_result();
$workout_freq_month = $result_workout_freq_month->fetch_assoc()['workout_days'];
$stmt_workout_freq_month->close();

// Frequência de cardio
$stmt_cardio_freq = $conn->prepare("SELECT COUNT(*) as cardio_days FROM sf_user_daily_tracking WHERE user_id = ? AND date BETWEEN ? AND ? AND cardio_hours > 0");
$stmt_cardio_freq->bind_param("iss", $user_id, $week_start, $today);
$stmt_cardio_freq->execute();
$result_cardio_freq = $stmt_cardio_freq->get_result();
$cardio_freq_week = $result_cardio_freq->fetch_assoc()['cardio_days'];
$stmt_cardio_freq->close();

$stmt_cardio_freq_month = $conn->prepare("SELECT COUNT(*) as cardio_days FROM sf_user_daily_tracking WHERE user_id = ? AND date BETWEEN ? AND ? AND cardio_hours > 0");
$stmt_cardio_freq_month->bind_param("iss", $user_id, $month_start, $today);
$stmt_cardio_freq_month->execute();
$result_cardio_freq_month = $stmt_cardio_freq_month->get_result();
$cardio_freq_month = $result_cardio_freq_month->fetch_assoc()['cardio_days'];
$stmt_cardio_freq_month->close();

// ===========================
// HISTÓRICO DE PESO (30 dias)
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

// ===========================
// PREPARAÇÃO PARA GRÁFICOS
// ===========================
// Dados para gráfico de nutrição diária vs meta
$nutrition_chart_data = [
    'labels' => ['Calorias', 'Proteínas (g)', 'Carboidratos (g)', 'Gorduras (g)', 'Água (copos)'],
    'consumed_today' => [$today_data['kcal'], $today_data['protein'], $today_data['carbs'], $today_data['fat'], $today_data['water']],
    'consumed_week' => [$week_avg['kcal'], $week_avg['protein'], $week_avg['carbs'], $week_avg['fat'], $week_avg['water']],
    'goals_daily' => [$goals['kcal'], $goals['protein'], $goals['carbs'], $goals['fat'], $goals['water']],
    'goals_weekly' => [$goals['kcal'] * 7, $goals['protein'] * 7, $goals['carbs'] * 7, $goals['fat'] * 7, $goals['water'] * 7]
];

// --- PREPARAÇÃO PARA O LAYOUT ---
$page_title = "Progresso Completo";
$extra_js = ['script.js'];
$extra_css = ['pages/_progress.css'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* ===================================
   ESTILOS PROGRESS V2 - MÓVEL NATIVO
   =================================== */

/* Layout principal - IGUAL ao progress.php original */
.progress-page-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 20px 8px 40px 8px;
}

.page-header h1 {
    font-size: 1.9rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 8px 0;
}

.page-subtitle {
    font-size: 0.95rem;
    color: var(--text-secondary);
    margin: 0 0 20px 0;
}

/* Glass Card - IGUAL ao progress.php original */
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
    margin: 0 0 16px 0;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.section-subtitle {
    color: var(--text-secondary);
    font-size: 0.85rem;
    margin: -8px 0 16px 0;
    text-align: center;
}

/* Grid de Comparação - MÓVEL OTIMIZADO */
.comparison-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}

/* Card de Comparação - MÓVEL NATIVO */
.comparison-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 16px 12px;
    text-align: center;
    transition: all 0.3s ease;
    min-height: 120px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.comparison-card:hover {
    background: rgba(255, 255, 255, 0.05);
    transform: translateY(-2px);
}

.comparison-icon {
    font-size: 1.8rem;
    margin-bottom: 8px;
    line-height: 1;
}

.comparison-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 12px 0;
    line-height: 1.2;
}

.comparison-values {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex: 1;
}

.value-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8rem;
    line-height: 1.2;
}

.value-label {
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 0.75rem;
}

.value-number {
    color: var(--text-primary);
    font-weight: 700;
    font-size: 0.85rem;
    text-align: right;
}

.value-number.good {
    color: #22c55e;
}

.value-number.warning {
    color: #eab308;
}

.value-number.alert {
    color: #ef4444;
}

/* Progress Bar */
.progress-bar-container {
    width: 100%;
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    overflow: hidden;
    margin-top: 8px;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #FF6B00, #FF8533);
    border-radius: 3px;
    transition: width 0.5s ease;
}

/* Chart Container */
.chart-container {
    position: relative;
    height: 250px;
    width: 100%;
    margin: 16px 0;
}

/* Stats Grid - MÓVEL OTIMIZADO */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-top: 16px;
}

.stat-box {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    padding: 12px 8px;
    text-align: center;
    min-height: 80px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.stat-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 4px 0;
    line-height: 1.2;
}

.stat-label {
    font-size: 0.7rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    line-height: 1.2;
}

/* Lista de opções - IGUAL ao progress.php original */
.options-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.option-item {
    display: flex;
    align-items: center;
    padding: 18px 20px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.2s ease;
    margin-bottom: 8px;
    position: relative;
    overflow: hidden;
}

.option-item:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 107, 0, 0.2);
    transform: translateY(-1px);
}

.list-icon {
    font-size: 1.3rem;
    margin-right: 16px;
    width: 24px;
    text-align: center;
    transition: transform 0.2s ease;
}

.option-item:hover .list-icon {
    transform: scale(1.1);
}

.option-item span {
    flex: 1;
    font-size: 1rem;
    font-weight: 500;
    margin: 0;
}

.arrow-icon-list {
    color: var(--text-secondary);
    font-size: 0.9rem;
    transition: transform 0.2s ease;
}

.option-item:hover .arrow-icon-list {
    transform: translateX(4px);
}

/* Responsive - MÓVEL PRIMEIRO */
@media (max-width: 768px) {
    .progress-page-grid {
        padding: 20px 6px 30px 6px;
    }
    
    .page-header h1 {
        font-size: 1.7rem;
    }
    
    .comparison-grid {
        gap: 10px;
    }
    
    .comparison-card {
        padding: 14px 10px;
        min-height: 110px;
    }
    
    .comparison-icon {
        font-size: 1.6rem;
        margin-bottom: 6px;
    }
    
    .comparison-label {
        font-size: 0.8rem;
        margin-bottom: 10px;
    }
    
    .value-row {
        font-size: 0.75rem;
    }
    
    .value-label {
        font-size: 0.7rem;
    }
    
    .value-number {
        font-size: 0.8rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    
    .stat-box {
        padding: 10px 6px;
        min-height: 70px;
    }
    
    .stat-value {
        font-size: 1rem;
    }
    
    .stat-label {
        font-size: 0.65rem;
    }
    
    .chart-container {
        height: 200px;
    }
    
    .glass-card {
        padding: 20px;
    }
    
    .option-item {
        padding: 16px 18px;
    }
}

/* Telas muito pequenas */
@media (max-width: 480px) {
    .comparison-grid {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 6px;
    }
    
    .stat-box {
        padding: 8px 4px;
        min-height: 60px;
    }
    
    .stat-value {
        font-size: 0.9rem;
    }
    
    .stat-label {
        font-size: 0.6rem;
    }
}
</style>

<div class="app-container">
    <section class="progress-page-grid">
        <!-- Header -->
        <header class="page-header">
            <h1>📊 Progresso Completo</h1>
            <p class="page-subtitle">Acompanhe suas metas diárias, semanais e mensais</p>
        </header>

        <!-- ==========================================
             1. NUTRIÇÃO: DIA vs SEMANA vs META
             ========================================== -->
        <div class="glass-card">
            <h3 class="section-title">🍽️ Nutrição: Ingerido vs Meta</h3>
            <p class="section-subtitle">Comparação diária e semanal</p>
            
            <div class="comparison-grid">
                <!-- Calorias -->
                <div class="comparison-card">
                    <div class="comparison-icon">🔥</div>
                    <h4 class="comparison-label">Calorias</h4>
                    <div class="comparison-values">
                        <div class="value-row">
                            <span class="value-label">Hoje:</span>
                            <span class="value-number <?php echo $today_data['kcal'] >= $goals['kcal'] * 0.9 ? 'good' : 'warning'; ?>">
                                <?php echo number_format($today_data['kcal'], 0); ?> / <?php echo $goals['kcal']; ?>
                            </span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Semana:</span>
                            <span class="value-number">
                                <?php echo number_format($week_avg['kcal'], 0); ?> / <?php echo $goals['kcal'] * 7; ?>
                            </span>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo min(($today_data['kcal'] / $goals['kcal']) * 100, 100); ?>%;"></div>
                    </div>
                </div>

                <!-- Proteínas -->
                <div class="comparison-card">
                    <div class="comparison-icon">🥩</div>
                    <h4 class="comparison-label">Proteínas</h4>
                    <div class="comparison-values">
                        <div class="value-row">
                            <span class="value-label">Hoje:</span>
                            <span class="value-number <?php echo $today_data['protein'] >= $goals['protein'] * 0.9 ? 'good' : 'warning'; ?>">
                                <?php echo number_format($today_data['protein'], 1); ?> / <?php echo $goals['protein']; ?>g
                            </span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Semana:</span>
                            <span class="value-number">
                                <?php echo number_format($week_avg['protein'], 1); ?> / <?php echo $goals['protein'] * 7; ?>g
                            </span>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo min(($today_data['protein'] / $goals['protein']) * 100, 100); ?>%;"></div>
                    </div>
                </div>

                <!-- Carboidratos -->
                <div class="comparison-card">
                    <div class="comparison-icon">🍞</div>
                    <h4 class="comparison-label">Carboidratos</h4>
                    <div class="comparison-values">
                        <div class="value-row">
                            <span class="value-label">Hoje:</span>
                            <span class="value-number <?php echo $today_data['carbs'] >= $goals['carbs'] * 0.9 ? 'good' : 'warning'; ?>">
                                <?php echo number_format($today_data['carbs'], 1); ?> / <?php echo $goals['carbs']; ?>g
                            </span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Semana:</span>
                            <span class="value-number">
                                <?php echo number_format($week_avg['carbs'], 1); ?> / <?php echo $goals['carbs'] * 7; ?>g
                            </span>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo min(($today_data['carbs'] / $goals['carbs']) * 100, 100); ?>%;"></div>
                    </div>
                </div>

                <!-- Gorduras -->
                <div class="comparison-card">
                    <div class="comparison-icon">🥑</div>
                    <h4 class="comparison-label">Gorduras</h4>
                    <div class="comparison-values">
                        <div class="value-row">
                            <span class="value-label">Hoje:</span>
                            <span class="value-number <?php echo $today_data['fat'] >= $goals['fat'] * 0.9 ? 'good' : 'warning'; ?>">
                                <?php echo number_format($today_data['fat'], 1); ?> / <?php echo $goals['fat']; ?>g
                            </span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Semana:</span>
                            <span class="value-number">
                                <?php echo number_format($week_avg['fat'], 1); ?> / <?php echo $goals['fat'] * 7; ?>g
                            </span>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo min(($today_data['fat'] / $goals['fat']) * 100, 100); ?>%;"></div>
                    </div>
                </div>
            </div>

            <!-- Gráfico Comparativo -->
            <div class="chart-container">
                <canvas id="nutritionComparisonChart"></canvas>
            </div>
        </div>

        <!-- ==========================================
             2. ÁGUA: DIA vs SEMANA vs META
             ========================================== -->
        <div class="glass-card">
            <h3 class="section-title">💧 Hidratação vs Meta</h3>
            
            <div class="comparison-grid">
                <div class="comparison-card">
                    <div class="comparison-icon">💧</div>
                    <h4 class="comparison-label">Água - Hoje</h4>
                    <div class="comparison-values">
                        <div class="value-row">
                            <span class="value-label">Ingerido:</span>
                            <span class="value-number <?php echo $today_data['water'] >= $goals['water'] ? 'good' : 'warning'; ?>">
                                <?php echo $today_data['water']; ?> copos
                            </span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Meta:</span>
                            <span class="value-number"><?php echo $goals['water']; ?> copos</span>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo min(($today_data['water'] / $goals['water']) * 100, 100); ?>%;"></div>
                    </div>
                </div>

                <div class="comparison-card">
                    <div class="comparison-icon">💧</div>
                    <h4 class="comparison-label">Água - Semana</h4>
                    <div class="comparison-values">
                        <div class="value-row">
                            <span class="value-label">Média Diária:</span>
                            <span class="value-number">
                                <?php echo number_format($week_avg['water'], 1); ?> copos
                            </span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Meta:</span>
                            <span class="value-number"><?php echo $goals['water'] * 7; ?> copos</span>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo min(($week_avg['water'] / $goals['water']) * 100, 100); ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==========================================
             3. PASSOS: DIÁRIO vs SEMANAL vs META
             ========================================== -->
        <div class="glass-card">
            <h3 class="section-title">👟 Passos e Distância</h3>
            <p class="section-subtitle">Média de <?php echo $goals['step_length']; ?>cm por passo (<?php echo $goals['gender'] == 'male' ? 'homem' : 'mulher'; ?>)</p>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <p class="stat-value"><?php echo number_format($today_data['steps']); ?></p>
                    <p class="stat-label">Passos Hoje</p>
                </div>
                <div class="stat-box">
                    <p class="stat-value"><?php echo $steps_distance_today; ?> km</p>
                    <p class="stat-label">Distância Hoje</p>
                </div>
                <div class="stat-box">
                    <p class="stat-value"><?php echo number_format($goals['steps_daily']); ?></p>
                    <p class="stat-label">Meta Diária</p>
                </div>
            </div>

            <div class="comparison-grid" style="margin-top: 16px;">
                <div class="comparison-card">
                    <div class="comparison-icon">🚶</div>
                    <h4 class="comparison-label">Semana</h4>
                    <div class="comparison-values">
                        <div class="value-row">
                            <span class="value-label">Total:</span>
                            <span class="value-number"><?php echo number_format($week_data['steps']); ?> passos</span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Média/dia:</span>
                            <span class="value-number"><?php echo number_format($steps_avg_weekly); ?> passos</span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Distância:</span>
                            <span class="value-number"><?php echo $steps_distance_week; ?> km</span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Meta Semanal:</span>
                            <span class="value-number"><?php echo number_format($goals['steps_weekly']); ?> passos</span>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo min(($week_data['steps'] / $goals['steps_weekly']) * 100, 100); ?>%;"></div>
                    </div>
                </div>

                <div class="comparison-card">
                    <div class="comparison-icon">🏃</div>
                    <h4 class="comparison-label">Mês</h4>
                    <div class="comparison-values">
                        <div class="value-row">
                            <span class="value-label">Total:</span>
                            <span class="value-number"><?php echo number_format($month_data['steps']); ?> passos</span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Média/dia:</span>
                            <span class="value-number"><?php echo number_format($steps_avg_monthly); ?> passos</span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Distância:</span>
                            <span class="value-number"><?php echo $steps_distance_month; ?> km</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==========================================
             4. TREINO: FREQUÊNCIA E VOLUME
             ========================================== -->
        <div class="glass-card">
            <h3 class="section-title">💪 Treino (Exercícios)</h3>
            
            <div class="comparison-grid">
                <div class="comparison-card">
                    <div class="comparison-icon">🏋️</div>
                    <h4 class="comparison-label">Semana</h4>
                    <div class="comparison-values">
                        <div class="value-row">
                            <span class="value-label">Frequência:</span>
                            <span class="value-number"><?php echo $workout_freq_week; ?> dias</span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Volume Total:</span>
                            <span class="value-number"><?php echo number_format($week_data['workout'], 1); ?>h</span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Meta Semanal:</span>
                            <span class="value-number"><?php echo $goals['workout_weekly']; ?>h</span>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo min(($week_data['workout'] / $goals['workout_weekly']) * 100, 100); ?>%;"></div>
                    </div>
                </div>

                <div class="comparison-card">
                    <div class="comparison-icon">💪</div>
                    <h4 class="comparison-label">Mês</h4>
                    <div class="comparison-values">
                        <div class="value-row">
                            <span class="value-label">Frequência:</span>
                            <span class="value-number"><?php echo $workout_freq_month; ?> dias</span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Volume Total:</span>
                            <span class="value-number"><?php echo number_format($month_data['workout'], 1); ?>h</span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Meta Mensal:</span>
                            <span class="value-number"><?php echo $goals['workout_monthly']; ?>h</span>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo min(($month_data['workout'] / $goals['workout_monthly']) * 100, 100); ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==========================================
             5. CARDIO: FREQUÊNCIA E VOLUME
             ========================================== -->
        <div class="glass-card">
            <h3 class="section-title">🏃‍♂️ Cardio</h3>
            
            <div class="comparison-grid">
                <div class="comparison-card">
                    <div class="comparison-icon">🏃</div>
                    <h4 class="comparison-label">Semana</h4>
                    <div class="comparison-values">
                        <div class="value-row">
                            <span class="value-label">Frequência:</span>
                            <span class="value-number"><?php echo $cardio_freq_week; ?> dias</span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Volume Total:</span>
                            <span class="value-number"><?php echo number_format($week_data['cardio'], 1); ?>h</span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Meta Semanal:</span>
                            <span class="value-number"><?php echo $goals['cardio_weekly']; ?>h</span>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo min(($week_data['cardio'] / $goals['cardio_weekly']) * 100, 100); ?>%;"></div>
                    </div>
                </div>

                <div class="comparison-card">
                    <div class="comparison-icon">🚴</div>
                    <h4 class="comparison-label">Mês</h4>
                    <div class="comparison-values">
                        <div class="value-row">
                            <span class="value-label">Frequência:</span>
                            <span class="value-number"><?php echo $cardio_freq_month; ?> dias</span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Volume Total:</span>
                            <span class="value-number"><?php echo number_format($month_data['cardio'], 1); ?>h</span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Meta Mensal:</span>
                            <span class="value-number"><?php echo $goals['cardio_monthly']; ?>h</span>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo min(($month_data['cardio'] / $goals['cardio_monthly']) * 100, 100); ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==========================================
             6. SONO
             ========================================== -->
        <div class="glass-card">
            <h3 class="section-title">😴 Horas Dormidas</h3>
            
            <div class="comparison-grid">
                <div class="comparison-card">
                    <div class="comparison-icon">🌙</div>
                    <h4 class="comparison-label">Hoje</h4>
                    <div class="comparison-values">
                        <div class="value-row">
                            <span class="value-label">Dormiu:</span>
                            <span class="value-number <?php echo $today_data['sleep'] >= $goals['sleep'] ? 'good' : 'warning'; ?>">
                                <?php echo number_format($today_data['sleep'], 1); ?>h
                            </span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Meta:</span>
                            <span class="value-number"><?php echo $goals['sleep']; ?>h</span>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo min(($today_data['sleep'] / $goals['sleep']) * 100, 100); ?>%;"></div>
                    </div>
                </div>

                <div class="comparison-card">
                    <div class="comparison-icon">😴</div>
                    <h4 class="comparison-label">Média Semanal</h4>
                    <div class="comparison-values">
                        <div class="value-row">
                            <span class="value-label">Média/noite:</span>
                            <span class="value-number">
                                <?php echo number_format($week_avg['sleep'], 1); ?>h
                            </span>
                        </div>
                        <div class="value-row">
                            <span class="value-label">Meta:</span>
                            <span class="value-number"><?php echo $goals['sleep']; ?>h</span>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo min(($week_avg['sleep'] / $goals['sleep']) * 100, 100); ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==========================================
             7. GRÁFICO DE PESO
             ========================================== -->
        <?php if (count($weight_history) > 1): ?>
        <div class="glass-card">
            <h3 class="section-title">⚖️ Evolução do Peso</h3>
            <p class="section-subtitle">Últimos 30 dias</p>
            <div class="chart-container">
                <canvas id="weightChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- ==========================================
             8. BOTÃO DE FOTOS - RESTAURADO!
             ========================================== -->
        <div class="glass-card">
            <h3 class="section-title">Acompanhamento Visual</h3>
            <ul class="options-list">
                <li>
                    <a href="<?php echo BASE_APP_URL; ?>/measurements_progress.php" class="option-item">
                        <i class="fas fa-camera list-icon" style="color: #8b5cf6;"></i>
                        <span>Fotos e Medidas</span>
                        <i class="fas fa-chevron-right arrow-icon-list"></i>
                    </a>
                </li>
            </ul>
        </div>

    </section>
</div>

<!-- Scripts dos Gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===========================
    // GRÁFICO DE NUTRIÇÃO COMPARATIVO
    // ===========================
    const nutritionCtx = document.getElementById('nutritionComparisonChart');
    if (nutritionCtx) {
        new Chart(nutritionCtx, {
            type: 'bar',
            data: {
                labels: ['Calorias', 'Proteínas (g)', 'Carbs (g)', 'Gorduras (g)', 'Água (copos)'],
                datasets: [
                    {
                        label: 'Meta Diária',
                        data: <?php echo json_encode($nutrition_chart_data['goals_daily']); ?>,
                        backgroundColor: 'rgba(255, 107, 0, 0.3)',
                        borderColor: '#FF6B00',
                        borderWidth: 2
                    },
                    {
                        label: 'Meta Semanal',
                        data: <?php echo json_encode($nutrition_chart_data['goals_weekly']); ?>,
                        backgroundColor: 'rgba(255, 107, 0, 0.1)',
                        borderColor: '#FF6B00',
                        borderWidth: 1,
                        borderDash: [5, 5]
                    },
                    {
                        label: 'Hoje',
                        data: <?php echo json_encode($nutrition_chart_data['consumed_today']); ?>,
                        backgroundColor: 'rgba(34, 197, 94, 0.6)',
                        borderColor: '#22c55e',
                        borderWidth: 2
                    },
                    {
                        label: 'Média Semanal',
                        data: <?php echo json_encode($nutrition_chart_data['consumed_week']); ?>,
                        backgroundColor: 'rgba(59, 130, 246, 0.6)',
                        borderColor: '#3b82f6',
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            color: '#ffffff',
                            font: { size: 12 }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: '#FF6B00',
                        borderWidth: 1,
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#ffffff',
                            font: { size: 11 }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#ffffff',
                            font: { size: 10 }
                        }
                    }
                }
            }
        });
    }

    // ===========================
    // GRÁFICO DE PESO
    // ===========================
    <?php if (count($weight_history) > 1): ?>
    const weightData = <?php echo json_encode($weight_history); ?>;
    const weightCtx = document.getElementById('weightChart');
    if (weightCtx) {
        const labels = weightData.map(item => {
            const date = new Date(item.date_recorded);
            return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
        });
        
        const weights = weightData.map(item => parseFloat(item.weight_kg));
        
        new Chart(weightCtx, {
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
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
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
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#ffffff',
                            font: { size: 11 },
                            callback: function(value) {
                                return value + ' kg';
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#ffffff',
                            font: { size: 11 }
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>
});
</script>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>