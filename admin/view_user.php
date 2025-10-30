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
<script>
// Fallbacks imediatos: garantem que os handlers inline existam
(function(){
    if (typeof window.showRevertModal !== 'function') {
        window.showRevertModal = function(userId){
            try {
                window.currentUserIdToRevert = userId;
                document.body.style.overflow = 'hidden';
                var m = document.getElementById('revertGoalsModal');
                if (m) m.classList.add('active');
            } catch(e) { console.error(e); }
        };
    }
    if (typeof window.closeRevertModal !== 'function') {
        window.closeRevertModal = function(){
            try {
                var m = document.getElementById('revertGoalsModal');
                if (m) m.classList.remove('active');
                document.body.style.overflow = '';
                window.currentUserIdToRevert = null;
            } catch(e) { console.error(e); }
        };
    }
    if (typeof window.openSleepDetailsModal !== 'function') {
        window.openSleepDetailsModal = function(){
            try {
                document.body.style.overflow = 'hidden';
                var m = document.getElementById('sleepDetailsModal');
                if (m) m.classList.add('active');
            } catch(e) { console.error(e); }
        };
    }
    if (typeof window.closeSleepDetailsModal !== 'function') {
        window.closeSleepDetailsModal = function(){
            try {
                var m = document.getElementById('sleepDetailsModal');
                if (m) m.classList.remove('active');
                document.body.style.overflow = '';
            } catch(e) { console.error(e); }
        };
    }
    // Fallback simples de abas
    document.addEventListener('DOMContentLoaded', function(){
        var tabLinks = document.querySelectorAll('.tab-link');
        var tabContents = document.querySelectorAll('.tab-content');
        if (tabLinks.length) {
            tabLinks.forEach(function(link){
                link.addEventListener('click', function(){
                    var tabId = this.getAttribute('data-tab');
                    tabLinks.forEach(function(l){ l.classList.remove('active'); });
                    tabContents.forEach(function(c){ c.classList.remove('active'); });
                    this.classList.add('active');
                    var target = document.getElementById('tab-' + tabId);
                    if (target) target.classList.add('active');
                });
            });
        }
    });
})();
</script>
<style>
/* Deploy check badge */
/* .deploy-check removido */
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

<!-- Forçar exibição de modais quando .active (garante visibilidade mesmo sem CSS externo) -->
<style>
.custom-modal { display: none; }
.custom-modal.active { display: flex !important; }
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

<!-- CONTEÚDO DAS ABAS - SERÁ PREENCHIDO VIA INCLUDES -->
<?php include __DIR__ . '/view_user_diary.php'; ?>

<div id="tab-hydration" class="tab-content">
    <!-- Conteúdo da aba Hidratação será inserido via include -->
</div>

<div id="tab-nutrients" class="tab-content">
    <!-- Conteúdo da aba Nutrientes será inserido via include -->
</div>

<div id="tab-progress" class="tab-content">
    <!-- Conteúdo da aba Progresso será inserido via include -->
</div>

<div id="tab-routine" class="tab-content">
    <!-- Conteúdo da aba Rotina será inserido via include -->
</div>

<div id="tab-feedback_analysis" class="tab-content">
    <!-- Placeholder para aba de Feedback - será implementada posteriormente -->
    <div class="placeholder-content">
        <div class="placeholder-icon">
            <i class="fas fa-comments"></i>
        </div>
        <h3>Análise de Feedback</h3>
        <p>Esta funcionalidade será implementada em breve.</p>
    </div>
</div>

<div id="tab-personalized_goals" class="tab-content">
    <!-- Placeholder para aba de Metas - será implementada posteriormente -->
    <div class="placeholder-content">
        <div class="placeholder-icon">
            <i class="fas fa-bullseye"></i>
        </div>
        <h3>Metas Personalizadas</h3>
        <p>Esta funcionalidade será implementada em breve.</p>
    </div>
</div>

<style>
/* Estilos para placeholders */
.placeholder-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    text-align: center;
    color: var(--secondary-text-color);
}

.placeholder-icon {
    font-size: 4rem;
    color: var(--accent-orange);
    margin-bottom: 20px;
    opacity: 0.7;
}

.placeholder-content h3 {
    margin: 0 0 10px 0;
    color: var(--primary-text-color);
    font-size: 1.5rem;
    font-weight: 600;
}

.placeholder-content p {
    margin: 0;
    font-size: 1rem;
    opacity: 0.8;
}
</style>

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
                </div>
            <?php else: ?>
                <p>Informações de sono não informadas.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Sistema de modais customizados para reverter metas (idêntico ao referência)
let currentUserIdToRevert = null;

function showRevertModal(userId) {
    console.log('[showRevertModal] userId=', userId);
    currentUserIdToRevert = userId;
    document.body.style.overflow = 'hidden';
    var m = document.getElementById('revertGoalsModal');
    if (m) { m.classList.add('active'); m.style.display = 'flex'; }
    else { alert('Modal de confirmação não encontrado (#revertGoalsModal)'); }
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
    if (modal.dataset.reloadOnClose === 'true') {
        location.reload();
    }
}

function openSleepDetailsModal() {
    console.log('[openSleepDetailsModal]');
    document.body.style.overflow = 'hidden';
    var m = document.getElementById('sleepDetailsModal');
    if (m) { m.classList.add('active'); m.style.display = 'flex'; }
    else { alert('Modal de sono não encontrado (#sleepDetailsModal)'); }
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
    const userIdToRevert = currentUserIdToRevert;
    closeRevertModal();
    try {
        const formData = new FormData();
        formData.append('user_id', String(userIdToRevert));
        const response = await fetch('<?php echo BASE_ADMIN_URL; ?>/actions/revert_to_auto_goals.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
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
</script>

<script>
// Expor funções usadas por handlers inline
window.showRevertModal = showRevertModal;
window.closeRevertModal = closeRevertModal;
window.showAlertModal = showAlertModal;
window.closeAlertModal = closeAlertModal;
window.openSleepDetailsModal = openSleepDetailsModal;
window.closeSleepDetailsModal = closeSleepDetailsModal;

// Fallback de tabs: garante troca de abas mesmo se o JS externo falhar
document.addEventListener('DOMContentLoaded', function(){
    const tabLinks = document.querySelectorAll('.tab-link');
    const tabContents = document.querySelectorAll('.tab-content');
    tabLinks.forEach(link => {
        link.addEventListener('click', function(){
            const tabId = this.getAttribute('data-tab');
            tabLinks.forEach(l => l.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            const target = document.getElementById(`tab-${tabId}`);
            if (target) target.classList.add('active');
        });
    });
});
</script>

<script>
// Sistema de edição inline para metas (idêntico ao referência)
document.addEventListener('DOMContentLoaded', function() {
    const editableValues = document.querySelectorAll('.editable-value');
    editableValues.forEach(element => {
        element.addEventListener('click', function() {
            if (this.querySelector('input')) return;
            const field = this.dataset.field;
            const userId = this.dataset.userId;
            const currentValue = this.dataset.original;
            const fullText = this.textContent;
            const suffix = fullText.replace(currentValue, '').trim();
            const originalStyles = {
                fontSize: window.getComputedStyle(this).fontSize,
                fontWeight: window.getComputedStyle(this).fontWeight,
                color: window.getComputedStyle(this).color,
                textAlign: window.getComputedStyle(this).textAlign
            };
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
            this.textContent = '';
            this.appendChild(input);
            input.focus();
            input.select();

            const saveValue = async () => {
                const newValue = input.value;
                if (!newValue || newValue === currentValue) {
                    cancelEdit();
                    return;
                }
                try {
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
                    formData.append('water_ml', <?php echo $water_goal_ml; ?>);
                    const response = await fetch('<?php echo BASE_ADMIN_URL; ?>/actions/update_user_goals.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData
                    });
                    const responseText = await response.text();
                    let result;
                    try {
                        result = JSON.parse(responseText);
                    } catch (e) {
                        console.error('Erro ao fazer parse do JSON:', e, responseText);
                        alert('Erro: Resposta inválida do servidor');
                        cancelEdit();
                        return;
                    }
                    if (result.success) {
                        element.dataset.original = newValue;
                        element.textContent = newValue + suffix;
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

            const cancelEdit = () => {
                element.textContent = currentValue + suffix;
            };

            input.addEventListener('blur', saveValue);
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); saveValue(); }
                else if (e.key === 'Escape') { cancelEdit(); }
            });
        });

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

// Animação pulse usada no feedback visual
(function(){
  const style = document.createElement('style');
  style.textContent = `@keyframes pulse { 0%,100%{transform:scale(1);} 50%{transform:scale(1.05); color: var(--accent-orange);} }`;
  document.head.appendChild(style);
})();
</script>

<script>
// Delegação de eventos resiliente p/ edição inline (garante funcionamento mesmo se outro script falhar)
(function(){
    function startInlineEdit(element){
        console.log('[inline-edit] click on', element?.dataset?.field, element?.dataset);
        if (element.querySelector('input')) return;
        const field = element.dataset.field;
        const userId = element.dataset.userId;
        const currentValue = element.dataset.original;
        const fullText = element.textContent;
        const suffix = fullText.replace(currentValue, '').trim();
        const cs = window.getComputedStyle(element);
        const input = document.createElement('input');
        input.type = 'number';
        input.value = currentValue;
        input.style.cssText = `background: rgba(255,255,255,.08); border:2px solid var(--accent-orange); border-radius:8px; padding:.25rem .5rem; color:${cs.color}; font-size:${cs.fontSize}; font-weight:${cs.fontWeight}; text-align:${cs.textAlign}; width:100%; max-width:150px; outline:none; font-family:'Montserrat',sans-serif;`;
        element.textContent = '';
        element.appendChild(input);
        input.focus();
        input.select();

        const cancelEdit = () => { element.textContent = currentValue + (suffix? ' ' + suffix : ''); };

        const saveValue = async () => {
            const newValue = input.value;
            if (!newValue || newValue === currentValue) { cancelEdit(); return; }
            try {
                const caloriesEl = document.querySelector('[data-field="daily_calories"]');
                const proteinEl = document.querySelector('[data-field="protein_g"]');
                const carbsEl = document.querySelector('[data-field="carbs_g"]');
                const fatEl = document.querySelector('[data-field="fat_g"]');
                const formData = new FormData();
                formData.append('user_id', userId);
                formData.append('daily_calories', field === 'daily_calories' ? newValue : (caloriesEl?.dataset.original || ''));
                formData.append('protein_g', field === 'protein_g' ? newValue : (proteinEl?.dataset.original || ''));
                formData.append('carbs_g', field === 'carbs_g' ? newValue : (carbsEl?.dataset.original || ''));
                formData.append('fat_g', field === 'fat_g' ? newValue : (fatEl?.dataset.original || ''));
                formData.append('water_ml', <?php echo $water_goal_ml; ?>);
                const response = await fetch('<?php echo BASE_ADMIN_URL; ?>/actions/update_user_goals.php', { method:'POST', headers:{ 'X-Requested-With':'XMLHttpRequest' }, body: formData });
                const text = await response.text();
                let result;
                try { result = JSON.parse(text); } catch(e){ console.error('JSON parse error:', e, text); cancelEdit(); return; }
                if (result.success){
                    element.dataset.original = newValue;
                    element.textContent = newValue + (suffix? ' ' + suffix : '');
                    element.style.animation = 'pulse 0.5s ease';
                    setTimeout(() => element.style.animation = '', 500);
                } else { alert('Erro ao salvar: ' + result.message); cancelEdit(); }
            } catch(err){ console.error('Erro:', err); alert('Erro ao salvar alterações'); cancelEdit(); }
        };

        input.addEventListener('blur', saveValue);
        input.addEventListener('keydown', function(e){ if (e.key==='Enter'){ e.preventDefault(); saveValue(); } else if (e.key==='Escape'){ cancelEdit(); } });
    }

    document.addEventListener('click', function(e){
        const el = e.target.closest('.editable-value');
        if (el) { e.preventDefault(); startInlineEdit(el); }
    });
})();

// Logs leves para diagnosticar modais no prod
window.showRevertModal = (function(orig){
    return function(userId){ console.log('[showRevertModal] click', { userId }); try{ return orig ? orig(userId) : (document.getElementById('revertGoalsModal')?.classList.add('active'), document.body.style.overflow='hidden'); }catch(e){ console.error(e); }};
})(window.showRevertModal);

window.openSleepDetailsModal = (function(orig){
    return function(){ console.log('[openSleepDetailsModal] click'); try{ return orig ? orig() : (document.getElementById('sleepDetailsModal')?.classList.add('active'), document.body.style.overflow='hidden'); }catch(e){ console.error(e); }};
})(window.openSleepDetailsModal);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>








