<?php
// admin/view_user.php - VERSÃO LIMPA APENAS COM INCLUDES DAS ABAS

// Definir fuso horário do Brasil
date_default_timezone_set('America/Sao_Paulo');

// --- INCLUDES E AUTENTICAÇÃO ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
$conn = require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/functions_admin.php';
requireAdminLogin();

// --- VALIDAÇÃO E BUSCA DE DADOS ---
$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$user_id) {
    header("Location: users.php");
    exit;
}

// Busca completa dos dados do usuário, incluindo os novos campos da anamnese
$stmt_user = $conn->prepare(
    "SELECT u.*, p.* FROM sf_users u LEFT JOIN sf_user_profiles p ON u.id = p.user_id WHERE u.id = ?"
);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

if (!$user_data) {
    $error_message = "Erro: Paciente com o ID " . htmlspecialchars($user_id) . " não foi encontrado.";
    $page_title = "Erro";
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container"><p class="error-message">' . $error_message . '</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// --- DADOS PARA AS ABAS ---
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$daysToShow = 30; // Buscar últimos 30 dias
$startDate = date('Y-m-d', strtotime($endDate . " -" . ($daysToShow - 1) . " days"));
$meal_history = getGroupedMealHistory($conn, $user_id, $startDate, $endDate);

// --- LÓGICA GRÁFICO DE PESO ---
$stmt_weight_history = $conn->prepare("SELECT date_recorded, weight_kg FROM sf_user_weight_history WHERE user_id = ? ORDER BY date_recorded ASC");
$stmt_weight_history->bind_param("i", $user_id);
$stmt_weight_history->execute();
$history_result = $stmt_weight_history->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_weight_history->close();

// Preparar dados para o gráfico de peso
$weight_chart_data = [];
foreach ($history_result as $record) {
    $weight_chart_data[] = [
        'date' => $record['date_recorded'],
        'weight' => floatval($record['weight_kg'])
    ];
}

// --- DADOS PARA HIDRATAÇÃO ---
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// Buscar dados de hidratação dos últimos 30 dias
$stmt_hydration = $conn->prepare("
    SELECT DATE(created_at) as date, SUM(water_ml) as water_ml 
    FROM sf_user_water_intake 
    WHERE user_id = ? AND DATE(created_at) >= ? 
    GROUP BY DATE(created_at) 
    ORDER BY date DESC
");
$stmt_hydration->bind_param("is", $user_id, $startDate);
$stmt_hydration->execute();
$hydration_result = $stmt_hydration->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_hydration->close();

// Processar dados de hidratação
$hydration_data = [];
foreach ($hydration_result as $record) {
    $hydration_data[] = [
        'date' => $record['date'],
        'water_ml' => intval($record['water_ml'])
    ];
}

// Meta de água (padrão 2000ml se não definida)
$water_goal_ml = 2000;

// Calcular estatísticas de hidratação
$water_stats_today = ['water_ml' => 0, 'goal_ml' => $water_goal_ml, 'percentage' => 0];
$water_stats_yesterday = ['water_ml' => 0, 'goal_ml' => $water_goal_ml, 'percentage' => 0];
$water_stats_7 = ['total_ml' => 0, 'avg_ml' => 0, 'avg_percentage' => 0];
$water_stats_15 = ['total_ml' => 0, 'avg_ml' => 0, 'avg_percentage' => 0];
$water_stats_30 = ['total_ml' => 0, 'avg_ml' => 0, 'avg_percentage' => 0];
$water_stats_90 = ['total_ml' => 0, 'avg_ml' => 0, 'avg_percentage' => 0];
$water_stats_all = ['total_ml' => 0, 'avg_ml' => 0, 'avg_percentage' => 0];

// Processar estatísticas
if (!empty($hydration_data)) {
    // Hoje
    $today_data = array_filter($hydration_data, function($day) use ($today) {
        return $day['date'] === $today;
    });
    if (!empty($today_data)) {
        $water_stats_today['water_ml'] = array_sum(array_column($today_data, 'water_ml'));
        $water_stats_today['percentage'] = ($water_stats_today['water_ml'] / $water_goal_ml) * 100;
    }
    
    // Ontem
    $yesterday_data = array_filter($hydration_data, function($day) use ($yesterday) {
        return $day['date'] === $yesterday;
    });
    if (!empty($yesterday_data)) {
        $water_stats_yesterday['water_ml'] = array_sum(array_column($yesterday_data, 'water_ml'));
        $water_stats_yesterday['percentage'] = ($water_stats_yesterday['water_ml'] / $water_goal_ml) * 100;
    }
    
    // Últimos 7 dias
    $last_7_days = array_slice($hydration_data, 0, 7);
    if (!empty($last_7_days)) {
        $water_stats_7['total_ml'] = array_sum(array_column($last_7_days, 'water_ml'));
        $water_stats_7['avg_ml'] = $water_stats_7['total_ml'] / count($last_7_days);
        $water_stats_7['avg_percentage'] = ($water_stats_7['avg_ml'] / $water_goal_ml) * 100;
    }
    
    // Últimos 15 dias
    $last_15_days = array_slice($hydration_data, 0, 15);
    if (!empty($last_15_days)) {
        $water_stats_15['total_ml'] = array_sum(array_column($last_15_days, 'water_ml'));
        $water_stats_15['avg_ml'] = $water_stats_15['total_ml'] / count($last_15_days);
        $water_stats_15['avg_percentage'] = ($water_stats_15['avg_ml'] / $water_goal_ml) * 100;
    }
    
    // Últimos 30 dias
    $last_30_days = array_slice($hydration_data, 0, 30);
    if (!empty($last_30_days)) {
        $water_stats_30['total_ml'] = array_sum(array_column($last_30_days, 'water_ml'));
        $water_stats_30['avg_ml'] = $water_stats_30['total_ml'] / count($last_30_days);
        $water_stats_30['avg_percentage'] = ($water_stats_30['avg_ml'] / $water_goal_ml) * 100;
    }
    
    // Últimos 90 dias
    $last_90_days = array_slice($hydration_data, 0, 90);
    if (!empty($last_90_days)) {
        $water_stats_90['total_ml'] = array_sum(array_column($last_90_days, 'water_ml'));
        $water_stats_90['avg_ml'] = $water_stats_90['total_ml'] / count($last_90_days);
        $water_stats_90['avg_percentage'] = ($water_stats_90['avg_ml'] / $water_goal_ml) * 100;
    }
    
    // Todos os dados
    $water_stats_all['total_ml'] = array_sum(array_column($hydration_data, 'water_ml'));
    $water_stats_all['avg_ml'] = $water_stats_all['total_ml'] / count($hydration_data);
    $water_stats_all['avg_percentage'] = ($water_stats_all['avg_ml'] / $water_goal_ml) * 100;
}

// --- DADOS PARA NUTRIENTES ---
$last_7_days_data = [];
$nutrients_stats_today = ['consumed_kcal' => 0, 'goal_kcal' => 2000, 'percentage' => 0];
$nutrients_stats_yesterday = ['consumed_kcal' => 0, 'goal_kcal' => 2000, 'percentage' => 0];
$nutrients_stats_7 = ['total_kcal' => 0, 'avg_kcal' => 0, 'avg_percentage' => 0];
$nutrients_stats_15 = ['total_kcal' => 0, 'avg_kcal' => 0, 'avg_percentage' => 0];
$nutrients_stats_30 = ['total_kcal' => 0, 'avg_kcal' => 0, 'avg_percentage' => 0];
$nutrients_stats_90 = ['total_kcal' => 0, 'avg_kcal' => 0, 'avg_percentage' => 0];
$nutrients_stats_all = ['total_kcal' => 0, 'avg_kcal' => 0, 'avg_percentage' => 0];

// Processar dados de nutrientes dos últimos 7 dias
foreach ($meal_history as $day) {
    $last_7_days_data[] = [
        'date' => $day['date'],
        'total_kcal' => $day['total_kcal'],
        'total_protein' => $day['total_protein'],
        'total_carbs' => $day['total_carbs'],
        'total_fat' => $day['total_fat']
    ];
}

// Calcular estatísticas de nutrientes
if (!empty($last_7_days_data)) {
    // Hoje
    $today_nutrients = array_filter($last_7_days_data, function($day) use ($today) {
        return $day['date'] === $today;
    });
    if (!empty($today_nutrients)) {
        $nutrients_stats_today['consumed_kcal'] = array_sum(array_column($today_nutrients, 'total_kcal'));
        $nutrients_stats_today['percentage'] = ($nutrients_stats_today['consumed_kcal'] / $nutrients_stats_today['goal_kcal']) * 100;
    }
    
    // Ontem
    $yesterday_nutrients = array_filter($last_7_days_data, function($day) use ($yesterday) {
        return $day['date'] === $yesterday;
    });
    if (!empty($yesterday_nutrients)) {
        $nutrients_stats_yesterday['consumed_kcal'] = array_sum(array_column($yesterday_nutrients, 'total_kcal'));
        $nutrients_stats_yesterday['percentage'] = ($nutrients_stats_yesterday['consumed_kcal'] / $nutrients_stats_yesterday['goal_kcal']) * 100;
    }
    
    // Últimos 7 dias
    $nutrients_stats_7['total_kcal'] = array_sum(array_column($last_7_days_data, 'total_kcal'));
    $nutrients_stats_7['avg_kcal'] = $nutrients_stats_7['total_kcal'] / count($last_7_days_data);
    $nutrients_stats_7['avg_percentage'] = ($nutrients_stats_7['avg_kcal'] / $nutrients_stats_today['goal_kcal']) * 100;
}

// --- DADOS PARA ROTINA ---
$routine_data = [
    'steps' => [],
    'exercise' => [],
    'sleep' => []
];

// Buscar dados de passos
$stmt_steps = $conn->prepare("
    SELECT date_recorded, steps_daily 
    FROM sf_user_steps 
    WHERE user_id = ? AND date_recorded >= ? 
    ORDER BY date_recorded DESC
");
$stmt_steps->bind_param("is", $user_id, $startDate);
$stmt_steps->execute();
$steps_result = $stmt_steps->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_steps->close();

foreach ($steps_result as $record) {
    $routine_data['steps'][] = [
        'date' => $record['date_recorded'],
        'steps_daily' => intval($record['steps_daily'])
    ];
}

// Buscar dados de exercícios
$stmt_exercise = $conn->prepare("
    SELECT updated_at, duration_minutes, exercise_type 
    FROM sf_user_exercises 
    WHERE user_id = ? AND DATE(updated_at) >= ? 
    ORDER BY updated_at DESC
");
$stmt_exercise->bind_param("is", $user_id, $startDate);
$stmt_exercise->execute();
$exercise_result = $stmt_exercise->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_exercise->close();

foreach ($exercise_result as $record) {
    $routine_data['exercise'][] = [
        'updated_at' => $record['updated_at'],
        'duration_minutes' => intval($record['duration_minutes']),
        'exercise_type' => $record['exercise_type']
    ];
}

// Buscar dados de sono
$stmt_sleep = $conn->prepare("
    SELECT date_recorded, sleep_hours 
    FROM sf_user_sleep 
    WHERE user_id = ? AND date_recorded >= ? 
    ORDER BY date_recorded DESC
");
$stmt_sleep->bind_param("is", $user_id, $startDate);
$stmt_sleep->execute();
$sleep_result = $stmt_sleep->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_sleep->close();

foreach ($sleep_result as $record) {
    $routine_data['sleep'][] = [
        'date' => $record['date_recorded'],
        'sleep_hours' => floatval($record['sleep_hours'])
    ];
}

// --- DADOS PARA PROGRESSO ---
$progress_data = [
    'weight_history' => $weight_chart_data,
    'current_weight' => $user_data['current_weight'] ?? 0,
    'target_weight' => $user_data['target_weight'] ?? 0,
    'height_cm' => $user_data['height_cm'] ?? 0
];

// --- DADOS PARA FEEDBACK ---
$feedback_data = [
    'checkins' => [],
    'routines' => []
];

// --- DADOS PARA METAS ---
$goals_data = [
    'daily_calories' => $user_data['daily_calories'] ?? 2000,
    'daily_protein' => $user_data['daily_protein'] ?? 100,
    'daily_carbs' => $user_data['daily_carbs'] ?? 250,
    'daily_fat' => $user_data['daily_fat'] ?? 65,
    'daily_water' => $user_data['daily_water'] ?? 2000
];

// --- DADOS PARA DIÁRIO ---
$diary_data = [
    'meal_history' => $meal_history,
    'current_date' => $today,
    'user_id' => $user_id
];

// --- INCLUIR HEADER ---
require_once __DIR__ . '/includes/header.php';
?>

<!-- CONTEÚDO DAS ABAS - APENAS INCLUDES -->
<div class="view-user-tab">
    <?php include 'view_user_diary.php'; ?>
    <?php include 'view_user_nutrients.php'; ?>
    <?php include 'view_user_progress.php'; ?>
    <?php include 'view_user_hydration.php'; ?>
    <?php include 'view_user_routine.php'; ?>
    <?php include 'view_user_feedback.php'; ?>
    <?php include 'view_user_goals.php'; ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>
