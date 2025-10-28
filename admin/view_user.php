<?php
// admin/view_user.php (VERSÃO FINAL COM ANAMNESE COMPLETA E SEM OMISSÕES)

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
$current_weight_from_profile = (float)($user_data['weight_kg'] ?? 0);
$all_weights = [];
foreach ($history_result as $row) {
    $all_weights[date('Y-m-d', strtotime($row['date_recorded']))] = (float)($row['weight_kg'] ?? 0);
}
if ($current_weight_from_profile > 0) {
    $all_weights[date('Y-m-d')] = $current_weight_from_profile;
}
ksort($all_weights);
$weight_chart_data = ['labels' => [], 'data' => []];
foreach ($all_weights as $date => $weight) {
    $weight_chart_data['labels'][] = date('d/m/Y', strtotime($date));
    $weight_chart_data['data'][] = $weight;
}

// --- FOTOS ---
$stmt_photos = $conn->prepare("SELECT date_recorded, photo_front, photo_side, photo_back FROM sf_user_measurements WHERE user_id = ? AND (photo_front IS NOT NULL OR photo_side IS NOT NULL OR photo_back IS NOT NULL) ORDER BY date_recorded DESC");
$stmt_photos->bind_param("i", $user_id);
$stmt_photos->execute();
$photo_history = $stmt_photos->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_photos->close();

// --- HISTÓRICO DE HIDRATAÇÃO ---
$stmt_water = $conn->prepare("SELECT date, water_consumed_cups FROM sf_user_daily_tracking WHERE user_id = ? AND water_consumed_cups > 0 ORDER BY date DESC LIMIT 120");
$stmt_water->bind_param("i", $user_id);
$stmt_water->execute();
$water_history = $stmt_water->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_water->close();

// Meta de água - priorizar customizada se existir
if (!empty($user_data['custom_water_goal_ml'])) {
    $water_goal_ml = (int)$user_data['custom_water_goal_ml'];
    $water_goal_cups = ceil($water_goal_ml / 250); // 250ml por copo
} else {
$water_goal_data = getWaterIntakeSuggestion($user_data['weight_kg'] ?? 0);
$water_goal_ml = $water_goal_data['total_ml'];
$water_goal_cups = $water_goal_data['cups'];
}

// --- DURAÇÕES DOS EXERCÍCIOS ---
$stmt_durations = $conn->prepare("SELECT exercise_name, duration_minutes, updated_at FROM sf_user_exercise_durations WHERE user_id = ? ORDER BY exercise_name ASC");
$stmt_durations->bind_param("i", $user_id);
$stmt_durations->execute();
$exercise_durations = $stmt_durations->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_durations->close();

// Calcular metas de nutrientes (definir logo após buscar dados do usuário)
$gender = $user_data['gender'] ?? 'male';
$weight_kg = (float)($user_data['weight_kg'] ?? 70);
$height_cm = (int)($user_data['height_cm'] ?? 170);
$dob = $user_data['dob'] ?? date('Y-m-d', strtotime('-30 years'));
$exercise_frequency = $user_data['exercise_frequency'] ?? 'sedentary';
$objective = $user_data['objective'] ?? 'maintain';

$age_years = calculateAge($dob);

// Priorizar metas customizadas se existirem, senão calcular automaticamente
if (!empty($user_data['custom_calories_goal'])) {
    $total_daily_calories_goal = (int)$user_data['custom_calories_goal'];
} else {
$total_daily_calories_goal = calculateTargetDailyCalories($gender, $weight_kg, $height_cm, $age_years, $exercise_frequency, $objective);
}

if (!empty($user_data['custom_protein_goal_g']) && !empty($user_data['custom_carbs_goal_g']) && !empty($user_data['custom_fat_goal_g'])) {
    $macros_goal = [
        'protein_g' => (float)$user_data['custom_protein_goal_g'],
        'carbs_g' => (float)$user_data['custom_carbs_goal_g'],
        'fat_g' => (float)$user_data['custom_fat_goal_g']
    ];
} else {
$macros_goal = calculateMacronutrients($total_daily_calories_goal, $objective);
}

// Processar histórico de hidratação
$hydration_data = [];
foreach ($water_history as $day) {
    $water_ml = $day['water_consumed_cups'] * 250; // 250ml por copo
    $raw_percentage = $water_goal_ml > 0 ? ($water_ml / $water_goal_ml) * 100 : 0;
    $percentage = min(round($raw_percentage, 1), 100); // Limitar a 100% máximo
    
    // Determinar status detalhado
    $status = 'excellent';
    $status_text = 'Excelente';
    $status_class = 'success';
    
    if ($percentage == 0) {
        $status = 'empty';
        $status_text = 'Sem dados';
        $status_class = 'info';
    } elseif ($percentage == 100) {
        $status = 'excellent';
        $status_text = 'Meta atingida';
        $status_class = 'success';
    } elseif ($percentage >= 90) {
        $status = 'good';
        $status_text = 'Quase na meta';
        $status_class = 'info';
    } elseif ($percentage >= 70) {
        $status = 'fair';
        $status_text = 'Abaixo da meta';
        $status_class = 'warning';
    } elseif ($percentage >= 50) {
        $status = 'poor';
        $status_text = 'Muito abaixo';
        $status_class = 'warning';
    } else {
        $status = 'critical';
        $status_text = 'Crítico';
        $status_class = 'error';
    }
    
    $hydration_data[] = [
        'date' => $day['date'],
        'ml' => $water_ml,
        'cups' => $day['water_consumed_cups'],
        'percentage' => $percentage,
        'raw_percentage' => $raw_percentage, // Manter porcentagem real para referência
        'goal_reached' => $water_ml >= $water_goal_ml,
        'status' => $status,
        'status_text' => $status_text,
        'status_class' => $status_class
    ];
}

// Calcular estatísticas por data específica
function calculateHydrationStatsByDate($data, $target_date) {
    $filtered_data = array_filter($data, function($day) use ($target_date) {
        return $day['date'] === $target_date;
    });
    
    if (empty($filtered_data)) {
        return [
            'avg_ml' => 0, 
            'avg_percentage' => 0, 
            'compliance_rate' => 0, 
            'total_days' => 0,
            'excellent_days' => 0,
            'good_days' => 0,
            'fair_days' => 0,
            'poor_days' => 0,
            'critical_days' => 0,
            'best_day' => 0,
            'worst_day' => 0,
            'consistency_score' => 0
        ];
    }
    
    return calculateHydrationStats($filtered_data);
}

// Calcular estatísticas por período
function calculateHydrationStats($data, $days = null, $offset = 0) {
    if (empty($data)) return [
        'avg_ml' => 0, 
        'avg_percentage' => 0, 
        'compliance_rate' => 0, 
        'total_days' => 0,
        'excellent_days' => 0,
        'good_days' => 0,
        'fair_days' => 0,
        'poor_days' => 0,
        'critical_days' => 0,
        'best_day' => 0,
        'worst_day' => 0,
        'consistency_score' => 0
    ];
    
    $filtered_data = $days ? array_slice($data, $offset, $days) : $data;
    $total_ml = array_sum(array_column($filtered_data, 'ml'));
    $total_percentage = array_sum(array_column($filtered_data, 'percentage'));
    $goal_reached_days = array_sum(array_column($filtered_data, 'goal_reached'));
    
    // Contar status detalhados
    $excellent_days = count(array_filter($filtered_data, fn($d) => $d['status'] === 'excellent'));
    $good_days = count(array_filter($filtered_data, fn($d) => $d['status'] === 'good'));
    $fair_days = count(array_filter($filtered_data, fn($d) => $d['status'] === 'fair'));
    $poor_days = count(array_filter($filtered_data, fn($d) => $d['status'] === 'poor'));
    $critical_days = count(array_filter($filtered_data, fn($d) => $d['status'] === 'critical'));
    
    // Melhor e pior dia
    $best_day = max(array_column($filtered_data, 'percentage'));
    $worst_day = min(array_column($filtered_data, 'percentage'));
    
    // Score de consistência (menor variação = melhor)
    $percentages = array_column($filtered_data, 'percentage');
    $variance = count($percentages) > 1 ? array_sum(array_map(fn($x) => pow($x - ($total_percentage / count($filtered_data)), 2), $percentages)) / count($percentages) : 0;
    $consistency_score = max(0, 100 - sqrt($variance));
    
    return [
        'avg_ml' => round($total_ml / count($filtered_data), 0),
        'avg_percentage' => round($total_percentage / count($filtered_data), 1),
        'compliance_rate' => round(($goal_reached_days / count($filtered_data)) * 100, 1),
        'total_days' => count($filtered_data),
        'excellent_days' => $excellent_days,
        'good_days' => $good_days,
        'fair_days' => $fair_days,
        'poor_days' => $poor_days,
        'critical_days' => $critical_days,
        'best_day' => round($best_day, 1),
        'worst_day' => round($worst_day, 1),
        'consistency_score' => round($consistency_score, 1)
    ];
}

$water_stats_all = calculateHydrationStats($hydration_data);
$water_stats_90 = calculateHydrationStats($hydration_data, 90);
$water_stats_30 = calculateHydrationStats($hydration_data, 30);
$water_stats_15 = calculateHydrationStats($hydration_data, 15);
$water_stats_7 = calculateHydrationStats($hydration_data, 7);
// Calcular estatísticas para hoje e ontem baseado na data real
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// Debug: Verificar datas (movido para depois do processamento de nutrientes)

$water_stats_today = calculateHydrationStatsByDate($hydration_data, $today);
$water_stats_yesterday = calculateHydrationStatsByDate($hydration_data, $yesterday);

// --- PROCESSAMENTO DE DADOS DE NUTRIENTES (LÓGICA DEFINITIVA) ---
// Função robusta que garante range completo de datas com LEFT JOIN

function getNutrientStats($conn, $userId, $periodDays, $macros_goal, $total_daily_calories_goal) {
    $today = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime("-" . ($periodDays - 1) . " days", strtotime($today)));

    // Gera um range completo de dias (garante que até os dias sem registro apareçam)
    $dates = [];
    for ($i = 0; $i < $periodDays; $i++) {
        $dates[] = date('Y-m-d', strtotime("-$i days", strtotime($today)));
    }

    // Monta o SQL com LEFT JOIN para incluir dias sem refeição
    $sql = "
        SELECT 
            d.date AS dia,
            COALESCE(SUM(t.kcal_consumed), 0) AS total_kcal,
            COALESCE(SUM(t.protein_consumed_g), 0) AS total_protein,
            COALESCE(SUM(t.carbs_consumed_g), 0) AS total_carbs,
            COALESCE(SUM(t.fat_consumed_g), 0) AS total_fat
        FROM (
            SELECT DATE('$today' - INTERVAL n DAY) AS date
            FROM (
                SELECT 0 AS n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 
                UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 
                UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 
                UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 
                UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 
                UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29
            ) AS x
            WHERE DATE('$today' - INTERVAL n DAY) BETWEEN '$startDate' AND '$today'
        ) AS d
        LEFT JOIN sf_user_daily_tracking t 
            ON DATE(t.date) = d.date 
            AND t.user_id = ?
        GROUP BY d.date
        ORDER BY d.date ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $totalKcal = 0;
    $totalProtein = 0;
    $totalCarbs = 0;
    $totalFat = 0;
    $dailyData = [];

    while ($row = $result->fetch_assoc()) {
        $totalKcal += $row['total_kcal'];
        $totalProtein += $row['total_protein'];
        $totalCarbs += $row['total_carbs'];
        $totalFat += $row['total_fat'];
        
        $dailyData[] = [
            'date' => $row['dia'],
            'kcal_consumed' => $row['total_kcal'],
            'protein_consumed_g' => $row['total_protein'],
            'carbs_consumed_g' => $row['total_carbs'],
            'fat_consumed_g' => $row['total_fat']
        ];
    }

    // Calcular dias com consumo real (kcal > 0)
    $daysWithConsumption = 0;
    $excellentDays = 0;
    $goodDays = 0;
    foreach ($dailyData as $day) {
        if ($day['kcal_consumed'] > 0) {
            $daysWithConsumption++;
            
            $dayKcalPercentage = $total_daily_calories_goal > 0 ? round(($day['kcal_consumed'] / $total_daily_calories_goal) * 100, 1) : 0;
            if ($dayKcalPercentage >= 90) {
                $excellentDays++;
            } elseif ($dayKcalPercentage >= 70) {
                $goodDays++;
            }
        }
    }
    
    // Calcular médias ponderadas (dividir pelo total de dias do período)
    // Isso reflete a constância/disciplina do paciente
    $avgKcal = round($totalKcal / $periodDays, 0);
    $avgProtein = round($totalProtein / $periodDays, 1);
    $avgCarbs = round($totalCarbs / $periodDays, 1);
    $avgFat = round($totalFat / $periodDays, 1);
    
    // Calcular médias reais (apenas dias com consumo) para o gráfico
    $realAvgKcal = $daysWithConsumption > 0 ? round($totalKcal / $daysWithConsumption, 0) : 0;
    $realAvgProtein = $daysWithConsumption > 0 ? round($totalProtein / $daysWithConsumption, 1) : 0;
    $realAvgCarbs = $daysWithConsumption > 0 ? round($totalCarbs / $daysWithConsumption, 1) : 0;
    $realAvgFat = $daysWithConsumption > 0 ? round($totalFat / $daysWithConsumption, 1) : 0;
    
    // Calcular percentuais baseados na média ponderada (para mostrar disciplina)
    $avgKcalPercentage = $total_daily_calories_goal > 0 ? round(($avgKcal / $total_daily_calories_goal) * 100, 1) : 0;
    $avgProteinPercentage = $macros_goal['protein_g'] > 0 ? round(($avgProtein / $macros_goal['protein_g']) * 100, 1) : 0;
    $avgCarbsPercentage = $macros_goal['carbs_g'] > 0 ? round(($avgCarbs / $macros_goal['carbs_g']) * 100, 1) : 0;
    $avgFatPercentage = $macros_goal['fat_g'] > 0 ? round(($avgFat / $macros_goal['fat_g']) * 100, 1) : 0;
    
    // Calcular percentual geral da meta (média dos percentuais de todos os macronutrientes)
    $avgOverallPercentage = round(($avgKcalPercentage + $avgProteinPercentage + $avgCarbsPercentage + $avgFatPercentage) / 4, 1);
    
    // Calcular aderência (dias com registro / total de dias)
    $adherencePercentage = round(($daysWithConsumption / $periodDays) * 100, 1);

    return [
        // Médias ponderadas (para cards - mostram disciplina/constância)
        'avg_kcal' => $avgKcal,
        'avg_protein' => $avgProtein,
        'avg_carbs' => $avgCarbs,
        'avg_fat' => $avgFat,
        'avg_kcal_percentage' => $avgKcalPercentage,
        'avg_protein_percentage' => $avgProteinPercentage,
        'avg_carbs_percentage' => $avgCarbsPercentage,
        'avg_fat_percentage' => $avgFatPercentage,
        'avg_overall_percentage' => $avgOverallPercentage,
        
        // Médias reais (para gráfico - mostram comportamento alimentar)
        'real_avg_kcal' => $realAvgKcal,
        'real_avg_protein' => $realAvgProtein,
        'real_avg_carbs' => $realAvgCarbs,
        'real_avg_fat' => $realAvgFat,
        
        // Aderência e qualidade
        'excellent_days' => $excellentDays,
        'good_days' => $goodDays,
        'days_with_consumption' => $daysWithConsumption,
        'adherence_percentage' => $adherencePercentage,
        'total_days' => $periodDays,
        'daily_data' => $dailyData
    ];
}

// Calcular estatísticas para cada período
$nutrients_stats_7 = getNutrientStats($conn, $user_id, 7, $macros_goal, $total_daily_calories_goal);
$nutrients_stats_15 = getNutrientStats($conn, $user_id, 15, $macros_goal, $total_daily_calories_goal);
$nutrients_stats_30 = getNutrientStats($conn, $user_id, 30, $macros_goal, $total_daily_calories_goal);

// Dados para o gráfico dos últimos 7 dias
$last_7_days_data = $nutrients_stats_7['daily_data'];

// Dados para hoje e ontem
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$stmt_today = $conn->prepare("
    SELECT 
        kcal_consumed, protein_consumed_g, carbs_consumed_g, fat_consumed_g
    FROM sf_user_daily_tracking 
    WHERE user_id = ? AND date = ?
");
$stmt_today->bind_param("is", $user_id, $today);
$stmt_today->execute();
$today_data = $stmt_today->get_result()->fetch_assoc();
$stmt_today->close();

$stmt_yesterday = $conn->prepare("
    SELECT 
        kcal_consumed, protein_consumed_g, carbs_consumed_g, fat_consumed_g
    FROM sf_user_daily_tracking 
    WHERE user_id = ? AND date = ?
");
$stmt_yesterday->bind_param("is", $user_id, $yesterday);
$stmt_yesterday->execute();
$yesterday_data = $stmt_yesterday->get_result()->fetch_assoc();
$stmt_yesterday->close();

$nutrients_stats_today = [
    'avg_kcal' => $today_data['kcal_consumed'] ?? 0,
    'avg_protein' => $today_data['protein_consumed_g'] ?? 0,
    'avg_carbs' => $today_data['carbs_consumed_g'] ?? 0,
    'avg_fat' => $today_data['fat_consumed_g'] ?? 0,
    'avg_kcal_percentage' => $total_daily_calories_goal > 0 ? round((($today_data['kcal_consumed'] ?? 0) / $total_daily_calories_goal) * 100, 1) : 0,
    'avg_protein_percentage' => $macros_goal['protein_g'] > 0 ? round((($today_data['protein_consumed_g'] ?? 0) / $macros_goal['protein_g']) * 100, 1) : 0,
    'avg_carbs_percentage' => $macros_goal['carbs_g'] > 0 ? round((($today_data['carbs_consumed_g'] ?? 0) / $macros_goal['carbs_g']) * 100, 1) : 0,
    'avg_fat_percentage' => $macros_goal['fat_g'] > 0 ? round((($today_data['fat_consumed_g'] ?? 0) / $macros_goal['fat_g']) * 100, 1) : 0,
    'total_days' => 1
];

$nutrients_stats_yesterday = [
    'avg_kcal' => $yesterday_data['kcal_consumed'] ?? 0,
    'avg_protein' => $yesterday_data['protein_consumed_g'] ?? 0,
    'avg_carbs' => $yesterday_data['carbs_consumed_g'] ?? 0,
    'avg_fat' => $yesterday_data['fat_consumed_g'] ?? 0,
    'avg_kcal_percentage' => $total_daily_calories_goal > 0 ? round((($yesterday_data['kcal_consumed'] ?? 0) / $total_daily_calories_goal) * 100, 1) : 0,
    'avg_protein_percentage' => $macros_goal['protein_g'] > 0 ? round((($yesterday_data['protein_consumed_g'] ?? 0) / $macros_goal['protein_g']) * 100, 1) : 0,
    'avg_carbs_percentage' => $macros_goal['carbs_g'] > 0 ? round((($yesterday_data['carbs_consumed_g'] ?? 0) / $macros_goal['carbs_g']) * 100, 1) : 0,
    'avg_fat_percentage' => $macros_goal['fat_g'] > 0 ? round((($yesterday_data['fat_consumed_g'] ?? 0) / $macros_goal['fat_g']) * 100, 1) : 0,
    'total_days' => 1
];

// Debug: Verificar se as médias fazem sentido
error_log("DEBUG - Média 7 dias: " . $nutrients_stats_7['avg_kcal']);
error_log("DEBUG - Média 15 dias: " . $nutrients_stats_15['avg_kcal']);
error_log("DEBUG - Média 30 dias: " . $nutrients_stats_30['avg_kcal']);
error_log("DEBUG - Total de dias disponíveis: " . count($last_7_days_data));

// Debug: Verificar datas
error_log("DEBUG - Data de hoje: " . $today);
error_log("DEBUG - Data de ontem: " . $yesterday);
error_log("DEBUG - Primeiras 3 datas de hidratação: " . json_encode(array_slice(array_column($hydration_data, 'date'), 0, 3)));

// Debug: Verificar estatísticas de hoje e ontem
error_log("DEBUG - Stats hoje hidratação: " . json_encode($water_stats_today));
error_log("DEBUG - Stats ontem hidratação: " . json_encode($water_stats_yesterday));
error_log("DEBUG - Stats hoje nutrientes: " . json_encode($nutrients_stats_today));
error_log("DEBUG - Stats ontem nutrientes: " . json_encode($nutrients_stats_yesterday));

// --- PREPARAÇÃO DE DADOS PARA EXIBIÇÃO ---
$page_slug = 'users';
$page_title = 'Dossiê: ' . htmlspecialchars($user_data['name']);
$extra_js = ['user_view_logic.js'];

// ARRAYS DE MAPEAMENTO
$objective_names = ['lose_fat' => 'Emagrecimento', 'gain_muscle' => 'Hipertrofia', 'maintain_weight' => 'Manter Peso'];
$gender_names = ['male' => 'Masculino', 'female' => 'Feminino', 'other' => 'Outro'];
$meal_type_names = ['breakfast' => 'Café da Manhã', 'morning_snack' => 'Lanche da Manhã', 'lunch' => 'Almoço', 'afternoon_snack' => 'Lanche da Tarde', 'dinner' => 'Jantar', 'supper' => 'Ceia', 'pre_workout' => 'Pré-Treino', 'post_workout' => 'Pós-Treino'];
$exercise_freq_names = ['1_2x_week' => '1 a 2x/semana', '3_4x_week' => '3 a 4x/semana', '5_6x_week' => '5 a 6x/semana', '6_7x_week' => '6 a 7x/semana', '7plus_week' => '+ de 7x/semana', 'sedentary' => 'Sedentário'];
$water_intake_names = ['_1l' => 'Até 1 Litro', '1_2l' => '1 a 2 Litros', '2_3l' => '2 a 3 Litros', '3plus_l' => 'Mais de 3 Litros'];
$vegetarian_type_names = ['strict_vegetarian' => 'Vegetariano Estrito', 'ovolacto' => 'Ovolactovegetariano', 'vegan' => 'Vegano', 'not_like' => 'Apenas não gosta'];

// CÁLCULOS E FORMATAÇÃO
$age_years = !empty($user_data['dob']) ? calculateAge($user_data['dob']) : 'N/A';
$full_phone = !empty($user_data['phone_ddd']) && !empty($user_data['phone_number']) ? '(' . htmlspecialchars($user_data['phone_ddd']) . ') ' . htmlspecialchars($user_data['phone_number']) : 'Não informado';
$location = !empty($user_data['city']) && !empty($user_data['uf']) ? htmlspecialchars($user_data['city']) . ' - ' . htmlspecialchars($user_data['uf']) : 'Não informado';

// LÓGICA DO SONO
$sleep_html = 'Não informado';
if (!empty($user_data['sleep_time_bed']) && !empty($user_data['sleep_time_wake'])) {
    $bed_time = new DateTime($user_data['sleep_time_bed']);
    $wake_time = new DateTime($user_data['sleep_time_wake']);
    if ($wake_time < $bed_time) { $wake_time->modify('+1 day'); }
    $interval = $bed_time->diff($wake_time);
    
    // Calcular horas totais de sono
    $total_hours = $interval->h + ($interval->i / 60);
    $rounded_hours = round($total_hours);
    
    // Formatar de forma mais amigável
    if ($rounded_hours == 1) {
        $sleep_html = "Média de {$rounded_hours} hora por dia";
    } else {
        $sleep_html = "Média de {$rounded_hours} horas por dia";
    }
}

// LÓGICA DE AVATAR
$avatar_html = '';
if (!empty($user_data['profile_image_filename'])) {
    $avatar_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($user_data['profile_image_filename']);
    $avatar_html = '<img src="' . $avatar_url . '" alt="Foto de ' . htmlspecialchars($user_data['name']) . '" class="profile-avatar-large">';
}
if (empty($avatar_html)) {
    $name_parts = explode(' ', trim($user_data['name']));
    $initials = count($name_parts) > 1 ? strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1)) : (!empty($name_parts[0]) ? strtoupper(substr($name_parts[0], 0, 2)) : '??');
    // Gerar cor escura para bom contraste com texto branco
    $hash = md5($user_data['name']);
    $r = hexdec(substr($hash, 0, 2)) % 156 + 50;  // 50-205
    $g = hexdec(substr($hash, 2, 2)) % 156 + 50;  // 50-205
    $b = hexdec(substr($hash, 4, 2)) % 156 + 50;  // 50-205
    // Garantir que pelo menos um canal seja escuro
    $max = max($r, $g, $b);
    if ($max > 180) {
        $r = (int)($r * 0.7);
        $g = (int)($g * 0.7);
        $b = (int)($b * 0.7);
    }
    $bgColor = sprintf('#%02x%02x%02x', $r, $g, $b);
    $avatar_html = '<div class="initials-avatar large" style="background-color: ' . $bgColor . ';">' . $initials . '</div>';
}

require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?php echo BASE_ADMIN_URL; ?>/assets/css/view_user_addon.css?v=<?php echo time(); ?>">
<style>
/* Força topo reto nas barras de hidratação */
.improved-bar {
    border-radius: 0 0 6px 6px !important;
}
.improved-bar-wrapper {
    border-radius: 0 0 8px 8px !important;
}
.improved-goal-line {
    left: 0 !important;
    right: 0 !important;
    width: 100% !important;
}
</style>

<div class="view-user-header">
    <div class="user-main-info">
        <?php echo $avatar_html; ?>
        <div class="user-contact-details">
            <h2><?php echo htmlspecialchars($user_data['name']); ?></h2>
            <p><i class="fas fa-envelope icon-sm"></i> <?php echo htmlspecialchars($user_data['email']); ?></p>
            <p><i class="fas fa-phone-alt icon-sm"></i> <?php echo $full_phone; ?></p>
            <p><i class="fas fa-map-marker-alt icon-sm"></i> <?php echo $location; ?></p>
        </div>
    </div>
</div>

<div class="details-grid-3-cols">
    <div class="dashboard-card">
        <div class="card-header-with-action">
        <h3>Meta Calórica e Macros</h3>
            <button class="btn-icon-only btn-revert-goals" 
                    onclick="showRevertModal(<?php echo $user_id; ?>)" 
                    title="Reverter para cálculo automático">
                <i class="fas fa-undo"></i>
            </button>
        </div>
        
        <div class="meta-card-main">
            <span class="meta-value editable-value" 
                  data-field="daily_calories" 
                  data-user-id="<?php echo $user_id; ?>"
                  data-original="<?php echo $total_daily_calories_goal; ?>"
                  title="Clique para editar"><?php echo $total_daily_calories_goal; ?></span>
            <span class="meta-label">Kcal / dia</span>
        </div>
        <div class="meta-card-macros">
            <div>
                <span class="editable-value" 
                      data-field="carbs_g" 
                      data-user-id="<?php echo $user_id; ?>"
                      data-original="<?php echo $macros_goal['carbs_g']; ?>"
                      title="Clique para editar"><?php echo $macros_goal['carbs_g']; ?>g</span>
                Carboidratos
            </div>
            <div>
                <span class="editable-value" 
                      data-field="protein_g" 
                      data-user-id="<?php echo $user_id; ?>"
                      data-original="<?php echo $macros_goal['protein_g']; ?>"
                      title="Clique para editar"><?php echo $macros_goal['protein_g']; ?>g</span>
                Proteínas
            </div>
            <div>
                <span class="editable-value" 
                      data-field="fat_g" 
                      data-user-id="<?php echo $user_id; ?>"
                      data-original="<?php echo $macros_goal['fat_g']; ?>"
                      title="Clique para editar"><?php echo $macros_goal['fat_g']; ?>g</span>
                Gorduras
            </div>
        </div>
    </div>

    <div class="dashboard-card">
        <h3>Dados Físicos</h3>
        <div class="physical-data-grid">
            <div class="data-item">
                <div class="data-title">
                    <i class="fas fa-birthday-cake icon"></i>
                    <label>Idade</label>
                </div>
                <span><?php echo $age_years; ?> anos</span>
            </div>
            <div class="data-item">
                <div class="data-title">
                    <i class="fas fa-weight icon"></i>
                    <label>Peso Atual</label>
                </div>
                <span><?php echo number_format((float)($user_data['weight_kg'] ?? 0), 1, ',', '.'); ?> kg</span>
            </div>
            <div class="data-item">
                <div class="data-title">
                    <i class="fas fa-ruler-vertical icon"></i>
                    <label>Altura</label>
                </div>
                <span><?php echo htmlspecialchars($user_data['height_cm'] ?? 'N/A'); ?> cm</span>
            </div>
            <div class="data-item">
                <div class="data-title">
                    <i class="fas fa-venus-mars icon"></i>
                    <label>Gênero</label>
                </div>
                <span><?php echo $gender_names[$user_data['gender']] ?? 'Não informado'; ?></span>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <h3>Anamnese e Hábitos</h3>
        <div class="physical-data-grid">
            <div class="data-item">
                <div class="data-title">
                    <i class="fas fa-dumbbell icon"></i>
                    <label>Tipo de Treino</label>
                </div>
                <span><?php echo htmlspecialchars($user_data['exercise_type'] ?? 'N/I'); ?></span>
            </div>
            <div class="data-item">
                <div class="data-title">
                    <i class="fas fa-calendar-check icon"></i>
                    <label>Frequência</label>
                </div>
                <span><?php echo $exercise_freq_names[$user_data['exercise_frequency']] ?? 'N/I'; ?></span>
            </div>
            <div class="data-item">
                <div class="data-title">
                    <i class="fas fa-tint icon"></i>
                    <label>Consumo de Água</label>
                </div>
                <span><?php echo $water_intake_names[$user_data['water_intake_liters']] ?? 'N/I'; ?></span>
            </div>
            <div class="data-item sleep-item" onclick="openSleepDetailsModal()">
                <div class="data-title">
                    <i class="fas fa-bed icon"></i>
                    <label>Duração do Sono</label>
                    <i class="fas fa-question-circle sleep-details-icon" title="Ver detalhes do sono"></i>
                </div>
                <span><?php echo $sleep_html; ?></span>
            </div>
        </div>
    </div>
</div>

<div class="details-grid-1-col" style="margin-top:24px;">
    <div class="dashboard-card">
        <h3>Plano e Preferências</h3>
         <div class="physical-data-grid-pref">
            <div class="data-item">
                <div class="data-title">
                    <i class="fas fa-bullseye icon"></i>
                    <label>Objetivo</label>
                </div>
                <span><?php echo $objective_names[$user_data['objective']] ?? 'N/I'; ?></span>
            </div>
            <div class="data-item">
                <div class="data-title">
                    <i class="fas fa-drumstick-bite icon"></i>
                    <label>Consumo de Carne</label>
                </div>
                <span>
                    <?php 
                        if (isset($user_data['meat_consumption'])) {
                            echo $user_data['meat_consumption'] ? 'Sim' : 'Não (' . ($vegetarian_type_names[$user_data['vegetarian_type']] ?? 'N/E') . ')';
                        } else { echo 'Não informado'; }
                    ?>
                </span>
            </div>
            <div class="data-item">
                <div class="data-title">
                    <i class="fas fa-ban icon"></i>
                    <label>Intolerâncias</label>
                </div>
                <span>
                    <?php 
                        $intolerances = [];
                        if (!empty($user_data['lactose_intolerance'])) $intolerances[] = 'Lactose';
                        if (!empty($user_data['gluten_intolerance'])) $intolerances[] = 'Glúten';
                        echo !empty($intolerances) ? implode(', ', $intolerances) : 'Nenhuma informada.';
                    ?>
                </span>
            </div>
            <div class="data-item">
                <div class="data-title">
                    <i class="fas fa-leaf icon"></i>
                    <label>Restrições Alimentares</label>
                </div>
                <span>
                    <?php 
                        // Carregar restrições do usuário
                        $stmt_restrictions = $conn->prepare("
                            SELECT dro.name 
                            FROM sf_user_selected_restrictions usr 
                            JOIN sf_dietary_restrictions_options dro ON usr.restriction_id = dro.id 
                            WHERE usr.user_id = ? 
                            ORDER BY dro.name
                        ");
                        $stmt_restrictions->bind_param("i", $user_id);
                        $stmt_restrictions->execute();
                        $restrictions_result = $stmt_restrictions->get_result();
                        
                        $dietary_restrictions = [];
                        while ($row = $restrictions_result->fetch_assoc()) {
                            $dietary_restrictions[] = $row['name'];
                        }
                        $stmt_restrictions->close();
                        
                        echo !empty($dietary_restrictions) ? implode(', ', $dietary_restrictions) : 'Nenhuma informada.';
                    ?>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="tabs-wrapper">
    <div class="tabs-row">
        <div class="tab-link active" data-tab="diary">
            <i class="fas fa-book"></i>
            <span>Diário</span>
        </div>
        <div class="tab-link" data-tab="hydration">
            <i class="fas fa-tint"></i>
            <span>Hidratação</span>
        </div>
        <div class="tab-link" data-tab="nutrients">
            <i class="fas fa-apple-alt"></i>
            <span>Nutrientes</span>
        </div>
        <div class="tab-link" data-tab="progress">
            <i class="fas fa-chart-line"></i>
            <span>Progresso</span>
        </div>
        <div class="tab-link" data-tab="measurements">
            <i class="fas fa-camera"></i>
            <span>Medidas</span>
        </div>
    </div>
    <div class="tabs-row">
        <div class="tab-link" data-tab="weekly_analysis">
            <i class="fas fa-calendar-week"></i>
            <span>Análise Semanal</span>
        </div>
        <div class="tab-link" data-tab="feedback_analysis">
            <i class="fas fa-comments"></i>
            <span>Feedback</span>
        </div>
        <div class="tab-link" data-tab="diet_comparison">
            <i class="fas fa-balance-scale"></i>
            <span>Comparação</span>
        </div>
        <div class="tab-link" data-tab="weekly_tracking">
            <i class="fas fa-tasks"></i>
            <span>Rastreio</span>
        </div>
        <div class="tab-link" data-tab="personalized_goals">
            <i class="fas fa-bullseye"></i>
            <span>Metas</span>
        </div>
    </div>
</div>

<div id="tab-diary" class="tab-content active">
    <div class="diary-slider-container">
        <div class="diary-header-redesign">
            <!-- Ano no topo -->
            <div class="diary-year" id="diaryYear">2025</div>
            
            <!-- Navegação e data principal -->
            <div class="diary-nav-row">
                <button class="diary-nav-side diary-nav-left" onclick="navigateDiary(-1)" type="button">
                    <i class="fas fa-chevron-left"></i>
                    <span id="diaryPrevDate">26 out</span>
                </button>
                
                <div class="diary-main-date">
                    <div class="diary-day-month" id="diaryDayMonth">27 OUT</div>
                    <div class="diary-weekday" id="diaryWeekday">SEGUNDA</div>
                </div>
                
                <button class="diary-nav-side diary-nav-right" onclick="navigateDiary(1)" type="button">
                    <span id="diaryNextDate">28 out</span>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            
            <!-- Resumo de calorias e macros -->
            <div class="diary-summary-row">
                <div class="diary-kcal" id="diarySummaryKcal">
                    <i class="fas fa-fire"></i>
                    <span>0 kcal</span>
                </div>
                <div class="diary-macros" id="diarySummaryMacros">
                    P: 0g • C: 0g • G: 0g
                </div>
            </div>
            
            <!-- Botão de calendário -->
            <button class="diary-calendar-icon-btn" onclick="openDiaryCalendar()" type="button" title="Ver calendário">
                <i class="fas fa-calendar-alt"></i>
            </button>
        </div>
        
        <div class="diary-slider-wrapper" id="diarySliderWrapper">
            <div class="diary-slider-track" id="diarySliderTrack">
                <?php 
                // Gerar array com TODOS os dias, mesmo se não houver dados
                $all_dates = [];
                for ($i = 0; $i < $daysToShow; $i++) {
                    $current_date = date('Y-m-d', strtotime($endDate . " -$i days"));
                    $all_dates[] = $current_date;
                }
                
                // Debug: verificar intervalo gerado
                // Primeira data (mais antiga) será $all_dates[0] após reverse
                // Última data (mais recente) será $all_dates[count-1] após reverse
                
                // Inverter ordem: mais antigo à esquerda, mais recente à direita
                $all_dates = array_reverse($all_dates);
                
                foreach ($all_dates as $date): 
                    $meals = $meal_history[$date] ?? [];
                    $day_total_kcal = 0;
                    $day_total_prot = 0;
                    $day_total_carb = 0;
                    $day_total_fat = 0;
                    
                    if (!empty($meals)) {
                        foreach ($meals as $meal_type_slug => $items) {
                            $day_total_kcal += array_sum(array_column($items, 'kcal_consumed'));
                            $day_total_prot += array_sum(array_column($items, 'protein_consumed_g'));
                            $day_total_carb += array_sum(array_column($items, 'carbs_consumed_g'));
                            $day_total_fat += array_sum(array_column($items, 'fat_consumed_g'));
                        }
                    }
                    
                    // Formatar data por extenso
                    $timestamp = strtotime($date);
                    $day_of_week = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][date('w', $timestamp)];
                    $day_number = date('d', $timestamp);
                    $month_name_abbr = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'][date('n', $timestamp) - 1];
                    $year = date('Y', $timestamp);
                ?>
                <div class="diary-day-card" data-date="<?php echo $date; ?>">
                    <!-- Dados escondidos para o JavaScript buscar -->
                    <div class="diary-day-summary" style="display: none;">
                        <div class="diary-summary-item">
                            <i class="fas fa-fire"></i>
                            <span><?php echo round($day_total_kcal); ?> kcal</span>
                        </div>
                        <div class="diary-summary-macros">
                            P: <?php echo round($day_total_prot); ?>g • 
                            C: <?php echo round($day_total_carb); ?>g • 
                            G: <?php echo round($day_total_fat); ?>g
                        </div>
                    </div>
                    
                    <div class="diary-day-meals">
                        <?php if (empty($meals)): ?>
                            <div class="diary-empty-state">
                                <i class="fas fa-utensils"></i>
                                <p>Nenhum registro neste dia</p>
                            </div>
            <?php else: ?>
                        <?php foreach ($meals as $meal_type_slug => $items): 
                            $total_kcal = array_sum(array_column($items, 'kcal_consumed'));
                            $total_prot = array_sum(array_column($items, 'protein_consumed_g'));
                            $total_carb = array_sum(array_column($items, 'carbs_consumed_g'));
                            $total_fat  = array_sum(array_column($items, 'fat_consumed_g'));
                        ?>
                                <div class="diary-meal-card">
                                    <div class="diary-meal-header">
                                        <div class="diary-meal-icon">
                                            <?php
                                            $meal_icons = [
                                                'breakfast' => 'fa-coffee',
                                                'morning_snack' => 'fa-apple-alt',
                                                'lunch' => 'fa-drumstick-bite',
                                                'afternoon_snack' => 'fa-cookie-bite',
                                                'dinner' => 'fa-pizza-slice',
                                                'evening_snack' => 'fa-ice-cream'
                                            ];
                                            $icon = $meal_icons[$meal_type_slug] ?? 'fa-utensils';
                                            ?>
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="diary-meal-info">
                                    <h5><?php echo $meal_type_names[$meal_type_slug] ?? ucfirst($meal_type_slug); ?></h5>
                                            <span class="diary-meal-totals">
                                                <strong><?php echo round($total_kcal); ?> kcal</strong> • 
                                                P:<?php echo round($total_prot); ?>g • 
                                                C:<?php echo round($total_carb); ?>g • 
                                                G:<?php echo round($total_fat); ?>g
                                            </span>
                                    </div>
                                </div>
                                    <ul class="diary-food-list">
                                    <?php foreach ($items as $item): ?>
                                        <li>
                                            <span class="food-name"><?php echo htmlspecialchars($item['food_name']); ?></span>
                                            <span class="food-quantity"><?php echo htmlspecialchars($item['quantity_display']); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Sistema de navegação do diário
let diaryCards = document.querySelectorAll('.diary-day-card');
let currentDiaryIndex = diaryCards.length - 1; // Iniciar no último (dia mais recente)
const diaryTrack = document.getElementById('diarySliderTrack');
let isLoadingMoreDays = false; // Flag para evitar múltiplas chamadas

// Função para atualizar referência aos cards
function updateDiaryCards() {
    diaryCards = document.querySelectorAll('.diary-day-card');
}

function updateDiaryDisplay() {
    // Adicionar transição suave para o slider
    diaryTrack.style.transition = 'transform 0.3s ease-in-out';
    
    const offset = -currentDiaryIndex * 100;
    diaryTrack.style.transform = `translateX(${offset}%)`;
    
    const currentCard = diaryCards[currentDiaryIndex];
    if (!currentCard) return;
    
    const date = currentCard.getAttribute('data-date');
    const dateObj = new Date(date + 'T00:00:00');
    
    // Nomes dos meses e dias da semana
    const monthNamesShort = ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];
    const monthNamesLower = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    const weekdayNames = ['DOMINGO', 'SEGUNDA', 'TERÇA', 'QUARTA', 'QUINTA', 'SEXTA', 'SÁBADO'];
    
    // Debug
    console.log('Diary index:', currentDiaryIndex, 'Date:', date, 'Month:', dateObj.getMonth());
    
    // Atualizar ano
    document.getElementById('diaryYear').textContent = dateObj.getFullYear();
    
    // Atualizar dia e mês principal
    const day = dateObj.getDate();
    const month = monthNamesShort[dateObj.getMonth()];
    document.getElementById('diaryDayMonth').textContent = `${day} ${month}`;
    
    // Atualizar dia da semana
    document.getElementById('diaryWeekday').textContent = weekdayNames[dateObj.getDay()];
    
    // Atualizar datas de navegação (anterior e próximo)
    const prevIndex = currentDiaryIndex - 1;
    const nextIndex = currentDiaryIndex + 1;
    
    // Atualizar data anterior (sempre mostrar o dia anterior real)
    const prevBtn = document.getElementById('diaryPrevDate');
    if (prevBtn) {
        // Calcular sempre o dia anterior baseado na data atual
        const currentDate = new Date(date + 'T00:00:00');
        const prevDate = new Date(currentDate);
        prevDate.setDate(prevDate.getDate() - 1);
        
        prevBtn.textContent = `${prevDate.getDate()} ${monthNamesLower[prevDate.getMonth()]}`;
        prevBtn.parentElement.style.visibility = 'visible';
    }
    
    // Atualizar data próxima (se existir e não for futuro)
    const nextBtn = document.getElementById('diaryNextDate');
    if (nextBtn) {
        if (nextIndex < diaryCards.length && diaryCards[nextIndex]) {
            const nextDate = new Date(diaryCards[nextIndex].getAttribute('data-date') + 'T00:00:00');
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (nextDate <= today) {
                nextBtn.textContent = `${nextDate.getDate()} ${monthNamesLower[nextDate.getMonth()]}`;
                nextBtn.parentElement.style.visibility = 'visible';
            } else {
                nextBtn.parentElement.style.visibility = 'hidden';
            }
        } else {
            nextBtn.parentElement.style.visibility = 'hidden';
        }
    }
    
    // Buscar e atualizar resumo de calorias e macros do card atual
    const summaryDiv = currentCard.querySelector('.diary-day-summary');
    if (summaryDiv) {
        const kcalText = summaryDiv.querySelector('.diary-summary-item span');
        const macrosText = summaryDiv.querySelector('.diary-summary-macros');
        
        if (kcalText) {
            document.getElementById('diarySummaryKcal').innerHTML = 
                `<i class="fas fa-fire"></i><span>${kcalText.textContent}</span>`;
        }
        
        if (macrosText) {
            document.getElementById('diarySummaryMacros').textContent = macrosText.textContent;
        }
    } else {
        // Sem dados
        document.getElementById('diarySummaryKcal').innerHTML = 
            `<i class="fas fa-fire"></i><span>0 kcal</span>`;
        document.getElementById('diarySummaryMacros').textContent = 'P: 0g • C: 0g • G: 0g';
    }
    
    // Atualizar estado dos botões de navegação
    updateNavigationButtons();
}

function updateNavigationButtons() {
    const currentCard = diaryCards[currentDiaryIndex];
    if (!currentCard) return;
    
    const currentDate = currentCard.getAttribute('data-date');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const currentDateObj = new Date(currentDate + 'T00:00:00');
    
    console.log('Current date:', currentDate, 'Today:', today.toISOString().split('T')[0]);
    
    // Botão de avançar (direita) - desabilitar se estiver no dia atual ou futuro
    const nextBtn = document.querySelector('.diary-nav-right');
    if (nextBtn) {
        // Verificar se existe um próximo card e se ele não é futuro
        const nextIndex = currentDiaryIndex + 1;
        if (nextIndex < diaryCards.length) {
            const nextCard = diaryCards[nextIndex];
            const nextDate = nextCard.getAttribute('data-date');
            const nextDateObj = new Date(nextDate + 'T00:00:00');
            
            if (nextDateObj > today) {
                nextBtn.classList.add('disabled');
                nextBtn.disabled = true;
            } else {
                nextBtn.classList.remove('disabled');
                nextBtn.disabled = false;
            }
        } else {
            // Não há próximo card
            nextBtn.classList.add('disabled');
            nextBtn.disabled = true;
        }
    }
}

function navigateDiary(direction) {
    let newIndex = currentDiaryIndex + direction;
    
    // Se tentar ir para frente
    if (direction > 0) {
        // Verificar se o próximo dia seria futuro
        if (newIndex >= diaryCards.length) {
            // Já está no último, não faz nada
            return;
        }
        
        const nextCard = diaryCards[newIndex];
        if (nextCard) {
            const nextDate = nextCard.getAttribute('data-date');
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const nextDateObj = new Date(nextDate + 'T00:00:00');
            
            // Se o próximo dia for futuro, não permite
            if (nextDateObj > today) {
                return; // Bloqueia navegação
            }
        }
    }
    
    // Se tentar ir para trás
    if (direction < 0) {
        // Se já está carregando, ignora
        if (isLoadingMoreDays) {
            console.log('Já está carregando mais dias...');
            return;
        }
        
        // Calcular a data do dia anterior
        const currentCard = diaryCards[currentDiaryIndex];
        if (currentCard) {
            const currentDate = currentCard.getAttribute('data-date');
            const dateObj = new Date(currentDate + 'T00:00:00');
            dateObj.setDate(dateObj.getDate() - 1);
            const prevDate = dateObj.toISOString().split('T')[0];
            
            // Verificar se já existe um card para essa data
            const existingCardIndex = Array.from(diaryCards).findIndex(card => 
                card.getAttribute('data-date') === prevDate
            );
            
            if (existingCardIndex !== -1) {
                // Se existe, navegar diretamente
                currentDiaryIndex = existingCardIndex;
                updateDiaryDisplay();
                return;
            } else {
                // Se não existe, carregar via AJAX
                console.log('Carregando 1 dia anterior via AJAX. Data atual:', currentDate, 'Nova end_date:', prevDate);
                loadMoreDiaryDays(prevDate, 1);
                return;
            }
        }
    }
    
    // Se tentar ir para frente e já está no último card (mais recente)
    if (direction > 0 && newIndex >= diaryCards.length) {
        console.log('Já está no dia mais recente');
        return;
    }
    
    currentDiaryIndex = newIndex;
    updateDiaryDisplay();
}

       async function loadMoreDiaryDays(endDate, daysToLoad = 1) {
           if (isLoadingMoreDays) {
               console.log('Já está carregando, ignorando chamada duplicada...');
               return;
           }
           
           isLoadingMoreDays = true;
           
           try {
               // Buscar apenas 1 dia via AJAX (sem loading visual)
               const userId = <?php echo $user_id; ?>;
               const url = `actions/load_diary_days.php?user_id=${userId}&end_date=${endDate}&days=${daysToLoad}`;
               
               console.log('Fazendo requisição AJAX para:', url);
               
               const response = await fetch(url);
               console.log('Resposta recebida, status:', response.status);
               
               if (response.ok) {
                   const html = await response.text();
                   console.log('HTML recebido, tamanho:', html.length);
                   
                   if (html.trim().length === 0) {
                       throw new Error('Resposta vazia do servidor');
                   }
                   
                   // Adicionar novo card ANTES dos existentes
                   const diaryTrack = document.getElementById('diarySliderTrack');
                   
                   // Criar container temporário
                   const tempDiv = document.createElement('div');
                   tempDiv.innerHTML = html;
                   const newCards = tempDiv.querySelectorAll('.diary-day-card');
                   
                   console.log('Novos cards encontrados:', newCards.length);
                   
                   if (newCards.length > 0) {
                       // Adicionar novo card no início (mais antigo primeiro)
                       const fragment = document.createDocumentFragment();
                       while (tempDiv.firstChild) {
                           fragment.appendChild(tempDiv.firstChild);
                       }
                       diaryTrack.insertBefore(fragment, diaryTrack.firstChild);
                       
                       // Atualizar referência aos cards
                       updateDiaryCards();
                       
                       // Navegar automaticamente para o dia carregado (primeiro card = mais antigo)
                       currentDiaryIndex = 0;
                       
                       console.log(`Adicionado 1 novo card. Total: ${diaryCards.length}`);
                       console.log('Primeira data após adição:', diaryCards[0]?.getAttribute('data-date'));
                       console.log('Última data após adição:', diaryCards[diaryCards.length - 1]?.getAttribute('data-date'));
                       console.log('Navegando para o dia carregado, índice:', currentDiaryIndex);
                       
                       // Manter URL inalterada - não atualizar endDate na URL
                       // const urlParams = new URLSearchParams(window.location.search);
                       // urlParams.set('end_date', endDate);
                       // window.history.replaceState({}, '', window.location.pathname + '?' + urlParams.toString());
                       
                       // Simular swipe: primeiro ir para posição anterior, depois para a correta
                       const previousIndex = currentDiaryIndex + 1;
                       const previousOffset = -previousIndex * 100;
                       
                       // Posicionar no card anterior (como se estivesse vindo da direita)
                       diaryTrack.style.transition = 'none';
                       diaryTrack.style.transform = `translateX(${previousOffset}%)`;
                       
                       // Forçar reflow
                       diaryTrack.offsetHeight;
                       
                       // Agora animar para a posição correta
                       diaryTrack.style.transition = 'transform 0.3s ease-in-out';
                       diaryTrack.style.transform = `translateX(${-currentDiaryIndex * 100}%)`;
                       
                       // Atualizar display
                       updateDiaryDisplay();
                   } else {
                       console.log('Nenhum novo card encontrado na resposta');
                   }
               } else {
                   throw new Error(`HTTP error! status: ${response.status}`);
               }
           } catch (error) {
               console.error('Erro ao carregar mais dias:', error);
               alert('Erro ao carregar mais dias: ' + error.message);
           } finally {
               isLoadingMoreDays = false;
           }
       }


function goToDiaryIndex(index) {
    currentDiaryIndex = index;
    updateDiaryDisplay();
}

// Suporte a swipe/touch
let touchStartX = 0;
let touchEndX = 0;

diaryTrack.addEventListener('touchstart', (e) => {
    touchStartX = e.changedTouches[0].screenX;
});

diaryTrack.addEventListener('touchend', (e) => {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe();
});

function handleSwipe() {
    const swipeThreshold = 50;
    const diff = touchStartX - touchEndX;
    
    if (Math.abs(diff) > swipeThreshold) {
        if (diff > 0) {
            // Swipe left - dia anterior
            navigateDiary(-1);
        } else {
            // Swipe right - próximo dia
            navigateDiary(1);
        }
    }
}

// Suporte a teclado
document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') navigateDiary(-1);
    if (e.key === 'ArrowRight') navigateDiary(1);
});

// Inicializar quando a aba de diário estiver ativa
function initDiary() {
    if (diaryCards.length > 0) {
        updateDiaryDisplay();
    }
}

// Inicializar se a aba já estiver ativa ou quando for aberta
if (document.getElementById('tab-diary').classList.contains('active')) {
    initDiary();
}

// Observar mudanças de aba
const tabLinks = document.querySelectorAll('.tab-link');
tabLinks.forEach(link => {
    link.addEventListener('click', function() {
        if (this.getAttribute('data-tab') === 'diary') {
            setTimeout(initDiary, 100);
        }
    });
});
</script>

<?php
// Calcular insights automáticos
$days_with_goal = $water_stats_7['excellent_days'] + $water_stats_7['good_days'];
$total_days_7 = $water_stats_7['total_days'];
$avg_ml_7 = $water_stats_7['avg_ml'];
$avg_percentage_7 = $water_stats_7['avg_percentage'];

// Determinar status geral
if ($avg_percentage_7 >= 90) {
    $status_text = 'Excelente';
    $status_class = 'excellent';
    $status_icon = 'fa-check-circle';
} elseif ($avg_percentage_7 >= 70) {
    $status_text = 'Bom';
    $status_class = 'good';
    $status_icon = 'fa-check';
} elseif ($avg_percentage_7 >= 50) {
    $status_text = 'Regular';
    $status_class = 'fair';
    $status_icon = 'fa-exclamation-triangle';
} elseif ($avg_percentage_7 >= 30) {
    $status_text = 'Abaixo da meta';
    $status_class = 'poor';
    $status_icon = 'fa-exclamation';
} else {
    $status_text = 'Crítico';
    $status_class = 'critical';
    $status_icon = 'fa-times-circle';
}

// Gerar insights em linguagem natural
$insights = [];
$insights[] = "O paciente atingiu a meta em <strong>{$days_with_goal} de {$total_days_7} dias</strong> analisados.";

// Comparar com semana anterior se houver dados
$avg_ml_14 = $water_stats_15['avg_ml'] ?? 0;
if ($avg_ml_14 > 0 && count($hydration_data) >= 14) {
    $diff = $avg_ml_7 - $avg_ml_14;
    if (abs($diff) > 100) {
        if ($diff > 0) {
            $insights[] = "Houve <strong class='text-success'>melhora de " . round($diff) . "ml</strong> em relação aos 7 dias anteriores.";
        } else {
            $insights[] = "Houve <strong class='text-danger'>redução de " . round(abs($diff)) . "ml</strong> em relação aos 7 dias anteriores.";
        }
    }
}

// Analisar padrão de dias da semana (se houver dados suficientes)
if (count($hydration_data) >= 7) {
    $weekend_avg = 0;
    $weekday_avg = 0;
    $weekend_count = 0;
    $weekday_count = 0;
    
    foreach (array_slice($hydration_data, 0, 14) as $day) {
        $dayOfWeek = date('N', strtotime($day['date']));
        if ($dayOfWeek >= 6) {
            $weekend_avg += $day['ml'];
            $weekend_count++;
        } else {
            $weekday_avg += $day['ml'];
            $weekday_count++;
        }
    }
    
    if ($weekend_count > 0 && $weekday_count > 0) {
        $weekend_avg = $weekend_avg / $weekend_count;
        $weekday_avg = $weekday_avg / $weekday_count;
        $diff_weekend = $weekend_avg - $weekday_avg;
        
        if (abs($diff_weekend) > 300) {
            if ($diff_weekend < 0) {
                $insights[] = "Consumo <strong>reduzido nos fins de semana</strong> (em média " . round(abs($diff_weekend)) . "ml a menos).";
            } else {
                $insights[] = "Consumo <strong>maior nos fins de semana</strong> (em média " . round($diff_weekend) . "ml a mais).";
            }
        }
    }
}
?>

<div id="tab-hydration" class="tab-content">
    <div class="hydration-container">
        
        <!-- 1. CARD RESUMO COMPACTO -->
        <div class="hydration-summary-card">
            <div class="summary-main">
                <div class="summary-icon">
                    <i class="fas fa-tint"></i>
                    </div>
                <div class="summary-info">
                    <h3>Hidratação</h3>
                    <div class="summary-meta">Meta diária: <strong><?php echo $water_goal_ml; ?>ml</strong></div>
                </div>
                <div class="summary-status status-<?php echo $status_class; ?>">
                    <i class="fas <?php echo $status_icon; ?>"></i>
                    <span><?php echo $status_text; ?></span>
                </div>
            </div>
            <div class="summary-stats">
                <div class="summary-stat">
                    <div class="stat-value"><?php echo $avg_ml_7; ?>ml</div>
                    <div class="stat-label">Média Atual (7 dias)</div>
                    </div>
                <div class="summary-stat">
                    <div class="stat-value"><?php echo $avg_percentage_7; ?>%</div>
                    <div class="stat-label">da Meta Atingido</div>
                    </div>
                <div class="summary-stat">
                    <div class="stat-value"><?php echo $days_with_goal; ?>/<?php echo $total_days_7; ?></div>
                    <div class="stat-label">Dias na Meta</div>
                </div>
                    </div>
                </div>
                
        <!-- 2. INSIGHTS AUTOMÁTICOS -->
        <?php if (!empty($insights)): ?>
        <div class="hydration-insights">
            <h4><i class="fas fa-lightbulb"></i> Insights</h4>
            <ul class="insights-list">
                <?php foreach ($insights as $insight): ?>
                    <li><?php echo $insight; ?></li>
                <?php endforeach; ?>
            </ul>
                    </div>
        <?php endif; ?>

        <!-- 3. GRÁFICO SIMPLIFICADO -->
        <div class="chart-section">
            <div class="chart-section-header">
                <h4><i class="fas fa-chart-bar"></i> Progresso dos Últimos 7 Dias</h4>
            </div>
            <div class="hydration-chart-improved">
                <div class="improved-chart" id="improved-chart">
                <?php if (empty($hydration_data)): ?>
                    <div class="empty-chart">
                        <i class="fas fa-tint"></i>
                        <p>Nenhum registro encontrado</p>
                    </div>
                <?php else: ?>
                    <div class="improved-bars" id="improved-bars">
                        <?php 
                        $display_data = array_slice($hydration_data, 0, 7);
                        foreach ($display_data as $day): 
                            $limitedPercentage = min($day['percentage'], 100);
                            $barHeight = 0;
                            if ($limitedPercentage === 0) {
                                $barHeight = 0;
                            } else if ($limitedPercentage === 100) {
                                $barHeight = 160;
                            } else {
                                $barHeight = ($limitedPercentage / 100) * 160;
                            }
                        ?>
                            <div class="improved-bar-container">
                                <div class="improved-bar-wrapper">
                                    <div class="improved-bar <?php echo $day['status']; ?>" style="height: <?php echo $barHeight; ?>px"></div>
                                    <div class="bar-percentage-text"><?php echo $limitedPercentage; ?>%</div>
                                    <div class="improved-goal-line"></div>
                                </div>
                                <div class="improved-bar-info">
                                    <span class="improved-date"><?php echo date('d/m', strtotime($day['date'])); ?></span>
                                    <span class="improved-ml"><?php echo $day['ml']; ?>ml</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 4. MÉDIAS DE PERÍODOS (COMPACTO) -->
        <div class="hydration-periods-compact">
            <h4><i class="fas fa-calendar-alt"></i> Médias por Período</h4>
            <div class="periods-grid">
                <div class="period-item">
                    <span class="period-label">Semana (7 dias)</span>
                    <span class="period-value"><?php echo $water_stats_7['avg_ml']; ?>ml</span>
                    <span class="period-percentage"><?php echo $water_stats_7['avg_percentage']; ?>%</span>
            </div>
                <div class="period-item">
                    <span class="period-label">Quinzena (15 dias)</span>
                    <span class="period-value"><?php echo $water_stats_15['avg_ml']; ?>ml</span>
                    <span class="period-percentage"><?php echo $water_stats_15['avg_percentage']; ?>%</span>
                    </div>
                <div class="period-item">
                    <span class="period-label">Mês (30 dias)</span>
                    <span class="period-value"><?php echo $water_stats_30['avg_ml']; ?>ml</span>
                    <span class="period-percentage"><?php echo $water_stats_30['avg_percentage']; ?>%</span>
                            </div>
                            </div>
                        </div>

        <!-- 5. REGISTROS DETALHADOS (COLAPSÁVEL) -->
        <div class="hydration-records-collapsible">
            <button class="collapse-toggle" onclick="toggleHydrationRecords()">
                <i class="fas fa-chevron-down"></i>
                <span>Mostrar Registros Detalhados</span>
            </button>
            <div class="records-content" id="hydration-records-content" style="display: none;">
                <table class="records-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Consumo</th>
                            <th>% da Meta</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($hydration_data)): ?>
                            <tr>
                                <td colspan="4" class="empty-state">Nenhum registro de hidratação</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach (array_slice($hydration_data, 0, 30) as $day): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($day['date'])); ?></td>
                                    <td><strong><?php echo $day['ml']; ?>ml</strong></td>
                                    <td><?php echo $day['percentage']; ?>%</td>
                                    <td>
                                        <span class="status-badge status-<?php echo $day['status']; ?>">
                                            <?php 
                                            $status_labels = [
                                                'excellent' => 'Excelente',
                                                'good' => 'Bom',
                                                'fair' => 'Regular',
                                                'poor' => 'Baixo',
                                                'critical' => 'Crítico'
                                            ];
                                            echo $status_labels[$day['status']] ?? $day['status'];
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleHydrationRecords() {
    const content = document.getElementById('hydration-records-content');
    const button = event.currentTarget;
    const icon = button.querySelector('i');
    const text = button.querySelector('span');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
        text.textContent = 'Ocultar Registros Detalhados';
    } else {
        content.style.display = 'none';
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
        text.textContent = 'Mostrar Registros Detalhados';
    }
}

function toggleNutrientsRecords() {
    const content = document.getElementById('nutrients-records-content');
    const chevron = document.getElementById('nutrients-records-chevron');
    
    if (content.style.display === 'none' || content.style.display === '') {
        content.style.display = 'block';
        chevron.style.transform = 'rotate(180deg)';
    } else {
        content.style.display = 'none';
        chevron.style.transform = 'rotate(0deg)';
    }
}
</script>

<div id="tab-nutrients" class="tab-content">
    <div class="nutrients-container">
        
        <!-- 1. RESUMO GERAL -->
        <!-- 1. CARD RESUMO COMPACTO -->
        <div class="nutrients-summary-card">
            <div class="summary-main">
                <div class="summary-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <div class="summary-info">
                    <h3>Consumo Nutricional</h3>
                    <div class="summary-meta">Meta calórica diária: <strong><?php echo $total_daily_calories_goal; ?> kcal</strong></div>
                    <div class="summary-description">Baseado nos registros de refeições do paciente no aplicativo</div>
                </div>
                <div class="summary-status status-<?php echo $nutrients_stats_7['avg_overall_percentage'] >= 90 ? 'excellent' : ($nutrients_stats_7['avg_overall_percentage'] >= 70 ? 'good' : ($nutrients_stats_7['avg_overall_percentage'] >= 50 ? 'fair' : 'poor')); ?>">
                    <i class="fas <?php echo $nutrients_stats_7['avg_overall_percentage'] >= 90 ? 'fa-check-circle' : ($nutrients_stats_7['avg_overall_percentage'] >= 70 ? 'fa-check' : ($nutrients_stats_7['avg_overall_percentage'] >= 50 ? 'fa-exclamation-triangle' : 'fa-exclamation')); ?>"></i>
                    <span><?php echo $nutrients_stats_7['avg_overall_percentage'] >= 90 ? 'Excelente' : ($nutrients_stats_7['avg_overall_percentage'] >= 70 ? 'Bom' : ($nutrients_stats_7['avg_overall_percentage'] >= 50 ? 'Regular' : 'Abaixo da meta')); ?></span>
                </div>
            </div>
            <div class="summary-stats">
                <div class="summary-stat">
                    <div class="stat-value"><?php echo $nutrients_stats_7['avg_kcal']; ?> kcal</div>
                    <div class="stat-label">Média de Calorias</div>
                    <div class="stat-description">Últimos 7 dias</div>
                </div>
                <div class="summary-stat">
                    <div class="stat-value"><?php echo $nutrients_stats_7['avg_overall_percentage']; ?>%</div>
                    <div class="stat-label">Aderência Geral</div>
                    <div class="stat-description">Meta calórica atingida</div>
                </div>
                <div class="summary-stat">
                    <div class="stat-value"><?php echo $nutrients_stats_7['days_with_consumption']; ?>/<?php echo $nutrients_stats_7['total_days']; ?></div>
                    <div class="stat-label">Dias com Registro</div>
                    <div class="stat-description"><?php echo $nutrients_stats_7['adherence_percentage']; ?>% de aderência</div>
                </div>
            </div>
        </div>

        <!-- 2. INSIGHTS AUTOMÁTICOS -->
        <?php
        // Calcular insights automáticos para nutrientes
        $nutrients_insights = [];
        
        // Insight sobre aderência geral
        $excellent_good_days = $nutrients_stats_7['excellent_days'] + $nutrients_stats_7['good_days'];
        $days_with_consumption = $nutrients_stats_7['days_with_consumption'];
        $total_days = $nutrients_stats_7['total_days'];
        $adherence_percentage = $nutrients_stats_7['adherence_percentage'];
        
        if ($days_with_consumption > 0) {
            $nutrients_insights[] = "O paciente registrou refeições em <strong>{$days_with_consumption} de {$total_days} dias</strong> analisados ({$adherence_percentage}% de aderência). <em>Baseado nos registros de refeições do paciente no aplicativo.</em>";
            
            if ($excellent_good_days > 0) {
                $quality_rate = round(($excellent_good_days / $days_with_consumption) * 100, 1);
                $nutrients_insights[] = "Dos dias com registro, <strong>{$excellent_good_days} dias</strong> atingiram as metas nutricionais ({$quality_rate}% de qualidade).";
            }
        } else {
            $nutrients_insights[] = "O paciente não registrou refeições nos últimos 7 dias. <em>Nenhum dado nutricional disponível para análise.</em>";
        }
        
        // Insight sobre calorias (usar média ponderada para mostrar disciplina)
        if ($nutrients_stats_7['avg_kcal_percentage'] > 0) {
            if ($nutrients_stats_7['avg_kcal_percentage'] >= 100) {
                $nutrients_insights[] = "Consumo calórico <strong class='text-success'>excelente</strong> - atingindo " . round($nutrients_stats_7['avg_kcal_percentage']) . "% da meta diária em média.";
            } elseif ($nutrients_stats_7['avg_kcal_percentage'] >= 80) {
                $nutrients_insights[] = "Consumo calórico <strong class='text-info'>bom</strong> - atingindo " . round($nutrients_stats_7['avg_kcal_percentage']) . "% da meta diária em média.";
            } elseif ($nutrients_stats_7['avg_kcal_percentage'] >= 60) {
                $nutrients_insights[] = "Consumo calórico <strong class='text-warning'>regular</strong> - atingindo " . round($nutrients_stats_7['avg_kcal_percentage']) . "% da meta diária em média.";
            } else {
                $nutrients_insights[] = "Consumo calórico <strong class='text-danger'>abaixo da meta</strong> - apenas " . round($nutrients_stats_7['avg_kcal_percentage']) . "% da meta diária em média.";
            }
        }
        
        // Insight sobre comportamento alimentar (usar média real)
        if ($nutrients_stats_7['real_avg_kcal'] > 0) {
            $nutrients_insights[] = "Nos dias com registro, o paciente consome em média <strong>" . $nutrients_stats_7['real_avg_kcal'] . " kcal</strong> por dia.";
        }
        
        // Insight sobre proteínas
        if ($nutrients_stats_7['avg_protein_percentage'] > 0) {
            if ($nutrients_stats_7['avg_protein_percentage'] >= 100) {
                $nutrients_insights[] = "Consumo de proteínas <strong class='text-success'>excelente</strong> - " . round($nutrients_stats_7['avg_protein_percentage']) . "% da meta.";
            } elseif ($nutrients_stats_7['avg_protein_percentage'] >= 80) {
                $nutrients_insights[] = "Consumo de proteínas <strong class='text-info'>bom</strong> - " . round($nutrients_stats_7['avg_protein_percentage']) . "% da meta.";
            } else {
                $nutrients_insights[] = "Consumo de proteínas <strong class='text-warning'>abaixo da meta</strong> - apenas " . round($nutrients_stats_7['avg_protein_percentage']) . "% da meta.";
            }
        }
        
        // Comparar com período anterior se houver dados
        if ($nutrients_stats_15['avg_kcal'] > 0 && $nutrients_stats_7['avg_kcal'] > 0) {
            $kcal_diff = $nutrients_stats_7['avg_kcal'] - $nutrients_stats_15['avg_kcal'];
            if (abs($kcal_diff) > 50) {
                if ($kcal_diff > 0) {
                    $nutrients_insights[] = "Houve <strong class='text-success'>aumento de " . round($kcal_diff) . " kcal</strong> em relação aos 7 dias anteriores.";
                } else {
                    $nutrients_insights[] = "Houve <strong class='text-danger'>redução de " . round(abs($kcal_diff)) . " kcal</strong> em relação aos 7 dias anteriores.";
                }
            }
        }
        ?>
        
        <?php if (!empty($nutrients_insights)): ?>
        <div class="nutrients-insights">
            <h4><i class="fas fa-lightbulb"></i> Insights Nutricionais</h4>
            <ul class="insights-list">
                <?php foreach ($nutrients_insights as $insight): ?>
                    <li><?php echo $insight; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- 3. GRÁFICO SIMPLIFICADO -->
        <div class="chart-section">
            <div class="chart-section-header">
                <h4><i class="fas fa-chart-bar"></i> Progresso dos Últimos 7 Dias</h4>
            </div>
            <div class="nutrients-chart-improved">
                <div class="improved-chart" id="nutrients-improved-chart">
                <?php if (empty($last_7_days_data)): ?>
                    <div class="empty-chart">
                        <i class="fas fa-utensils"></i>
                        <p>Nenhum registro encontrado</p>
                    </div>
                <?php else: ?>
                    <div class="improved-bars" id="nutrients-improved-bars">
                        <?php 
                        $display_data = array_slice($last_7_days_data, 0, 7);
                        foreach ($display_data as $day): 
                            // Calcular percentual baseado na meta calórica diária
                            $percentage = $total_daily_calories_goal > 0 ? round(($day['kcal_consumed'] / $total_daily_calories_goal) * 100, 1) : 0;
                            
                            // Determinar status da barra
                            $status = 'poor';
                            if ($percentage >= 90) {
                                $status = 'excellent';
                            } elseif ($percentage >= 70) {
                                $status = 'good';
                            } elseif ($percentage >= 50) {
                                $status = 'fair';
                            }
                            
                            // Calcular altura da barra
                            $barHeight = 0;
                            if ($percentage === 0) {
                                $barHeight = 0;
                            } else if ($percentage >= 100) {
                                $barHeight = 160 + min(($percentage - 100) * 0.4, 40);
                            } else {
                                $barHeight = ($percentage / 100) * 160;
                            }
                        ?>
                            <div class="improved-bar-container">
                                <div class="improved-bar-wrapper">
                                    <div class="improved-bar <?php echo $status; ?>" style="height: <?php echo $barHeight; ?>px"></div>
                                    <div class="bar-percentage-text"><?php echo $percentage; ?>%</div>
                                    <div class="improved-goal-line"></div>
                                </div>
                                <div class="improved-bar-info">
                                    <span class="improved-date"><?php echo date('d/m', strtotime($day['date'])); ?></span>
                                    <span class="improved-ml"><?php echo $day['kcal_consumed']; ?> kcal</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 4. MÉDIAS POR PERÍODO (COMPACTO) -->
        <div class="nutrients-periods-compact">
            <h4><i class="fas fa-calendar-alt"></i> Médias de Consumo por Período</h4>
            <p class="section-description">Análise do consumo calórico médio em diferentes períodos para identificar tendências e padrões alimentares.</p>
            <div class="periods-grid">
                <div class="period-item">
                    <span class="period-label">Última Semana</span>
                    <span class="period-value"><?php echo $nutrients_stats_7['avg_kcal']; ?> kcal</span>
                    <span class="period-percentage"><?php echo $nutrients_stats_7['avg_overall_percentage']; ?>% da meta</span>
                    <div class="period-details">Média dos últimos 7 dias</div>
                </div>
                <div class="period-item">
                    <span class="period-label">Última Quinzena</span>
                    <span class="period-value"><?php echo $nutrients_stats_15['avg_kcal']; ?> kcal</span>
                    <span class="period-percentage"><?php echo $nutrients_stats_15['avg_overall_percentage']; ?>% da meta</span>
                    <div class="period-details">Média dos últimos 15 dias</div>
                </div>
                <div class="period-item">
                    <span class="period-label">Último Mês</span>
                    <span class="period-value"><?php echo $nutrients_stats_30['avg_kcal']; ?> kcal</span>
                    <span class="period-percentage"><?php echo $nutrients_stats_30['avg_overall_percentage']; ?>% da meta</span>
                    <div class="period-details">Média dos últimos 30 dias</div>
                </div>
            </div>
        </div>

        <!-- 5. DETALHAMENTO DE MACRONUTRIENTES -->
        <div class="nutrients-macros-detail">
            <h4><i class="fas fa-chart-pie"></i> Detalhamento de Macronutrientes</h4>
            <p class="section-description">Análise detalhada do consumo de proteínas, carboidratos e gorduras baseado nos alimentos registrados pelo paciente no aplicativo.</p>
            <div class="macros-grid">
                <div class="macro-card">
                    <div class="macro-header">
                        <div class="macro-icon protein">
                            <i class="fas fa-drumstick-bite"></i>
                        </div>
                        <div class="macro-info">
                            <h5>Proteínas</h5>
                            <p>Consumo médio dos últimos 7 dias</p>
                        </div>
                    </div>
                    <div class="macro-content">
                        <div class="macro-value">
                            <span class="current"><?php echo $nutrients_stats_7['avg_protein']; ?>g</span>
                            <span class="target">/ <?php echo $macros_goal['protein_g']; ?>g</span>
                        </div>
                        <div class="macro-percentage">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo min($nutrients_stats_7['avg_protein_percentage'], 100); ?>%"></div>
                            </div>
                            <span class="percentage-text"><?php echo $nutrients_stats_7['avg_protein_percentage']; ?>% da meta</span>
                        </div>
                    </div>
                </div>

                <div class="macro-card">
                    <div class="macro-header">
                        <div class="macro-icon carbs">
                            <i class="fas fa-bread-slice"></i>
                        </div>
                        <div class="macro-info">
                            <h5>Carboidratos</h5>
                            <p>Consumo médio dos últimos 7 dias</p>
                        </div>
                    </div>
                    <div class="macro-content">
                        <div class="macro-value">
                            <span class="current"><?php echo $nutrients_stats_7['avg_carbs']; ?>g</span>
                            <span class="target">/ <?php echo $macros_goal['carbs_g']; ?>g</span>
                        </div>
                        <div class="macro-percentage">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo min($nutrients_stats_7['avg_carbs_percentage'], 100); ?>%"></div>
                            </div>
                            <span class="percentage-text"><?php echo $nutrients_stats_7['avg_carbs_percentage']; ?>% da meta</span>
                        </div>
                    </div>
                </div>

                <div class="macro-card">
                    <div class="macro-header">
                        <div class="macro-icon fat">
                            <i class="fas fa-seedling"></i>
                        </div>
                        <div class="macro-info">
                            <h5>Gorduras</h5>
                            <p>Consumo médio dos últimos 7 dias</p>
                        </div>
                    </div>
                    <div class="macro-content">
                        <div class="macro-value">
                            <span class="current"><?php echo $nutrients_stats_7['avg_fat']; ?>g</span>
                            <span class="target">/ <?php echo $macros_goal['fat_g']; ?>g</span>
                        </div>
                        <div class="macro-percentage">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo min($nutrients_stats_7['avg_fat_percentage'], 100); ?>%"></div>
                            </div>
                            <span class="percentage-text"><?php echo $nutrients_stats_7['avg_fat_percentage']; ?>% da meta</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dados para JavaScript -->
<script>
const hydrationData = <?php echo json_encode($hydration_data); ?>;
const waterGoalMl = <?php echo $water_goal_ml; ?>;
const waterStats = {
    'today': <?php echo json_encode($water_stats_today); ?>,
    'yesterday': <?php echo json_encode($water_stats_yesterday); ?>,
    '7': <?php echo json_encode($water_stats_7); ?>,
    '15': <?php echo json_encode($water_stats_15); ?>,
    '30': <?php echo json_encode($water_stats_30); ?>,
    '90': <?php echo json_encode($water_stats_90); ?>,
    'all': <?php echo json_encode($water_stats_all); ?>
};

const nutrientsData = <?php echo json_encode($last_7_days_data); ?>;
const nutrientsStats = {
    'today': <?php echo json_encode($nutrients_stats_today); ?>,
    'yesterday': <?php echo json_encode($nutrients_stats_yesterday); ?>,
    '7': <?php echo json_encode($nutrients_stats_7); ?>,
    '15': <?php echo json_encode($nutrients_stats_15); ?>,
    '30': <?php echo json_encode($nutrients_stats_30); ?>,
    '90': <?php echo json_encode($nutrients_stats_90); ?>,
    'all': <?php echo json_encode($nutrients_stats_all); ?>
};

// Sistema de edição inline para metas
document.addEventListener('DOMContentLoaded', function() {
    const editableValues = document.querySelectorAll('.editable-value');
    
    editableValues.forEach(element => {
        element.addEventListener('click', function() {
            if (this.querySelector('input')) return; // Já está em edição
            
            const field = this.dataset.field;
            const userId = this.dataset.userId;
            const currentValue = this.dataset.original;
            const fullText = this.textContent;
            const suffix = fullText.replace(currentValue, '').trim(); // Pega 'g' ou ''
            
            // Salvar estilo original
            const originalStyles = {
                fontSize: window.getComputedStyle(this).fontSize,
                fontWeight: window.getComputedStyle(this).fontWeight,
                color: window.getComputedStyle(this).color,
                textAlign: window.getComputedStyle(this).textAlign
            };
            
            // Criar input
            const input = document.createElement('input');
            input.type = 'number';
            input.value = currentValue;
            input.style.cssText = `
                background: rgba(255, 255, 255, 0.08);
                border: 2px solid var(--accent-orange);
                border-radius: 8px;
                padding: 0.25rem 0.5rem;
                color: ${originalStyles.color};
                font-size: ${originalStyles.fontSize};
                font-weight: ${originalStyles.fontWeight};
                text-align: ${originalStyles.textAlign};
                width: 100%;
                max-width: 150px;
                outline: none;
                font-family: 'Montserrat', sans-serif;
            `;
            
            // Substituir conteúdo
            this.textContent = '';
            this.appendChild(input);
            input.focus();
            input.select();
            
            // Função de salvar
            const saveValue = async () => {
                const newValue = input.value;
                if (!newValue || newValue === currentValue) {
                    cancelEdit();
                    return;
                }
                
                try {
                    // Coletar valores atuais de todos os campos
                    const caloriesEl = document.querySelector('[data-field="daily_calories"]');
                    const proteinEl = document.querySelector('[data-field="protein_g"]');
                    const carbsEl = document.querySelector('[data-field="carbs_g"]');
                    const fatEl = document.querySelector('[data-field="fat_g"]');
                    
                    const formData = new FormData();
                    formData.append('user_id', userId);
                    formData.append('daily_calories', field === 'daily_calories' ? newValue : caloriesEl.dataset.original);
                    formData.append('protein_g', field === 'protein_g' ? newValue : proteinEl.dataset.original);
                    formData.append('carbs_g', field === 'carbs_g' ? newValue : carbsEl.dataset.original);
                    formData.append('fat_g', field === 'fat_g' ? newValue : fatEl.dataset.original);
                    formData.append('water_ml', <?php echo $water_goal_ml; ?>); // Valor do PHP
                    
                    // Dados enviados
                    console.log('Dados enviados:', Array.from(formData.entries()));
                    
                    const response = await fetch('<?php echo BASE_ADMIN_URL; ?>/actions/update_user_goals.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    
                    // Debug: ver resposta bruta
                    const responseText = await response.text();
                    console.log('Resposta do servidor:', responseText);
                    
                    let result;
                    try {
                        result = JSON.parse(responseText);
                    } catch (parseError) {
                        console.error('Erro ao fazer parse do JSON:', parseError);
                        console.error('Resposta recebida:', responseText);
                        alert('Erro: Resposta inválida do servidor');
                        cancelEdit();
                        return;
                    }
                    
                    if (result.success) {
                        // Atualizar valor exibido
                        element.dataset.original = newValue;
                        element.textContent = newValue + suffix;
                        
                        // Mostrar feedback visual
                        element.style.animation = 'pulse 0.5s ease';
                        setTimeout(() => element.style.animation = '', 500);
                    } else {
                        alert('Erro ao salvar: ' + result.message);
                        cancelEdit();
                    }
                } catch (error) {
                    console.error('Erro:', error);
                    alert('Erro ao salvar alterações');
                    cancelEdit();
                }
            };
            
            // Função de cancelar
            const cancelEdit = () => {
                element.textContent = currentValue + suffix;
            };
            
            // Events
            input.addEventListener('blur', saveValue);
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveValue();
                } else if (e.key === 'Escape') {
                    cancelEdit();
                }
            });
        });
        
        // Hover effect
        element.style.cursor = 'pointer';
        element.addEventListener('mouseenter', function() {
            if (!this.querySelector('input')) {
                this.style.textDecoration = 'underline';
                this.style.textDecorationStyle = 'dashed';
                this.style.textDecorationColor = 'var(--accent-orange)';
            }
        });
        element.addEventListener('mouseleave', function() {
            this.style.textDecoration = 'none';
        });
    });
});

// Sistema de modais customizados para reverter metas
let currentUserIdToRevert = null;

function showRevertModal(userId) {
    currentUserIdToRevert = userId;
    document.body.style.overflow = 'hidden';
    document.getElementById('revertGoalsModal').classList.add('active');
}

function closeRevertModal() {
    document.getElementById('revertGoalsModal').classList.remove('active');
    document.body.style.overflow = '';
    currentUserIdToRevert = null;
}

function showAlertModal(title, message, isSuccess = true) {
    const modal = document.getElementById('alertModal');
    const header = document.getElementById('alertModalHeader');
    const icon = document.getElementById('alertModalIcon');
    const titleEl = document.getElementById('alertModalTitle');
    const messageEl = document.getElementById('alertModalMessage');
    
    // Configurar ícone e cor
    if (isSuccess) {
        header.style.color = 'var(--success-green)';
        icon.className = 'fas fa-check-circle';
    } else {
        header.style.color = 'var(--danger-red)';
        icon.className = 'fas fa-times-circle';
    }
    
    titleEl.textContent = title;
    messageEl.textContent = message;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeAlertModal() {
    const modal = document.getElementById('alertModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    
    // Se foi sucesso, recarregar a página
    if (modal.dataset.reloadOnClose === 'true') {
        location.reload();
    }
}

// Funções para modal de detalhes do sono
function openSleepDetailsModal() {
    document.body.style.overflow = 'hidden';
    document.getElementById('sleepDetailsModal').classList.add('active');
}

function closeSleepDetailsModal() {
    document.getElementById('sleepDetailsModal').classList.remove('active');
    document.body.style.overflow = '';
}

async function confirmRevertGoals() {
    if (!currentUserIdToRevert) {
        alert('Erro: ID do usuário não encontrado. Recarregue a página e tente novamente.');
        return;
    }
    
    // Salvar o user_id antes de fechar o modal
    const userIdToRevert = currentUserIdToRevert;
    
    closeRevertModal(); // Fechar modal de confirmação
    
    try {
        const formData = new FormData();
        formData.append('user_id', String(userIdToRevert));
        
        const response = await fetch('<?php echo BASE_ADMIN_URL; ?>/actions/revert_to_auto_goals.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            const modal = document.getElementById('alertModal');
            modal.dataset.reloadOnClose = 'true';
            showAlertModal('Sucesso!', data.message, true);
        } else {
            showAlertModal('Erro', data.message, false);
        }
    } catch (error) {
        console.error('Erro ao reverter metas:', error);
        showAlertModal('Erro', 'Erro ao reverter metas. Verifique o console para mais detalhes.', false);
    }
}

// Animação de pulse
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); color: var(--accent-orange); }
    }
`;
document.head.appendChild(style);

// Funcionalidade dos filtros de hidratação
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const avgConsumption = document.getElementById('avg-consumption');
    const avgPercentage = document.getElementById('avg-percentage');
    const complianceRate = document.getElementById('compliance-rate');
    const totalDays = document.getElementById('total-days');
    const chartBars = document.getElementById('chart-bars');
    const hydrationList = document.getElementById('hydration-list');

    function updateDisplay(period) {
        // Atualizar estatísticas principais
        const stats = waterStats[period];
        document.getElementById('avg-consumption').textContent = stats.avg_ml + 'ml';
        document.getElementById('avg-percentage').textContent = stats.avg_percentage + '%';
        document.getElementById('compliance-rate').textContent = stats.compliance_rate + '%';
        document.getElementById('total-days').textContent = stats.total_days;
        
        // Atualizar médias específicas
        document.getElementById('weekly-avg-ml').textContent = waterStats['7'].avg_ml + 'ml';
        document.getElementById('weekly-avg-percentage').textContent = waterStats['7'].avg_percentage + '% da meta';
        document.getElementById('biweekly-avg-ml').textContent = waterStats['15'].avg_ml + 'ml';
        document.getElementById('biweekly-avg-percentage').textContent = waterStats['15'].avg_percentage + '% da meta';
        
        // Atualizar círculo de porcentagem
        const circle = document.getElementById('avg-percentage-circle');
        if (circle) {
            circle.style.setProperty('--percentage', stats.avg_percentage);
        }
        
        // Atualizar período
        let periodText;
        if (period === 'all') {
            periodText = 'Período: Todos os registros';
        } else if (period === 'today') {
            periodText = 'Período: Hoje (apenas dados de hoje)';
        } else if (period === 'yesterday') {
            periodText = 'Período: Ontem (apenas dados de ontem)';
        } else {
            periodText = `Período: Últimos ${period} dias (média dos últimos ${period} dias)`;
        }
        document.getElementById('period-info').textContent = periodText;

        // Atualizar gráfico melhorado
        const improvedBars = document.getElementById('improved-bars');
        if (improvedBars) {
            let daysToShow;
            if (period === 'all') {
                daysToShow = hydrationData.length;
            } else if (period === 'today') {
                daysToShow = 1;
            } else if (period === 'yesterday') {
                daysToShow = 1;
            } else {
                daysToShow = parseInt(period);
            }
            let displayData;
            if (period === 'today') {
                // Filtrar apenas dados de hoje - usar a data do servidor
                const today = '<?php echo $today; ?>'; // Data do servidor
                console.log('DEBUG - Filtrando dados de hoje:', today);
                displayData = hydrationData.filter(day => {
                    console.log('DEBUG - Comparando:', day.date, 'com', today);
                    return day.date === today;
                });
                console.log('DEBUG - Dados filtrados para hoje:', displayData);
            } else if (period === 'yesterday') {
                // Filtrar apenas dados de ontem - usar a data do servidor
                const yesterday = '<?php echo $yesterday; ?>'; // Data do servidor
                console.log('DEBUG - Filtrando dados de ontem:', yesterday);
                displayData = hydrationData.filter(day => {
                    console.log('DEBUG - Comparando:', day.date, 'com', yesterday);
                    return day.date === yesterday;
                });
                console.log('DEBUG - Dados filtrados para ontem:', displayData);
            } else {
                displayData = hydrationData.slice(0, daysToShow);
            }
            
            improvedBars.innerHTML = displayData.map(day => {
                // Para hidratação, limitar a 100% (como já está)
                const limitedPercentage = Math.min(day.percentage, 100);
                console.log('DEBUG - Processando dia:', day.date, 'porcentagem:', day.percentage, 'limitada:', limitedPercentage);
                
                // Calcular altura da barra: 0% = 0px, 100% = 160px (altura total), outros valores proporcionais
                let barHeight;
                if (limitedPercentage === 0) {
                    barHeight = 0; // Sem altura para 0%
                } else if (limitedPercentage === 100) {
                    barHeight = 160; // Altura total do wrapper
                } else {
                    // Proporcional: 0px (mínimo) + (porcentagem * 160px)
                    barHeight = (limitedPercentage / 100) * 160;
                }
                console.log('DEBUG - Altura calculada:', barHeight, 'para porcentagem:', limitedPercentage);
                return `
                    <div class="improved-bar-container">
                        <div class="improved-bar-wrapper">
                            <div class="improved-bar ${day.status}" style="height: ${barHeight}px"></div>
                            <div class="bar-percentage-text">${limitedPercentage}%</div>
                            <div class="improved-goal-line"></div>
                        </div>
                        <div class="improved-bar-info">
                            <span class="improved-date">${day.date.split('-').reverse().slice(0, 2).join('/')}</span>
                            <span class="improved-ml">${day.ml}ml</span>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Atualizar lista simples
        const simpleList = document.getElementById('simple-list');
        if (simpleList) {
            let daysToShow;
            if (period === 'all') {
                daysToShow = hydrationData.length;
            } else if (period === 'today') {
                daysToShow = 1;
            } else if (period === 'yesterday') {
                daysToShow = 1;
            } else {
                daysToShow = parseInt(period);
            }
            let displayData;
            if (period === 'today') {
                // Filtrar apenas dados de hoje - usar a data do servidor
                const today = '<?php echo $today; ?>'; // Data do servidor
                displayData = hydrationData.filter(day => day.date === today);
            } else if (period === 'yesterday') {
                // Filtrar apenas dados de ontem - usar a data do servidor
                const yesterday = '<?php echo $yesterday; ?>'; // Data do servidor
                displayData = hydrationData.filter(day => day.date === yesterday);
            } else {
                displayData = hydrationData.slice(0, daysToShow);
            }
            
            simpleList.innerHTML = displayData.map(day => {
                const iconMap = {
                    'excellent': 'fa-check-circle',
                    'good': 'fa-check',
                    'fair': 'fa-exclamation-triangle',
                    'poor': 'fa-exclamation',
                    'critical': 'fa-times-circle',
                    'empty': 'fa-minus-circle'
                };
                
                // Limitar porcentagem a 100% para a lista também
                const limitedPercentage = Math.min(day.percentage, 100);
                return `
                    <div class="simple-item">
                        <div class="simple-date">${day.date.split('-').reverse().join('/')}</div>
                        <div class="simple-amount">
                            <span class="simple-ml-value">${day.ml}ml</span>
                            <span class="simple-percentage">(${limitedPercentage}%)</span>
                        </div>
                        <div class="simple-status ${day.status}">
                            <i class="fas ${iconMap[day.status]}"></i>
                        </div>
                    </div>
                `;
            }).join('');
        }
    }

    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remover classe active de todos os botões
            filterButtons.forEach(btn => btn.classList.remove('active'));
            
            // Adicionar classe active ao botão clicado
            this.classList.add('active');
            
            // Atualizar display com o período selecionado
            const period = this.getAttribute('data-period');
            updateDisplay(period);
        });
    });
    
    // Inicializar o círculo de porcentagem
    const circle = document.getElementById('avg-percentage-circle');
    if (circle) {
        const initialPercentage = waterStats['7'].avg_percentage;
        circle.style.setProperty('--percentage', initialPercentage);
    }
    
    // Funcionalidade dos filtros de nutrientes
    const nutrientsFilterButtons = document.querySelectorAll('#tab-nutrients .filter-btn');
    
    function updateNutrientsDisplay(period) {
        const stats = nutrientsStats[period];
        
        // Atualizar estatísticas principais
        document.getElementById('nutrients-avg-kcal').textContent = stats.avg_kcal + ' kcal';
        document.getElementById('nutrients-percentage').textContent = stats.avg_overall_percentage + '%';
        
        // Atualizar médias específicas
        document.getElementById('nutrients-kcal-avg').textContent = stats.avg_kcal + ' kcal';
        document.getElementById('nutrients-kcal-percentage').textContent = stats.avg_kcal_percentage + '% da meta';
        document.getElementById('nutrients-protein-avg').textContent = stats.avg_protein + 'g';
        document.getElementById('nutrients-protein-percentage').textContent = stats.avg_protein_percentage + '% da meta';
        document.getElementById('nutrients-carbs-avg').textContent = stats.avg_carbs + 'g';
        document.getElementById('nutrients-carbs-percentage').textContent = stats.avg_carbs_percentage + '% da meta';
        document.getElementById('nutrients-fat-avg').textContent = stats.avg_fat + 'g';
        document.getElementById('nutrients-fat-percentage').textContent = stats.avg_fat_percentage + '% da meta';
        
        // Atualizar círculo de porcentagem
        const nutrientsCircle = document.getElementById('nutrients-percentage-circle');
        if (nutrientsCircle) {
            nutrientsCircle.style.setProperty('--percentage', stats.avg_overall_percentage);
        }
        
        // Atualizar período
        let periodText;
        if (period === 'all') {
            periodText = 'Período: Todos os registros';
        } else if (period === 'today') {
            periodText = 'Período: Hoje (apenas dados de hoje)';
        } else if (period === 'yesterday') {
            periodText = 'Período: Ontem (apenas dados de ontem)';
        } else {
            periodText = `Período: Últimos ${period} dias (média dos últimos ${period} dias)`;
        }
        document.getElementById('nutrients-period-info').textContent = periodText;

        // Atualizar gráfico de nutrientes
        const nutrientsBars = document.getElementById('nutrients-improved-bars');
        if (nutrientsBars) {
            let daysToShow;
            if (period === 'all') {
                daysToShow = nutrientsData.length;
            } else if (period === 'today') {
                daysToShow = 1;
            } else if (period === 'yesterday') {
                daysToShow = 1;
            } else {
                daysToShow = parseInt(period);
            }
            let displayData;
            if (period === 'today') {
                // Filtrar apenas dados de hoje - usar a data do servidor
                const today = '<?php echo $today; ?>'; // Data do servidor
                displayData = nutrientsData.filter(day => day.date === today);
            } else if (period === 'yesterday') {
                // Filtrar apenas dados de ontem - usar a data do servidor
                const yesterday = '<?php echo $yesterday; ?>'; // Data do servidor
                displayData = nutrientsData.filter(day => day.date === yesterday);
            } else {
                displayData = nutrientsData.slice(0, daysToShow);
            }
            
            nutrientsBars.innerHTML = displayData.map(day => {
                // Para nutrientes, permitir porcentagem > 100%
                const percentage = day.avg_percentage;
                console.log('DEBUG - Processando nutrientes dia:', day.date, 'porcentagem:', percentage);
                
                // Calcular altura da barra: 0% = 0px, 100% = 160px, >100% pode ir até 200px
                let barHeight;
                if (percentage === 0) {
                    barHeight = 0; // Sem altura para 0%
                } else if (percentage >= 100) {
                    barHeight = 160 + Math.min((percentage - 100) * 0.4, 40); // 100% = 160px, máximo 200px
                } else {
                    barHeight = (percentage / 100) * 160; // Proporcional entre 0px e 160px
                }
                console.log('DEBUG - Altura calculada nutrientes:', barHeight, 'para porcentagem:', percentage);
                return `
                    <div class="improved-bar-container">
                        <div class="improved-bar-wrapper">
                            <div class="improved-bar ${day.status}" style="height: ${barHeight}px"></div>
                            <div class="bar-percentage-text">${percentage}%</div>
                            <div class="improved-goal-line"></div>
                        </div>
                        <div class="improved-bar-info">
                            <span class="improved-date">${day.date.split('-').reverse().slice(0, 2).join('/')}</span>
                            <span class="improved-ml">${day.kcal} kcal</span>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Atualizar lista de nutrientes
        const nutrientsList = document.getElementById('nutrients-simple-list');
        if (nutrientsList) {
            let daysToShow;
            if (period === 'all') {
                daysToShow = nutrientsData.length;
            } else if (period === 'today') {
                daysToShow = 1;
            } else if (period === 'yesterday') {
                daysToShow = 1;
            } else {
                daysToShow = parseInt(period);
            }
            let displayData;
            if (period === 'today') {
                // Filtrar apenas dados de hoje - usar a data do servidor
                const today = '<?php echo $today; ?>'; // Data do servidor
                displayData = nutrientsData.filter(day => day.date === today);
            } else if (period === 'yesterday') {
                // Filtrar apenas dados de ontem - usar a data do servidor
                const yesterday = '<?php echo $yesterday; ?>'; // Data do servidor
                displayData = nutrientsData.filter(day => day.date === yesterday);
            } else {
                displayData = nutrientsData.slice(0, daysToShow);
            }
            
            nutrientsList.innerHTML = displayData.map(day => {
                const iconMap = {
                    'excellent': 'fa-check-circle',
                    'good': 'fa-check',
                    'fair': 'fa-exclamation-triangle',
                    'poor': 'fa-exclamation',
                    'critical': 'fa-times-circle',
                    'empty': 'fa-minus-circle'
                };
                
                // Para nutrientes, mostrar porcentagem exata (pode ser > 100%)
                return `
                    <div class="simple-item">
                        <div class="simple-date">${day.date.split('-').reverse().join('/')}</div>
                        <div class="simple-amount">
                            <span class="simple-ml-value">${day.kcal} kcal</span>
                            <span class="simple-percentage">(${day.avg_percentage}%)</span>
                        </div>
                        <div class="simple-status ${day.status}">
                            <i class="fas ${iconMap[day.status]}"></i>
                        </div>
                    </div>
                `;
            }).join('');
        }
    }

    nutrientsFilterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remover classe active de todos os botões de nutrientes
            nutrientsFilterButtons.forEach(btn => btn.classList.remove('active'));
            
            // Adicionar classe active ao botão clicado
            this.classList.add('active');
            
            // Atualizar display com o período selecionado
            const period = this.getAttribute('data-period');
            updateNutrientsDisplay(period);
        });
    });
    
    // Inicializar o círculo de porcentagem de nutrientes
    const nutrientsCircle = document.getElementById('nutrients-percentage-circle');
    if (nutrientsCircle) {
        const initialPercentage = nutrientsStats['7'].avg_overall_percentage;
        nutrientsCircle.style.setProperty('--percentage', initialPercentage);
    }
});
</script>

<!-- ===== ANÁLISE SEMANAL - FERRAMENTA DE COMPARAÇÃO PROFISSIONAL ===== -->
<div id="tab-weekly_analysis" class="tab-content">
    <div class="weekly-analysis-container">
        <!-- Cabeçalho com Controles -->
        <div class="analysis-header">
            <div class="analysis-title">
                <h3><i class="fas fa-chart-line"></i> Análise Semanal Comparativa</h3>
                <p>Compare o plano prescrito com o consumo real do paciente</p>
            </div>
            <div class="analysis-controls">
                <div class="metric-selector">
                    <label><i class="fas fa-filter"></i> Métrica:</label>
                    <select id="weeklyMetric" class="form-control" onchange="updateWeeklyAnalysis()">
                        <option value="calories">Calorias</option>
                        <option value="protein">Proteínas</option>
                        <option value="carbs">Carboidratos</option>
                        <option value="fat">Gorduras</option>
                    </select>
                </div>
                <div class="period-selector">
                    <label><i class="fas fa-calendar"></i> Período:</label>
                    <select id="weeklyPeriod" class="form-control" onchange="updateWeeklyAnalysis()">
                        <option value="7">Últimos 7 dias</option>
                        <option value="14">Últimos 14 dias</option>
                        <option value="30">Últimos 30 dias</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Cards de Resumo da Semana -->
        <div class="weekly-summary-cards">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Média de Aderência</div>
                    <div class="stat-value" id="adherenceAverage">--</div>
                    <div class="stat-description">Proximidade da meta</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-balance-scale"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Déficit/Superávit</div>
                    <div class="stat-value" id="calorieDifference">--</div>
                    <div class="stat-description">vs. meta semanal</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Melhor Dia</div>
                    <div class="stat-value" id="bestDay">--</div>
                    <div class="stat-description">Maior aderência</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Pior Dia</div>
                    <div class="stat-value" id="worstDay">--</div>
                    <div class="stat-description">Maior desvio</div>
                </div>
            </div>
        </div>

        <!-- Gráfico Comparativo Semanal -->
        <div class="dashboard-card">
            <div class="card-header">
                <h4><i class="fas fa-chart-bar"></i> Comparação Diária: Meta vs. Consumido</h4>
                <div class="chart-legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background: var(--primary-blue);"></div>
                        <span>Meta do Plano</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: var(--accent-orange);"></div>
                        <span>Consumido Real</span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="weeklyComparisonChart" width="800" height="400"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabela Detalhada -->
        <div class="dashboard-card">
            <div class="card-header">
                <h4><i class="fas fa-table"></i> Detalhamento Diário</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="analysis-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Meta</th>
                                <th>Consumido</th>
                                <th>Diferença</th>
                                <th>Aderência</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="weeklyAnalysisTable">
                            <!-- Dados serão preenchidos via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== ANÁLISE DE FEEDBACK - DADOS SUBJETIVOS ===== -->
<div id="tab-feedback_analysis" class="tab-content">
    <div class="feedback-analysis-container">
        <!-- Cabeçalho -->
        <div class="analysis-header">
            <div class="analysis-title">
                <h3><i class="fas fa-comments"></i> Análise de Feedback e Check-ins</h3>
                <p>Visualize os dados subjetivos do paciente para entender o "porquê" por trás dos números</p>
            </div>
            <div class="analysis-controls">
                <div class="period-selector">
                    <label><i class="fas fa-calendar"></i> Período:</label>
                    <select id="feedbackPeriod" class="form-control" onchange="updateFeedbackAnalysis()">
                        <option value="7">Últimos 7 dias</option>
                        <option value="14">Últimos 14 dias</option>
                        <option value="30">Últimos 30 dias</option>
                        <option value="90">Últimos 90 dias</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Cards de Resumo do Feedback -->
        <div class="feedback-summary-cards">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Satisfação com Dieta</div>
                    <div class="stat-value" id="dietSatisfaction">--</div>
                    <div class="stat-description">Média dos últimos 30 dias</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Nível de Energia</div>
                    <div class="stat-value" id="energyLevel">--</div>
                    <div class="stat-description">Média dos últimos 30 dias</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-bed"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Qualidade do Sono</div>
                    <div class="stat-value" id="sleepQuality">--</div>
                    <div class="stat-description">Média dos últimos 30 dias</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Nível de Fome</div>
                    <div class="stat-value" id="hungerLevel">--</div>
                    <div class="stat-description">Média dos últimos 30 dias</div>
                </div>
            </div>
        </div>

        <!-- Gráficos de Tendência -->
        <div class="feedback-charts-grid">
            <div class="dashboard-card">
                <div class="card-header">
                    <h4><i class="fas fa-chart-line"></i> Satisfação com a Dieta</h4>
                </div>
                <div class="card-body">
                    <canvas id="dietSatisfactionChart" width="400" height="200"></canvas>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="card-header">
                    <h4><i class="fas fa-chart-line"></i> Nível de Energia</h4>
                </div>
                <div class="card-body">
                    <canvas id="energyLevelChart" width="400" height="200"></canvas>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="card-header">
                    <h4><i class="fas fa-chart-line"></i> Qualidade do Sono</h4>
                </div>
                <div class="card-body">
                    <canvas id="sleepQualityChart" width="400" height="200"></canvas>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="card-header">
                    <h4><i class="fas fa-chart-line"></i> Nível de Fome</h4>
                </div>
                <div class="card-body">
                    <canvas id="hungerLevelChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabela de Check-ins Recentes -->
        <div class="dashboard-card">
            <div class="card-header">
                <h4><i class="fas fa-list"></i> Histórico de Check-ins</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="feedback-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Satisfação Dieta</th>
                                <th>Energia</th>
                                <th>Sono</th>
                                <th>Fome</th>
                                <th>Observações</th>
                            </tr>
                        </thead>
                        <tbody id="feedbackHistoryTable">
                            <!-- Dados serão preenchidos via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="tab-diet-comparison" class="tab-content">
    <div class="diet-comparison-container">
        <!-- Cabeçalho com Informações da Dieta -->
        <div class="diet-header">
            <div class="diet-info">
                <h3><i class="fas fa-balance-scale"></i> Comparação: Real vs Meta da Dieta</h3>
                <div class="diet-meta-info">
                    <span class="meta-goal">Meta da Dieta: <?php echo $total_daily_calories_goal; ?> kcal/dia</span>
                    <span class="meta-macros">(P:<?php echo $macros_goal['protein_g']; ?>g, C:<?php echo $macros_goal['carbs_g']; ?>g, G:<?php echo $macros_goal['fat_g']; ?>g)</span>
                </div>
            </div>
            <div class="diet-actions">
                <button class="btn btn-primary" onclick="openDietPlanModal()">
                    <i class="fas fa-upload"></i> Carregar Plano Alimentar
                </button>
            </div>
        </div>

        <!-- Filtros de Período -->
        <div class="filter-section">
            <div class="filter-buttons">
                <button class="filter-btn active" data-period="today">Hoje</button>
                <button class="filter-btn" data-period="yesterday">Ontem</button>
                <button class="filter-btn" data-period="7">Últimos 7 dias</button>
                <button class="filter-btn" data-period="15">Últimos 15 dias</button>
                <button class="filter-btn" data-period="30">Últimos 30 dias</button>
                <button class="filter-btn" data-period="all">Todos os registros</button>
            </div>
        </div>

        <!-- Cards de Comparação -->
        <div class="comparison-cards">
            <div class="comparison-card">
                <div class="card-header">
                    <i class="fas fa-fire"></i>
                    <h4>Calorias</h4>
                </div>
                <div class="comparison-content">
                    <div class="comparison-values">
                        <div class="value-item">
                            <span class="label">Consumido:</span>
                            <span class="value" id="calories-consumed">0 kcal</span>
                        </div>
                        <div class="value-item">
                            <span class="label">Meta:</span>
                            <span class="value" id="calories-goal"><?php echo $total_daily_calories_goal; ?> kcal</span>
                        </div>
                        <div class="value-item">
                            <span class="label">Diferença:</span>
                            <span class="value" id="calories-diff">0 kcal</span>
                        </div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="calories-progress"></div>
                        <span class="progress-text" id="calories-percentage">0%</span>
                    </div>
                </div>
            </div>

            <div class="comparison-card">
                <div class="card-header">
                    <i class="fas fa-drumstick-bite"></i>
                    <h4>Proteínas</h4>
                </div>
                <div class="comparison-content">
                    <div class="comparison-values">
                        <div class="value-item">
                            <span class="label">Consumido:</span>
                            <span class="value" id="protein-consumed">0g</span>
                        </div>
                        <div class="value-item">
                            <span class="label">Meta:</span>
                            <span class="value" id="protein-goal"><?php echo $macros_goal['protein_g']; ?>g</span>
                        </div>
                        <div class="value-item">
                            <span class="label">Diferença:</span>
                            <span class="value" id="protein-diff">0g</span>
                        </div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="protein-progress"></div>
                        <span class="progress-text" id="protein-percentage">0%</span>
                    </div>
                </div>
            </div>

            <div class="comparison-card">
                <div class="card-header">
                    <i class="fas fa-bread-slice"></i>
                    <h4>Carboidratos</h4>
                </div>
                <div class="comparison-content">
                    <div class="comparison-values">
                        <div class="value-item">
                            <span class="label">Consumido:</span>
                            <span class="value" id="carbs-consumed">0g</span>
                        </div>
                        <div class="value-item">
                            <span class="label">Meta:</span>
                            <span class="value" id="carbs-goal"><?php echo $macros_goal['carbs_g']; ?>g</span>
                        </div>
                        <div class="value-item">
                            <span class="label">Diferença:</span>
                            <span class="value" id="carbs-diff">0g</span>
                        </div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="carbs-progress"></div>
                        <span class="progress-text" id="carbs-percentage">0%</span>
                    </div>
                </div>
            </div>

            <div class="comparison-card">
                <div class="card-header">
                    <i class="fas fa-seedling"></i>
                    <h4>Gorduras</h4>
                </div>
                <div class="comparison-content">
                    <div class="comparison-values">
                        <div class="value-item">
                            <span class="label">Consumido:</span>
                            <span class="value" id="fat-consumed">0g</span>
                        </div>
                        <div class="value-item">
                            <span class="label">Meta:</span>
                            <span class="value" id="fat-goal"><?php echo $macros_goal['fat_g']; ?>g</span>
                        </div>
                        <div class="value-item">
                            <span class="label">Diferença:</span>
                            <span class="value" id="fat-diff">0g</span>
                        </div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="fat-progress"></div>
                        <span class="progress-text" id="fat-percentage">0%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico de Comparação Semanal -->
        <div class="chart-section">
            <div class="chart-section-header">
                <h4>Comparação Semanal: Real vs Meta</h4>
            </div>
            <div class="chart-container">
                <canvas id="dietComparisonChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div id="tab-weekly-tracking" class="tab-content">
    <div class="weekly-tracking-container">
        <!-- Cabeçalho com Informações da Semana -->
        <div class="weekly-header">
            <div class="weekly-info">
                <h3><i class="fas fa-calendar-week"></i> Rastreio Semanal de Calorias</h3>
                <div class="week-selector">
                    <button class="week-btn" onclick="changeWeek(-1)">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span class="current-week" id="currentWeek">Semana Atual</span>
                    <button class="week-btn" onclick="changeWeek(1)">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            <div class="weekly-summary">
                <div class="summary-item">
                    <span class="label">Meta Semanal:</span>
                    <span class="value" id="weeklyGoal"><?php echo $total_daily_calories_goal * 7; ?> kcal</span>
                </div>
                <div class="summary-item">
                    <span class="label">Consumido:</span>
                    <span class="value" id="weeklyConsumed">0 kcal</span>
                </div>
                <div class="summary-item">
                    <span class="label">Diferença:</span>
                    <span class="value" id="weeklyDiff">0 kcal</span>
                </div>
            </div>
        </div>

        <!-- Gráfico de Barras Semanal -->
        <div class="chart-section">
            <div class="chart-section-header">
                <h4>Consumo Diário vs Meta</h4>
                <div class="chart-legend">
                    <div class="legend-item">
                        <div class="legend-bar meta"></div>
                        <span>Meta Diária</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-bar consumed"></div>
                        <span>Consumido</span>
                    </div>
                </div>
            </div>
            <div class="weekly-chart-container">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>

        <!-- Tabela Detalhada da Semana -->
        <div class="weekly-table-section">
            <h4>Detalhamento da Semana</h4>
            <div class="table-container">
                <table class="weekly-table">
                    <thead>
                        <tr>
                            <th>Dia</th>
                            <th>Data</th>
                            <th>Meta (kcal)</th>
                            <th>Consumido (kcal)</th>
                            <th>Diferença</th>
                            <th>% da Meta</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="weeklyTableBody">
                        <!-- Dados serão preenchidos via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Resumo da Semana -->
        <div class="weekly-summary-cards">
            <div class="summary-card">
                <div class="card-icon">
                    <i class="fas fa-fire"></i>
                </div>
                <div class="card-content">
                    <h5>Total Consumido</h5>
                    <div class="card-value" id="totalConsumed">0 kcal</div>
                    <div class="card-subtitle">Esta semana</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="card-icon">
                    <i class="fas fa-target"></i>
                </div>
                <div class="card-content">
                    <h5>Meta Semanal</h5>
                    <div class="card-value" id="totalGoal"><?php echo $total_daily_calories_goal * 7; ?> kcal</div>
                    <div class="card-subtitle">7 dias × <?php echo $total_daily_calories_goal; ?> kcal</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="card-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="card-content">
                    <h5>Média Diária</h5>
                    <div class="card-value" id="dailyAverage">0 kcal</div>
                    <div class="card-subtitle">Por dia</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="card-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="card-content">
                    <h5>% da Meta</h5>
                    <div class="card-value" id="weeklyPercentage">0%</div>
                    <div class="card-subtitle">Atingida</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="tab-feedback-analysis" class="tab-content">
    <div class="feedback-analysis-container">
        <!-- Cabeçalho -->
        <div class="analysis-header">
            <h3><i class="fas fa-chart-line"></i> Análise de Feedback e Rotinas</h3>
            <p>Análise comparativa dos feedbacks de check-in e aderência às rotinas diárias</p>
        </div>

        <!-- Filtros de Período -->
        <div class="analysis-filters">
            <div class="filter-group">
                <label>Período de Análise:</label>
                <div class="filter-buttons">
                    <button class="filter-btn active" data-period="7">Últimos 7 dias</button>
                    <button class="filter-btn" data-period="15">Últimos 15 dias</button>
                    <button class="filter-btn" data-period="30">Últimos 30 dias</button>
                    <button class="filter-btn" data-period="90">Últimos 90 dias</button>
                </div>
            </div>
        </div>

        <!-- Cards de Resumo -->
        <div class="analysis-summary-cards">
            <div class="summary-card">
                <div class="card-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="card-content">
                    <h5>Check-ins Realizados</h5>
                    <div class="card-value" id="totalCheckins">0</div>
                    <div class="card-subtitle">Total no período</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="card-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="card-content">
                    <h5>Rotinas Completadas</h5>
                    <div class="card-value" id="totalRoutines">0</div>
                    <div class="card-subtitle">Total no período</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="card-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="card-content">
                    <h5>Taxa de Aderência</h5>
                    <div class="card-value" id="adherenceRate">0%</div>
                    <div class="card-subtitle">Check-ins vs Rotinas</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="card-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="card-content">
                    <h5>Média de Satisfação</h5>
                    <div class="card-value" id="avgSatisfaction">0.0</div>
                    <div class="card-subtitle">Escala 1-5</div>
                </div>
            </div>
        </div>

        <!-- Gráficos de Análise -->
        <div class="analysis-charts">
            <!-- Gráfico de Aderência Diária -->
            <div class="chart-section">
                <div class="chart-section-header">
                    <h4>Aderência Diária</h4>
                    <p>Comparação entre check-ins realizados e rotinas completadas por dia</p>
                </div>
                <div class="chart-container">
                    <canvas id="adherenceChart"></canvas>
                </div>
            </div>

            <!-- Gráfico de Satisfação -->
            <div class="chart-section">
                <div class="chart-section-header">
                    <h4>Evolução da Satisfação</h4>
                    <p>Média de satisfação reportada nos check-ins ao longo do tempo</p>
                </div>
                <div class="chart-container">
                    <canvas id="satisfactionChart"></canvas>
                </div>
            </div>

            <!-- Gráfico de Rotinas por Categoria -->
            <div class="chart-section">
                <div class="chart-section-header">
                    <h4>Rotinas por Categoria</h4>
                    <p>Distribuição das rotinas completadas por tipo de atividade</p>
                </div>
                <div class="chart-container">
                    <canvas id="routineCategoryChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabela de Detalhamento -->
        <div class="analysis-table-section">
            <h4>Histórico Detalhado</h4>
            <div class="table-container">
                <table class="analysis-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Check-in</th>
                            <th>Satisfação</th>
                            <th>Rotinas Completadas</th>
                            <th>Observações</th>
                        </tr>
                    </thead>
                    <tbody id="analysisTableBody">
                        <!-- Dados serão preenchidos via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="tab-personalized-goals" class="tab-content">
    <div class="personalized-goals-container">
        <!-- Cabeçalho -->
        <div class="goals-header">
            <h3><i class="fas fa-target"></i> Metas Personalizadas</h3>
            <p>Configure metas específicas para passos, exercícios e outras atividades do paciente</p>
        </div>

        <!-- Seção de Metas de Atividade Física -->
        <div class="goals-section">
            <div class="section-header">
                <h4><i class="fas fa-running"></i> Atividade Física</h4>
                <button class="btn btn-primary" onclick="openGoalsModal('physical')">
                    <i class="fas fa-edit"></i> Editar Metas
                </button>
            </div>
            
            <div class="goals-grid">
                <div class="goal-card">
                    <div class="goal-icon">
                        <i class="fas fa-walking"></i>
                    </div>
                    <div class="goal-content">
                        <h5>Passos Diários</h5>
                        <div class="goal-value" id="stepsGoal"><?php echo $user_data['steps_goal'] ?? 10000; ?></div>
                        <div class="goal-subtitle">passos/dia</div>
                        <div class="goal-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" id="stepsProgress" style="width: 0%"></div>
                            </div>
                            <span class="progress-text" id="stepsText">0 / <?php echo $user_data['steps_goal'] ?? 10000; ?></span>
                        </div>
                    </div>
                </div>

                <div class="goal-card">
                    <div class="goal-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <div class="goal-content">
                        <h5>Minutos de Exercício</h5>
                        <div class="goal-value" id="exerciseGoal"><?php echo $user_data['exercise_goal'] ?? 30; ?></div>
                        <div class="goal-subtitle">minutos/dia</div>
                        <div class="goal-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" id="exerciseProgress" style="width: 0%"></div>
                            </div>
                            <span class="progress-text" id="exerciseText">0 / <?php echo $user_data['exercise_goal'] ?? 30; ?></span>
                        </div>
                    </div>
                </div>

                <div class="goal-card">
                    <div class="goal-icon">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div class="goal-content">
                        <h5>Calorias Queimadas</h5>
                        <div class="goal-value" id="caloriesBurnedGoal"><?php echo $user_data['calories_burned_goal'] ?? 300; ?></div>
                        <div class="goal-subtitle">kcal/dia</div>
                        <div class="goal-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" id="caloriesBurnedProgress" style="width: 0%"></div>
                            </div>
                            <span class="progress-text" id="caloriesBurnedText">0 / <?php echo $user_data['calories_burned_goal'] ?? 300; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seção de Metas de Sono -->
        <div class="goals-section">
            <div class="section-header">
                <h4><i class="fas fa-bed"></i> Sono e Descanso</h4>
                <button class="btn btn-primary" onclick="openGoalsModal('sleep')">
                    <i class="fas fa-edit"></i> Editar Metas
                </button>
            </div>
            
            <div class="goals-grid">
                <div class="goal-card">
                    <div class="goal-icon">
                        <i class="fas fa-moon"></i>
                    </div>
                    <div class="goal-content">
                        <h5>Horas de Sono</h5>
                        <div class="goal-value" id="sleepGoal"><?php echo $user_data['sleep_goal'] ?? 8; ?></div>
                        <div class="goal-subtitle">horas/noite</div>
                        <div class="goal-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" id="sleepProgress" style="width: 0%"></div>
                            </div>
                            <span class="progress-text" id="sleepText">0 / <?php echo $user_data['sleep_goal'] ?? 8; ?></span>
                        </div>
                    </div>
                </div>

                <div class="goal-card">
                    <div class="goal-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="goal-content">
                        <h5>Horário de Dormir</h5>
                        <div class="goal-value" id="bedtimeGoal"><?php echo $user_data['bedtime_goal'] ?? '22:00'; ?></div>
                        <div class="goal-subtitle">horário ideal</div>
                        <div class="goal-status" id="bedtimeStatus">
                            <span class="status-indicator good"></span>
                            <span>No horário</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seção de Metas de Bem-estar -->
        <div class="goals-section">
            <div class="section-header">
                <h4><i class="fas fa-heart"></i> Bem-estar</h4>
                <button class="btn btn-primary" onclick="openGoalsModal('wellness')">
                    <i class="fas fa-edit"></i> Editar Metas
                </button>
            </div>
            
            <div class="goals-grid">
                <div class="goal-card">
                    <div class="goal-icon">
                        <i class="fas fa-tint"></i>
                    </div>
                    <div class="goal-content">
                        <h5>Hidratação</h5>
                        <div class="goal-value" id="waterGoal"><?php echo $water_goal_ml; ?>ml</div>
                        <div class="goal-subtitle">por dia</div>
                        <div class="goal-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" id="waterProgress" style="width: 0%"></div>
                            </div>
                            <span class="progress-text" id="waterText">0 / <?php echo $water_goal_ml; ?>ml</span>
                        </div>
                    </div>
                </div>

                <div class="goal-card">
                    <div class="goal-icon">
                        <i class="fas fa-apple-alt"></i>
                    </div>
                    <div class="goal-content">
                        <h5>Refeições Saudáveis</h5>
                        <div class="goal-value" id="mealsGoal"><?php echo $user_data['healthy_meals_goal'] ?? 3; ?></div>
                        <div class="goal-subtitle">refeições/dia</div>
                        <div class="goal-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" id="mealsProgress" style="width: 0%"></div>
                            </div>
                            <span class="progress-text" id="mealsText">0 / <?php echo $user_data['healthy_meals_goal'] ?? 3; ?></span>
                        </div>
                    </div>
                </div>

                <div class="goal-card">
                    <div class="goal-icon">
                        <i class="fas fa-meditation"></i>
                    </div>
                    <div class="goal-content">
                        <h5>Minutos de Meditação</h5>
                        <div class="goal-value" id="meditationGoal"><?php echo $user_data['meditation_goal'] ?? 10; ?></div>
                        <div class="goal-subtitle">minutos/dia</div>
                        <div class="goal-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" id="meditationProgress" style="width: 0%"></div>
                            </div>
                            <span class="progress-text" id="meditationText">0 / <?php echo $user_data['meditation_goal'] ?? 10; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Histórico de Metas -->
        <div class="goals-history-section">
            <h4><i class="fas fa-history"></i> Histórico de Alterações</h4>
            <div class="table-container">
                <table class="goals-history-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo de Meta</th>
                            <th>Valor Anterior</th>
                            <th>Novo Valor</th>
                            <th>Alterado por</th>
                        </tr>
                    </thead>
                    <tbody id="goalsHistoryBody">
                        <!-- Dados serão preenchidos via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="tab-progress" class="tab-content">
    <div class="progress-grid">
        <div class="dashboard-card weight-history-card">
            <h4>Histórico de Peso</h4>
            <?php if (empty($weight_chart_data['data'])): ?>
                <p class="empty-state">O paciente ainda não registrou nenhum peso.</p>
            <?php else: ?>
                <canvas id="weightHistoryChart"></canvas>
                <?php if (count($weight_chart_data['data']) < 2): ?>
                    <p class="info-message-chart">Aguardando o próximo registro de peso para traçar a linha de progresso.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="dashboard-card photos-history-card">
            <div class="section-header">
                <h4>Fotos de Progresso</h4>
                <?php if (count($photo_history) > 3): ?>
                    <button class="btn-secondary" onclick="openGalleryModal()">
                        <i class="fas fa-images"></i> Ver Todas (<?php echo count($photo_history); ?>)
                    </button>
                <?php endif; ?>
            </div>
            <?php if (empty($photo_history)): ?>
                <p class="empty-state">Nenhuma foto de progresso encontrada.</p>
            <?php else: ?>
                <div class="photo-gallery">
                    <?php 
                    $displayed_count = 0;
                    foreach($photo_history as $photo_set): 
                        if ($displayed_count >= 3) break;
                        foreach(['photo_front' => 'Frente', 'photo_side' => 'Lado', 'photo_back' => 'Costas'] as $photo_type => $label): 
                            if ($displayed_count >= 3) break;
                            if(!empty($photo_set[$photo_type])): 
                                $displayed_count++;
                    ?>
                                <?php 
                                $timestamp = !empty($photo_set['created_at']) ? strtotime($photo_set['created_at']) : strtotime($photo_set['date_recorded']);
                                $display_date = $timestamp ? date('d/m/Y H:i', $timestamp) : date('d/m/Y H:i');
                                ?>
                                <div class="photo-item" onclick="openPhotoModal('<?php echo BASE_APP_URL . '/uploads/measurements/' . htmlspecialchars($photo_set[$photo_type]); ?>', '<?php echo $label; ?>', '<?php echo $display_date; ?>')">
                                    <img src="<?php echo BASE_APP_URL . '/uploads/measurements/' . htmlspecialchars($photo_set[$photo_type]); ?>" loading="lazy" alt="Foto de progresso - <?php echo $label; ?>" onerror="this.style.display='none'">
                                    <div class="photo-date">
                                        <span><?php echo $label; ?></span>
                                        <span><?php echo $display_date; ?></span>
                                    </div>
                                </div>
                            <?php 
                            endif; 
                        endforeach; 
                    endforeach; 
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="tab-measurements" class="tab-content">
    <div class="dashboard-card">
        <h3>Histórico de Medidas Corporais</h3>
        <p class="empty-state">Funcionalidade a ser implementada.</p>
    </div>
</div>


<script>
const userViewData = {
    weightHistory: <?php echo json_encode($weight_chart_data); ?>
};


// --- FUNCIONALIDADES DO RASTREIO SEMANAL ---

let currentWeekOffset = 0;
let weeklyChart = null;

// Dados para o rastreio semanal (serão preenchidos via PHP)
const weeklyData = <?php echo json_encode($last_7_days_data); ?>;
const dailyCalorieGoal = <?php echo $total_daily_calories_goal; ?>;

// Função para mudar a semana
function changeWeek(direction) {
    currentWeekOffset += direction;
    updateWeeklyDisplay();
}

// Função para atualizar a exibição semanal
function updateWeeklyDisplay() {
    const today = new Date();
    const startOfWeek = new Date(today);
    startOfWeek.setDate(today.getDate() - today.getDay() + (currentWeekOffset * 7));
    
    const endOfWeek = new Date(startOfWeek);
    endOfWeek.setDate(startOfWeek.getDate() + 6);
    
    // Atualizar texto da semana
    const weekText = currentWeekOffset === 0 ? 'Semana Atual' : 
                    currentWeekOffset > 0 ? `Semana +${currentWeekOffset}` : 
                    `Semana ${currentWeekOffset}`;
    document.getElementById('currentWeek').textContent = weekText;
    
    // Calcular dados da semana
    const weekData = calculateWeekData(startOfWeek, endOfWeek);
    
    // Atualizar resumo
    updateWeeklySummary(weekData);
    
    // Atualizar tabela
    updateWeeklyTable(weekData);
    
    // Atualizar gráfico
    updateWeeklyChart(weekData);
}

// Função para calcular dados da semana
function calculateWeekData(startDate, endDate) {
    const weekData = [];
    let totalConsumed = 0;
    let totalGoal = 0;
    
    for (let i = 0; i < 7; i++) {
        const currentDate = new Date(startDate);
        currentDate.setDate(startDate.getDate() + i);
        const dateStr = currentDate.toISOString().split('T')[0];
        
        // Buscar dados do dia
        const dayData = weeklyData.find(day => day.date === dateStr);
        const consumed = dayData ? dayData.total_kcal : 0;
        const goal = dailyCalorieGoal;
        
        const percentage = goal > 0 ? (consumed / goal) * 100 : 0;
        const difference = consumed - goal;
        
        let status = 'critical';
        if (percentage >= 100) status = 'excellent';
        else if (percentage >= 90) status = 'good';
        else if (percentage >= 70) status = 'fair';
        else if (percentage >= 50) status = 'poor';
        
        weekData.push({
            date: currentDate,
            dateStr: dateStr,
            dayName: currentDate.toLocaleDateString('pt-BR', { weekday: 'long' }),
            consumed: consumed,
            goal: goal,
            difference: difference,
            percentage: percentage,
            status: status
        });
        
        totalConsumed += consumed;
        totalGoal += goal;
    }
    
    return {
        days: weekData,
        totalConsumed: totalConsumed,
        totalGoal: totalGoal,
        averageConsumed: totalConsumed / 7,
        weeklyPercentage: totalGoal > 0 ? (totalConsumed / totalGoal) * 100 : 0
    };
}

// Função para atualizar resumo semanal
function updateWeeklySummary(data) {
    document.getElementById('weeklyGoal').textContent = `${data.totalGoal} kcal`;
    document.getElementById('weeklyConsumed').textContent = `${data.totalConsumed} kcal`;
    document.getElementById('weeklyDiff').textContent = `${data.totalConsumed - data.totalGoal} kcal`;
    
    document.getElementById('totalConsumed').textContent = `${data.totalConsumed} kcal`;
    document.getElementById('dailyAverage').textContent = `${Math.round(data.averageConsumed)} kcal`;
    document.getElementById('weeklyPercentage').textContent = `${Math.round(data.weeklyPercentage)}%`;
}

// Função para atualizar tabela semanal
function updateWeeklyTable(data) {
    const tbody = document.getElementById('weeklyTableBody');
    tbody.innerHTML = '';
    
    data.days.forEach(day => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${day.dayName}</td>
            <td>${day.date.toLocaleDateString('pt-BR')}</td>
            <td>${day.goal} kcal</td>
            <td>${day.consumed} kcal</td>
            <td class="${day.difference >= 0 ? 'positive' : 'negative'}">${day.difference >= 0 ? '+' : ''}${day.difference} kcal</td>
            <td>${Math.round(day.percentage)}%</td>
            <td><span class="status-badge ${day.status}">${day.status}</span></td>
        `;
        tbody.appendChild(row);
    });
}

// Função para atualizar gráfico semanal
function updateWeeklyChart(data) {
    const ctx = document.getElementById('weeklyChart');
    if (!ctx) return;
    
    if (weeklyChart) {
        weeklyChart.destroy();
    }
    
    const labels = data.days.map(day => day.dayName);
    const consumedData = data.days.map(day => day.consumed);
    const goalData = data.days.map(day => day.goal);
    
    weeklyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Meta Diária',
                    data: goalData,
                    backgroundColor: 'rgba(255, 107, 0, 0.3)',
                    borderColor: '#ff6b00',
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false,
                },
                {
                    label: 'Consumido',
                    data: consumedData,
                    backgroundColor: 'rgba(76, 175, 80, 0.3)',
                    borderColor: '#4caf50',
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#b0b0b0'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#b0b0b0'
                    }
                }
            }
        }
    });
}

// Inicializar rastreio semanal quando a aba for ativada
document.addEventListener('DOMContentLoaded', function() {
    // Adicionar listener para mudança de abas
    document.querySelectorAll('.tab-link').forEach(tab => {
        tab.addEventListener('click', function() {
            if (this.dataset.tab === 'weekly-tracking') {
                setTimeout(() => {
                    updateWeeklyDisplay();
                }, 100);
            } else if (this.dataset.tab === 'feedback-analysis') {
                setTimeout(() => {
                    updateFeedbackAnalysis();
                }, 100);
            }
        });
    });
});

// --- FUNCIONALIDADES DA ANÁLISE DE FEEDBACK ---

let currentAnalysisPeriod = 7;
let adherenceChart = null;
let satisfactionChart = null;
let routineCategoryChart = null;

// Dados simulados para análise de feedback (em produção, viriam do banco)
const feedbackData = {
    checkins: [
        { date: '2024-10-01', satisfaction: 4.5, notes: 'Dia produtivo' },
        { date: '2024-09-30', satisfaction: 3.8, notes: 'Cansado' },
        { date: '2024-09-29', satisfaction: 4.2, notes: 'Bom progresso' },
        { date: '2024-09-28', satisfaction: 3.5, notes: 'Dificuldades' },
        { date: '2024-09-27', satisfaction: 4.0, notes: 'Estável' },
        { date: '2024-09-26', satisfaction: 4.3, notes: 'Motivado' },
        { date: '2024-09-25', satisfaction: 3.9, notes: 'Regular' }
    ],
    routines: [
        { date: '2024-10-01', completed: 3, total: 4, categories: { exercise: 1, nutrition: 1, hydration: 1, sleep: 0 } },
        { date: '2024-09-30', completed: 2, total: 4, categories: { exercise: 0, nutrition: 1, hydration: 1, sleep: 0 } },
        { date: '2024-09-29', completed: 4, total: 4, categories: { exercise: 1, nutrition: 1, hydration: 1, sleep: 1 } },
        { date: '2024-09-28', completed: 1, total: 4, categories: { exercise: 0, nutrition: 1, hydration: 0, sleep: 0 } },
        { date: '2024-09-27', completed: 3, total: 4, categories: { exercise: 1, nutrition: 1, hydration: 1, sleep: 0 } },
        { date: '2024-09-26', completed: 4, total: 4, categories: { exercise: 1, nutrition: 1, hydration: 1, sleep: 1 } },
        { date: '2024-09-25', completed: 2, total: 4, categories: { exercise: 0, nutrition: 1, hydration: 1, sleep: 0 } }
    ]
};

// Função para atualizar análise de feedback
function updateFeedbackAnalysis() {
    // Adicionar listeners para filtros
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentAnalysisPeriod = parseInt(this.dataset.period);
            updateFeedbackAnalysis();
        });
    });
    
    // Calcular dados do período
    const analysisData = calculateAnalysisData();
    
    // Atualizar cards de resumo
    updateAnalysisSummary(analysisData);
    
    // Atualizar gráficos
    updateAdherenceChart(analysisData);
    updateSatisfactionChart(analysisData);
    updateRoutineCategoryChart(analysisData);
    
    // Atualizar tabela
    updateAnalysisTable(analysisData);
}

// Função para calcular dados da análise
function calculateAnalysisData() {
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(endDate.getDate() - currentAnalysisPeriod);
    
    const filteredCheckins = feedbackData.checkins.filter(item => {
        const itemDate = new Date(item.date);
        return itemDate >= startDate && itemDate <= endDate;
    });
    
    const filteredRoutines = feedbackData.routines.filter(item => {
        const itemDate = new Date(item.date);
        return itemDate >= startDate && itemDate <= endDate;
    });
    
    const totalCheckins = filteredCheckins.length;
    const totalRoutines = filteredRoutines.reduce((sum, item) => sum + item.completed, 0);
    const totalPossibleRoutines = filteredRoutines.reduce((sum, item) => sum + item.total, 0);
    const adherenceRate = totalPossibleRoutines > 0 ? (totalRoutines / totalPossibleRoutines) * 100 : 0;
    const avgSatisfaction = filteredCheckins.length > 0 ? 
        filteredCheckins.reduce((sum, item) => sum + item.satisfaction, 0) / filteredCheckins.length : 0;
    
    return {
        checkins: filteredCheckins,
        routines: filteredRoutines,
        totalCheckins,
        totalRoutines,
        totalPossibleRoutines,
        adherenceRate,
        avgSatisfaction
    };
}

// Função para atualizar resumo da análise
function updateAnalysisSummary(data) {
    document.getElementById('totalCheckins').textContent = data.totalCheckins;
    document.getElementById('totalRoutines').textContent = data.totalRoutines;
    document.getElementById('adherenceRate').textContent = `${Math.round(data.adherenceRate)}%`;
    document.getElementById('avgSatisfaction').textContent = data.avgSatisfaction.toFixed(1);
}

// Função para atualizar gráfico de aderência
function updateAdherenceChart(data) {
    const ctx = document.getElementById('adherenceChart');
    if (!ctx) return;
    
    if (adherenceChart) {
        adherenceChart.destroy();
    }
    
    const labels = data.routines.map(item => new Date(item.date).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }));
    const completedData = data.routines.map(item => item.completed);
    const totalData = data.routines.map(item => item.total);
    
    adherenceChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Rotinas Completadas',
                    data: completedData,
                    backgroundColor: 'rgba(76, 175, 80, 0.3)',
                    borderColor: '#4caf50',
                    borderWidth: 2,
                    borderRadius: 6,
                },
                {
                    label: 'Total de Rotinas',
                    data: totalData,
                    backgroundColor: 'rgba(255, 107, 0, 0.3)',
                    borderColor: '#ff6b00',
                    borderWidth: 2,
                    borderRadius: 6,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: '#b0b0b0'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#b0b0b0'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#b0b0b0'
                    }
                }
            }
        }
    });
}

// Função para atualizar gráfico de satisfação
function updateSatisfactionChart(data) {
    const ctx = document.getElementById('satisfactionChart');
    if (!ctx) return;
    
    if (satisfactionChart) {
        satisfactionChart.destroy();
    }
    
    const labels = data.checkins.map(item => new Date(item.date).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }));
    const satisfactionData = data.checkins.map(item => item.satisfaction);
    
    satisfactionChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Satisfação',
                data: satisfactionData,
                borderColor: '#ff6b00',
                backgroundColor: 'rgba(255, 107, 0, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#ff6b00',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 5,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#b0b0b0'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#b0b0b0'
                    }
                }
            }
        }
    });
}

// Função para atualizar gráfico de rotinas por categoria
function updateRoutineCategoryChart(data) {
    const ctx = document.getElementById('routineCategoryChart');
    if (!ctx) return;
    
    if (routineCategoryChart) {
        routineCategoryChart.destroy();
    }
    
    const categories = ['exercise', 'nutrition', 'hydration', 'sleep'];
    const categoryLabels = ['Exercício', 'Nutrição', 'Hidratação', 'Sono'];
    const categoryData = categories.map(category => 
        data.routines.reduce((sum, item) => sum + (item.categories[category] || 0), 0)
    );
    
    routineCategoryChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: categoryLabels,
            datasets: [{
                data: categoryData,
                backgroundColor: [
                    'rgba(76, 175, 80, 0.8)',
                    'rgba(33, 150, 243, 0.8)',
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(156, 39, 176, 0.8)'
                ],
                borderColor: [
                    '#4caf50',
                    '#2196f3',
                    '#ffc107',
                    '#9c27b0'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#b0b0b0',
                        padding: 20
                    }
                }
            }
        }
    });
}

// Função para atualizar tabela de análise
function updateAnalysisTable(data) {
    const tbody = document.getElementById('analysisTableBody');
    tbody.innerHTML = '';
    
    // Combinar dados de check-ins e rotinas por data
    const combinedData = [];
    const allDates = [...new Set([...data.checkins.map(c => c.date), ...data.routines.map(r => r.date)])];
    
    allDates.forEach(date => {
        const checkin = data.checkins.find(c => c.date === date);
        const routine = data.routines.find(r => r.date === date);
        
        combinedData.push({
            date: new Date(date),
            checkin: checkin ? 'Sim' : 'Não',
            satisfaction: checkin ? checkin.satisfaction : '-',
            routines: routine ? `${routine.completed}/${routine.total}` : '0/0',
            notes: checkin ? checkin.notes : '-'
        });
    });
    
    // Ordenar por data (mais recente primeiro)
    combinedData.sort((a, b) => b.date - a.date);
    
    combinedData.forEach(item => {
        const row = document.createElement('tr');
        const satisfactionBadge = item.satisfaction !== '-' ? 
            `<span class="satisfaction-badge ${getSatisfactionClass(item.satisfaction)}">${item.satisfaction}</span>` : 
            '-';
        
        row.innerHTML = `
            <td>${item.date.toLocaleDateString('pt-BR')}</td>
            <td>${item.checkin}</td>
            <td>${satisfactionBadge}</td>
            <td>${item.routines}</td>
            <td>${item.notes}</td>
        `;
        tbody.appendChild(row);
    });
}

// Função para determinar classe de satisfação
function getSatisfactionClass(satisfaction) {
    if (satisfaction >= 4.5) return 'excellent';
    if (satisfaction >= 4.0) return 'good';
    if (satisfaction >= 3.5) return 'fair';
    if (satisfaction >= 3.0) return 'poor';
    return 'critical';
}

// --- FUNCIONALIDADES DAS METAS PERSONALIZADAS ---

// Dados simulados para metas personalizadas (em produção, viriam do banco)
const personalizedGoalsData = {
    physical: {
        steps: { current: 7500, goal: 10000 },
        exercise: { current: 25, goal: 30 },
        caloriesBurned: { current: 250, goal: 300 }
    },
    sleep: {
        sleep: { current: 7.5, goal: 8 },
        bedtime: { current: '22:30', goal: '22:00' }
    },
    wellness: {
        water: { current: 1500, goal: 2000 },
        meals: { current: 2, goal: 3 },
        meditation: { current: 5, goal: 10 }
    }
};

// Função para abrir modal de edição de metas
function openGoalsModal(category) {
    // Implementar modal de edição de metas
    alert(`Modal de edição de metas para categoria: ${category}`);
}

// Função para atualizar metas personalizadas
function updatePersonalizedGoals() {
    // Atualizar progresso das metas físicas
    updateGoalProgress('steps', personalizedGoalsData.physical.steps.current, personalizedGoalsData.physical.steps.goal);
    updateGoalProgress('exercise', personalizedGoalsData.physical.exercise.current, personalizedGoalsData.physical.exercise.goal);
    updateGoalProgress('caloriesBurned', personalizedGoalsData.physical.caloriesBurned.current, personalizedGoalsData.physical.caloriesBurned.goal);
    
    // Atualizar progresso das metas de sono
    updateGoalProgress('sleep', personalizedGoalsData.sleep.sleep.current, personalizedGoalsData.sleep.sleep.goal);
    updateBedtimeStatus(personalizedGoalsData.sleep.bedtime.current, personalizedGoalsData.sleep.bedtime.goal);
    
    // Atualizar progresso das metas de bem-estar
    updateGoalProgress('water', personalizedGoalsData.wellness.water.current, personalizedGoalsData.wellness.water.goal);
    updateGoalProgress('meals', personalizedGoalsData.wellness.meals.current, personalizedGoalsData.wellness.meals.goal);
    updateGoalProgress('meditation', personalizedGoalsData.wellness.meditation.current, personalizedGoalsData.wellness.meditation.goal);
    
    // Atualizar histórico
    updateGoalsHistory();
}

// Função para atualizar progresso de uma meta
function updateGoalProgress(goalId, current, goal) {
    const percentage = goal > 0 ? Math.min((current / goal) * 100, 100) : 0;
    
    // Atualizar barra de progresso
    const progressBar = document.getElementById(`${goalId}Progress`);
    if (progressBar) {
        progressBar.style.width = `${percentage}%`;
    }
    
    // Atualizar texto de progresso
    const progressText = document.getElementById(`${goalId}Text`);
    if (progressText) {
        progressText.textContent = `${current} / ${goal}`;
    }
    
    // Atualizar cor da barra baseada na porcentagem
    if (progressBar) {
        if (percentage >= 100) {
            progressBar.style.background = 'linear-gradient(90deg, #4caf50, #66bb6a)';
        } else if (percentage >= 70) {
            progressBar.style.background = 'linear-gradient(90deg, #2196f3, #42a5f5)';
        } else if (percentage >= 50) {
            progressBar.style.background = 'linear-gradient(90deg, #ff9800, #ffb74d)';
        } else {
            progressBar.style.background = 'linear-gradient(90deg, #f44336, #ef5350)';
        }
    }
}

// Função para atualizar status do horário de dormir
function updateBedtimeStatus(current, goal) {
    const statusElement = document.getElementById('bedtimeStatus');
    if (!statusElement) return;
    
    const currentTime = parseTime(current);
    const goalTime = parseTime(goal);
    
    const diffMinutes = Math.abs(currentTime - goalTime);
    
    let statusClass = 'bad';
    let statusText = 'Fora do horário';
    
    if (diffMinutes <= 15) {
        statusClass = 'good';
        statusText = 'No horário';
    } else if (diffMinutes <= 30) {
        statusClass = 'warning';
        statusText = 'Próximo do horário';
    }
    
    statusElement.innerHTML = `
        <span class="status-indicator ${statusClass}"></span>
        <span>${statusText}</span>
    `;
}

// Função para converter horário em minutos
function parseTime(timeString) {
    const [hours, minutes] = timeString.split(':').map(Number);
    return hours * 60 + minutes;
}

// Função para atualizar histórico de metas
function updateGoalsHistory() {
    const tbody = document.getElementById('goalsHistoryBody');
    if (!tbody) return;
    
    // Dados simulados do histórico
    const historyData = [
        {
            date: '2024-10-01',
            type: 'Passos Diários',
            oldValue: '8000',
            newValue: '10000',
            changedBy: 'Dr. Silva'
        },
        {
            date: '2024-09-28',
            type: 'Minutos de Exercício',
            oldValue: '20',
            newValue: '30',
            changedBy: 'Dr. Silva'
        },
        {
            date: '2024-09-25',
            type: 'Horas de Sono',
            oldValue: '7',
            newValue: '8',
            changedBy: 'Dr. Silva'
        }
    ];
    
    tbody.innerHTML = '';
    
    historyData.forEach(item => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${new Date(item.date).toLocaleDateString('pt-BR')}</td>
            <td>${item.type}</td>
            <td>${item.oldValue}</td>
            <td>${item.newValue}</td>
            <td>${item.changedBy}</td>
        `;
        tbody.appendChild(row);
    });
}

// Inicializar metas personalizadas quando a aba for ativada
document.addEventListener('DOMContentLoaded', function() {
    // Adicionar listener para mudança de abas
    document.querySelectorAll('.tab-link').forEach(tab => {
        tab.addEventListener('click', function() {
            if (this.dataset.tab === 'personalized-goals') {
                setTimeout(() => {
                    updatePersonalizedGoals();
                }, 100);
            }
            if (this.dataset.tab === 'weekly_analysis') {
                setTimeout(() => {
                    updateWeeklyAnalysis();
                }, 100);
            }
            if (this.dataset.tab === 'feedback_analysis') {
                setTimeout(() => {
                    updateFeedbackAnalysis();
                }, 100);
            }
        });
    });
});

// ===== ANÁLISE SEMANAL - JAVASCRIPT =====
// weeklyChart já declarado acima

function updateWeeklyAnalysis() {
    const metric = document.getElementById('weeklyMetric').value;
    const period = parseInt(document.getElementById('weeklyPeriod').value);
    
    // Simular dados (em produção, viria do servidor)
    const data = generateWeeklyAnalysisData(metric, period);
    
    updateWeeklySummaryCards(data);
    updateWeeklyChart(data, metric);
    updateWeeklyTable(data, metric);
}

function generateWeeklyAnalysisData(metric, period) {
    // Dados simulados - em produção, fazer requisição AJAX
    const data = {
        days: [],
        totalMeta: 0,
        totalConsumido: 0,
        adherence: 0,
        bestDay: '',
        worstDay: '',
        difference: 0
    };
    
    const today = new Date();
    const metricLabels = {
        calories: { unit: 'kcal', meta: 2200 },
        protein: { unit: 'g', meta: 120 },
        carbs: { unit: 'g', meta: 250 },
        fat: { unit: 'g', meta: 80 }
    };
    
    const currentMetric = metricLabels[metric];
    
    for (let i = period - 1; i >= 0; i--) {
        const date = new Date(today);
        date.setDate(date.getDate() - i);
        
        const meta = currentMetric.meta + (Math.random() - 0.5) * 200;
        const consumido = meta + (Math.random() - 0.5) * 400;
        const adherence = Math.min(100, Math.max(0, (consumido / meta) * 100));
        
        data.days.push({
            date: date.toISOString().split('T')[0],
            dateFormatted: date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }),
            meta: Math.round(meta),
            consumido: Math.round(consumido),
            difference: Math.round(consumido - meta),
            adherence: Math.round(adherence)
        });
        
        data.totalMeta += meta;
        data.totalConsumido += consumido;
    }
    
    data.difference = Math.round(data.totalConsumido - data.totalMeta);
    data.adherence = Math.round((data.totalConsumido / data.totalMeta) * 100);
    
    // Encontrar melhor e pior dia
    const adherenceValues = data.days.map(d => d.adherence);
    const bestIndex = adherenceValues.indexOf(Math.max(...adherenceValues));
    const worstIndex = adherenceValues.indexOf(Math.min(...adherenceValues));
    
    data.bestDay = data.days[bestIndex].dateFormatted;
    data.worstDay = data.days[worstIndex].dateFormatted;
    
    return data;
}

function updateWeeklySummaryCards(data) {
    document.getElementById('adherenceAverage').textContent = data.adherence + '%';
    document.getElementById('calorieDifference').textContent = 
        (data.difference >= 0 ? '+' : '') + data.difference + ' ' + getCurrentMetricUnit();
    document.getElementById('bestDay').textContent = data.bestDay;
    document.getElementById('worstDay').textContent = data.worstDay;
}

function updateWeeklyChart(data, metric) {
    const ctx = document.getElementById('weeklyComparisonChart').getContext('2d');
    
    if (weeklyChart) {
        weeklyChart.destroy();
    }
    
    const unit = getCurrentMetricUnit();
    
    weeklyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.days.map(d => d.dateFormatted),
            datasets: [{
                label: 'Meta do Plano',
                data: data.days.map(d => d.meta),
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1
            }, {
                label: 'Consumido Real',
                data: data.days.map(d => d.consumido),
                backgroundColor: 'rgba(255, 107, 0, 0.8)',
                borderColor: 'rgba(255, 107, 0, 1)',
                borderWidth: 1
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
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y + ' ' + unit;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: getCurrentMetricLabel() + ' (' + unit + ')'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Dias'
                    }
                }
            }
        }
    });
}

function updateWeeklyTable(data, metric) {
    const tbody = document.getElementById('weeklyAnalysisTable');
    tbody.innerHTML = '';
    
    data.days.forEach(day => {
        const row = document.createElement('tr');
        
        const statusClass = getAdherenceStatus(day.adherence);
        const statusText = getAdherenceText(day.adherence);
        
        row.innerHTML = `
            <td>${day.dateFormatted}</td>
            <td>${day.meta} ${getCurrentMetricUnit()}</td>
            <td>${day.consumido} ${getCurrentMetricUnit()}</td>
            <td>${day.difference >= 0 ? '+' : ''}${day.difference} ${getCurrentMetricUnit()}</td>
            <td>${day.adherence}%</td>
            <td><span class="status-badge ${statusClass}">${statusText}</span></td>
        `;
        
        tbody.appendChild(row);
    });
}

function getCurrentMetricUnit() {
    const metric = document.getElementById('weeklyMetric').value;
    const units = {
        calories: 'kcal',
        protein: 'g',
        carbs: 'g',
        fat: 'g'
    };
    return units[metric];
}

function getCurrentMetricLabel() {
    const metric = document.getElementById('weeklyMetric').value;
    const labels = {
        calories: 'Calorias',
        protein: 'Proteínas',
        carbs: 'Carboidratos',
        fat: 'Gorduras'
    };
    return labels[metric];
}

function getAdherenceStatus(adherence) {
    if (adherence >= 95) return 'excellent';
    if (adherence >= 85) return 'good';
    if (adherence >= 70) return 'warning';
    return 'critical';
}

function getAdherenceText(adherence) {
    if (adherence >= 95) return 'Excelente';
    if (adherence >= 85) return 'Bom';
    if (adherence >= 70) return 'Atenção';
    return 'Crítico';
}

// ===== ANÁLISE DE FEEDBACK - JAVASCRIPT =====
let feedbackCharts = {};

function updateFeedbackAnalysis() {
    const period = parseInt(document.getElementById('feedbackPeriod').value);
    
    // Simular dados (em produção, viria do servidor)
    const data = generateFeedbackData(period);
    
    updateFeedbackSummaryCards(data);
    updateFeedbackCharts(data);
    updateFeedbackTable(data);
}

function generateFeedbackData(period) {
    const data = {
        dietSatisfaction: 0,
        energyLevel: 0,
        sleepQuality: 0,
        hungerLevel: 0,
        history: []
    };
    
    const today = new Date();
    let totalSatisfaction = 0, totalEnergy = 0, totalSleep = 0, totalHunger = 0;
    let count = 0;
    
    for (let i = period - 1; i >= 0; i--) {
        const date = new Date(today);
        date.setDate(date.getDate() - i);
        
        // Simular dados com alguma variação
        const satisfaction = 3 + Math.random() * 2; // 3-5
        const energy = 3 + Math.random() * 2; // 3-5
        const sleep = 3 + Math.random() * 2; // 3-5
        const hunger = 2 + Math.random() * 2; // 2-4
        
        data.history.push({
            date: date.toISOString().split('T')[0],
            dateFormatted: date.toLocaleDateString('pt-BR'),
            satisfaction: Math.round(satisfaction * 10) / 10,
            energy: Math.round(energy * 10) / 10,
            sleep: Math.round(sleep * 10) / 10,
            hunger: Math.round(hunger * 10) / 10,
            observations: Math.random() > 0.7 ? 'Paciente relatou fome excessiva' : ''
        });
        
        totalSatisfaction += satisfaction;
        totalEnergy += energy;
        totalSleep += sleep;
        totalHunger += hunger;
        count++;
    }
    
    data.dietSatisfaction = Math.round((totalSatisfaction / count) * 10) / 10;
    data.energyLevel = Math.round((totalEnergy / count) * 10) / 10;
    data.sleepQuality = Math.round((totalSleep / count) * 10) / 10;
    data.hungerLevel = Math.round((totalHunger / count) * 10) / 10;
    
    return data;
}

function updateFeedbackSummaryCards(data) {
    document.getElementById('dietSatisfaction').textContent = data.dietSatisfaction + '/5';
    document.getElementById('energyLevel').textContent = data.energyLevel + '/5';
    document.getElementById('sleepQuality').textContent = data.sleepQuality + '/5';
    document.getElementById('hungerLevel').textContent = data.hungerLevel + '/5';
}

function updateFeedbackCharts(data) {
    const charts = ['dietSatisfaction', 'energyLevel', 'sleepQuality', 'hungerLevel'];
    
    charts.forEach(chartName => {
        const ctx = document.getElementById(chartName + 'Chart').getContext('2d');
        
        if (feedbackCharts[chartName]) {
            feedbackCharts[chartName].destroy();
        }
        
        const chartData = data.history.map(item => ({
            x: item.date,
            y: item[chartName]
        }));
        
        feedbackCharts[chartName] = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [{
                    label: getFeedbackLabel(chartName),
                    data: chartData,
                    borderColor: 'rgba(255, 107, 0, 1)',
                    backgroundColor: 'rgba(255, 107, 0, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day'
                        },
                        title: {
                            display: true,
                            text: 'Data'
                        }
                    },
                    y: {
                        min: 1,
                        max: 5,
                        title: {
                            display: true,
                            text: 'Nota (1-5)'
                        }
                    }
                }
            }
        });
    });
}

function updateFeedbackTable(data) {
    const tbody = document.getElementById('feedbackHistoryTable');
    tbody.innerHTML = '';
    
    data.history.slice(-10).reverse().forEach(item => {
        const row = document.createElement('tr');
        
        row.innerHTML = `
            <td>${item.dateFormatted}</td>
            <td>${item.satisfaction}/5</td>
            <td>${item.energy}/5</td>
            <td>${item.sleep}/5</td>
            <td>${item.hunger}/5</td>
            <td>${item.observations || '-'}</td>
        `;
        
        tbody.appendChild(row);
    });
}

function getFeedbackLabel(chartName) {
    const labels = {
        dietSatisfaction: 'Satisfação com Dieta',
        energyLevel: 'Nível de Energia',
        sleepQuality: 'Qualidade do Sono',
        hungerLevel: 'Nível de Fome'
    };
    return labels[chartName];
}
</script>

<!-- Modal de Visualização de Fotos -->
<div id="photoModal" class="photo-modal">
    <div class="photo-modal-content">
        <div class="photo-modal-header">
            <h3 id="photoModalTitle">Visualizar Foto</h3>
            <button class="photo-modal-close" onclick="closePhotoModal()">&times;</button>
        </div>
        <div class="photo-modal-body">
            <div class="photo-viewer">
                <button class="photo-nav-btn photo-prev" onclick="previousPhoto()" id="prevBtn" style="display: none;">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="photo-container">
                    <img id="photoModalImage" src="" alt="Foto de progresso">
                    <div class="photo-loading" id="photoLoading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Carregando...</span>
                    </div>
                </div>
                <button class="photo-nav-btn photo-next" onclick="nextPhoto()" id="nextBtn" style="display: none;">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="photo-info">
                <div class="photo-details">
                    <span id="photoModalLabel">-</span>
                    <span id="photoModalDate">-</span>
                </div>
                <div class="photo-counter">
                    <span id="photoCounter">1 / 1</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.photo-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    backdrop-filter: blur(5px);
}

.photo-modal-content {
    position: relative;
    margin: 1% auto;
    width: 95%;
    max-width: 1400px;
    height: 95%;
    background: var(--card-bg);
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.photo-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 30px;
    background: var(--primary-bg);
    border-bottom: 1px solid var(--border-color);
}

.photo-modal-header h3 {
    margin: 0;
    color: var(--primary-text-color);
    font-size: 1.5rem;
    font-weight: 600;
}

.photo-modal-close {
    background: none;
    border: none;
    font-size: 2rem;
    color: var(--secondary-text-color);
    cursor: pointer;
    padding: 5px;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.photo-modal-close:hover {
    background: var(--hover-bg);
    color: var(--primary-text-color);
}

.photo-modal-body {
    height: calc(100% - 80px);
    display: flex;
    flex-direction: column;
}

.photo-viewer {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    background: #000;
    padding: 40px;
    box-sizing: border-box;
    overflow: hidden;
}

.photo-container {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    max-width: calc(100% - 80px);
    max-height: calc(100% - 80px);
    overflow: hidden;
    border-radius: 15px;
}

.photo-container img {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
    border-radius: 15px;
    display: block;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    transition: transform 0.1s ease;
    cursor: crosshair;
    transform-origin: center center;
}

.photo-container img.zoom-active {
    transform: scale(2);
    cursor: grab;
}

.photo-container img.zoom-active:active {
    cursor: grabbing;
}

.photo-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: var(--secondary-text-color);
    text-align: center;
    display: none;
}

.photo-loading.show {
    display: block;
}

.photo-loading i {
    font-size: 2rem;
    margin-bottom: 10px;
    display: block;
}

.photo-nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.7);
    border: none;
    color: white;
    font-size: 1.5rem;
    padding: 15px 20px;
    cursor: pointer;
    border-radius: 50%;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    z-index: 10;
}

.photo-nav-btn:hover {
    background: rgba(0, 0, 0, 0.9);
    transform: translateY(-50%) scale(1.1);
}

.photo-prev {
    left: 20px;
}

.photo-next {
    right: 20px;
}

.photo-info {
    padding: 20px 30px;
    background: var(--card-bg);
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.photo-details {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.photo-details span:first-child {
    font-weight: 600;
    color: var(--primary-text-color);
    font-size: 1.1rem;
}

.photo-details span:last-child {
    color: var(--secondary-text-color);
    font-size: 0.9rem;
}

.photo-counter {
    color: var(--secondary-text-color);
    font-size: 0.9rem;
    font-weight: 500;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.section-header h4 {
    margin: 0;
}

.photo-item {
    cursor: pointer;
    transition: transform 0.3s ease;
}

.photo-item:hover {
    transform: scale(1.05);
}

/* Modal de Galeria Completa */
.gallery-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    backdrop-filter: blur(5px);
}

.gallery-modal-content {
    position: relative;
    margin: 2% auto;
    width: 95%;
    max-width: 1400px;
    height: 95%;
    background: var(--card-bg);
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.gallery-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 30px;
    background: var(--primary-bg);
    border-bottom: 1px solid var(--border-color);
}

.gallery-modal-header h3 {
    margin: 0;
    color: var(--primary-text-color);
    font-size: 1.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.gallery-modal-close {
    background: none;
    border: none;
    font-size: 2rem;
    color: var(--secondary-text-color);
    cursor: pointer;
    padding: 5px;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.gallery-modal-close:hover {
    background: var(--hover-bg);
    color: var(--primary-text-color);
}

.gallery-modal-body {
    height: calc(100% - 80px);
    overflow-y: auto;
    padding: 20px;
}

.gallery-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.gallery-session-group {
    background: var(--card-bg);
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid var(--border-color);
}

.gallery-session-header {
    background: var(--primary-bg);
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
}

.gallery-session-header h4 {
    margin: 0;
    color: var(--primary-text-color);
    font-size: 1.1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.gallery-session-card {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
}

.gallery-session-card:last-child {
    border-bottom: none;
}

.gallery-session-info {
    display: flex;
    gap: 15px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.gallery-session-time, .gallery-session-weight {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.9rem;
    color: var(--secondary-text-color);
    background: rgba(255, 255, 255, 0.05);
    padding: 6px 10px;
    border-radius: 6px;
}

.gallery-session-photos {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
}

.gallery-photo-item {
    position: relative;
    aspect-ratio: 1;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.gallery-photo-item:hover {
    transform: scale(1.02);
}

.gallery-photo-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.gallery-photo-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
    padding: 8px;
}

.gallery-photo-type {
    color: white;
    font-size: 0.8rem;
    font-weight: 500;
}
</style>

<!-- Modal de Galeria Completa -->
<div id="galleryModal" class="gallery-modal">
    <div class="gallery-modal-content">
        <div class="gallery-modal-header">
            <h3><i class="fas fa-images"></i> Galeria Completa de Fotos</h3>
            <button class="gallery-modal-close" onclick="closeGalleryModal()">&times;</button>
        </div>
        <div class="gallery-modal-body">
            <div class="gallery-container">
                <?php 
                // Agrupar fotos por data e sessão
                $grouped_photos = [];
                foreach($photo_history as $photo_set) {
                    $date_key = date('Y-m-d', strtotime($photo_set['date_recorded']));
                    $timestamp = !empty($photo_set['created_at']) ? strtotime($photo_set['created_at']) : false;
                    $time_key = $timestamp ? date('H:i', $timestamp) : date('H:i');
                    
                    if (!isset($grouped_photos[$date_key])) {
                        $grouped_photos[$date_key] = [];
                    }
                    
                    $session_key = $time_key;
                    if (!isset($grouped_photos[$date_key][$session_key])) {
                        $grouped_photos[$date_key][$session_key] = [
                            'date' => $photo_set['date_recorded'],
                            'time' => $time_key,
                            'weight' => $photo_set['weight_kg'] ?? null,
                            'photos' => []
                        ];
                    }
                    
                    // Adicionar fotos
                    $photo_types = ['photo_front' => 'Frente', 'photo_side' => 'Lado', 'photo_back' => 'Costas'];
                    foreach ($photo_types as $photo_key => $photo_label) {
                        if ($photo_set[$photo_key]) {
                            $grouped_photos[$date_key][$session_key]['photos'][] = [
                                'type' => $photo_key,
                                'label' => $photo_label,
                                'filename' => $photo_set[$photo_key]
                            ];
                        }
                    }
                }
                
                // Exibir sessões agrupadas (apenas as que têm fotos)
                foreach ($grouped_photos as $date_key => $sessions):
                    $date_display = date('d/m/Y', strtotime($date_key));
                    
                    // Filtrar sessões que têm fotos
                    $sessions_with_photos = array_filter($sessions, function($session) {
                        return !empty($session['photos']);
                    });
                    
                    // Só mostrar se há sessões com fotos
                    if (!empty($sessions_with_photos)):
                ?>
                    <div class="gallery-session-group">
                        <div class="gallery-session-header">
                            <h4><i class="fas fa-calendar-day"></i> <?php echo $date_display; ?></h4>
                        </div>
                        
                        <?php foreach ($sessions_with_photos as $time_key => $session): ?>
                            <div class="gallery-session-card">
                                <div class="gallery-session-info">
                                    <span class="gallery-session-time">
                                        <i class="fas fa-clock"></i> <?php echo $session['time']; ?>
                                    </span>
                                    <?php if ($session['weight']): ?>
                                        <span class="gallery-session-weight">
                                            <i class="fas fa-weight"></i> <?php echo number_format($session['weight'], 1); ?> kg
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="gallery-session-photos">
                                    <?php foreach ($session['photos'] as $photo): ?>
                                        <div class="gallery-photo-item" onclick="openPhotoModal('<?php echo BASE_APP_URL; ?>/uploads/measurements/<?php echo htmlspecialchars($photo['filename']); ?>', '<?php echo $photo['label']; ?>', '<?php echo $date_display . ' ' . $session['time']; ?>')">
                                            <img src="<?php echo BASE_APP_URL; ?>/uploads/measurements/<?php echo htmlspecialchars($photo['filename']); ?>" alt="<?php echo $photo['label']; ?>" onerror="this.style.display='none'">
                                            <div class="gallery-photo-overlay">
                                                <span class="gallery-photo-type"><?php echo $photo['label']; ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
        </div>
    </div>
</div>

<script>
let currentPhotoIndex = 0;
let allPhotos = [];

function openPhotoModal(imageSrc, label, date) {
    // Coletar todas as fotos disponíveis
    allPhotos = [];
    document.querySelectorAll('.photo-item img').forEach((img, index) => {
        if (img.src && !img.src.includes('data:image')) {
            allPhotos.push({
                src: img.src,
                label: img.closest('.photo-item').querySelector('.photo-date span:first-child').textContent,
                date: img.closest('.photo-item').querySelector('.photo-date span:last-child').textContent
            });
        }
    });
    
    // Encontrar o índice da foto clicada
    currentPhotoIndex = allPhotos.findIndex(photo => photo.src === imageSrc);
    
    if (currentPhotoIndex === -1) {
        currentPhotoIndex = 0;
    }
    
    // Mostrar modal
    document.getElementById('photoModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Carregar foto
    loadPhoto();
}

function closePhotoModal() {
    document.getElementById('photoModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function loadPhoto() {
    if (allPhotos.length === 0) return;
    
    const photo = allPhotos[currentPhotoIndex];
    const img = document.getElementById('photoModalImage');
    const loading = document.getElementById('photoLoading');
    const title = document.getElementById('photoModalTitle');
    const label = document.getElementById('photoModalLabel');
    const date = document.getElementById('photoModalDate');
    const counter = document.getElementById('photoCounter');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    
    // Resetar estilos da imagem anterior
    img.style.width = 'auto';
    img.style.height = 'auto';
    img.style.transform = 'none';
    
    // Mostrar loading
    loading.classList.add('show');
    img.style.display = 'none';
    
    // Atualizar informações
    title.textContent = 'Visualizar Foto';
    label.textContent = photo.label;
    date.textContent = photo.date;
    counter.textContent = `${currentPhotoIndex + 1} / ${allPhotos.length}`;
    
    // Mostrar/esconder botões de navegação
    prevBtn.style.display = allPhotos.length > 1 ? 'flex' : 'none';
    nextBtn.style.display = allPhotos.length > 1 ? 'flex' : 'none';
    
    // Carregar imagem
    img.onload = function() {
        loading.classList.remove('show');
        img.style.display = 'block';
        
        // Garantir que a imagem seja redimensionada corretamente
        const container = img.parentElement;
        const containerWidth = container.clientWidth;
        const containerHeight = container.clientHeight;
        
        // Calcular escala para caber completamente no container
        const scaleX = containerWidth / img.naturalWidth;
        const scaleY = containerHeight / img.naturalHeight;
        const scale = Math.min(scaleX, scaleY, 1);
        
        // Aplicar escala
        const scaledWidth = img.naturalWidth * scale;
        const scaledHeight = img.naturalHeight * scale;
        
        img.style.width = scaledWidth + 'px';
        img.style.height = scaledHeight + 'px';
        img.style.maxWidth = '100%';
        img.style.maxHeight = '100%';
        
        // Garantir que o zoom não saia do container
        img.style.transformOrigin = 'center center';
        
        // Adicionar eventos de zoom interativo
        addZoomInteraction(img);
    };
    
    img.onerror = function() {
        loading.classList.remove('show');
        img.style.display = 'block';
        img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjMzMzIi8+Cjx0ZXh0IHg9IjEwMCIgeT0iMTAwIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM2NjYiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5FcnJvIGFvIGNhcnJlZ2FyPC90ZXh0Pgo8L3N2Zz4=';
    };
    
    img.src = photo.src;
}

function previousPhoto() {
    if (allPhotos.length <= 1) return;
    currentPhotoIndex = (currentPhotoIndex - 1 + allPhotos.length) % allPhotos.length;
    loadPhoto();
}

function nextPhoto() {
    if (allPhotos.length <= 1) return;
    currentPhotoIndex = (currentPhotoIndex + 1) % allPhotos.length;
    loadPhoto();
}

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePhotoModal();
    } else if (e.key === 'ArrowLeft') {
        previousPhoto();
    } else if (e.key === 'ArrowRight') {
        nextPhoto();
    }
});

// Fechar modal clicando fora
document.getElementById('photoModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePhotoModal();
    }
});

// Função para zoom interativo tipo loja de roupas
function addZoomInteraction(img) {
    let isZoomed = false;
    let isDragging = false;
    let startX, startY, currentX, currentY;
    
    // Evento de mouse enter - ativar zoom
    img.addEventListener('mouseenter', function(e) {
        if (!isZoomed) {
            img.classList.add('zoom-active');
            isZoomed = true;
        }
    });
    
    // Evento de mouse leave - desativar zoom
    img.addEventListener('mouseleave', function(e) {
        if (isZoomed && !isDragging) {
            img.classList.remove('zoom-active');
            isZoomed = false;
            img.style.transform = 'scale(1)';
        }
    });
    
    // Evento de mouse move - controlar posição do zoom
    img.addEventListener('mousemove', function(e) {
        if (isZoomed) {
            const rect = img.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            // Calcular posição relativa (0 a 1)
            const relX = x / rect.width;
            const relY = y / rect.height;
            
            // Aplicar transform origin baseado na posição do mouse
            img.style.transformOrigin = `${relX * 100}% ${relY * 100}%`;
            img.style.transform = 'scale(2)';
        }
    });
    
    // Evento de mouse down - iniciar arrastar
    img.addEventListener('mousedown', function(e) {
        if (isZoomed) {
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            img.style.cursor = 'grabbing';
        }
    });
    
    // Evento de mouse up - parar de arrastar
    img.addEventListener('mouseup', function(e) {
        if (isDragging) {
            isDragging = false;
            img.style.cursor = 'grab';
        }
    });
    
    // Evento de mouse move durante arrastar
    img.addEventListener('mousemove', function(e) {
        if (isDragging && isZoomed) {
            currentX = e.clientX;
            currentY = e.clientY;
            
            const deltaX = currentX - startX;
            const deltaY = currentY - startY;
            
            // Aplicar movimento baseado no delta
            const rect = img.getBoundingClientRect();
            const relX = (e.clientX - rect.left) / rect.width;
            const relY = (e.clientY - rect.top) / rect.height;
            
            img.style.transformOrigin = `${relX * 100}% ${relY * 100}%`;
            img.style.transform = 'scale(2)';
        }
    });
    
    // Prevenir seleção de texto durante o zoom
    img.addEventListener('selectstart', function(e) {
        e.preventDefault();
    });
}

// Funções para o modal de galeria
function openGalleryModal() {
    const modal = document.getElementById('galleryModal');
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeGalleryModal() {
    const modal = document.getElementById('galleryModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Fechar modal de galeria com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeGalleryModal();
    }
});

// Fechar modal de galeria clicando fora
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('gallery-modal')) {
        closeGalleryModal();
    }
});

// ========== SISTEMA DE CALENDÁRIO DO DIÁRIO ==========
let currentCalendarDate = new Date();
const daysWithData = new Set();

       // Marcar dias com dados do meal_history (incluindo todos os meses)
       <?php
       // Buscar TODOS os dias com dados, não apenas do mês atual
       $stmt_all_dates = $conn->prepare("
           SELECT DISTINCT DATE(logged_at) as date 
           FROM sf_user_meal_log 
           WHERE user_id = ? 
           ORDER BY date DESC
       ");
       $stmt_all_dates->bind_param("i", $user_id);
       $stmt_all_dates->execute();
       $all_dates_result = $stmt_all_dates->get_result();
       $all_dates_with_data = [];
       while ($row = $all_dates_result->fetch_assoc()) {
           $all_dates_with_data[] = $row['date'];
       }
       $stmt_all_dates->close();
       echo "const allDatesWithData = " . json_encode($all_dates_with_data) . ";\n";
       ?>
       allDatesWithData.forEach(date => daysWithData.add(date));

function openDiaryCalendar() {
    currentCalendarDate = new Date();
    renderCalendar();
    document.body.style.overflow = 'hidden';
    document.getElementById('diaryCalendarModal').classList.add('active');
}

function closeDiaryCalendar() {
    document.getElementById('diaryCalendarModal').classList.remove('active');
    document.body.style.overflow = '';
}

function changeCalendarMonth(direction) {
    const newDate = new Date(currentCalendarDate);
    newDate.setMonth(newDate.getMonth() + direction);
    
    // Não permitir ir além do mês atual
    const now = new Date();
    if (newDate.getFullYear() > now.getFullYear() || 
        (newDate.getFullYear() === now.getFullYear() && newDate.getMonth() > now.getMonth())) {
        return; // Não avança
    }
    
    currentCalendarDate = newDate;
    renderCalendar();
}

       function renderCalendar() {
           const year = currentCalendarDate.getFullYear();
           const month = currentCalendarDate.getMonth();
           
           // Atualizar ano e mês separadamente
           const monthNamesShort = ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN',
                                   'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];
           document.querySelector('.calendar-year').textContent = year;
           document.querySelector('.calendar-month').textContent = monthNamesShort[month];
           
           // Verificar se estamos no mês atual para desabilitar setinha de próximo mês
           const today = new Date();
           const isCurrentMonth = (year === today.getFullYear() && month === today.getMonth());
           const nextMonthBtn = document.getElementById('nextMonthBtn');
           
           if (isCurrentMonth) {
               nextMonthBtn.classList.add('disabled');
               nextMonthBtn.disabled = true;
           } else {
               nextMonthBtn.classList.remove('disabled');
               nextMonthBtn.disabled = false;
           }
           
           // Primeiro e último dia do mês atual
           const firstDay = new Date(year, month, 1);
           const lastDay = new Date(year, month + 1, 0);
           const daysInMonth = lastDay.getDate();
           const startingDayOfWeek = firstDay.getDay();
           
           // Calcular dias do mês anterior para preencher
           const prevMonth = new Date(year, month - 1, 0);
           const daysInPrevMonth = prevMonth.getDate();
           
           // Grid de dias
           const grid = document.getElementById('calendarDaysGrid');
           grid.innerHTML = '';
           
           // Dias do mês anterior (bloqueados)
           for (let i = startingDayOfWeek - 1; i >= 0; i--) {
               const dayEl = document.createElement('div');
               dayEl.className = 'calendar-day other-month';
               dayEl.textContent = daysInPrevMonth - i;
               grid.appendChild(dayEl);
           }
           
           // Dias do mês atual
           for (let day = 1; day <= daysInMonth; day++) {
               const dayEl = document.createElement('button');
               dayEl.className = 'calendar-day current-month';
               dayEl.textContent = day;
               
               const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
               const today = new Date();
               const currentDate = new Date(year, month, day);
               
               // Verificar se é dia futuro
               if (currentDate > today) {
                   dayEl.classList.add('future-day');
                   dayEl.disabled = true;
               } else {
                   // Verificar se tem dados
                   if (daysWithData.has(dateStr)) {
                       dayEl.classList.add('has-data');
                   }
                   
                   // Marcar hoje
                   if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
                       dayEl.classList.add('today');
                   }
                   
                   // Click handler apenas para dias não futuros
                   dayEl.onclick = () => goToDiaryDate(dateStr);
               }
               
               grid.appendChild(dayEl);
           }
           
           // Calcular quantos dias faltam para completar a grade (6 semanas = 42 dias)
           const totalCells = 42;
           const usedCells = startingDayOfWeek + daysInMonth;
           const remainingCells = totalCells - usedCells;
           
           // Dias do próximo mês (bloqueados)
           for (let day = 1; day <= remainingCells; day++) {
               const dayEl = document.createElement('div');
               dayEl.className = 'calendar-day other-month';
               dayEl.textContent = day;
               grid.appendChild(dayEl);
           }
       }

function goToDiaryDate(dateStr) {
    // Encontrar o card correspondente
    const cards = document.querySelectorAll('.diary-day-card');
    let targetIndex = -1;
    
    cards.forEach((card, index) => {
        if (card.getAttribute('data-date') === dateStr) {
            targetIndex = index;
        }
    });
    
    if (targetIndex !== -1) {
        // Se o dia está nos cards carregados, navegar diretamente
        goToDiaryIndex(targetIndex);
        closeDiaryCalendar();
    } else {
        // Se o dia não estiver nos cards carregados, carregar via AJAX
        loadSpecificDate(dateStr);
        closeDiaryCalendar();
    }
}

async function loadSpecificDate(dateStr) {
    try {
        const userId = <?php echo $user_id; ?>;
        const url = `actions/load_diary_days.php?user_id=${userId}&end_date=${dateStr}&days=1`;
        
        console.log('Carregando data específica:', dateStr);
        
        const response = await fetch(url);
        if (response.ok) {
            const html = await response.text();
            
            if (html.trim().length > 0) {
                // Adicionar novo card
                const diaryTrack = document.getElementById('diarySliderTrack');
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                const newCards = tempDiv.querySelectorAll('.diary-day-card');
                
                if (newCards.length > 0) {
                    // Adicionar no início (mais antigo primeiro)
                    const fragment = document.createDocumentFragment();
                    while (tempDiv.firstChild) {
                        fragment.appendChild(tempDiv.firstChild);
                    }
                    diaryTrack.insertBefore(fragment, diaryTrack.firstChild);
                    
                    // Atualizar referência aos cards
                    updateDiaryCards();
                    
                    // Navegar para o dia carregado
                    currentDiaryIndex = 0;
                    updateDiaryDisplay();
                    
                    console.log('Data específica carregada com sucesso:', dateStr);
                }
            }
        } else {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
    } catch (error) {
        console.error('Erro ao carregar data específica:', error);
        alert('Erro ao carregar a data selecionada: ' + error.message);
    }
}
</script>

<!-- Modal Customizado para Reverter Metas -->
<div id="revertGoalsModal" class="custom-modal">
    <div class="custom-modal-overlay" onclick="closeRevertModal()"></div>
    <div class="custom-modal-content">
        <div class="custom-modal-header">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Reverter para Cálculo Automático?</h3>
        </div>
        <div class="custom-modal-body">
            <p>Tem certeza que deseja reverter para o cálculo automático?</p>
            <p class="modal-warning">As metas personalizadas serão removidas e o sistema voltará a calcular automaticamente com base nos dados do usuário.</p>
        </div>
        <div class="custom-modal-footer">
            <button class="btn-modal-cancel" onclick="closeRevertModal()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button class="btn-modal-confirm" onclick="confirmRevertGoals()">
                <i class="fas fa-check"></i> Confirmar
            </button>
        </div>
    </div>
</div>

<!-- Modal de Sucesso/Erro -->
<div id="alertModal" class="custom-modal">
    <div class="custom-modal-overlay" onclick="closeAlertModal()"></div>
    <div class="custom-modal-content custom-modal-small">
        <div class="custom-modal-header" id="alertModalHeader">
            <i id="alertModalIcon"></i>
            <h3 id="alertModalTitle"></h3>
        </div>
        <div class="custom-modal-body">
            <p id="alertModalMessage"></p>
        </div>
        <div class="custom-modal-footer">
            <button class="btn-modal-primary" onclick="closeAlertModal()">
                OK
            </button>
        </div>
    </div>
</div>

<!-- Modal de Detalhes do Sono -->
<div id="sleepDetailsModal" class="custom-modal">
    <div class="custom-modal-overlay" onclick="closeSleepDetailsModal()"></div>
    <div class="custom-modal-content custom-modal-small">
        <button class="sleep-modal-close" onclick="closeSleepDetailsModal()" type="button">
            <i class="fas fa-times"></i>
        </button>
        <div class="custom-modal-header">
            <i class="fas fa-bed"></i>
            <h3>Detalhes do Sono</h3>
        </div>
        <div class="custom-modal-body">
            <?php if (!empty($user_data['sleep_time_bed']) && !empty($user_data['sleep_time_wake'])): ?>
                <div class="sleep-details">
                    <div class="sleep-detail-item">
                        <i class="fas fa-moon"></i>
                        <div class="sleep-detail-content">
                            <label>Horário de Dormir</label>
                            <span><?php echo date('H:i', strtotime($user_data['sleep_time_bed'])); ?></span>
                        </div>
                    </div>
                    <div class="sleep-detail-item">
                        <i class="fas fa-sun"></i>
                        <div class="sleep-detail-content">
                            <label>Horário de Acordar</label>
                            <span><?php echo date('H:i', strtotime($user_data['sleep_time_wake'])); ?></span>
                        </div>
                    </div>
                    <div class="sleep-detail-item">
                        <i class="fas fa-clock"></i>
                        <div class="sleep-detail-content">
                            <label>Duração Total</label>
                            <span><?php 
                                $bed_time = new DateTime($user_data['sleep_time_bed']);
                                $wake_time = new DateTime($user_data['sleep_time_wake']);
                                if ($wake_time < $bed_time) { $wake_time->modify('+1 day'); }
                                $interval = $bed_time->diff($wake_time);
                                echo $interval->format('%H:%I');
                            ?></span>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p class="no-data">Nenhum horário de sono foi definido pelo usuário.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de Calendário do Diário - REDESIGN COMPLETO -->
<div id="diaryCalendarModal" class="custom-modal">
    <div class="custom-modal-overlay" onclick="closeDiaryCalendar()"></div>
    <div class="diary-calendar-wrapper">
        <button class="calendar-btn-close" onclick="closeDiaryCalendar()" type="button">
            <i class="fas fa-times"></i>
        </button>
        
               <div class="calendar-header-title">
                   <div class="calendar-year">2025</div>
               </div>
        
        <div class="calendar-nav-buttons">
            <button class="calendar-btn-nav" onclick="changeCalendarMonth(-1)" type="button">
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="calendar-month">OUT</div>
            <button class="calendar-btn-nav" id="nextMonthBtn" onclick="changeCalendarMonth(1)" type="button">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        
        <div class="calendar-weekdays-row">
            <span>DOM</span>
            <span>SEG</span>
            <span>TER</span>
            <span>QUA</span>
            <span>QUI</span>
            <span>SEX</span>
            <span>SÁB</span>
        </div>
        
        <div class="calendar-days-grid" id="calendarDaysGrid"></div>
        
        <div class="calendar-separator">
            <div class="separator-line"></div>
            <div class="separator-dots">
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            </div>
            <div class="separator-line"></div>
        </div>
        
               <div class="calendar-footer-legend">
                   <div class="legend-row">
                       <span class="legend-marker today-marker"></span>
                       <span class="legend-text">Hoje</span>
                   </div>
                   <div class="legend-row">
                       <span class="legend-marker has-data-marker"></span>
                       <span class="legend-text">Com registros</span>
                   </div>
                   <div class="legend-row">
                       <span class="legend-marker no-data-marker"></span>
                       <span class="legend-text">Sem registros</span>
                   </div>
               </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>