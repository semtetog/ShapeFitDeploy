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

// Função para gerar dados de hidratação dos últimos 7 dias (calendário real)
function getHydrationStats($conn, $userId, $water_goal_ml) {
    $hoje = new DateTime();
    
    function gerarPeriodoHidratacao($dias, $userId, $conn, $water_goal_ml) {
        $hoje = new DateTime();
        $datas = [];
        
        // 1. Gera o calendário dos últimos X dias (ANTES da query)
        for ($i = 0; $i < $dias; $i++) {
            $d = clone $hoje;
            $d->modify("-$i days");
            $dataStr = $d->format('Y-m-d');
            $datas[$dataStr] = [
                'ml' => 0,
                'cups' => 0,
                'percentage' => 0,
                'goal_reached' => false,
                'status' => 'empty',
                'status_text' => 'Sem dados',
                'status_class' => 'info'
            ];
        }
        
        $startDate = (clone $hoje)->modify("-" . ($dias - 1) . " days")->format('Y-m-d');
        $endDate = $hoje->format('Y-m-d');
        
        // 2. Busca os dados reais
        $stmt = $conn->prepare("
            SELECT 
                DATE(date) AS dia,
                SUM(water_consumed_cups) AS total_cups
            FROM sf_user_daily_tracking
            WHERE user_id = ? 
              AND DATE(date) BETWEEN ? AND ?
            GROUP BY DATE(date)
        ");
        $stmt->bind_param("iss", $userId, $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $daysWithData = 0;
        $excellentDays = 0;
        $goodDays = 0;
        
        // 3. Preenche no calendário os dias com registro
        while ($row = $result->fetch_assoc()) {
            $data = $row['dia'];
            $cups = (int)$row['total_cups'];
            $water_ml = $cups * 250; // 250ml por copo
    $raw_percentage = $water_goal_ml > 0 ? ($water_ml / $water_goal_ml) * 100 : 0;
            $percentage = min(round($raw_percentage, 1), 100);
            
            if (isset($datas[$data])) {
                $datas[$data]['ml'] = $water_ml;
                $datas[$data]['cups'] = $cups;
                $datas[$data]['percentage'] = $percentage;
                $datas[$data]['goal_reached'] = $water_ml >= $water_goal_ml;
                
                // Determinar status
    if ($percentage == 0) {
                    $datas[$data]['status'] = 'empty';
                    $datas[$data]['status_text'] = 'Sem dados';
                    $datas[$data]['status_class'] = 'info';
                } elseif ($percentage >= 100) {
                    $datas[$data]['status'] = 'excellent';
                    $datas[$data]['status_text'] = 'Meta atingida';
                    $datas[$data]['status_class'] = 'success';
    } elseif ($percentage >= 90) {
                    $datas[$data]['status'] = 'good';
                    $datas[$data]['status_text'] = 'Quase na meta';
                    $datas[$data]['status_class'] = 'info';
    } elseif ($percentage >= 70) {
                    $datas[$data]['status'] = 'fair';
                    $datas[$data]['status_text'] = 'Abaixo da meta';
                    $datas[$data]['status_class'] = 'warning';
    } elseif ($percentage >= 50) {
                    $datas[$data]['status'] = 'poor';
                    $datas[$data]['status_text'] = 'Muito abaixo';
                    $datas[$data]['status_class'] = 'warning';
    } else {
                    $datas[$data]['status'] = 'critical';
                    $datas[$data]['status_text'] = 'Crítico';
                    $datas[$data]['status_class'] = 'error';
                }
                
                if ($water_ml > 0) {
                    $daysWithData++;
                    if ($percentage >= 90) {
                        $excellentDays++;
                    } elseif ($percentage >= 70) {
                        $goodDays++;
                    }
                }
            }
        }
        
        // 4. Calcula médias fixas (sempre divide por todos os dias)
        $somaMl = array_sum(array_column($datas, 'ml'));
        $somaPercentage = array_sum(array_column($datas, 'percentage'));
        
        $mediaMl = $somaMl / $dias;
        $mediaPercentage = $somaPercentage / $dias;
        
        // 5. Média real (apenas dias com registro)
        $avgRealMl = $daysWithData > 0 ? round($somaMl / $daysWithData, 0) : 0;
        $avgRealPercentage = $daysWithData > 0 ? round($somaPercentage / $daysWithData, 1) : 0;
        
        // 6. Aderência (dias com registro / total de dias)
        $adherencePercentage = round(($daysWithData / $dias) * 100, 1);
        
        // 7. Preparar dados para o gráfico (ordem cronológica)
        $dailyData = [];
        foreach (array_reverse($datas, true) as $dia => $valores) {
            $dailyData[] = [
                'date' => $dia,
                'ml' => $valores['ml'],
                'cups' => $valores['cups'],
                'percentage' => $valores['percentage'],
                'goal_reached' => $valores['goal_reached'],
                'status' => $valores['status'],
                'status_text' => $valores['status_text'],
                'status_class' => $valores['status_class']
            ];
        }
        
        return [
            'avg_ml' => round($mediaMl),
            'avg_percentage' => round($mediaPercentage, 1),
            'avg_real_ml' => $avgRealMl,
            'avg_real_percentage' => $avgRealPercentage,
            'excellent_days' => $excellentDays,
            'good_days' => $goodDays,
            'days_with_consumption' => $daysWithData,
            'adherence_percentage' => $adherencePercentage,
            'total_days' => $dias,
            'daily_data' => $dailyData
        ];
    }
    
    // Calcula cada período isoladamente
    $semana = gerarPeriodoHidratacao(7, $userId, $conn, $water_goal_ml);
    $quinzena = gerarPeriodoHidratacao(15, $userId, $conn, $water_goal_ml);
    $mes = gerarPeriodoHidratacao(30, $userId, $conn, $water_goal_ml);
    
    return [
        'semana' => $semana,
        'quinzena' => $quinzena,
        'mes' => $mes
    ];
}

// Calcular estatísticas de hidratação para cada período
$hydration_stats_all = getHydrationStats($conn, $user_id, $water_goal_ml);
$hydration_stats_7 = $hydration_stats_all['semana'];
$hydration_stats_15 = $hydration_stats_all['quinzena'];
$hydration_stats_30 = $hydration_stats_all['mes'];

// Usar os dados da função para o gráfico
$hydration_data = $hydration_stats_7['daily_data'];

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

// Usar os dados da função getHydrationStats
$water_stats_7 = $hydration_stats_7;
$water_stats_15 = $hydration_stats_15;
$water_stats_30 = $hydration_stats_30;
$water_stats_90 = $hydration_stats_30; // Usar 30 dias como 90 por enquanto

// Calcular estatísticas para hoje e ontem baseado na data real
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$water_stats_today = calculateHydrationStatsByDate($hydration_data, $today);
$water_stats_yesterday = calculateHydrationStatsByDate($hydration_data, $yesterday);

// --- PROCESSAMENTO DE DADOS DE NUTRIENTES (LÓGICA DEFINITIVA) ---
// Função robusta que garante range completo de datas com LEFT JOIN

function getNutrientStats($conn, $userId, $macros_goal, $total_daily_calories_goal) {
    $hoje = new DateTime();

    function gerarPeriodo($dias, $userId, $conn, $macros_goal, $total_daily_calories_goal) {
        $hoje = new DateTime();
        $datas = [];

        // 1. Gera o calendário dos últimos X dias (ANTES da query)
        for ($i = 0; $i < $dias; $i++) {
            $d = clone $hoje;
            $d->modify("-$i days");
            $dataStr = $d->format('Y-m-d');
            $datas[$dataStr] = [
                'kcal' => 0,
                'protein' => 0,
                'carbs' => 0,
                'fat' => 0,
                'percent' => 0
            ];
        }

        $startDate = $hoje->modify("-" . ($dias - 1) . " days")->format('Y-m-d');
        $endDate = (new DateTime())->format('Y-m-d');

        // 2. Busca os dados reais
        $stmt = $conn->prepare("
    SELECT 
                DATE(date_consumed) AS dia,
                SUM(kcal_consumed) AS total_kcal,
                SUM(protein_consumed_g) AS total_protein,
                SUM(carbs_consumed_g) AS total_carbs,
                SUM(fat_consumed_g) AS total_fat
            FROM sf_user_meal_log
    WHERE user_id = ? 
              AND DATE(date_consumed) BETWEEN ? AND ?
            GROUP BY DATE(date_consumed)
        ");
        $stmt->bind_param("iss", $userId, $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();

        $daysWithData = 0;
        $excellentDays = 0;
        $goodDays = 0;

        // 3. Preenche no calendário os dias com registro
        while ($row = $result->fetch_assoc()) {
            $data = $row['dia'];
            $kcal = (int)$row['total_kcal'];
            $protein = (float)$row['total_protein'];
            $carbs = (float)$row['total_carbs'];
            $fat = (float)$row['total_fat'];
            
            if (isset($datas[$data])) {
                $datas[$data]['kcal'] = $kcal;
                $datas[$data]['protein'] = $protein;
                $datas[$data]['carbs'] = $carbs;
                $datas[$data]['fat'] = $fat;
                $datas[$data]['percent'] = $total_daily_calories_goal > 0 ? round(($kcal / $total_daily_calories_goal) * 100, 1) : 0;
                
                if ($kcal > 0) {
                    $daysWithData++;
                    
                    // Calcular percentual do dia para classificar qualidade
                    if ($datas[$data]['percent'] >= 90) {
                        $excellentDays++;
                    } elseif ($datas[$data]['percent'] >= 70) {
                        $goodDays++;
                    }
                }
            }
        }

        // 4. Calcula médias fixas (sempre divide por todos os dias)
        $somaKcal = array_sum(array_column($datas, 'kcal'));
        $somaProtein = array_sum(array_column($datas, 'protein'));
        $somaCarbs = array_sum(array_column($datas, 'carbs'));
        $somaFat = array_sum(array_column($datas, 'fat'));
        $somaPercent = array_sum(array_column($datas, 'percent'));

        $mediaKcal = $somaKcal / $dias;
        $mediaProtein = $somaProtein / $dias;
        $mediaCarbs = $somaCarbs / $dias;
        $mediaFat = $somaFat / $dias;
        $mediaPercent = $somaPercent / $dias;

        // 5. Média real (apenas dias com registro)
        $avgRealKcal = $daysWithData > 0 ? round($somaKcal / $daysWithData, 0) : 0;
        $avgRealProtein = $daysWithData > 0 ? round($somaProtein / $daysWithData, 1) : 0;
        $avgRealCarbs = $daysWithData > 0 ? round($somaCarbs / $daysWithData, 1) : 0;
        $avgRealFat = $daysWithData > 0 ? round($somaFat / $daysWithData, 1) : 0;

        // 6. Percentuais da meta (baseados na média ponderada)
        $avgKcalPercentage = $total_daily_calories_goal > 0 ? round(($mediaKcal / $total_daily_calories_goal) * 100, 1) : 0;
        $avgProteinPercentage = $macros_goal['protein_g'] > 0 ? round(($mediaProtein / $macros_goal['protein_g']) * 100, 1) : 0;
        $avgCarbsPercentage = $macros_goal['carbs_g'] > 0 ? round(($mediaCarbs / $macros_goal['carbs_g']) * 100, 1) : 0;
        $avgFatPercentage = $macros_goal['fat_g'] > 0 ? round(($mediaFat / $macros_goal['fat_g']) * 100, 1) : 0;
        
        // 7. Percentual geral da meta
        $avgOverallPercentage = round(($avgKcalPercentage + $avgProteinPercentage + $avgCarbsPercentage + $avgFatPercentage) / 4, 1);
        
        // 8. Aderência (dias com registro / total de dias)
        $adherencePercentage = round(($daysWithData / $dias) * 100, 1);

        // 9. Preparar dados para o gráfico (ordem cronológica)
        $dailyData = [];
        foreach (array_reverse($datas, true) as $dia => $valores) {
            $dailyData[] = [
                'date' => $dia,
                'kcal_consumed' => $valores['kcal'],
                'protein_consumed_g' => $valores['protein'],
                'carbs_consumed_g' => $valores['carbs'],
                'fat_consumed_g' => $valores['fat'],
                'avg_percentage' => $valores['percent']
            ];
        }

        return [
            // Médias ponderadas (para cards - mostram disciplina/constância)
            'avg_kcal' => round($mediaKcal),
            'avg_protein' => round($mediaProtein, 1),
            'avg_carbs' => round($mediaCarbs, 1),
            'avg_fat' => round($mediaFat, 1),
            'avg_kcal_percentage' => $avgKcalPercentage,
            'avg_protein_percentage' => $avgProteinPercentage,
            'avg_carbs_percentage' => $avgCarbsPercentage,
            'avg_fat_percentage' => $avgFatPercentage,
            'avg_overall_percentage' => $avgOverallPercentage,
            
            // Médias reais (para referência - mostram consumo quando registra)
            'avg_real_kcal' => $avgRealKcal,
            'avg_real_protein' => $avgRealProtein,
            'avg_real_carbs' => $avgRealCarbs,
            'avg_real_fat' => $avgRealFat,
            
            // Aderência e qualidade
            'excellent_days' => $excellentDays,
            'good_days' => $goodDays,
            'days_with_consumption' => $daysWithData,
            'adherence_percentage' => $adherencePercentage,
            'total_days' => $dias,
            'daily_data' => $dailyData
        ];
    }

    // Calcula cada período isoladamente
    $semana = gerarPeriodo(7, $userId, $conn, $macros_goal, $total_daily_calories_goal);
    $quinzena = gerarPeriodo(15, $userId, $conn, $macros_goal, $total_daily_calories_goal);
    $mes = gerarPeriodo(30, $userId, $conn, $macros_goal, $total_daily_calories_goal);

        return [
        'semana' => $semana,
        'quinzena' => $quinzena,
        'mes' => $mes
    ];
}

// Calcular estatísticas para cada período
$nutrients_stats_all = getNutrientStats($conn, $user_id, $macros_goal, $total_daily_calories_goal);
$nutrients_stats_7 = $nutrients_stats_all['semana'];
$nutrients_stats_15 = $nutrients_stats_all['quinzena'];
$nutrients_stats_30 = $nutrients_stats_all['mes'];

// Usar os dados da função getNutrientStats para o gráfico
$last_7_days_data = $nutrients_stats_7['daily_data'];

// Dados para hoje e ontem
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$stmt_today = $conn->prepare("
    SELECT 
        SUM(kcal_consumed) as kcal_consumed, 
        SUM(protein_consumed_g) as protein_consumed_g, 
        SUM(carbs_consumed_g) as carbs_consumed_g, 
        SUM(fat_consumed_g) as fat_consumed_g
    FROM sf_user_meal_log 
    WHERE user_id = ? AND DATE(date_consumed) = ?
");
$stmt_today->bind_param("is", $user_id, $today);
$stmt_today->execute();
$today_data = $stmt_today->get_result()->fetch_assoc();
$stmt_today->close();

$stmt_yesterday = $conn->prepare("
    SELECT 
        SUM(kcal_consumed) as kcal_consumed, 
        SUM(protein_consumed_g) as protein_consumed_g, 
        SUM(carbs_consumed_g) as carbs_consumed_g, 
        SUM(fat_consumed_g) as fat_consumed_g
    FROM sf_user_meal_log 
    WHERE user_id = ? AND DATE(date_consumed) = ?
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

// ===========================
// DADOS PARA ABA ROTINA (SIMPLES)
// ===========================

// Dados de passos dos últimos 30 dias
$routine_steps_data = [];
$stmt_routine_steps = $conn->prepare("
    SELECT date, steps_daily 
    FROM sf_user_daily_tracking 
    WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY date DESC
");
$stmt_routine_steps->bind_param("i", $user_id);
$stmt_routine_steps->execute();
$routine_steps_result = $stmt_routine_steps->get_result();
while ($row = $routine_steps_result->fetch_assoc()) {
    $routine_steps_data[] = $row;
}
$stmt_routine_steps->close();

// Dados de sono dos últimos 30 dias
$routine_sleep_data = [];
$stmt_routine_sleep = $conn->prepare("
    SELECT date, sleep_hours 
    FROM sf_user_daily_tracking 
    WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY date DESC
");
$stmt_routine_sleep->bind_param("i", $user_id);
$stmt_routine_sleep->execute();
$routine_sleep_result = $stmt_routine_sleep->get_result();
while ($row = $routine_sleep_result->fetch_assoc()) {
    $routine_sleep_data[] = $row;
}
$stmt_routine_sleep->close();

// Dados de exercícios (simples, sem JOIN)
$routine_exercise_data = [];
$stmt_routine_exercise = $conn->prepare("
    SELECT exercise_name, duration_minutes, updated_at
    FROM sf_user_exercise_durations 
    WHERE user_id = ? 
        AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY updated_at DESC
");
$stmt_routine_exercise->bind_param("i", $user_id);
$stmt_routine_exercise->execute();
$routine_exercise_result = $stmt_routine_exercise->get_result();
while ($row = $routine_exercise_result->fetch_assoc()) {
    $routine_exercise_data[] = $row;
}
$stmt_routine_exercise->close();

// Buscar dados de rotina (missões) dos últimos 30 dias com JOIN para pegar título e ícone
$routine_log_data = [];
$stmt_routine_log = $conn->prepare("
    SELECT rl.date, rl.routine_item_id, rl.is_completed, rl.exercise_duration_minutes,
           ri.title, ri.icon_class, ri.is_exercise
    FROM sf_user_routine_log rl
    LEFT JOIN sf_routine_items ri ON rl.routine_item_id = ri.id
    WHERE rl.user_id = ? AND rl.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY rl.date DESC
");
$stmt_routine_log->bind_param("i", $user_id);
$stmt_routine_log->execute();
$routine_log_result = $stmt_routine_log->get_result();
while ($row = $routine_log_result->fetch_assoc()) {
    $routine_log_data[] = $row;
}
$stmt_routine_log->close();

// Buscar missões disponíveis para o usuário (sf_routine_items)
$routine_items_data = [];
$stmt_routine_items = $conn->prepare("
    SELECT id, title, icon_class, description, is_exercise, exercise_type, default_for_all_users, user_id_creator
    FROM sf_routine_items 
    WHERE is_active = 1 AND (default_for_all_users = 1 OR user_id_creator = ?)
    ORDER BY id
");
$stmt_routine_items->bind_param("i", $user_id);
$stmt_routine_items->execute();
$routine_items_result = $stmt_routine_items->get_result();
while ($row = $routine_items_result->fetch_assoc()) {
    $routine_items_data[] = $row;
}
$stmt_routine_items->close();

// Buscar atividades do onboarding (sf_user_onboarding_completion)
$onboarding_activities = [];
$stmt_onboarding = $conn->prepare("
    SELECT id, activity_name, completion_date 
    FROM sf_user_onboarding_completion 
    WHERE user_id = ? 
    ORDER BY completion_date DESC
");
$stmt_onboarding->bind_param("i", $user_id);
$stmt_onboarding->execute();
$onboarding_result = $stmt_onboarding->get_result();
while ($row = $onboarding_result->fetch_assoc()) {
    $onboarding_activities[] = $row;
}
$stmt_onboarding->close();

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

/* Estilo específico para o ícone do card da rotina - gradiente laranja */
#tab-routine .routine-icon {
    background: linear-gradient(135deg, #ff6f00, #ff8a00) !important;
}

/* Estilos para o calendário da rotina */
.routine-calendar-section {
    /* Removido card de fundo desnecessário */
    margin-top: 25px;
}

/* Garantir que o slider da rotina funcione igual ao diário */
#tab-routine .diary-slider-container {
    width: 100%;
    max-width: 100%;
    margin: 0 auto;
    overflow: hidden !important;
    position: relative;
    contain: layout style paint;
    min-height: auto;
    height: auto;
    background: none;
    border: none;
    border-radius: 0;
    padding: 0;
}

#tab-routine .diary-slider-wrapper {
    width: 100%;
    max-width: 100%;
    overflow: hidden !important;
    position: relative;
    border-radius: 16px;
    contain: layout style;
    min-height: auto;
    height: auto;
}

#tab-routine .diary-slider-track {
    display: flex;
    transition: transform 0.3s ease-in-out;
    will-change: transform;
    width: 100%;
    max-width: 100%;
    min-height: auto;
    height: auto;
}

#tab-routine .diary-day-card {
    min-width: 100%;
    max-width: 100%;
    width: 100%;
    flex-shrink: 0;
    padding: 0;
    box-sizing: border-box;
    overflow: hidden;
    contain: layout style;
    min-height: auto;
    height: auto;
    display: block;
    background: none;
    border: none;
    border-radius: 0;
    margin-bottom: 0;
    transition: all 0.3s ease;
    cursor: pointer;
}

#tab-routine .diary-day-card:hover {
    border-color: rgba(255, 102, 0, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

#tab-routine .diary-day-card.selected {
    border-color: var(--accent-orange);
    box-shadow: 0 0 0 2px rgba(255, 102, 0, 0.2);
}

#tab-routine .diary-day-content {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    width: 100%;
    box-sizing: border-box;
}

#tab-routine .diary-day-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(255, 102, 0, 0.1);
    border: 1px solid rgba(255, 102, 0, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

#tab-routine .diary-day-icon i {
    font-size: 1.5rem;
    color: var(--accent-orange);
}

#tab-routine .diary-day-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

#tab-routine .diary-day-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

#tab-routine .diary-day-subtitle {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin: 0;
}


#tab-routine .diary-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
    background: var(--surface-color);
    border: 1px dashed rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    gap: 0.75rem;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    margin: 0;
    min-height: auto;
    height: auto;
}

#tab-routine .diary-empty-state i {
    font-size: 2.5rem;
    color: var(--text-secondary);
    opacity: 0.5;
}

#tab-routine .diary-empty-state p {
    font-size: 1rem;
    color: var(--text-secondary);
    margin: 0;
    text-align: center;
}

.calendar-header h4 {
    color: var(--primary-text-color);
    font-size: 1.2rem;
    font-weight: 600;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
}

.calendar-header .section-description {
    color: var(--secondary-text-color);
    font-size: 0.9rem;
    margin: 0 0 20px 0;
}

.routine-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
    margin-top: 20px;
}

.routine-calendar-day {
    aspect-ratio: 1;
    background: var(--glass-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.routine-calendar-day:hover {
    background: rgba(255, 111, 0, 0.1);
    border-color: var(--accent-orange);
    transform: translateY(-2px);
}

.routine-calendar-day.selected {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
    color: white !important;
}

.routine-calendar-day.has-data {
    border-color: var(--accent-orange);
}

.routine-calendar-day.has-data::after {
    content: '';
    position: absolute;
    bottom: 4px;
    width: 4px;
    height: 4px;
    background: var(--accent-orange);
    border-radius: 50%;
}

.routine-calendar-day.selected::after {
    background: white;
}

.routine-calendar-day-number {
    font-size: 1rem;
    font-weight: 600;
    color: var(--primary-text-color);
}

.routine-calendar-day.selected .routine-calendar-day-number {
    color: white;
}

.routine-calendar-day-name {
    font-size: 0.7rem;
    color: var(--secondary-text-color);
    text-transform: uppercase;
    margin-top: 2px;
}

.routine-calendar-day.selected .routine-calendar-day-name {
    color: rgba(255, 255, 255, 0.9);
}

/* Estilos para missões */
.missions-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.mission-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: var(--glass-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    transition: all 0.2s;
}

.mission-item:hover {
    background: rgba(255, 111, 0, 0.05);
}

.mission-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 111, 0, 0.1);
    color: var(--accent-orange);
    flex-shrink: 0;
}

.mission-info {
    flex: 1;
}

.mission-name {
    font-weight: 600;
    color: var(--primary-text-color);
    margin-bottom: 2px;
}

.mission-duration {
    font-size: 0.85rem;
    color: var(--secondary-text-color);
}

.mission-status {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.mission-status.completed {
    background: rgba(76, 175, 80, 0.2);
    color: #4caf50;
}

.mission-status.pending {
    background: rgba(158, 158, 158, 0.2);
    color: #9e9e9e;
}

/* Estilos para atividades físicas */
.activities-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: var(--glass-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 111, 0, 0.1);
    color: var(--accent-orange);
}

.activity-info {
    flex: 1;
}

.activity-name {
    font-weight: 600;
    color: var(--primary-text-color);
    margin-bottom: 2px;
}

.activity-duration {
    font-size: 0.85rem;
    color: var(--secondary-text-color);
}

/* Estilos para sono */
.sleep-info {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.sleep-stat {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
    background: var(--glass-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
}

.sleep-stat-label {
    color: var(--secondary-text-color);
    font-size: 0.9rem;
}

.sleep-stat-value {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--primary-text-color);
}

.sleep-progress {
    margin-top: 8px;
}

.sleep-progress-bar {
    height: 8px;
    background: var(--glass-bg);
    border-radius: 4px;
    overflow: hidden;
}

.sleep-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #ff6f00, #ff8a00);
    transition: width 0.3s ease;
}

.sleep-progress-text {
    font-size: 0.85rem;
    color: var(--secondary-text-color);
    margin-top: 4px;
    display: block;
}

/* Responsividade do calendário */
@media (max-width: 768px) {
    .routine-calendar-grid {
        gap: 4px;
    }
    
    .routine-calendar-day-number {
        font-size: 0.9rem;
    }
    
    .routine-calendar-day-name {
        font-size: 0.6rem;
    }
}

/* Estilos para modal de missões */
.mission-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}

.modal-content {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 30px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 25px;
}

.modal-header h3 {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--primary-text-color);
    margin: 0;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--primary-text-color);
    margin-bottom: 8px;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px 16px;
    background: var(--glass-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--primary-text-color);
    font-size: 1rem;
    font-family: inherit;
    transition: all 0.2s;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 111, 0, 0.05);
}

.modal-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 30px;
}

.btn-cancel,
.btn-save {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-cancel {
    background: var(--glass-bg);
    color: var(--secondary-text-color);
}

.btn-cancel:hover {
    background: rgba(255, 255, 255, 0.1);
}

.btn-save {
    background: linear-gradient(135deg, #ff6f00, #ff8a00);
    color: white;
}

.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255, 111, 0, 0.3);
}

/* Estilos para tabela de missões administrativas */
.missions-admin-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.missions-admin-table thead {
    background: var(--glass-bg);
}

.missions-admin-table th {
    padding: 12px 16px;
    text-align: left;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--secondary-text-color);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.missions-admin-table td {
    padding: 16px;
    border-top: 1px solid var(--border-color);
    color: var(--primary-text-color);
}

.missions-admin-table tbody tr {
    transition: background 0.2s;
}

.missions-admin-table tbody tr:hover {
    background: rgba(255, 111, 0, 0.05);
}

.mission-table-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 111, 0, 0.1);
    color: var(--accent-orange);
    border-radius: 50%;
}

.mission-type-badge {
    display: inline-block;
    padding: 4px 12px;
    background: var(--glass-bg);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    font-size: 0.85rem;
    color: var(--secondary-text-color);
}

.mission-table-actions {
    display: flex;
    gap: 8px;
}

.action-btn {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--glass-bg);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    color: var(--secondary-text-color);
}

.action-btn:hover {
    background: rgba(255, 111, 0, 0.1);
    border-color: var(--accent-orange);
    color: var(--accent-orange);
}

.action-btn.delete:hover {
    background: rgba(244, 67, 54, 0.1);
    border-color: #f44336;
    color: #f44336;
}

/* Estilos para botão adicionar missão */
.add-mission-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: linear-gradient(135deg, #ff6f00, #ff8a00);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.add-mission-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255, 111, 0, 0.3);
}

.add-mission-btn svg {
    width: 18px;
    height: 18px;
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
                    <label>Hidratação</label>
            </div>
                <span><?php echo ($water_intake_names[$user_data['water_intake_liters']] ?? 'N/I') . ($user_data['water_intake_liters'] ? ' por dia' : ''); ?></span>
        </div>
            <div class="data-item sleep-item" onclick="openSleepDetailsModal()">
                <div class="data-title">
                    <i class="fas fa-bed icon"></i>
                    <label>Sono</label>
                    <i class="fas fa-question-circle sleep-details-icon" title="Ver detalhes do sono"></i>
                </div>
                <span><?php echo $sleep_html; ?></span>
            </div>
        </div>
    </div>
</div>

<style>
/* Reduzir espaço entre título e subcards do Plano e Preferências */
.details-grid-1-col .dashboard-card h3 {
    margin-bottom: 5px !important;
    padding-bottom: 0 !important;
}

.details-grid-1-col .dashboard-card .physical-data-grid {
    margin-top: 5px !important;
    padding-top: 0 !important;
}

/* Reduzir espaço entre ícone/label e valor nos subcards */
.details-grid-1-col .dashboard-card .data-item .data-title {
    margin-bottom: 1px !important;
}

.details-grid-1-col .dashboard-card .data-item span {
    margin-top: 1px !important;
}
/* Reduzir altura do contorno dos subcards */
.details-grid-1-col .dashboard-card .data-item {
    padding: 8px 15px 2px 15px !important; /* top right bottom left - margem de baixo menor */
    min-height: auto !important;
    line-height: 1.2 !important;
}

/* Ajustar espaço entre título do card e subcards */
.details-grid-1-col .dashboard-card h3 {
    margin-bottom: 16px !important; /* diminuir distância entre título e subcards */
}

/* Diminuir distância entre títulos e subcards das seções Dados Físicos e Anamnese */
.details-grid-3-cols .dashboard-card h3 {
    margin-bottom: 24px !important; /* distância reduzida 5x para Dados Físicos e Anamnese */
}

/* Ajustar margin-top do physical-data-grid nas seções Dados Físicos e Anamnese */
.details-grid-3-cols .physical-data-grid {
    margin-top: 12px !important; /* subir menos os subcards para ficar com distância adequada */
}

/* Diminuir padding-bottom dos cards das seções Dados Físicos e Anamnese */
.details-grid-3-cols .dashboard-card {
    padding-bottom: 8px !important; /* reduzir espaço entre borda de baixo e subcards */
}

/* Descer ícone e label do topo */
.details-grid-1-col .dashboard-card .data-item .icon {
    margin-top: 4px !important;
}

.details-grid-1-col .dashboard-card .data-item .label {
    margin-top: 4px !important;
}

/* Ajustar padding interno dos cards da seção "Plano e Preferências" */
.details-grid-1-col .physical-data-grid .data-item {
    padding: 18px 18px 18px 18px !important; /* top right bottom left - padding aumentado 1,5x */
    min-height: auto !important;
    height: auto !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: flex-start !important; /* colar no topo */
    align-items: flex-start !important; /* colar na esquerda */
}

/* Ajustar padding do card principal "Plano e Preferências" */
.details-grid-1-col .dashboard-card {
    padding: 20px 24px 3px 24px !important; /* top right bottom left - padding aumentado e bottom reduzido */
}

/* Alinhar títulos dos 3 cards principais */
.details-grid-3-cols .dashboard-card h3 {
    margin-top: 0 !important; /* remover margem superior dos títulos */
    padding-top: 0 !important; /* remover padding superior dos títulos */
    line-height: 1.2 !important; /* altura de linha consistente */
}

/* Alinhar alturas dos 3 cards principais para ficarem nivelados */
.details-grid-3-cols {
    align-items: stretch !important; /* esticar cards para mesma altura */
}

.details-grid-3-cols .dashboard-card {
    height: 100% !important; /* altura total do container */
    padding-top: 20px !important; /* padding-top igual para todos os cards */
    padding-bottom: 20px !important; /* padding-bottom igual para todos os cards */
    display: flex !important; /* flex para controlar altura interna */
    flex-direction: column !important; /* direção vertical */
}

/* Ajustar posicionamento dos blocos de macros dentro do card */
.details-grid-3-cols .dashboard-card:first-child .meta-card-macros {
    margin-top: 8px !important; /* espaçamento consistente após o título */
}

/* Alinhar subcards com blocos de macronutrientes usando flexbox */
.details-grid-3-cols .dashboard-card .physical-data-grid,
.details-grid-3-cols .dashboard-card .habits-grid {
    margin-top: auto !important; /* empurrar subcards para baixo */
    margin-bottom: 0 !important; /* remover margem inferior */
}

/* Garantir que o conteúdo interno dos cards se distribua corretamente */
.details-grid-3-cols .dashboard-card > * {
    flex-shrink: 0 !important; /* não encolher elementos */
}

/* Estilos para a aba de Rotina */
.routine-container {
    padding: 20px;
}

.routine-summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.routine-chart-container {
    margin-bottom: 30px;
}

.routine-chart-container h4 {
    margin-bottom: 15px;
    color: var(--primary-text-color);
    font-size: 1.2rem;
    font-weight: 600;
}

.routine-chart-container canvas {
    max-height: 300px;
}

.routine-table-container h4 {
    margin-bottom: 15px;
    color: var(--primary-text-color);
    font-size: 1.2rem;
    font-weight: 600;
}

.routine-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card-bg);
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--border-color);
}

.routine-table th,
.routine-table td {
    padding: 12px 15px;
    text-align: center;
    border-bottom: 1px solid var(--border-color);
}

.routine-table th {
    background: var(--primary-bg);
    color: var(--primary-text-color);
    font-weight: 600;
    font-size: 0.9rem;
}

.routine-table td {
    color: var(--secondary-text-color);
    font-size: 0.9rem;
}

.routine-table tbody tr:hover {
    background: var(--hover-bg);
}

.text-success {
    color: #4caf50 !important;
}

.text-danger {
    color: #f44336 !important;
}

.badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.badge-success {
    background: rgba(76, 175, 80, 0.2);
    color: #4caf50;
}

.badge-warning {
    background: rgba(255, 152, 0, 0.2);
    color: #ff9800;
}

.badge-danger {
    background: rgba(244, 67, 54, 0.2);
    color: #f44336;
}

/* Estilos para a aba de Rotina - COPIA EXATA DAS OUTRAS ABAS COM CLASSES ESPECÍFICAS */
.routine-container {
    padding: 0;
}

/* ========== CARD DE GERENCIAMENTO DE MISSÕES ========== */
.routine-missions-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 24px;
    margin: 0 0 24px 0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    min-height: auto;
    height: auto;
}

.routine-missions-card:hover {
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border-color);
}

.card-title {
    display: flex;
    align-items: center;
    gap: 16px;
}

.title-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #ff6f00, #ff8a00);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    flex-shrink: 0;
}

.title-content h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-text-color);
    margin: 0 0 4px 0;
}

.title-content p {
    font-size: 0.9rem;
    color: var(--secondary-text-color);
    margin: 0;
}

.btn-add-mission {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: linear-gradient(135deg, #ff6f00, #ff8a00);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px rgba(255, 111, 0, 0.3);
}

.btn-add-mission:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(255, 111, 0, 0.4);
}

.btn-add-mission i {
    font-size: 14px;
}

.missions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
    margin: 0;
    padding: 0;
    min-height: auto;
    height: auto;
}

.mission-item {
    background: var(--glass-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.mission-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    border-color: rgba(255, 111, 0, 0.3);
}

.mission-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #ff6f00, #ff8a00);
}

.mission-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.mission-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: rgba(255, 111, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ff6f00;
    font-size: 18px;
    flex-shrink: 0;
}

.mission-info h4 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--primary-text-color);
    margin: 0 0 4px 0;
}

.mission-type {
    font-size: 0.8rem;
    color: var(--secondary-text-color);
    background: rgba(255, 111, 0, 0.1);
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-block;
}

.mission-actions {
    display: flex;
    gap: 8px;
    margin-top: 16px;
}

.btn-edit, .btn-delete {
    padding: 8px 12px;
    border: none;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-edit {
    background: rgba(255, 111, 0, 0.1);
    color: #ff6f00;
    border: 1px solid rgba(255, 111, 0, 0.2);
}

.btn-edit:hover {
    background: rgba(255, 111, 0, 0.2);
    transform: translateY(-1px);
}

.btn-delete {
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
    border: 1px solid rgba(220, 53, 69, 0.2);
}

.btn-delete:hover {
    background: rgba(220, 53, 69, 0.2);
    transform: translateY(-1px);
}

.loading-missions {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: var(--secondary-text-color);
    gap: 12px;
}

.loading-missions i {
    font-size: 24px;
    color: #ff6f00;
}

.empty-missions {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: var(--secondary-text-color);
    text-align: center;
}

.empty-missions i {
    font-size: 48px;
    color: #ff6f00;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-missions h4 {
    font-size: 1.2rem;
    margin: 0 0 8px 0;
    color: var(--primary-text-color);
}

.empty-missions p {
    margin: 0;
    font-size: 0.9rem;
}

/* ========== MODAL DE MISSÕES ========== */
#missionModal .modal-header {
    padding: 24px 24px 16px 24px;
    border-bottom: 1px solid var(--border-color);
}

#missionModal .modal-title {
    display: flex;
    align-items: center;
    gap: 16px;
}

#missionModal .title-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #ff6f00, #ff8a00);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    flex-shrink: 0;
}

#missionModal .title-content h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-text-color);
    margin: 0 0 4px 0;
}

#missionModal .title-content p {
    font-size: 0.9rem;
    color: var(--secondary-text-color);
    margin: 0;
}

#missionModal .modal-body {
    padding: 24px;
}

#missionModal .form-group {
    margin-bottom: 24px;
}

#missionModal .form-group label {
    display: block;
    font-weight: 600;
    color: var(--primary-text-color);
    margin-bottom: 8px;
    font-size: 0.9rem;
}

#missionModal .form-group input,
#missionModal .form-group select {
    width: 100%;
    padding: 12px 16px;
    background: var(--glass-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    color: var(--primary-text-color);
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

#missionModal .form-group input:focus,
#missionModal .form-group select:focus {
    outline: none;
    border-color: #ff6f00;
    box-shadow: 0 0 0 3px rgba(255, 111, 0, 0.1);
}

#missionModal .icon-picker {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(50px, 1fr));
    gap: 12px;
    margin-top: 12px;
    max-height: 200px;
    overflow-y: auto;
    padding: 8px;
    background: var(--glass-bg);
    border-radius: 10px;
    border: 1px solid var(--border-color);
}

.icon-option {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    border-radius: 10px;
    background: var(--card-bg);
    border: 2px solid transparent;
    cursor: pointer;
    transition: all 0.3s ease;
    color: #ff6f00;
    font-size: 20px;
}

.icon-option:hover {
    background: rgba(255, 111, 0, 0.1);
    border-color: rgba(255, 111, 0, 0.3);
    transform: scale(1.05);
}

.icon-option.selected {
    background: rgba(255, 111, 0, 0.2);
    border-color: #ff6f00;
    transform: scale(1.1);
}

#missionModal .form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 32px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

#missionModal .btn-cancel,
#missionModal .btn-save {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

#missionModal .btn-cancel {
    background: var(--glass-bg);
    color: var(--secondary-text-color);
    border: 1px solid var(--border-color);
}

#missionModal .btn-cancel:hover {
    background: var(--border-color);
    transform: translateY(-1px);
}

#missionModal .btn-save {
    background: linear-gradient(135deg, #ff6f00, #ff8a00);
    color: white;
    box-shadow: 0 2px 10px rgba(255, 111, 0, 0.3);
}

#missionModal .btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(255, 111, 0, 0.4);
}

#missionModal .btn-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Responsividade */
@media (max-width: 768px) {
    .card-header {
        flex-direction: column;
        gap: 16px;
        align-items: stretch;
    }
    
    .card-title {
        justify-content: center;
    }
    
    .missions-grid {
        grid-template-columns: 1fr;
    }
    
    .mission-actions {
        justify-content: center;
    }
    
    #missionModal .modal-title {
        flex-direction: column;
        text-align: center;
        gap: 12px;
    }
    
    #missionModal .form-actions {
        flex-direction: column;
    }
    
    #missionModal .btn-cancel,
    #missionModal .btn-save {
        width: 100%;
        justify-content: center;
    }
}

/* === CORREÇÃO FINAL DO ESPAÇO VAZIO DO DIÁRIO === */
.diary-day-card {
    height: auto !important;
    min-height: 0 !important;
    flex: 0 1 auto !important;
    display: block !important;
    padding-bottom: 0 !important;
}

/* Container interno */
.diary-day-meals {
    height: auto !important;
    min-height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
}

/* Placeholder */
.diary-empty-state {
    height: auto !important;
    padding: 20px 0 !important;
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
    flex-direction: column !important;
}


.diary-empty-state {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    height: auto !important;
    padding: 20px 0 !important;
}

/* Exibe apenas o card do dia atual */
.diary-day-card {
    display: none !important;
    width: 100% !important;
}

.diary-day-card.active {
    display: block !important;
    opacity: 1 !important;
    visibility: visible !important;
}

.routine-summary-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    border: 1px solid var(--border-color);
}

.routine-summary-main {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}

.routine-summary-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent-orange), #ff8a00);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    margin-right: 20px;
}

.routine-summary-info {
    flex: 1;
}

.routine-summary-info h3 {
    margin: 0 0 8px 0;
    color: var(--primary-text-color);
    font-size: 1.4rem;
    font-weight: 700;
}

.routine-summary-meta {
    color: var(--secondary-text-color);
    font-size: 0.9rem;
    margin-bottom: 5px;
}

.routine-summary-description {
    color: var(--secondary-text-color);
    font-size: 0.85rem;
    line-height: 1.4;
}

.routine-summary-status {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

.routine-status-good {
    background: rgba(76, 175, 80, 0.2);
    color: #4caf50;
}

.routine-summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
}

.routine-summary-stat {
    text-align: center;
    padding: 15px;
    background: var(--glass-bg);
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.routine-stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary-text-color);
    margin-bottom: 5px;
}

.routine-stat-label {
    color: var(--secondary-text-color);
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 3px;
}

.routine-stat-description {
    color: var(--secondary-text-color);
    font-size: 0.75rem;
}

/* Gráfico - COPIA EXATA DA HIDRATAÇÃO */
.routine-chart-section {
    margin-bottom: 25px;
}

.routine-chart-improved {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 25px;
    border: 1px solid var(--border-color);
}

.routine-chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.routine-chart-header h4 {
    margin: 0;
    color: var(--primary-text-color);
    font-size: 1.2rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.routine-chart-header h4 i {
    color: var(--accent-orange);
}

.routine-period-buttons {
    display: flex;
    gap: 8px;
}

.routine-period-btn {
    padding: 8px 16px;
    border: 1px solid var(--border-color);
    background: var(--card-bg);
    color: var(--secondary-text-color);
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.routine-period-btn:hover {
    background: var(--hover-bg);
    color: var(--primary-text-color);
}

.routine-period-btn.routine-active {
    background: var(--accent-orange);
    color: white;
    border-color: var(--accent-orange);
}

.routine-improved-chart {
    height: 200px;
    position: relative;
}

.routine-improved-bars {
    display: flex;
    align-items: end;
    justify-content: space-between;
    height: 100%;
    gap: 8px;
}

.routine-improved-bar-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
}

.routine-improved-bar-wrapper {
    position: relative;
    height: 160px;
    width: 100%;
    display: flex;
    align-items: end;
    justify-content: center;
}

.routine-improved-bar {
    width: 100%;
    border-radius: 4px 4px 0 0;
    transition: all 0.3s ease;
    position: relative;
}

.routine-improved-bar.routine-excellent { background: linear-gradient(to top, #4caf50, #66bb6a); }
.routine-improved-bar.routine-good { background: linear-gradient(to top, #8bc34a, #9ccc65); }
.routine-improved-bar.routine-fair { background: linear-gradient(to top, #ffc107, #ffca28); }
.routine-improved-bar.routine-poor { background: linear-gradient(to top, #ff9800, #ffb74d); }
.routine-improved-bar.routine-critical { background: linear-gradient(to top, #f44336, #ef5350); }
.routine-improved-bar.routine-empty { background: linear-gradient(to top, #e0e0e0, #f5f5f5); }

.routine-bar-percentage-text {
    position: absolute;
    top: -25px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--primary-text-color);
    white-space: nowrap;
}

.routine-improved-goal-line {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--accent-orange);
    border-radius: 1px;
}

.routine-improved-bar-info {
    margin-top: 10px;
    text-align: center;
}

.routine-improved-date {
    display: block;
    font-size: 0.75rem;
    color: var(--secondary-text-color);
    margin-bottom: 3px;
}

.routine-improved-ml {
    display: block;
    font-size: 0.7rem;
    color: var(--primary-text-color);
    font-weight: 600;
}

.routine-empty-chart {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--secondary-text-color);
}

.routine-empty-chart i {
    font-size: 3rem;
    margin-bottom: 10px;
    color: var(--accent-orange);
}

/* Médias de Períodos - COPIA EXATA */
.routine-periods-compact {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    border: 1px solid var(--border-color);
}

.routine-periods-compact h4 {
    margin: 0 0 10px 0;
    color: var(--primary-text-color);
    font-size: 1.2rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.routine-section-description {
    color: var(--secondary-text-color);
    font-size: 0.9rem;
    margin-bottom: 20px;
    line-height: 1.4;
}

.routine-periods-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.routine-period-item {
    background: var(--glass-bg);
    border-radius: 8px;
    padding: 20px;
    border: 1px solid var(--border-color);
    text-align: center;
}

.routine-period-label {
    display: block;
    color: var(--secondary-text-color);
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 8px;
}

.routine-period-value {
    display: block;
    color: var(--primary-text-color);
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 5px;
}

.routine-period-percentage {
    display: block;
    color: var(--accent-orange);
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 5px;
}

.routine-period-details {
    color: var(--secondary-text-color);
    font-size: 0.75rem;
}

/* Detalhamento - COPIA EXATA DOS MACROS */
.routine-details {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    border: 1px solid var(--border-color);
}

.routine-details h4 {
    margin: 0 0 10px 0;
    color: var(--primary-text-color);
    font-size: 1.2rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.routine-details h4 i {
    color: var(--accent-orange);
}

.routine-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.routine-detail-card {
    background: var(--glass-bg);
    border-radius: 8px;
    padding: 20px;
    border: 1px solid var(--border-color);
}

.routine-detail-header {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.routine-detail-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    margin-right: 15px;
}

.routine-detail-icon.routine-exercise {
    background: linear-gradient(135deg, var(--accent-orange), #ff8a00);
}

.routine-detail-icon.routine-sleep {
    background: linear-gradient(135deg, #9c27b0, #ba68c8);
}

.routine-detail-info h5 {
    margin: 0 0 5px 0;
    color: var(--primary-text-color);
    font-size: 1rem;
    font-weight: 600;
}

.routine-detail-info p {
    margin: 0;
    color: var(--secondary-text-color);
    font-size: 0.85rem;
}

.routine-detail-value {
    margin-bottom: 8px;
}

.routine-detail-value .routine-current {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-text-color);
}

.routine-detail-value .routine-target {
    color: var(--secondary-text-color);
    font-size: 0.9rem;
    margin-left: 5px;
}

.routine-detail-subtitle {
    color: var(--accent-orange);
    font-size: 0.85rem;
    font-weight: 500;
}

/* Estilos para os círculos de progresso da rotina */
.routine-progress-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.routine-circle-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 25px;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.routine-circle-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(255, 107, 0, 0.2);
}

.circle-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 25px;
}

.circle-card-header i {
    font-size: 1.5rem;
    color: var(--accent-orange);
}

.circle-card-header h5 {
    margin: 0;
    color: var(--primary-text-color);
    font-size: 1.1rem;
    font-weight: 600;
}

.circle-progress-container {
    position: relative;
    width: 200px;
    height: 200px;
    margin: 0 auto 20px;
}

.circle-progress {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.circle-bg {
    fill: none;
    stroke: rgba(255, 255, 255, 0.1);
    stroke-width: 12;
}

.circle-fill {
    fill: none;
    stroke: #4caf50;
    stroke-width: 12;
    stroke-linecap: round;
    stroke-dasharray: 534;
    transition: stroke-dashoffset 1s ease;
}

.circle-fill-orange {
    stroke: var(--accent-orange);
}

.circle-fill-purple {
    stroke: #9c27b0;
}

.circle-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.circle-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-text-color);
    line-height: 1;
    margin-bottom: 5px;
}

.circle-label {
    font-size: 0.85rem;
    color: var(--secondary-text-color);
    margin-bottom: 8px;
}

.circle-percentage {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--accent-orange);
}

.circle-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 15px;
    border-top: 1px solid var(--border-color);
}

.circle-card-footer span:first-child {
    font-size: 0.9rem;
    color: var(--secondary-text-color);
}

/* Responsividade */
@media (max-width: 768px) {
    .routine-summary-main {
        flex-direction: column;
        text-align: center;
    }
    
    .routine-summary-icon {
        margin-right: 0;
        margin-bottom: 15px;
    }
    
    .routine-summary-stats {
        grid-template-columns: 1fr;
    }
    
    .routine-periods-grid {
        grid-template-columns: 1fr;
    }
    
    .routine-details-grid {
        grid-template-columns: 1fr;
    }
    
    .routine-chart-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .routine-period-buttons {
        width: 100%;
        justify-content: center;
    }
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--accent-orange);
}

.card-header h3 {
    margin: 0;
    color: var(--primary-text-color);
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
}

.card-header h3 i {
    color: var(--accent-orange);
    font-size: 1.3rem;
}

.period-buttons {
    display: flex;
    gap: 6px;
    background: var(--glass-bg);
    padding: 4px;
    border-radius: 25px;
    border: 1px solid var(--border-color);
}
.period-btn {
    padding: 10px 20px;
    border: none;
    background: transparent;
    color: var(--secondary-text-color);
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.period-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--primary-text-color);
}

.period-btn.active {
    background: var(--accent-orange);
    color: white;
    box-shadow: 0 4px 15px rgba(255, 152, 0, 0.3);
}

.routine-main-content {
    display: flex;
    flex-direction: column;
    gap: 35px;
}

/* Grid de Métricas Principais */
.routine-metrics-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 25px;
    margin-bottom: 10px;
}

.metric-card {
    background: var(--glass-bg);
    border-radius: 16px;
    padding: 25px;
    border: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.metric-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--accent-orange);
    border-radius: 16px 16px 0 0;
}

.metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.metric-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent-orange), #ff8a00);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    box-shadow: 0 4px 15px rgba(255, 152, 0, 0.3);
}

.metric-content {
    flex: 1;
}

.metric-value {
    font-size: 2.2rem;
    font-weight: 800;
    color: var(--primary-text-color);
    line-height: 1;
    margin-bottom: 5px;
}

.metric-label {
    font-size: 0.9rem;
    color: var(--secondary-text-color);
    font-weight: 600;
    margin-bottom: 8px;
}

.metric-subtitle {
    font-size: 0.8rem;
    color: var(--accent-orange);
    font-weight: 500;
}

.metric-progress {
    margin-top: 10px;
}

.progress-bar {
    width: 100%;
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 5px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--accent-orange), #ff8a00);
    border-radius: 3px;
    transition: width 0.5s ease;
}

.progress-text {
    font-size: 0.75rem;
    color: var(--secondary-text-color);
    font-weight: 500;
}

/* Grid de Gráficos */
.routine-charts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 25px;
}

.chart-container {
    background: var(--glass-bg);
    border-radius: 16px;
    padding: 25px;
    border: 1px solid var(--border-color);
}

.chart-container h4 {
    margin: 0 0 20px 0;
    color: var(--primary-text-color);
    font-size: 1.1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.chart-container h4 i {
    color: var(--accent-orange);
    font-size: 1rem;
}

.chart-container canvas {
    width: 100% !important;
    height: 200px !important;
}

/* Seção de Exercícios Recentes */
.exercise-recent-section {
    background: var(--glass-bg);
    border-radius: 16px;
    padding: 25px;
    border: 1px solid var(--border-color);
}

.exercise-recent-section h4 {
    margin: 0 0 20px 0;
    color: var(--primary-text-color);
    font-size: 1.2rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.exercise-recent-section h4 i {
    color: var(--accent-orange);
    font-size: 1.1rem;
}

.exercise-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
}

.exercise-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px;
    background: var(--card-bg);
    border-radius: 12px;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.exercise-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    border-color: var(--accent-orange);
}

.exercise-info {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.exercise-name {
    color: var(--primary-text-color);
    font-weight: 600;
    font-size: 1rem;
}

.exercise-date {
    color: var(--secondary-text-color);
    font-size: 0.85rem;
}

.exercise-duration {
    color: var(--accent-orange);
    font-weight: 700;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 6px;
}

.empty-state {
    text-align: center;
    color: var(--secondary-text-color);
    padding: 40px;
    grid-column: 1 / -1;
}

.empty-state i {
    font-size: 3rem;
    color: var(--accent-orange);
    margin-bottom: 15px;
    display: block;
}

.empty-state p {
    margin: 0;
    font-size: 1rem;
    font-style: italic;
}

/* Responsividade */
@media (max-width: 1200px) {
    .routine-metrics-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .routine-charts-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 768px) {
    .routine-container {
        padding: 20px;
    }
    
    .card-header {
        flex-direction: column;
        gap: 20px;
        align-items: flex-start;
    }
    
    .period-buttons {
        width: 100%;
        justify-content: center;
    }
    
    .routine-metrics-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .routine-charts-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .exercise-list {
        grid-template-columns: 1fr;
    }
    
    .metric-card {
        padding: 20px;
    }
    
    .metric-value {
        font-size: 1.8rem;
    }
}

/* Descer posicionamento dos subcards dentro do card pai */
.details-grid-1-col .physical-data-grid {
    margin-top: 24px !important; /* descer ainda mais os subcards */
}

.details-grid-1-col .physical-data-grid .data-item .data-title {
    display: flex !important;
    align-items: center !important;
    margin-bottom: 12px !important; /* aumentar mais um pouco o espaço entre ícone/label e texto */
    gap: 6px !important;
}

.details-grid-1-col .physical-data-grid .data-item .data-title .icon {
    margin-right: 0 !important;
    margin-top: 0 !important;
    flex-shrink: 0 !important;
}

.details-grid-1-col .physical-data-grid .data-item .data-title .label {
    margin-top: 0 !important;
    margin-left: 0 !important;
    font-weight: 500 !important;
}

.details-grid-1-col .physical-data-grid .data-item span {
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
    text-align: left !important;
}
</style>

<div class="details-grid-1-col" style="margin-top:24px;">
    <div class="dashboard-card">
        <h3>Plano e Preferências</h3>
         <div class="physical-data-grid">
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
        <div class="tab-link" data-tab="routine">
            <i class="fas fa-tasks"></i>
            <span>Rotina</span>
        </div>
    </div>
    <div class="tabs-row">
        <div class="tab-link" data-tab="feedback_analysis">
            <i class="fas fa-comments"></i>
            <span>Feedback</span>
        </div>
        <div class="tab-link" data-tab="personalized_goals">
            <i class="fas fa-bullseye"></i>
            <span>Metas</span>
        </div>
    </div>
</div>

<?php include 'view_user_diary.php'; ?>


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

<?php include 'view_user_hydration.php'; ?>

<script>

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

<?php include 'view_user_nutrients.php'; ?>

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
    'all': <?php echo json_encode($water_stats_all ?? []); ?>
};

const nutrientsData = <?php echo json_encode($last_7_days_data); ?>;
const nutrientsStats = {
    'today': <?php echo json_encode($nutrients_stats_today); ?>,
    'yesterday': <?php echo json_encode($nutrients_stats_yesterday); ?>,
    '7': <?php echo json_encode($nutrients_stats_7); ?>,
    '15': <?php echo json_encode($nutrients_stats_15); ?>,
    '30': <?php echo json_encode($nutrients_stats_30); ?>,
    '90': <?php echo json_encode($nutrients_stats_90 ?? []); ?>,
    'all': <?php echo json_encode($nutrients_stats_all ?? []); ?>
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
                displayData = hydrationData.filter(day => {
                    return day.date === today;
                });
            } else if (period === 'yesterday') {
                // Filtrar apenas dados de ontem - usar a data do servidor
                const yesterday = '<?php echo $yesterday; ?>'; // Data do servidor
                displayData = hydrationData.filter(day => {
                    return day.date === yesterday;
                });
            } else {
                displayData = hydrationData.slice(0, daysToShow);
            }
            
            improvedBars.innerHTML = displayData.map(day => {
                // Para hidratação, limitar a 100% (como já está)
                const limitedPercentage = Math.min(day.percentage, 100);
                
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

<?php include 'view_user_progress.php'; ?>

<?php include 'view_user_routine.php'; ?>























</script>

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>
