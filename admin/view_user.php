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

/* Remove altura forçada da faixa de slider */
.diary-slider-track {
    display: flex !important;
    align-items: flex-start !important;
    height: auto !important;
    min-height: 0 !important;
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
let diaryCards = document.querySelectorAll('#diarySliderTrack .diary-day-card');
let currentDiaryIndex = diaryCards.length - 1; // Iniciar no último (dia mais recente)
const diaryTrack = document.getElementById('diarySliderTrack');
let isLoadingMoreDays = false; // Flag para evitar múltiplas chamadas

// Função para atualizar referência aos cards
function updateDiaryCards() {
    diaryCards = document.querySelectorAll('#diarySliderTrack .diary-day-card');
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
        if (window.isLoadingMoreDays) {
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
                    <div class="summary-description">Baseado nos registros de hidratação do paciente no aplicativo</div>
                    </div>
                <div class="summary-status status-<?php echo $status_class; ?>">
                    <i class="fas <?php echo $status_icon; ?>"></i>
                    <span><?php echo $status_text; ?></span>
                </div>
                    </div>
            <div class="summary-stats">
                <div class="summary-stat">
                    <div class="stat-value"><?php echo $water_stats_7['avg_ml']; ?>ml</div>
                    <div class="stat-label">Média de Água</div>
                    <div class="stat-description">Últimos 7 dias</div>
                    </div>
                <div class="summary-stat">
                    <div class="stat-value"><?php echo $water_stats_7['avg_percentage']; ?>%</div>
                    <div class="stat-label">
                        Aderência Geral
                        <i class="fas fa-question-circle help-icon" onclick="openHelpModal('hydration-adherence')" title="Clique para saber mais"></i>
                </div>
                    <div class="stat-description">Meta de hidratação atingida</div>
                    </div>
                <div class="summary-stat">
                    <div class="stat-value"><?php echo $water_stats_7['days_with_consumption']; ?>/<?php echo $water_stats_7['total_days']; ?></div>
                    <div class="stat-label">Dias com Registro</div>
                    <div class="stat-description"><?php echo $water_stats_7['adherence_percentage']; ?>% de aderência</div>
                </div>
            </div>
        </div>


        <!-- 3. GRÁFICO COM BOTÕES DE PERÍODO -->
        <div class="chart-section">
            <div class="hydration-chart-improved">
                <div class="chart-header">
                    <h4><i class="fas fa-chart-bar"></i> Progresso de Hidratação</h4>
                    <div class="period-buttons">
                        <button class="period-btn active" onclick="changeHydrationPeriod(7)" data-period="7">7 dias</button>
                        <button class="period-btn" onclick="changeHydrationPeriod(15)" data-period="15">15 dias</button>
                        <button class="period-btn" onclick="changeHydrationPeriod(30)" data-period="30">30 dias</button>
                </div>
            </div>
                <div class="improved-chart" id="hydration-chart">
                <?php if (empty($hydration_data)): ?>
                    <div class="empty-chart">
                        <i class="fas fa-tint"></i>
                        <p>Nenhum registro encontrado</p>
                    </div>
                <?php else: ?>
                    <div class="improved-bars" id="hydration-bars">
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
            <h4><i class="fas fa-calendar-alt" style="color: var(--accent-orange);"></i> Médias de Consumo por Período</h4>
            <p class="section-description">Análise do consumo de água médio em diferentes períodos para identificar tendências e padrões de hidratação.</p>
            <div class="periods-grid">
                <div class="period-item">
                    <span class="period-label">Última Semana</span>
                    <span class="period-value"><?php echo $water_stats_7['avg_ml']; ?>ml</span>
                    <span class="period-percentage"><?php echo $water_stats_7['avg_percentage']; ?>% da meta</span>
                    <div class="period-details">Média dos últimos 7 dias</div>
            </div>
                <div class="period-item">
                    <span class="period-label">Última Quinzena</span>
                    <span class="period-value"><?php echo $water_stats_15['avg_ml']; ?>ml</span>
                    <span class="period-percentage"><?php echo $water_stats_15['avg_percentage']; ?>% da meta</span>
                    <div class="period-details">Média dos últimos 15 dias</div>
                    </div>
                <div class="period-item">
                    <span class="period-label">Último Mês</span>
                    <span class="period-value"><?php echo $water_stats_30['avg_ml']; ?>ml</span>
                    <span class="period-percentage"><?php echo $water_stats_30['avg_percentage']; ?>% da meta</span>
                    <div class="period-details">Média dos últimos 30 dias</div>
                            </div>
                            </div>
                        </div>

    </div>
</div>

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
                    <div class="stat-label">
                        Aderência Geral
                        <i class="fas fa-question-circle help-icon" onclick="openHelpModal('nutrients-adherence')" title="Clique para saber mais"></i>
                        </div>
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
        
        // Insight sobre disciplina (média ponderada)
        if ($nutrients_stats_7['avg_kcal'] > 0) {
            if ($nutrients_stats_7['avg_kcal_percentage'] >= 100) {
                $nutrients_insights[] = "Média semanal ponderada: <strong class='text-success'>" . $nutrients_stats_7['avg_kcal'] . " kcal</strong> (" . round($nutrients_stats_7['avg_kcal_percentage']) . "% da meta).";
            } elseif ($nutrients_stats_7['avg_kcal_percentage'] >= 80) {
                $nutrients_insights[] = "Média semanal ponderada: <strong class='text-info'>" . $nutrients_stats_7['avg_kcal'] . " kcal</strong> (" . round($nutrients_stats_7['avg_kcal_percentage']) . "% da meta).";
            } elseif ($nutrients_stats_7['avg_kcal_percentage'] >= 60) {
                $nutrients_insights[] = "Média semanal ponderada: <strong class='text-warning'>" . $nutrients_stats_7['avg_kcal'] . " kcal</strong> (" . round($nutrients_stats_7['avg_kcal_percentage']) . "% da meta).";
            } else {
                $nutrients_insights[] = "Média semanal ponderada: <strong class='text-danger'>" . $nutrients_stats_7['avg_kcal'] . " kcal</strong> (" . round($nutrients_stats_7['avg_kcal_percentage']) . "% da meta).";
            }
        }
        
        // Insight sobre consumo real (média dos dias com registro)
        if ($nutrients_stats_7['avg_real_kcal'] > 0) {
            $realPercentage = $total_daily_calories_goal > 0 ? round(($nutrients_stats_7['avg_real_kcal'] / $total_daily_calories_goal) * 100, 1) : 0;
            $nutrients_insights[] = "Consumo médio (dias com registro): <strong>" . $nutrients_stats_7['avg_real_kcal'] . " kcal</strong> (" . $realPercentage . "% da meta).";
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
        

        <!-- 3. GRÁFICO COM BOTÕES DE PERÍODO -->
        <div class="chart-section">
            <div class="nutrients-chart-improved">
                <div class="chart-header">
                    <h4><i class="fas fa-chart-bar"></i> Progresso Nutricional</h4>
                    <div class="period-buttons">
                        <button class="period-btn active" onclick="changeNutrientsPeriod(7)" data-period="7">7 dias</button>
                        <button class="period-btn" onclick="changeNutrientsPeriod(15)" data-period="15">15 dias</button>
                        <button class="period-btn" onclick="changeNutrientsPeriod(30)" data-period="30">30 dias</button>
                </div>
            </div>
                <div class="improved-chart" id="nutrients-chart">
                <?php if (empty($last_7_days_data)): ?>
                        <div class="empty-chart">
                            <i class="fas fa-utensils"></i>
                            <p>Nenhum registro encontrado</p>
                        </div>
                    <?php else: ?>
                    <div class="improved-bars" id="nutrients-bars">
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
            <p class="section-description">Análise detalhada do consumo de proteínas, carboidratos e gorduras baseado nas refeições registradas pelo paciente no aplicativo.</p>
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
                            <i class="fas fa-tint"></i>
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
                
                // Calcular altura da barra: 0% = 0px, 100% = 160px, >100% pode ir até 200px
                let barHeight;
                if (percentage === 0) {
                    barHeight = 0; // Sem altura para 0%
                } else if (percentage >= 100) {
                    barHeight = 160 + Math.min((percentage - 100) * 0.4, 40); // 100% = 160px, máximo 200px
                } else {
                    barHeight = (percentage / 100) * 160; // Proporcional entre 0px e 160px
                }
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
<!-- Card de Medidas dentro da aba Progresso -->
<div id="tab-progress" class="tab-content">
    <div class="dashboard-card">
        <h3><i class="fas fa-camera"></i> Histórico de Medidas Corporais</h3>
        <div class="measurements-content">
            <?php if (empty($photo_history)): ?>
                <p class="empty-state">Nenhuma foto de progresso encontrada.</p>
            <?php else: ?>
                <div class="photo-gallery">
                    <?php 
                    $displayed_count = 0;
                    foreach($photo_history as $photo_set): 
                        if ($displayed_count >= 6) break;
                        foreach(['photo_front' => 'Frente', 'photo_side' => 'Lado', 'photo_back' => 'Costas'] as $photo_type => $label): 
                            if ($displayed_count >= 6) break;
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

<!-- Aba de Rotina - REFATORADA COMPLETAMENTE -->
<div id="tab-routine" class="tab-content">
    <div class="routine-container">
        
        <!-- 1. CARD DE RESUMO DA ROTINA -->
        <div class="nutrients-summary-card">
            <div class="summary-main">
                <div class="summary-icon routine-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 11L12 14L22 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M21 12V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="summary-info">
                    <h3>Resumo da Rotina Semanal</h3>
                    <div class="summary-meta">Acompanhamento de missões, treinos e sono dos últimos 7 dias</div>
                    <div class="summary-description">Dados baseados nos registros diários do paciente no aplicativo</div>
                </div>
            </div>
            <div class="summary-stats">
                <div class="summary-stat">
                    <div class="stat-value" id="routine-missions-completed">0/0</div>
                    <div class="stat-label">Missões Concluídas</div>
                    <div class="stat-description">Última semana</div>
                </div>
                <div class="summary-stat">
                    <div class="stat-value" id="routine-sleep-avg"><?php echo number_format($avg_sleep_7 ?? 0, 1); ?>h</div>
                    <div class="stat-label">Sono Médio</div>
                    <div class="stat-description">Últimos 7 dias</div>
                </div>
                <div class="summary-stat">
                    <div class="stat-value" id="routine-workouts-days"><?php echo count(array_slice($routine_exercise_data, 0, 7)); ?></div>
                    <div class="stat-label">Dias com Treino</div>
                    <div class="stat-description">Última semana</div>
                </div>
            </div>
        </div>

        <!-- 2. CALENDÁRIO EXATAMENTE IGUAL AO DIÁRIO (MAS COM MISSÕES) -->
        <div class="diary-slider-container">
            <div class="diary-header-redesign">
                <!-- Ano no topo -->
                <div class="diary-year" id="routineYear">2025</div>
                
                <!-- Navegação e data principal -->
                <div class="diary-nav-row">
                    <button class="diary-nav-side diary-nav-left" onclick="navigateRoutine(-1)" type="button">
                        <i class="fas fa-chevron-left"></i>
                        <span id="routinePrevDate">26 out</span>
                    </button>
                    
                    <div class="diary-main-date">
                        <div class="diary-day-month" id="routineDayMonth">27 OUT</div>
                        <div class="diary-weekday" id="routineWeekday">SEGUNDA</div>
                    </div>
                    
                    <button class="diary-nav-side diary-nav-right" onclick="navigateRoutine(1)" type="button">
                        <span id="routineNextDate">28 out</span>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                
                <!-- Resumo de missões -->
                <div class="diary-summary-row">
                    <div class="diary-kcal" id="routineSummaryMissions">
                        <i class="fas fa-check-circle"></i>
                        <span>0 missões</span>
                    </div>
                    <div class="diary-macros" id="routineSummaryProgress">
                        Progresso: 0%
                    </div>
                </div>
                
                <!-- Botão de calendário -->
                <button class="diary-calendar-icon-btn" onclick="openRoutineCalendar()" type="button" title="Ver calendário">
                    <i class="fas fa-calendar-alt"></i>
                </button>
            </div>
            
            <div class="diary-slider-wrapper" id="routineSliderWrapper">
                <div class="diary-slider-track" id="routineSliderTrack">
                    <?php 
                    // Gerar array com TODOS os dias, mesmo se não houver dados
                    $all_dates = [];
                    for ($i = 0; $i < $daysToShow; $i++) {
                        $current_date = date('Y-m-d', strtotime($endDate . " -$i days"));
                        $all_dates[] = $current_date;
                    }
                    
                    // Inverter ordem: mais antigo à esquerda, mais recente à direita
                    $all_dates = array_reverse($all_dates);
                    
                    foreach ($all_dates as $date): 
                        // Buscar missões do dia usando a mesma lógica do routine.php
                        $day_missions = getRoutineItemsForUser($conn, $user_id, $date, $user_profile);
                        $completed_missions = array_filter($day_missions, function($mission) {
                            return $mission['completion_status'] == 1;
                        });
                        
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
                                <i class="fas fa-check-circle"></i>
                                <span><?php echo count($completed_missions); ?> missões</span>
                            </div>
                            <div class="diary-summary-macros">
                                <?php echo count($completed_missions); ?> concluídas
                            </div>
                        </div>
                        
                        <div class="diary-day-meals">
                            <?php if (empty($completed_missions)): ?>
                                <div class="diary-empty-state">
                                    <i class="fas fa-calendar-day"></i>
                                    <p>Nenhum registro neste dia</p>
                    </div>
                            <?php else: ?>
                                <?php foreach ($completed_missions as $mission): ?>
                                    <div class="diary-meal-card">
                                        <div class="diary-meal-header">
                                            <div class="diary-meal-icon">
                                                <i class="fas <?php echo htmlspecialchars($mission['icon_class']); ?>"></i>
                    </div>
                                            <div class="diary-meal-info">
                                                <h5><?php echo htmlspecialchars($mission['title']); ?></h5>
                                                <span class="diary-meal-totals">
                                                    <strong><?php echo isset($mission['duration_minutes']) && $mission['duration_minutes'] ? $mission['duration_minutes'] . 'min' : 'Concluída'; ?></strong>
                                                </span>
                </div>
                        </div>
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
                    </div>


<!-- Modal do Calendário da Rotina (idêntico ao da aba Diário) -->
<div id="routineCalendarModal" class="custom-modal">
    <div class="custom-modal-overlay" onclick="closeRoutineCalendar()"></div>
    <div class="diary-calendar-wrapper">
        <button class="calendar-btn-close" onclick="closeRoutineCalendar()" type="button">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="calendar-header-title">
            <div class="calendar-year">2025</div>
                </div>

        <div class="calendar-nav-buttons">
            <button class="calendar-btn-nav" onclick="changeRoutineCalendarMonth(-1)" type="button">
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="calendar-month">OUT</div>
            <button class="calendar-btn-nav" id="routineNextMonthBtn" onclick="changeRoutineCalendarMonth(1)" type="button">
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

        <div class="calendar-days-grid" id="routineCalendarDaysGrid"></div>
        
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

        <!-- 2. CARD DE GERENCIAMENTO DE MISSÕES -->
        <div class="routine-missions-card">
            <div class="card-header">
                <div class="card-title">
                    <div class="title-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="title-content">
                        <h3>Missões do Usuário</h3>
                        <p>Gerencie as missões de rotina personalizadas para este paciente</p>
                    </div>
                </div>
                <button class="btn-add-mission" onclick="openMissionModal()">
                    <i class="fas fa-plus"></i>
                    <span>Adicionar Missão</span>
                </button>
            </div>
            
            <div class="missions-grid" id="missions-container">
                <!-- Missões serão carregadas aqui via JavaScript -->
                <div class="loading-missions">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Carregando missões...</span>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal de Gerenciamento de Missões -->
<div id="missionModal" class="custom-modal" style="display: none;">
    <div class="custom-modal-overlay" onclick="closeMissionModal()"></div>
    <div class="diary-calendar-wrapper">
        <button class="calendar-btn-close" onclick="closeMissionModal()" type="button">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="modal-header">
            <div class="modal-title">
                <div class="title-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="title-content">
                    <h3 id="missionModalTitle">Adicionar Nova Missão</h3>
                    <p>Configure uma missão personalizada para este paciente</p>
                </div>
            </div>
        </div>
        
        <div class="modal-body">
            <form id="missionForm">
                <input type="hidden" id="missionId" name="mission_id">
                
                <div class="form-group">
                    <label for="missionName">Nome da Missão</label>
                    <input type="text" id="missionName" name="mission_name" placeholder="Ex: Beber 2L de água por dia" required>
                </div>
                
                <div class="form-group">
                    <label for="missionType">Tipo de Missão</label>
                    <select id="missionType" name="mission_type" required>
                        <option value="binary">Sim/Não (Binária)</option>
                        <option value="duration">Com Duração (Exercício)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Escolha um Ícone</label>
                    <div class="icon-picker" id="iconPicker">
                        <!-- Ícones serão carregados via JavaScript -->
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeMissionModal()">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i>
                        <span id="saveButtonText">Salvar Missão</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const userViewData = {
    weightHistory: <?php echo json_encode($weight_chart_data); ?>,
    routineData: {
        steps: <?php echo json_encode($routine_steps_data); ?>,
        sleep: <?php echo json_encode($routine_sleep_data); ?>,
        exercise: <?php echo json_encode($routine_exercise_data); ?>
    }
};


// --- FUNCIONALIDADES DA ABA ROTINA ---
let stepsChart = null;
let exerciseChart = null;
let sleepChart = null;

// Função para calcular estatísticas de passos
function calculateStepsStats(data, period) {
    const today = new Date();
    const filteredData = data.filter(item => {
        const itemDate = new Date(item.date);
        const daysDiff = Math.floor((today - itemDate) / (1000 * 60 * 60 * 24));
        return daysDiff < period;
    });
    
    if (filteredData.length === 0) return { average: 0, total: 0 };
    
    const total = filteredData.reduce((sum, item) => sum + (parseInt(item.steps_daily) || 0), 0);
    const average = Math.round(total / filteredData.length);
    
    return { average, total };
}

// Função para calcular estatísticas de sono
function calculateSleepStats(data, period) {
    const today = new Date();
    const filteredData = data.filter(item => {
        const itemDate = new Date(item.date);
        const daysDiff = Math.floor((today - itemDate) / (1000 * 60 * 60 * 24));
        return daysDiff < period;
    });
    
    if (filteredData.length === 0) return { average: 0 };
    
    const total = filteredData.reduce((sum, item) => sum + (parseFloat(item.sleep_hours) || 0), 0);
    const average = Math.round((total / filteredData.length) * 10) / 10;
    
    return { average };
}

// Função para calcular estatísticas de exercícios
function calculateExerciseStats(data, period) {
    const today = new Date();
    const filteredData = data.filter(item => {
        const itemDate = new Date(item.updated_at);
        const daysDiff = Math.floor((today - itemDate) / (1000 * 60 * 60 * 24));
        return daysDiff < period;
    });
    
    const totalTime = filteredData.reduce((sum, item) => sum + (parseInt(item.duration_minutes) || 0), 0);
    
    return { count: filteredData.length, totalTime };
}

// Função para atualizar dados da rotina
function updateRoutineData(period = 7) {
    // Atualizar passos
    const stepsStats = calculateStepsStats(userViewData.routineData.steps, period);
    const stepsToday = userViewData.routineData.steps[0] ? parseInt(userViewData.routineData.steps[0].steps_daily) : 0;
    const stepsGoal = 10000;
    const stepsProgress = Math.min((stepsToday / stepsGoal) * 100, 100);
    
    document.getElementById('stepsToday').textContent = stepsToday.toLocaleString('pt-BR');
    document.getElementById('stepsProgress').style.width = stepsProgress + '%';
    document.getElementById('stepsProgressText').textContent = Math.round(stepsProgress) + '% da meta';
    
    // Atualizar sono
    const sleepStats = calculateSleepStats(userViewData.routineData.sleep, period);
    const sleepToday = userViewData.routineData.sleep[0] ? parseFloat(userViewData.routineData.sleep[0].sleep_hours) : 0;
    
    document.getElementById('sleepToday').textContent = sleepToday + 'h';
    document.getElementById('sleepAverage').textContent = 'Média: ' + sleepStats.average + 'h';
    
    // Atualizar exercícios
    const exerciseStats = calculateExerciseStats(userViewData.routineData.exercise, period);
    document.getElementById('exerciseTotalTime').textContent = exerciseStats.totalTime;
    document.getElementById('exerciseCount').textContent = exerciseStats.count + ' exercícios no período';
    
    // Atualizar gráficos
    updateStepsChart(period);
    updateExerciseChart(period);
    updateSleepChart(period);
}

// Função para atualizar gráfico de passos
function updateStepsChart(period) {
    const ctx = document.getElementById('stepsChart');
    if (!ctx) return;
    
    if (stepsChart) {
        stepsChart.destroy();
    }
    
    const today = new Date();
    const filteredData = userViewData.routineData.steps.filter(item => {
        const itemDate = new Date(item.date);
        const daysDiff = Math.floor((today - itemDate) / (1000 * 60 * 60 * 24));
        return daysDiff < period;
    }).reverse();
    
    const labels = filteredData.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    });
    
    const stepsData = filteredData.map(item => parseInt(item.steps_daily) || 0);
    
    stepsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Passos',
                data: stepsData,
                backgroundColor: 'rgba(33, 150, 243, 0.8)',
                borderColor: 'rgba(33, 150, 243, 1)',
                borderWidth: 1,
                borderRadius: 4
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
                    ticks: {
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                }
            }
        }
    });
}

// Função para atualizar gráfico de exercícios
function updateExerciseChart(period) {
    const ctx = document.getElementById('exerciseChart');
    if (!ctx) return;
    
    if (exerciseChart) {
        exerciseChart.destroy();
    }
    
    const today = new Date();
    const filteredData = userViewData.routineData.exercise.filter(item => {
        const itemDate = new Date(item.updated_at);
        const daysDiff = Math.floor((today - itemDate) / (1000 * 60 * 60 * 24));
        return daysDiff < period;
    });
    
    // Agrupar por data
    const exerciseByDate = {};
    filteredData.forEach(item => {
        const date = item.updated_at.split(' ')[0]; // Pegar só a data
        if (!exerciseByDate[date]) {
            exerciseByDate[date] = 0;
        }
        exerciseByDate[date] += parseInt(item.duration_minutes) || 0;
    });
    
    // Ordenar datas e criar arrays para o gráfico
    const sortedDates = Object.keys(exerciseByDate).sort();
    const labels = sortedDates.map(date => {
        const d = new Date(date);
        return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    });
    
    const exerciseData = sortedDates.map(date => exerciseByDate[date]);
    
    exerciseChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Minutos de Exercício',
                data: exerciseData,
                backgroundColor: 'rgba(255, 152, 0, 0.8)',
                borderColor: 'rgba(255, 152, 0, 1)',
                borderWidth: 1,
                borderRadius: 4
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
                    ticks: {
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                }
            }
        }
    });
}

// Função para atualizar gráfico de sono
function updateSleepChart(period) {
    const ctx = document.getElementById('sleepChart');
    if (!ctx) return;
    
    if (sleepChart) {
        sleepChart.destroy();
    }
    
    const today = new Date();
    const filteredData = userViewData.routineData.sleep.filter(item => {
        const itemDate = new Date(item.date);
        const daysDiff = Math.floor((today - itemDate) / (1000 * 60 * 60 * 24));
        return daysDiff < period;
    }).reverse();
    
    const labels = filteredData.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    });
    
    const sleepData = filteredData.map(item => parseFloat(item.sleep_hours) || 0);
    
    sleepChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Horas de Sono',
                data: sleepData,
                backgroundColor: 'rgba(156, 39, 176, 0.8)',
                borderColor: 'rgba(156, 39, 176, 1)',
                borderWidth: 1,
                borderRadius: 4
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
                    max: 12,
                    ticks: {
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                }
            }
        }
    });
}

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
            } else if (this.dataset.tab === 'routine') {
                setTimeout(() => {
                    updateRoutineData();
                }, 100);
            }
        });
    });
});

// --- FUNCIONALIDADES DA ABA ROTINA ---

// Dados simulados para rotinas (em produção, viriam do banco)
const routineData = {
    today: { exercise: 1, nutrition: 1, hydration: 0, sleep: 1 },
    week: [
        { date: '2024-10-01', exercise: 1, nutrition: 1, hydration: 1, sleep: 0 },
        { date: '2024-09-30', exercise: 0, nutrition: 1, hydration: 1, sleep: 1 },
        { date: '2024-09-29', exercise: 1, nutrition: 1, hydration: 0, sleep: 1 },
        { date: '2024-09-28', exercise: 1, nutrition: 0, hydration: 1, sleep: 1 },
        { date: '2024-09-27', exercise: 0, nutrition: 1, hydration: 1, sleep: 0 },
        { date: '2024-09-26', exercise: 1, nutrition: 1, hydration: 1, sleep: 1 },
        { date: '2024-09-25', exercise: 1, nutrition: 0, hydration: 0, sleep: 1 }
    ]
};

// Função para atualizar dados da rotina
function updateRoutineData() {
    // Esta função é chamada pelo código existente, mas não é necessária para a nova implementação
    // Mantida para compatibilidade
    console.log('updateRoutineData chamada');
    
    // Verificar se os elementos existem antes de tentar acessá-los
    const todayRoutines = document.getElementById('todayRoutines');
    const weekRoutines = document.getElementById('weekRoutines');
    const adherenceRate = document.getElementById('adherenceRate');
    
    if (todayRoutines) todayRoutines.textContent = '0/0';
    if (weekRoutines) weekRoutines.textContent = '0/0';
    if (adherenceRate) adherenceRate.textContent = '0%';
    
    // Atualizar tabela
    updateRoutineTable();
}

// Função para atualizar gráfico de rotinas
function updateRoutineChart() {
    const ctx = document.getElementById('routineChart');
    if (!ctx) return;
    
    const labels = routineData.week.map(day => 
        new Date(day.date).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
    );
    
    const exerciseData = routineData.week.map(day => day.exercise);
    const nutritionData = routineData.week.map(day => day.nutrition);
    const hydrationData = routineData.week.map(day => day.hydration);
    const sleepData = routineData.week.map(day => day.sleep);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Exercício',
                    data: exerciseData,
                    backgroundColor: 'rgba(76, 175, 80, 0.8)',
                    borderColor: 'rgba(76, 175, 80, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Alimentação',
                    data: nutritionData,
                    backgroundColor: 'rgba(255, 152, 0, 0.8)',
                    borderColor: 'rgba(255, 152, 0, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Hidratação',
                    data: hydrationData,
                    backgroundColor: 'rgba(33, 150, 243, 0.8)',
                    borderColor: 'rgba(33, 150, 243, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Sono',
                    data: sleepData,
                    backgroundColor: 'rgba(156, 39, 176, 0.8)',
                    borderColor: 'rgba(156, 39, 176, 1)',
                    borderWidth: 1
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
                    max: 1,
                    ticks: {
                        stepSize: 1,
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                }
            }
        }
    });
}

// Função para atualizar tabela de rotinas
function updateRoutineTable() {
    const tbody = document.getElementById('routineTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = routineData.week.map(day => {
        const total = Object.values(day).slice(1).reduce((sum, val) => sum + val, 0);
        const date = new Date(day.date).toLocaleDateString('pt-BR');
        
        return `
            <tr>
                <td>${date}</td>
                <td><i class="fas ${day.exercise ? 'fa-check text-success' : 'fa-times text-danger'}"></i></td>
                <td><i class="fas ${day.nutrition ? 'fa-check text-success' : 'fa-times text-danger'}"></i></td>
                <td><i class="fas ${day.hydration ? 'fa-check text-success' : 'fa-times text-danger'}"></i></td>
                <td><i class="fas ${day.sleep ? 'fa-check text-success' : 'fa-times text-danger'}"></i></td>
                <td><span class="badge ${total >= 3 ? 'badge-success' : total >= 2 ? 'badge-warning' : 'badge-danger'}">${total}/4</span></td>
            </tr>
        `;
    }).join('');
}

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

<!-- Modal de Ajuda -->
<div id="helpModal" class="help-modal">
    <div class="help-modal-content">
        <div class="help-modal-header">
            <h3 id="helpModalTitle">Aderência Geral</h3>
            <button class="help-modal-close" onclick="closeHelpModal()">&times;</button>
        </div>
        <div class="help-modal-body" id="helpModalBody">
            <!-- Conteúdo será preenchido via JavaScript -->
        </div>
    </div>
</div>

<script>
// Função para abrir modal de ajuda
function openHelpModal(type) {
    const modal = document.getElementById('helpModal');
    const title = document.getElementById('helpModalTitle');
    const body = document.getElementById('helpModalBody');
    
    if (type === 'hydration-adherence') {
        title.textContent = 'Aderência Geral - Hidratação';
        body.innerHTML = `
            <p>Percentual médio de cumprimento da meta de hidratação nos últimos 7 dias.</p>
            
            <p><strong>Cálculo:</strong><br>
            Soma dos percentuais de cada dia ÷ 7 dias<br>
            <em>(dias sem registro = 0%)</em></p>
            
            <p><strong>Exemplo:</strong><br>
            Se atingiu 100%, 0%, 80%, 0%, 90%, 0%, 70% da meta:<br>
            <strong>Aderência = (100+0+80+0+90+0+70) ÷ 7 = 48.6%</strong></p>
            
            <p>Avalia a <strong>consistência</strong> do paciente.</p>
        `;
    } else if (type === 'nutrients-adherence') {
        title.textContent = 'Aderência Geral - Nutrientes';
        body.innerHTML = `
            <p>Percentual médio de cumprimento da meta calórica nos últimos 7 dias.</p>
            
            <p><strong>Cálculo:</strong><br>
            Soma dos percentuais de cada dia ÷ 7 dias<br>
            <em>(dias sem registro = 0%)</em></p>
            
            <p><strong>Exemplo:</strong><br>
            Se atingiu 95%, 0%, 110%, 0%, 85%, 0%, 100% da meta:<br>
            <strong>Aderência = (95+0+110+0+85+0+100) ÷ 7 = 55.7%</strong></p>
            
            <p>Avalia a <strong>consistência</strong> do paciente.</p>
        `;
    }
    
    modal.style.display = 'block';
}

// Função para fechar modal de ajuda
function closeHelpModal() {
    const modal = document.getElementById('helpModal');
    modal.style.display = 'none';
}

// Fechar modal clicando fora dele
window.onclick = function(event) {
    const modal = document.getElementById('helpModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}

// Funções para mudar período dos gráficos
function changeHydrationPeriod(days) {
    // Atualizar botões ativos
    document.querySelectorAll('.period-buttons .period-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Atualizar layout das barras
    const barsContainer = document.getElementById('hydration-bars');
    if (barsContainer) {
        barsContainer.setAttribute('data-period', days);
        loadHydrationData(days);
    }
}
function changeNutrientsPeriod(days) {
    // Atualizar botões ativos
    document.querySelectorAll('.period-buttons .period-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Atualizar layout das barras
    const barsContainer = document.getElementById('nutrients-bars');
    if (barsContainer) {
        barsContainer.setAttribute('data-period', days);
        loadNutrientsData(days);
    }
}

// Função para carregar dados de hidratação
function loadHydrationData(days) {
    const chartContainer = document.getElementById('hydration-bars');
    if (!chartContainer) return;
    
    // Usar apenas os dados de 7 dias disponíveis e simular outros períodos
    const baseData = <?php echo json_encode($hydration_data); ?>;
    
    // Simular dados para períodos maiores repetindo os dados existentes
    let data = [...baseData];
    
    if (days > baseData.length) {
        // Se pediu mais dias que temos, repetir os dados existentes
        const repeatTimes = Math.ceil(days / baseData.length);
        for (let i = 1; i < repeatTimes; i++) {
            data = [...data, ...baseData];
        }
    }
    
    // Pegar apenas a quantidade solicitada
    data = data.slice(0, days);
    
    renderHydrationChart(data);
}

// Função para carregar dados de nutrientes
function loadNutrientsData(days) {
    const chartContainer = document.getElementById('nutrients-bars');
    if (!chartContainer) return;
    
    // Usar apenas os dados de 7 dias disponíveis e simular outros períodos
    const baseData = <?php echo json_encode($last_7_days_data); ?>;
    
    // Simular dados para períodos maiores repetindo os dados existentes
    let data = [...baseData];
    
    if (days > baseData.length) {
        // Se pediu mais dias que temos, repetir os dados existentes
        const repeatTimes = Math.ceil(days / baseData.length);
        for (let i = 1; i < repeatTimes; i++) {
            data = [...data, ...baseData];
        }
    }
    
    // Pegar apenas a quantidade solicitada
    data = data.slice(0, days);
    
    renderNutrientsChart(data);
}

// Função para mudar período da rotina
function changeRoutinePeriod(days) {
    // Atualizar botões ativos
    document.querySelectorAll('.period-buttons .period-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Atualizar layout das barras
    const barsContainer = document.getElementById('routine-bars');
    if (barsContainer) {
        barsContainer.setAttribute('data-period', days);
        loadRoutineData(days);
    }
}

// Função para carregar dados de rotina
function loadRoutineData(days) {
    const chartContainer = document.getElementById('routine-bars');
    if (!chartContainer) return;
    
    // Usar apenas os dados de 7 dias disponíveis e simular outros períodos
    const baseData = <?php echo json_encode($routine_steps_data); ?>;
    
    // Simular dados para períodos maiores repetindo os dados existentes
    let data = [...baseData];
    
    if (days > baseData.length) {
        // Se pediu mais dias que temos, repetir os dados existentes
        const repeatTimes = Math.ceil(days / baseData.length);
        for (let i = 1; i < repeatTimes; i++) {
            data = [...data, ...baseData];
        }
    }
    
    // Pegar apenas a quantidade solicitada
    data = data.slice(0, days);
    
    renderRoutineChart(data);
}

// Função para renderizar gráfico de rotina
function renderRoutineChart(data) {
    const chartContainer = document.getElementById('routine-bars');
    if (!chartContainer) return;
    
    if (data.length === 0) {
        chartContainer.innerHTML = `
            <div class="empty-chart">
                <i class="fas fa-walking"></i>
                <p>Nenhum registro encontrado</p>
            </div>
        `;
        return;
    }
    
    // Aplicar o atributo data-period baseado na quantidade de dados
    const period = data.length;
    chartContainer.setAttribute('data-period', period);
    
    let chartHTML = '';
    data.forEach(day => {
        const steps = parseInt(day.steps_daily);
        const percentage = Math.min((steps / 10000) * 100, 100);
        let barHeight = 0;
        if (percentage === 0) {
            barHeight = 0;
        } else if (percentage === 100) {
            barHeight = 160;
        } else {
            barHeight = (percentage / 100) * 160;
        }
        
        let status = 'empty';
        if (percentage >= 90) status = 'excellent';
        else if (percentage >= 70) status = 'good';
        else if (percentage >= 50) status = 'fair';
        else if (percentage >= 30) status = 'poor';
        else if (percentage > 0) status = 'critical';
        
        chartHTML += `
            <div class="improved-bar-container">
                <div class="improved-bar-wrapper">
                    <div class="improved-bar ${status}" style="height: ${barHeight}px"></div>
                    <div class="bar-percentage-text">${Math.round(percentage)}%</div>
                    <div class="improved-goal-line"></div>
                </div>
                <div class="improved-bar-info">
                    <span class="improved-date">${new Date(day.date).toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit'})}</span>
                    <span class="improved-ml">${steps.toLocaleString('pt-BR')} passos</span>
                </div>
            </div>
        `;
    });
    
    chartContainer.innerHTML = chartHTML;
}

// Função para renderizar gráfico de hidratação
function renderHydrationChart(data) {
    const chartContainer = document.getElementById('hydration-bars');
    if (!chartContainer) return;
    
    if (data.length === 0) {
        chartContainer.innerHTML = `
            <div class="empty-chart">
                <i class="fas fa-tint"></i>
                <p>Nenhum registro encontrado</p>
            </div>
        `;
        return;
    }
    
    // Aplicar o atributo data-period baseado na quantidade de dados
    const period = data.length;
    chartContainer.setAttribute('data-period', period);
    
    let chartHTML = '';
    data.forEach(day => {
        const limitedPercentage = Math.min(day.percentage, 100);
        let barHeight = 0;
        if (limitedPercentage === 0) {
            barHeight = 0;
        } else if (limitedPercentage === 100) {
            barHeight = 160;
        } else {
            barHeight = (limitedPercentage / 100) * 160;
        }
        
        chartHTML += `
            <div class="improved-bar-container">
                <div class="improved-bar-wrapper">
                    <div class="improved-bar ${day.status}" style="height: ${barHeight}px"></div>
                    <div class="bar-percentage-text">${limitedPercentage}%</div>
                    <div class="improved-goal-line"></div>
                </div>
                <div class="improved-bar-info">
                    <span class="improved-date">${new Date(day.date).toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit'})}</span>
                    <span class="improved-ml">${day.ml}ml</span>
                </div>
            </div>
        `;
    });
    
    chartContainer.innerHTML = chartHTML;
}

// Função para renderizar gráfico de nutrientes
function renderNutrientsChart(data) {
    const chartContainer = document.getElementById('nutrients-bars');
    if (!chartContainer) return;
    
    if (data.length === 0) {
        chartContainer.innerHTML = `
            <div class="empty-chart">
                <i class="fas fa-utensils"></i>
                <p>Nenhum registro encontrado</p>
            </div>
        `;
        return;
    }
    
    // Aplicar o atributo data-period baseado na quantidade de dados
    const period = data.length;
    chartContainer.setAttribute('data-period', period);
    
    const dailyGoal = <?php echo $total_daily_calories_goal; ?>;
    
    let chartHTML = '';
    data.forEach(day => {
        const percentage = dailyGoal > 0 ? Math.round((day.kcal_consumed / dailyGoal) * 100 * 10) / 10 : 0;
        
        let status = 'poor';
        if (percentage >= 90) {
            status = 'excellent';
        } else if (percentage >= 70) {
            status = 'good';
        } else if (percentage >= 50) {
            status = 'fair';
        }
        
        let barHeight = 0;
        if (percentage === 0) {
            barHeight = 0;
        } else if (percentage >= 100) {
            barHeight = 160 + Math.min((percentage - 100) * 0.4, 40);
        } else {
            barHeight = (percentage / 100) * 160;
        }
        
        chartHTML += `
            <div class="improved-bar-container">
                <div class="improved-bar-wrapper">
                    <div class="improved-bar ${status}" style="height: ${barHeight}px"></div>
                    <div class="bar-percentage-text">${percentage}%</div>
                    <div class="improved-goal-line"></div>
                </div>
                <div class="improved-bar-info">
                    <span class="improved-date">${new Date(day.date).toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit'})}</span>
                    <span class="improved-ml">${day.kcal_consumed} kcal</span>
                </div>
            </div>
        `;
    });
    
    chartContainer.innerHTML = chartHTML;
}

// Função para resetar botões de período para 7 dias
function resetPeriodButtons() {
    // Resetar botões de hidratação
    const hydrationButtons = document.querySelectorAll('#tab-hydration .period-btn');
    hydrationButtons.forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-period') === '7') {
            btn.classList.add('active');
        }
    });
    
    // Resetar botões de nutrientes
    const nutrientsButtons = document.querySelectorAll('#tab-nutrients .period-btn');
    nutrientsButtons.forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-period') === '7') {
            btn.classList.add('active');
        }
    });
    
    // Resetar dados dos gráficos para 7 dias
    const hydrationBars = document.getElementById('hydration-bars');
    if (hydrationBars) {
        hydrationBars.setAttribute('data-period', '7');
        loadHydrationData(7);
    }
    
    const nutrientsBars = document.getElementById('nutrients-bars');
    if (nutrientsBars) {
        nutrientsBars.setAttribute('data-period', '7');
        loadNutrientsData(7);
    }
}
// Inicializar layout correto quando a página carrega
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar gráfico de hidratação com 7 dias
    const hydrationBars = document.getElementById('hydration-bars');
    if (hydrationBars) {
        hydrationBars.setAttribute('data-period', '7');
    }
    
    // Inicializar gráfico de nutrientes com 7 dias
    const nutrientsBars = document.getElementById('nutrients-bars');
    if (nutrientsBars) {
        nutrientsBars.setAttribute('data-period', '7');
    }
    
    // Pré-posicionar o slider da aba Rotina no dia de HOJE (evita flicker ao abrir a aba)
    (function prepositionRoutineSlider() {
        const track = document.getElementById('routineSliderTrack');
        if (!track) return;
        const cards = track.querySelectorAll('.diary-day-card');
        if (cards.length === 0) return;
        const todayStr = new Date().toISOString().split('T')[0];
        let idx = cards.length - 1;
        for (let i = 0; i < cards.length; i++) {
            if (cards[i].getAttribute('data-date') === todayStr) { idx = i; break; }
        }
        track.style.transition = 'none';
        track.style.transform = `translateX(${-idx * 100}%)`;
    })();

    // Adicionar listeners para mudança de abas
    const tabLinks = document.querySelectorAll('.tab-link');
    tabLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Aguardar um pouco para a aba ser ativada
            setTimeout(() => {
                resetPeriodButtons();
            }, 100);
            
            // Atualizar dados da rotina quando clicada
            if (this.dataset.tab === 'routine') {
                setTimeout(() => {
                    updateRoutineDisplay(7); // Inicializa com 7 dias
                }, 100);
            }
        });
    });
    
    // Inicializar dados da rotina
    updateRoutineDisplay(7);
    
    // Adicionar listeners para botões de período da rotina
    document.querySelectorAll('#tab-routine .routine-period-btn').forEach(button => {
        button.addEventListener('click', function() {
            // Remover classe active de todos os botões
            document.querySelectorAll('#tab-routine .routine-period-btn').forEach(btn => btn.classList.remove('routine-active'));
            
            // Adicionar classe active ao botão clicado
            this.classList.add('routine-active');
            
            // Atualizar período atual
            const period = parseInt(this.dataset.period);
            
            // Atualizar dados
            updateRoutineDisplay(period);
        });
    });
    
    // ============ NOVO CÓDIGO: CALENDÁRIO DA ROTINA ============
    
    // Variável global para o dia selecionado
    let selectedRoutineDay = null;
    
    // Função para atualizar dados da rotina (compatibilidade com código existente)
    function updateRoutineDisplay(period) {
        // Esta função é chamada pelo código existente, mas não é necessária para a nova implementação
        // Mantida para compatibilidade
        console.log('updateRoutineDisplay chamada com período:', period);
    }
    
    // Dados movidos para escopo global
    
    // Função para inicializar o calendário da rotina
    function initRoutineCalendar() {
        generateRoutineSlider();
        updateRoutineSummary();
    }
    
    // Gerar slider de dias da rotina (igual ao da aba Diário)
    window.generateRoutineSlider = function() {
        const sliderTrack = document.getElementById('routineSliderTrack');
        if (!sliderTrack) {
            console.error('Slider track não encontrado!');
            return;
        }
        
        const daysToShow = 7; // Mostrar 7 dias
        
        let sliderHTML = '';
        
        for (let i = daysToShow - 1; i >= 0; i--) {
            const today = new Date();
            const date = new Date(today);
            date.setDate(date.getDate() - i);
            const dateStr = date.toISOString().split('T')[0];
            
            // Verificar se tem dados
            const hasData = routineLogData.some(log => log.date === dateStr) || 
                           sleepData.some(sleep => sleep.date === dateStr);
            
            const isToday = i === 0;
            
            // Formatar data
            const dayOfWeek = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][date.getDay()];
            const dayNumber = date.getDate();
            const monthName = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'][date.getMonth()];
            
            // Calcular resumo do dia - APENAS missões concluídas
    const dayMissions = routineLogData.filter(log => log.date === dateStr && Number(log.is_completed) === 1);
            const dayExercises = exerciseData.filter(ex => ex.updated_at && ex.updated_at.startsWith(dateStr));
            const daySleep = sleepData.filter(sleep => sleep.date === dateStr);
            
            // Gerar conteúdo baseado nos dados reais
            let contentHTML = '';
            if (hasData) {
                // Missões concluídas - mostrar cada uma individualmente
                if (dayMissions.length > 0) {
                    dayMissions.forEach(mission => {
                        const missionItem = (typeof routineItemsData !== 'undefined') ? routineItemsData.find(item => item.id === mission.routine_item_id) : null;
                        const baseIcon = missionItem?.icon_class || 'fa-clipboard-check';
                        const iconClass = `fas ${baseIcon}`;
                        const title = missionItem?.title || mission.title || `Missão #${mission.routine_item_id}`;
                        const duration = mission.exercise_duration_minutes || 0;
                        const hours = Math.floor(duration / 60);
                        const minutes = duration % 60;
                        const durationStr = duration > 0 ? (hours > 0 ? `${hours}h${minutes > 0 ? minutes.toString().padStart(2, '0') : ''}` : `${minutes}min`) : '';
                        const completedAt = mission.completed_at || mission.updated_at || '';
                        const timeStr = completedAt ? new Date(completedAt.replace(' ', 'T')).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';

                        contentHTML += `
                            <div class="diary-meal-card">
                                <div class="diary-meal-header">
                                    <div class="diary-meal-icon">
                                        <i class="${iconClass}"></i>
                                    </div>
                                    <div class="diary-meal-info">
                                        <h5>${title}</h5>
                                        <span class="diary-meal-totals">
                                            ${timeStr ? `<strong>${timeStr}</strong> • ` : ''}${durationStr ? `${durationStr} de duração` : 'Concluída'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
                
                // Sono registrado
                if (daySleep.length > 0 && daySleep[0].hours) {
                    const sleepHours = daySleep[0].hours;
                    const hours = Math.floor(sleepHours);
                    const minutes = Math.round((sleepHours - hours) * 60);
                    const timeStr = `${hours}h${minutes > 0 ? minutes.toString().padStart(2, '0') : ''}`;
                    
                    contentHTML += `
                        <div class="diary-meal-card">
                            <div class="diary-meal-header">
                                <div class="diary-meal-icon">
                                    <i class="fas fa-bed"></i>
                                </div>
                                <div class="diary-meal-info">
                                    <h5>Sono Registrado</h5>
                                    <span class="diary-meal-totals">
                                        <strong>${timeStr}</strong> de sono
                                    </span>
                                </div>
                            </div>
                        </div>
                    `;
                }
            }
            
            sliderHTML += `
                <div class="diary-day-card ${hasData ? 'has-data' : ''} ${isToday ? 'today' : ''}" 
                     data-date="${dateStr}" 
                     onclick="selectRoutineDay('${dateStr}')">
                    
                    <div class="diary-day-summary" style="display: none;">
                        <div class="diary-summary-item">
                            <i class="fas fa-check-circle"></i>
                            <span>${dayMissions.length} missões</span>
                        </div>
                        <div class="diary-summary-macros">
                            ${dayExercises.length} exercícios • ${daySleep.length > 0 && daySleep[0].hours ? daySleep[0].hours.toFixed(1) + 'h sono' : 'Sem sono'}
                        </div>
                    </div>
                    
                    <div class="diary-day-meals">
                        ${hasData ? contentHTML : `
                            <div class="diary-empty-state">
                                <i class="fas fa-calendar-day"></i>
                                <p>Nenhum registro neste dia</p>
                            </div>
                        `}
                    </div>
                </div>
            `;
        }
        
        sliderTrack.innerHTML = sliderHTML;
        console.log('HTML gerado:', sliderHTML);
        
        // Atualizar navegação
        updateRoutineCards();
        
        // Selecionar o dia atual automaticamente (hoje)
        const today = new Date();
        const todayStr = today.toISOString().split('T')[0];
        const todayCard = document.querySelector(`#routineSliderTrack .diary-day-card[data-date="${todayStr}"]`);
        console.log('Card do dia atual encontrado:', todayCard);
        if (todayCard) {
            todayCard.classList.add('selected');
            selectedRoutineDay = todayStr;
        }
        
        // Atualizar display para o dia de hoje
        currentRoutineIndex = Array.from(routineCards).findIndex(card => card.getAttribute('data-date') === todayStr);
        if (currentRoutineIndex === -1) {
            currentRoutineIndex = routineCards.length - 1;
        }
        updateRoutineSliderDisplay();
        
    };
    
    // Função para exibir detalhes do dia selecionado
    function showRoutineDayDetails(dateString) {
        const detailsContainer = document.getElementById('routine-day-details');
        if (!detailsContainer) return;
        
        // Mostrar a seção de detalhes
        detailsContainer.style.display = 'grid';
        
        // Filtrar dados do dia
        const dayRoutines = routineLogData.filter(log => log.date === dateString);
        const dayExercises = exerciseData.filter(ex => ex.updated_at.startsWith(dateString));
        const daySleep = sleepData.find(sl => sl.date === dateString);
        
        // Atualizar missões
        updateMissionsList(dayRoutines);
        
        // Atualizar atividades físicas
        updateActivitiesList(dayExercises);
        
        // Atualizar sono
        updateSleepInfo(daySleep);
    }
    
    // Função para atualizar lista de missões
    function updateMissionsList(routines) {
        const progressText = document.getElementById('missions-progress-text');
        const progressBar = document.getElementById('missions-progress-bar');
        const missionsList = document.getElementById('missions-list');
        
        if (!missionsList) return;
        
        // Calcular progresso
        const totalMissions = routines.length;
        const completedMissions = routines.filter(r => r.is_completed == 1).length;
        const progressPercent = totalMissions > 0 ? (completedMissions / totalMissions) * 100 : 0;
        
        // Atualizar texto de progresso
        if (progressText) {
            progressText.textContent = `${completedMissions} de ${totalMissions} missões concluídas`;
        }
        
        // Atualizar barra de progresso
        if (progressBar) {
            progressBar.style.width = progressPercent + '%';
        }
        
        // Limpar lista
        missionsList.innerHTML = '';
        
        if (totalMissions === 0) {
            missionsList.innerHTML = '<p style="color: var(--secondary-text-color); text-align: center; padding: 20px;">Nenhuma missão registrada para este dia.</p>';
            return;
        }
        
        // Adicionar missões
        routines.forEach(routine => {
            const missionItem = document.createElement('div');
            missionItem.className = 'mission-item';
            
            const isCompleted = routine.is_completed == 1;
            const statusClass = isCompleted ? 'completed' : 'pending';
            const statusIcon = isCompleted ? '✓' : '✕';
            
            let durationText = '';
            if (routine.exercise_duration_minutes && routine.exercise_duration_minutes > 0) {
                const hours = Math.floor(routine.exercise_duration_minutes / 60);
                const mins = routine.exercise_duration_minutes % 60;
                durationText = hours > 0 ? `${hours}h${mins > 0 ? mins.toString().padStart(2, '0') : ''}` : `${mins}min`;
            }
            
            missionItem.innerHTML = `
                <div class="mission-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 6v6l4 2"/>
                    </svg>
                </div>
                <div class="mission-info">
                    <div class="mission-name">Missão #${routine.routine_item_id}</div>
                    ${durationText ? `<div class="mission-duration">${durationText}</div>` : ''}
                </div>
                <div class="mission-status ${statusClass}">
                    <span>${statusIcon}</span>
                    <span>${isCompleted ? 'Concluída' : 'Pendente'}</span>
                </div>
            `;
            
            missionsList.appendChild(missionItem);
        });
    }
    
    // Função para atualizar lista de atividades físicas
    function updateActivitiesList(exercises) {
        const activitiesList = document.getElementById('activities-list');
        if (!activitiesList) return;
        
        // Limpar lista
        activitiesList.innerHTML = '';
        
        if (exercises.length === 0) {
            activitiesList.innerHTML = '<p style="color: var(--secondary-text-color); text-align: center; padding: 20px;">Nenhuma atividade registrada para este dia.</p>';
            return;
        }
        
        // Adicionar atividades
        exercises.forEach(exercise => {
            const activityItem = document.createElement('div');
            activityItem.className = 'activity-item';
            
            const hours = Math.floor(exercise.duration_minutes / 60);
            const mins = exercise.duration_minutes % 60;
            const durationText = hours > 0 ? `${hours}h${mins > 0 ? mins.toString().padStart(2, '0') : ''}` : `${mins}min`;
            
            activityItem.innerHTML = `
                <div class="activity-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6.5 6.5m-2.5 0a2.5 2.5 0 1 0 5 0a2.5 2.5 0 1 0 -5 0"/>
                        <path d="M17.5 17.5m-2.5 0a2.5 2.5 0 1 0 5 0a2.5 2.5 0 1 0 -5 0"/>
                        <path d="M6.5 9v3l3.5 3l3.5 -3v-3"/>
                        <path d="M17.5 15v-3l-3.5 -3l-3.5 3v3"/>
                    </svg>
                </div>
                <div class="activity-info">
                    <div class="activity-name">${exercise.exercise_name}</div>
                    <div class="activity-duration">${durationText}</div>
                </div>
            `;
            
            activitiesList.appendChild(activityItem);
        });
    }
    
    // Função para atualizar informações de sono
    function updateSleepInfo(sleepData) {
        const sleepValue = document.getElementById('sleep-hours-value');
        const sleepProgressBar = document.getElementById('sleep-progress-bar');
        const sleepProgressText = document.getElementById('sleep-progress-text');
        
        if (!sleepValue) return;
        
        if (!sleepData || !sleepData.sleep_hours || sleepData.sleep_hours == 0) {
            sleepValue.textContent = '--';
            if (sleepProgressBar) sleepProgressBar.style.width = '0%';
            if (sleepProgressText) sleepProgressText.textContent = 'Nenhum registro de sono para este dia';
            return;
        }
        
        const hours = Math.floor(sleepData.sleep_hours);
        const mins = Math.round((sleepData.sleep_hours - hours) * 60);
        const timeText = `${hours}h${mins > 0 ? mins.toString().padStart(2, '0') : ''}`;
        
        sleepValue.textContent = timeText;
        
        // Meta de sono (8 horas como padrão)
        const sleepGoal = 8;
        const progressPercent = Math.min((sleepData.sleep_hours / sleepGoal) * 100, 100);
        
        if (sleepProgressBar) {
            sleepProgressBar.style.width = progressPercent + '%';
        }
        
        if (sleepProgressText) {
            const diff = sleepData.sleep_hours - sleepGoal;
            if (diff >= 0) {
                sleepProgressText.textContent = `Meta: ${sleepGoal}h — dentro da média`;
            } else {
                sleepProgressText.textContent = `Meta: ${sleepGoal}h — ${Math.abs(diff).toFixed(1)}h abaixo da meta`;
            }
        }
    }
    
    // Inicializar calendário quando a aba de rotina for aberta
    const routineTabButton = document.querySelector('[data-tab="routine"]');
    if (routineTabButton) {
        routineTabButton.addEventListener('click', function() {
                // NÃO chamar generateRoutineSlider porque o PHP já gera os cards
                // Apenas inicializar navegação e display
                updateRoutineCards();
                
                
                // Buscar o card do dia de HOJE
                const today = new Date();
                const todayStr = today.toISOString().split('T')[0];
                
                
                // Procurar o índice do card de hoje
                let targetIndex = routineCards.length - 1; // Default: último card
                
                for (let i = 0; i < routineCards.length; i++) {
                    if (routineCards[i].getAttribute('data-date') === todayStr) {
                        targetIndex = i;
                        console.log('ENCONTROU o card de hoje no índice:', targetIndex);
                        break;
                    }
                }
                
                console.log('Target index (antes de atualizar):', targetIndex);
                currentRoutineIndex = targetIndex;
                console.log('currentRoutineIndex definido como:', currentRoutineIndex);
                
                // Posicionar o slider IMEDIATAMENTE no dia de hoje (sem animação) para evitar flicker
                if (routineTrack) {
                    routineTrack.style.transition = 'none';
                    const initialOffset = -currentRoutineIndex * 100;
                    routineTrack.style.transform = `translateX(${initialOffset}%)`;
                }
            
            // Atualizar cabeçalho imediatamente (sem depender da função de display)
            const dateObj = new Date(todayStr + 'T00:00:00');
            const monthNamesShort = ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];
            const monthNamesLower = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
            const weekdayNames = ['DOMINGO', 'SEGUNDA', 'TERÇA', 'QUARTA', 'QUINTA', 'SEXTA', 'SÁBADO'];
            const routineYear = document.getElementById('routineYear');
            const dayMonth = document.getElementById('routineDayMonth');
            const weekday = document.getElementById('routineWeekday');
            const prevDate = document.getElementById('routinePrevDate');
            const nextDate = document.getElementById('routineNextDate');
            if (routineYear) routineYear.textContent = dateObj.getFullYear();
            if (dayMonth) dayMonth.textContent = `${dateObj.getDate()} ${monthNamesShort[dateObj.getMonth()]}`;
            if (weekday) weekday.textContent = weekdayNames[dateObj.getDay()];
            // prev/next labels
            if (prevDate) {
                const prev = new Date(dateObj); prev.setDate(prev.getDate() - 1);
                prevDate.textContent = `${prev.getDate()} ${monthNamesLower[prev.getMonth()]}`;
                if (prevDate.parentElement) prevDate.parentElement.style.visibility = 'visible';
            }
            if (nextDate) {
                const cards = routineCards;
                const nextIndex = currentRoutineIndex + 1;
                const today = new Date(); today.setHours(0,0,0,0);
                if (nextIndex < cards.length && cards[nextIndex]) {
                    const nd = new Date(cards[nextIndex].getAttribute('data-date') + 'T00:00:00');
                    if (nd <= today) {
                        nextDate.textContent = `${nd.getDate()} ${monthNamesLower[nd.getMonth()]}`;
                        if (nextDate.parentElement) nextDate.parentElement.style.visibility = 'visible';
                    } else if (nextDate.parentElement) {
                        nextDate.parentElement.style.visibility = 'hidden';
                    }
                } else if (nextDate.parentElement) {
                    nextDate.parentElement.style.visibility = 'hidden';
                }
            }
                
                // Aguardar carregamento dos dados antes de atualizar display
                loadMissionsAdminList();
                loadExercisesAdminList();
                
                // Aguardar um pouco para os dados carregarem e o DOM aplicar o transform
                setTimeout(() => {
                    console.log('Chamando updateRoutineSliderDisplay() com currentRoutineIndex:', currentRoutineIndex);
                    updateRoutineSliderDisplay();
                }, 200);
        });
    }
    
    // Carregar missões ao abrir a aba Rotina pela primeira vez
    if (document.getElementById('tab-routine') && document.getElementById('tab-routine').style.display !== 'none') {
        loadMissionsAdminList();
        loadExercisesAdminList();
    }
    
    // ============ CRUD DE MISSÕES ============
    
    // Modal de missões
    const missionModal = document.getElementById('mission-modal');
    const addMissionBtn = document.getElementById('add-mission-btn');
    const cancelMissionBtn = document.getElementById('cancel-mission-btn');
    const saveMissionBtn = document.getElementById('save-mission-btn');
    
    // Carregar lista de missões
    function loadMissionsAdminList() {
        // Só carregar se estivermos na aba Rotina
        const routineTab = document.getElementById('tab-routine');
        if (routineTab && routineTab.style.display === 'none') return;
        
        const container = document.getElementById('missions-container');
        if (!container) return;
        
        // Mostrar loading
        container.innerHTML = `
            <div class="loading-missions">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Carregando missões...</span>
            </div>
        `;
        
        fetch(`api/routine_crud.php?action=list_missions&patient_id=${patientId}`)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    window.routineItemsData = result.data;
                    renderMissionsGrid(result.data);
                } else {
                    showEmptyMissions('Erro ao carregar missões');
                }
            })
            .catch(error => {
                console.error('Erro ao carregar missões:', error);
                showEmptyMissions('Erro ao carregar missões');
            });
    }
    
    // Renderizar grid de missões
    function renderMissionsGrid(missions) {
        const container = document.getElementById('missions-container');
        if (!container) return;
        
        if (missions.length === 0) {
            showEmptyMissions();
            return;
        }
        
        container.innerHTML = missions.map(mission => `
            <div class="mission-item" data-id="${mission.id}">
                <div class="mission-header">
                    <div class="mission-icon">
                        <i class="${mission.icon_class || 'fa-tasks'}"></i>
                    </div>
                    <div class="mission-info">
                        <h4>${mission.title}</h4>
                        <div class="mission-type">${mission.is_exercise ? 'Exercício' : 'Sim/Não'}</div>
                    </div>
                </div>
                <div class="mission-actions">
                    <button class="btn-edit" onclick="editMission(${mission.id})">
                        <i class="fas fa-edit"></i>
                        Editar
                    </button>
                    <button class="btn-delete" onclick="deleteMission(${mission.id}, '${mission.title.replace(/'/g, "\\'")}')">
                        <i class="fas fa-trash"></i>
                        Excluir
                    </button>
                </div>
            </div>
        `).join('');
    }
    
    // Mostrar estado vazio
    function showEmptyMissions(message = 'Nenhuma missão cadastrada') {
        const container = document.getElementById('missions-container');
        if (!container) return;
        
        container.innerHTML = `
            <div class="empty-missions">
                <i class="fas fa-tasks"></i>
                <h4>${message}</h4>
                <p>Clique em "Adicionar Missão" para criar a primeira missão personalizada</p>
            </div>
        `;
    }
    
    // Inicializar seletor de ícones
    function initIconPicker() {
        const picker = document.getElementById('iconPicker');
        if (!picker) return;
        
        const icons = [
            'fa-dumbbell', 'fa-running', 'fa-bicycle', 'fa-walking', 'fa-heart', 'fa-heartbeat', 'fa-fire',
            'fa-apple-alt', 'fa-seedling', 'fa-leaf', 'fa-carrot', 'fa-utensils', 'fa-water', 'fa-tint',
            'fa-bed', 'fa-moon', 'fa-sun', 'fa-clock', 'fa-check-circle', 'fa-clipboard-check', 'fa-weight',
            'fa-brain', 'fa-spa', 'fa-yin-yang', 'fa-meditation', 'fa-bolt', 'fa-dumbbell', 'fa-swimming-pool',
            'fa-basketball-ball', 'fa-volleyball-ball', 'fa-football-ball', 'fa-table-tennis', 'fa-golf-ball',
            'fa-hiking', 'fa-mountain', 'fa-tree', 'fa-paw', 'fa-dog', 'fa-cat', 'fa-fish', 'fa-bug'
        ];
        
        picker.innerHTML = icons.map(icon => `
            <div class="icon-option" data-icon="${icon}">
                <i class="fas ${icon}"></i>
            </div>
        `).join('');
        
        // Adicionar event listeners
        picker.querySelectorAll('.icon-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remover seleção anterior
                picker.querySelectorAll('.icon-option').forEach(opt => opt.classList.remove('selected'));
                // Selecionar atual
                this.classList.add('selected');
                // Atualizar input hidden
                document.getElementById('missionId').setAttribute('data-selected-icon', this.dataset.icon);
            });
        });
    }
    
    // ============ GERENCIAMENTO DE EXERCÍCIOS ============
    
    // Carregar lista de exercícios
    function loadExercisesAdminList() {
        
        fetch(`api/routine_crud.php?action=list_exercises&patient_id=${patientId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text(); // Primeiro como texto para debug
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const result = JSON.parse(text);
                    console.log('Parsed result:', result);
                    if (result.success) {
                        console.log('Dados dos exercícios:', result.data);
                        renderExercisesTable(result.data);
                    } else {
                        console.error('Erro ao carregar exercícios:', result.message);
                    }
                } catch (e) {
                    console.error('Erro ao fazer parse do JSON:', e);
                    console.error('Resposta recebida:', text);
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                console.error('Stack trace:', error.stack);
            })
            .finally(() => {
            });
    }
    
    // Renderizar tabela de exercícios
    function renderExercisesTable(exercises) {
        const tbody = document.querySelector('#exercises-admin-table tbody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        if (exercises.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--secondary-text-color); padding: 20px;">Nenhum exercício cadastrado</td></tr>';
            return;
        }
        
        exercises.forEach(exercise => {
            const row = document.createElement('tr');
            
            row.innerHTML = `
                <td style="padding: 12px;">
                    <strong>${exercise.activity_name}</strong>
                </td>
                <td style="padding: 12px;">
                    ${new Date(exercise.completion_date).toLocaleDateString('pt-BR')}
                </td>
                <td style="padding: 12px; text-align: center;">
                    <div class="mission-table-actions" style="display: flex; gap: 8px; justify-content: center;">
                        <button class="action-btn" onclick="editExercise(${exercise.id})" title="Editar" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: var(--glass-bg); border: 1px solid var(--border-color); border-radius: 6px; cursor: pointer; transition: all 0.2s; color: var(--secondary-text-color);">
                            <i class="fa fa-edit" style="font-size: 14px;"></i>
                        </button>
                        <button class="action-btn delete" onclick="deleteExercise(${exercise.id}, '${exercise.activity_name.replace(/'/g, "\\'")}')" title="Excluir" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: var(--glass-bg); border: 1px solid var(--border-color); border-radius: 6px; cursor: pointer; transition: all 0.2s; color: var(--secondary-text-color);">
                            <i class="fa fa-trash" style="font-size: 14px;"></i>
                        </button>
                    </div>
                </td>
            `;
            
            tbody.appendChild(row);
        });
    }
    // Funções de exercícios movidas para escopo global
    
    // Salvar exercício
    document.getElementById('exercise-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const exerciseId = document.getElementById('exercise-id').value;
        const exerciseName = document.getElementById('exercise-name').value.trim();
        const exerciseDate = document.getElementById('exercise-date').value;
        
        if (!exerciseName) {
            alert('Por favor, insira o nome do exercício.');
            return;
        }
        
        const data = {
            activity_name: exerciseName,
            completion_date: exerciseDate
        };
        
        const isEdit = exerciseId !== '';
        if (isEdit) {
            data.id = parseInt(exerciseId);
        }
        
        const action = isEdit ? 'update_exercise' : 'create_exercise';
        
        // Desabilitar botão enquanto processa
        const saveBtn = this.querySelector('button[type="submit"]');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Salvando...';
        
        fetch(`api/routine_crud.php?action=${action}&patient_id=${patientId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Salvar';
            
            if (result.success) {
                alert(isEdit ? 'Exercício atualizado com sucesso!' : 'Exercício adicionado com sucesso!');
                closeExerciseModal();
                loadExercisesAdminList();
            } else {
                alert('Erro ao salvar exercício: ' + result.message);
            }
        })
        .catch(error => {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Salvar';
            console.error('Erro ao salvar exercício:', error);
            alert('Erro ao salvar exercício');
        });
    });
    
    // Fechar modal ao clicar fora
    window.addEventListener('click', function(event) {
        if (event.target === document.getElementById('exercise-modal')) {
            closeExerciseModal();
        }
    });
    
    // Carregar listas quando a aba de rotina for aberta
    const routineTabBtn = document.querySelector('[data-tab="routine"]');
    if (routineTabBtn) {
        routineTabBtn.addEventListener('click', function() {
            setTimeout(() => {
                loadMissionsAdminList();
                loadExercisesAdminList();
            }, 200);
        });
    }
});

// ============ FUNÇÕES GLOBAIS PARA ROTINA ============

// Definir patientId globalmente
const patientId = <?php echo $user_id; ?>;

// Dados de rotina do PHP (escopo global)
const routineLogData = <?php echo json_encode($routine_log_data); ?>;
const exerciseData = <?php echo json_encode($routine_exercise_data); ?>;
const sleepData = <?php echo json_encode($routine_sleep_data); ?>;
const routineItemsData = <?php echo json_encode($routine_items_data); ?>;
const onboardingActivities = <?php echo json_encode($onboarding_activities); ?>;

// ============ FUNÇÕES GLOBAIS PARA MISSÕES ============

// Abrir modal de missão
window.openMissionModal = function() {
    const modal = document.getElementById('missionModal');
    if (!modal) return;
    
    // Limpar formulário
    document.getElementById('missionId').value = '';
    document.getElementById('missionName').value = '';
    document.getElementById('missionType').value = 'binary';
    document.getElementById('missionModalTitle').textContent = 'Adicionar Nova Missão';
    document.getElementById('saveButtonText').textContent = 'Salvar Missão';
    
    // Mostrar modal
    modal.style.display = 'flex';
    
    // Inicializar seletor de ícones
    setTimeout(() => {
        initIconPicker();
    }, 100);
};

// Fechar modal de missão
window.closeMissionModal = function() {
    const modal = document.getElementById('missionModal');
    if (modal) {
        modal.style.display = 'none';
    }
};

// Editar missão
window.editMission = function(id) {
    fetch(`api/routine_crud.php?action=get_mission&id=${id}&patient_id=${patientId}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                const mission = result.data;
                document.getElementById('missionId').value = mission.id;
                document.getElementById('missionName').value = mission.title;
                document.getElementById('missionType').value = mission.is_exercise ? 'duration' : 'binary';
                document.getElementById('missionModalTitle').textContent = 'Editar Missão';
                document.getElementById('saveButtonText').textContent = 'Atualizar Missão';
                
                // Mostrar modal
                const modal = document.getElementById('missionModal');
                if (modal) {
                    modal.style.display = 'flex';
                    
                    // Inicializar seletor de ícones com seleção
                    setTimeout(() => {
                        initIconPicker();
                        if (mission.icon_class) {
                            const selectedIcon = document.querySelector(`[data-icon="${mission.icon_class}"]`);
                            if (selectedIcon) {
                                selectedIcon.classList.add('selected');
                                document.getElementById('missionId').setAttribute('data-selected-icon', mission.icon_class);
                            }
                        }
                    }, 100);
                }
            } else {
                alert('Erro ao carregar missão: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar missão:', error);
            alert('Erro ao carregar missão');
        });
};

// Excluir missão
window.deleteMission = function(id, name) {
    if (confirm(`Tem certeza que deseja excluir a missão "${name}"?`)) {
        fetch(`api/routine_crud.php?action=delete_mission&id=${id}&patient_id=${patientId}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Missão excluída com sucesso!');
                loadMissionsAdminList();
            } else {
                alert('Erro ao excluir missão: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Erro ao excluir missão:', error);
            alert('Erro ao excluir missão');
        });
    }
};

// Event listener para o formulário de missão
document.addEventListener('DOMContentLoaded', function() {
    const missionForm = document.getElementById('missionForm');
    if (missionForm) {
        missionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const missionId = document.getElementById('missionId').value;
            const missionName = document.getElementById('missionName').value.trim();
            const missionType = document.getElementById('missionType').value;
            const selectedIcon = document.getElementById('missionId').getAttribute('data-selected-icon') || 'fa-tasks';
            
            if (!missionName) {
                alert('Por favor, insira o nome da missão.');
                return;
            }
            
            const data = {
                title: missionName,
                is_exercise: missionType === 'duration' ? 1 : 0,
                icon_class: selectedIcon
            };
            
            const isEdit = missionId !== '';
            if (isEdit) {
                data.id = parseInt(missionId);
            }
            
            const action = isEdit ? 'update_mission' : 'create_mission';
            const saveButton = document.querySelector('#missionModal .btn-save');
            
            // Desabilitar botão enquanto processa
            saveButton.disabled = true;
            saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            
            fetch(`api/routine_crud.php?action=${action}&patient_id=${patientId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                saveButton.disabled = false;
                saveButton.innerHTML = '<i class="fas fa-save"></i> <span id="saveButtonText">Salvar Missão</span>';
                
                if (result.success) {
                    alert(isEdit ? 'Missão atualizada com sucesso!' : 'Missão criada com sucesso!');
                    closeMissionModal();
                    loadMissionsAdminList();
                } else {
                    alert('Erro ao salvar missão: ' + result.message);
                }
            })
            .catch(error => {
                saveButton.disabled = false;
                saveButton.innerHTML = '<i class="fas fa-save"></i> <span id="saveButtonText">Salvar Missão</span>';
                console.error('Erro ao salvar missão:', error);
                alert('Erro ao salvar missão');
            });
        });
    }
});

// Abrir modal de exercício
window.openExerciseModal = function() {
    // Limpar formulário
    document.getElementById('exercise-id').value = '';
    document.getElementById('exercise-name').value = '';
    document.getElementById('exercise-date').value = new Date().toISOString().split('T')[0];
    
    // Mostrar modal
    document.getElementById('exercise-modal').style.display = 'flex';
};

// Fechar modal de exercício
window.closeExerciseModal = function() {
    document.getElementById('exercise-modal').style.display = 'none';
};

// Editar exercício (função global)
window.editExercise = function(id) {
    fetch(`api/routine_crud.php?action=get_exercise&id=${id}&patient_id=${patientId}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                const exercise = result.data;
                
                // Preencher formulário
                document.getElementById('exercise-id').value = exercise.id;
                document.getElementById('exercise-name').value = exercise.activity_name;
                document.getElementById('exercise-date').value = exercise.completion_date;
                
                // Mostrar modal
                document.getElementById('exercise-modal').style.display = 'flex';
            } else {
                alert('Erro ao carregar exercício: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar exercício:', error);
            alert('Erro ao carregar exercício');
        });
};

// Excluir exercício (função global)
window.deleteExercise = function(id, name) {
    if (!confirm(`Tem certeza que deseja excluir o exercício "${name}"?`)) {
        return;
    }
    
    fetch('api/routine_crud.php?action=delete_exercise', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Exercício excluído com sucesso!');
            // Recarregar a página para atualizar a lista
            location.reload();
        } else {
            alert('Erro ao excluir exercício: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Erro ao excluir exercício:', error);
        alert('Erro ao excluir exercício');
    });
};

// ============ FUNÇÕES GLOBAIS DO CALENDÁRIO DA ROTINA ============

// Abrir modal do calendário da rotina
window.openRoutineCalendar = function() {
    currentRoutineCalendarDate = new Date();
    renderRoutineCalendar();
    document.body.style.overflow = 'hidden';
    const modal = document.getElementById('routineCalendarModal');
    if (modal) {
        modal.classList.add('active');
    } else {
        console.error('Modal routineCalendarModal não encontrado!');
    }
};

// Fechar modal do calendário da rotina
window.closeRoutineCalendar = function() {
    document.getElementById('routineCalendarModal').classList.remove('active');
    document.body.style.overflow = '';
};

// Mudar mês no calendário da rotina
window.changeRoutineCalendarMonth = function(direction) {
    const newDate = new Date(currentRoutineCalendarDate);
    newDate.setMonth(newDate.getMonth() + direction);
    
    // Não permitir ir além do mês atual
    const now = new Date();
    if (newDate.getFullYear() > now.getFullYear() || 
        (newDate.getFullYear() === now.getFullYear() && newDate.getMonth() > now.getMonth())) {
        return; // Não avança
    }
    
    currentRoutineCalendarDate = newDate;
    renderRoutineCalendar();
};

// Renderizar calendário da rotina
function renderRoutineCalendar() {
    const year = currentRoutineCalendarDate.getFullYear();
    const month = currentRoutineCalendarDate.getMonth();
    
    // Atualizar ano e mês
    const monthNamesShort = ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN',
                            'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];
    document.querySelector('#routineCalendarModal .calendar-year').textContent = year;
    document.querySelector('#routineCalendarModal .calendar-month').textContent = monthNamesShort[month];
    
    // Verificar se estamos no mês atual para desabilitar setinha de próximo mês
    const today = new Date();
    const isCurrentMonth = (year === today.getFullYear() && month === today.getMonth());
    const nextMonthBtn = document.getElementById('routineNextMonthBtn');
    
    if (isCurrentMonth) {
        nextMonthBtn.classList.add('disabled');
        nextMonthBtn.disabled = true;
    } else {
        nextMonthBtn.classList.remove('disabled');
        nextMonthBtn.disabled = false;
    }
    
    // Gerar grid dos dias
    const daysGrid = document.getElementById('routineCalendarDaysGrid');
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDayOfWeek = firstDay.getDay();
    const prevMonth = new Date(year, month, 0);
    const daysInPrevMonth = prevMonth.getDate();
    
    let calendarHTML = '';
    
    // Dias do mês anterior (bloqueados), preenchendo o início da grade
    for (let i = startingDayOfWeek - 1; i >= 0; i--) {
        const dayNum = daysInPrevMonth - i;
        calendarHTML += `<div class="calendar-day other-month">${dayNum}</div>`;
    }
    
    // Dias do mês
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const hasData = routineLogData.some(log => log.date === dateStr) || 
                       exerciseData.some(ex => ex.updated_at.startsWith(dateStr)) ||
                       sleepData.some(sleep => sleep.date === dateStr);
        const isToday = day === today.getDate() && month === today.getMonth() && year === today.getFullYear();
        const currentDateObj = new Date(year, month, day);
        const isFuture = currentDateObj > today;
        
        const classes = [
            'calendar-day',
            hasData ? 'has-data' : '',
            isToday ? 'today' : '',
            isFuture ? 'future-day' : ''
        ].filter(Boolean).join(' ');
        
        const clickAttr = isFuture ? '' : `onclick="selectRoutineDayFromCalendar('${dateStr}')"`;
        
        calendarHTML += `
            <div class="${classes}" data-date="${dateStr}" ${clickAttr}>
                <span class="day-number">${day}</span>
                ${hasData ? '<div class="day-indicator"></div>' : ''}
            </div>
        `;
    }
    
    // Dias do próximo mês para completar 6 semanas (42 células)
    const totalCells = 42;
    const usedCells = startingDayOfWeek + daysInMonth;
    const remainingCells = totalCells - usedCells;
    for (let day = 1; day <= remainingCells; day++) {
        calendarHTML += `<div class="calendar-day other-month">${day}</div>`;
    }
    
    daysGrid.innerHTML = calendarHTML;
}

// Selecionar dia do calendário da rotina
window.selectRoutineDayFromCalendar = function(dateStr) {
    // Fechar modal do calendário
    closeRoutineCalendar();
    
    // Garantir que temos a lista atual de cards
    updateRoutineCards();
    
    // Encontrar o índice do card correspondente
    const cardsArray = Array.from(routineCards);
    const targetIndex = cardsArray.findIndex(card => card.getAttribute('data-date') === dateStr);
    if (targetIndex === -1) {
        console.warn('Dia selecionado no calendário não encontrado no slider:', dateStr);
        return;
    }
    
    // Atualizar índice atual e exibir com animação
    currentRoutineIndex = targetIndex;
    updateRoutineSliderDisplay();
    
    // Atualizar seleção visual
    document.querySelectorAll('#routineSliderTrack .diary-day-card').forEach(card => card.classList.remove('selected'));
    const selectedCard = document.querySelector(`#routineSliderTrack .diary-day-card[data-date="${dateStr}"]`);
    if (selectedCard) selectedCard.classList.add('selected');
    
    // Atualizar painel de detalhes
    updateRoutineDayDetails(dateStr);
};

// Diário: selecionar dia pelo calendário (delegation para garantir funcionamento)
window.selectDiaryDayFromCalendar = function(dateStr) {
    // Atualizar referência aos cards
    updateDiaryCards();
    // Encontrar índice
    const cardsArray = Array.from(diaryCards);
    const targetIndex = cardsArray.findIndex(card => card.getAttribute('data-date') === dateStr);
    if (targetIndex === -1) {
        console.warn('Dia selecionado no calendário (diário) não encontrado:', dateStr);
        return;
    }
    currentDiaryIndex = targetIndex;
    
    // Fechar modal do calendário se existir
    if (typeof closeDiaryCalendar === 'function') {
        try { closeDiaryCalendar(); } catch (e) {}
    } else {
        const modal = document.getElementById('diaryCalendarModal');
        if (modal) modal.style.display = 'none';
    }
    
    // Posicionar imediatamente sem animação para evitar flicker
    if (typeof diaryTrack !== 'undefined' && diaryTrack) {
        diaryTrack.style.transition = 'none';
        diaryTrack.style.transform = `translateX(${-currentDiaryIndex * 100}%)`;
    }
    
    // Atualizar UI
    setTimeout(() => {
        updateDiaryDisplay();
    }, 50);
    // Seleção visual
    document.querySelectorAll('#diarySliderTrack .diary-day-card').forEach(card => card.classList.remove('selected'));
    const selectedCard = document.querySelector(`#diarySliderTrack .diary-day-card[data-date="${dateStr}"]`);
    if (selectedCard) selectedCard.classList.add('selected');
};

// Delegar clique no calendário do Diário caso o HTML não tenha onclicks
document.addEventListener('click', function(e) {
    const dayEl = e.target.closest('#diaryCalendarModal .calendar-day[data-date]');
    if (dayEl && !dayEl.classList.contains('future-day')) {
        const dateStr = dayEl.getAttribute('data-date');
        selectDiaryDayFromCalendar(dateStr);
    }
});

// Sistema de navegação da rotina (COPIADO DO DIÁRIO)
let routineCards = document.querySelectorAll('#routineSliderTrack .diary-day-card');
let currentRoutineIndex = routineCards.length - 1; // Iniciar no último (dia mais recente)
const routineTrack = document.getElementById('routineSliderTrack');

// Função para atualizar referência aos cards
function updateRoutineCards() {
    routineCards = document.querySelectorAll('#routineSliderTrack .diary-day-card');
}

function updateRoutineSliderDisplay() {
    
    // Adicionar transição suave para o slider
    routineTrack.style.transition = 'transform 0.3s ease-in-out';
    
    const offset = -currentRoutineIndex * 100;
    routineTrack.style.transform = `translateX(${offset}%)`;
    console.log('Offset calculado:', offset);
    
    const currentCard = routineCards[currentRoutineIndex];
    console.log('currentCard:', currentCard);
    if (!currentCard) {
        console.log('ERRO: currentCard não encontrado!');
        return;
    }
    
    const date = currentCard.getAttribute('data-date');
    const dateObj = new Date(date + 'T00:00:00');
    console.log('Data do card selecionado:', date);
    console.log('dateObj:', dateObj);
    
    // Nomes dos meses e dias da semana
    const monthNamesShort = ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];
    const monthNamesLower = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    const weekdayNames = ['DOMINGO', 'SEGUNDA', 'TERÇA', 'QUARTA', 'QUINTA', 'SEXTA', 'SÁBADO'];
    
    // Atualizar ano
    const routineYear = document.getElementById('routineYear');
    if (routineYear) {
        routineYear.textContent = dateObj.getFullYear();
    }
    
    // Atualizar informações do cabeçalho
    const dayMonth = document.getElementById('routineDayMonth');
    const weekday = document.getElementById('routineWeekday');
    const prevDate = document.getElementById('routinePrevDate');
    const nextDate = document.getElementById('routineNextDate');
    
    if (dayMonth) {
        const day = dateObj.getDate();
        const month = monthNamesShort[dateObj.getMonth()];
        dayMonth.textContent = `${day} ${month}`;
    }
    
    if (weekday) {
        weekday.textContent = weekdayNames[dateObj.getDay()];
    }
    
    // Atualizar datas de navegação (anterior e próximo)
    const prevIndex = currentRoutineIndex - 1;
    const nextIndex = currentRoutineIndex + 1;
    
    // Atualizar data anterior (sempre mostrar o dia anterior real)
    if (prevDate) {
        // Calcular sempre o dia anterior baseado na data atual
        const currentDate = new Date(date + 'T00:00:00');
        const prevDateObj = new Date(currentDate);
        prevDateObj.setDate(prevDateObj.getDate() - 1);
        
        prevDate.textContent = `${prevDateObj.getDate()} ${monthNamesLower[prevDateObj.getMonth()]}`;
        if (prevDate.parentElement) {
            prevDate.parentElement.style.visibility = 'visible';
        }
    }
    
    // Atualizar data próxima (se existir e não for futuro)
    if (nextDate) {
        if (nextIndex < routineCards.length && routineCards[nextIndex]) {
            const nextDateObj = new Date(routineCards[nextIndex].getAttribute('data-date') + 'T00:00:00');
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (nextDateObj <= today) {
                nextDate.textContent = `${nextDateObj.getDate()} ${monthNamesLower[nextDateObj.getMonth()]}`;
                if (nextDate.parentElement) {
                    nextDate.parentElement.style.visibility = 'visible';
                }
            } else {
                if (nextDate.parentElement) {
                    nextDate.parentElement.style.visibility = 'hidden';
                }
            }
        } else {
            if (nextDate.parentElement) {
                nextDate.parentElement.style.visibility = 'hidden';
            }
        }
    }
    
    // Atualizar resumo de missões (baseado no DOM, com fallback para dados globais)
    const summaryDiv = currentCard.querySelector('.diary-day-summary');
    let completedCount = 0;
    if (summaryDiv) {
        const span = summaryDiv.querySelector('.diary-summary-item span');
        if (span) {
            const match = span.textContent.match(/(\d+)/);
            if (match) completedCount = parseInt(match[1], 10) || 0;
        }
    }
    const summaryMissions = document.getElementById('routineSummaryMissions');
    const summaryProgress = document.getElementById('routineSummaryProgress');
    if (summaryMissions) {
        const s = summaryMissions.querySelector('span');
        if (s) s.textContent = `${completedCount} missões`;
    }
    if (summaryProgress) {
        const totalMissions = (window.routineItemsData && Array.isArray(window.routineItemsData)) ? window.routineItemsData.length : 0;
        const percentage = totalMissions > 0 ? Math.round((completedCount / totalMissions) * 100) : (completedCount > 0 ? 100 : 0);
        summaryProgress.textContent = `Progresso: ${percentage}%`;
    }
}

function updateRoutineSummary(date) {
    const dayMissions = routineLogData.filter(log => log.date === date && Number(log.is_completed) === 1);
    const summaryMissions = document.getElementById('routineSummaryMissions');
    const summaryProgress = document.getElementById('routineSummaryProgress');
    
    if (summaryMissions) {
        summaryMissions.querySelector('span').textContent = `${dayMissions.length} missões`;
    }
    
    if (summaryProgress) {
        const totalMissions = routineItemsData.length;
        const percentage = totalMissions > 0 ? Math.round((dayMissions.length / totalMissions) * 100) : 0;
        summaryProgress.textContent = `Progresso: ${percentage}%`;
    }
}

// Função para navegar entre dias (IGUAL AO DIÁRIO)
window.navigateRoutine = function(direction) {
    const newIndex = currentRoutineIndex + direction;
    
    // Verificar limites
    if (newIndex < 0 || newIndex >= routineCards.length) {
        return; // Não navega se estiver nos limites
    }
    
    // Se tentar ir para frente
    if (direction > 0) {
        const nextCard = routineCards[newIndex];
        if (nextCard) {
            const nextDate = nextCard.getAttribute('data-date');
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const nextDateObj = new Date(nextDate + 'T00:00:00');
            
            // Se o próximo dia for futuro, não permite navegação
            if (nextDateObj > today) {
                return;
            }
        }
    }
    
    // Atualizar índice e display
    currentRoutineIndex = newIndex;
    updateRoutineSliderDisplay();
};

// Inicializar rotina quando a aba for ativada
function initRoutineCalendar() {
    updateRoutineCards();
    
    // Buscar o card do dia de HOJE
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    
    // Procurar o índice do card de hoje
    let targetIndex = routineCards.length - 1; // Default: último card
    
    for (let i = 0; i < routineCards.length; i++) {
        if (routineCards[i].getAttribute('data-date') === todayStr) {
            targetIndex = i;
            break;
        }
    }
    
    currentRoutineIndex = targetIndex;
    updateRoutineSliderDisplay();
}

// Selecionar dia na rotina
function selectRoutineDay(dateStr) {
    selectedRoutineDay = dateStr;
    
    // Atualizar visual do slider (apenas na aba Rotina)
    document.querySelectorAll('#routineSliderTrack .diary-day-card').forEach(card => {
        card.classList.remove('selected');
    });
    const selectedCard = document.querySelector(`#routineSliderTrack .diary-day-card[data-date="${dateStr}"]`);
    if (selectedCard) {
        selectedCard.classList.add('selected');
    }
    
    // Atualizar detalhes do dia
    updateRoutineDayDetails(dateStr);
}

// Atualizar detalhes do dia selecionado
function updateRoutineDayDetails(dateStr) {
    const detailsContainer = document.getElementById('routine-day-details');
    if (!detailsContainer) return;
    
    // Mostrar container de detalhes
    detailsContainer.style.display = 'block';
    
    // Buscar dados do dia
    const dayMissions = routineLogData.filter(log => log.date === dateStr);
    const dayExercises = exerciseData.filter(ex => ex.updated_at.startsWith(dateStr));
    const daySleep = sleepData.filter(sleep => sleep.date === dateStr);
    
    // Atualizar missões
    updateMissionsList(dayMissions);
    
    // Atualizar atividades físicas
    updateActivitiesList(dayExercises);
    
    // Atualizar sono
    updateSleepInfo(daySleep);
}

// Atualizar lista de missões
function updateMissionsList(dayMissions) {
    const missionsContainer = document.getElementById('missions-list');
    if (!missionsContainer) return;
    
    if (dayMissions.length === 0) {
        missionsContainer.innerHTML = '<p style="color: var(--secondary-text-color); text-align: center; padding: 20px;">Nenhuma missão registrada neste dia</p>';
        return;
    }
    
    let missionsHTML = '';
    dayMissions.forEach(mission => {
        const missionItem = routineItemsData.find(item => item.id === mission.routine_item_id);
        if (!missionItem) return;
        const iconClass = missionItem.icon_class || 'fas fa-clipboard-check';
        const title = missionItem.title || `Missão #${mission.routine_item_id}`;
        const completedAt = mission.completed_at || mission.updated_at || '';
        const timeStr = completedAt ? new Date(completedAt.replace(' ', 'T')).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
        const duration = mission.exercise_duration_minutes || 0;
        const hours = Math.floor(duration / 60);
        const minutes = duration % 60;
        const durationStr = duration > 0 ? (hours > 0 ? `${hours}h${minutes > 0 ? minutes.toString().padStart(2, '0') : ''}` : `${minutes}min`) : '';
        const statusText = Number(mission.is_completed) === 1 ? 'Concluída' : 'Pendente';
        
        missionsHTML += `
            <div class="diary-meal-card">
                <div class="diary-meal-header">
                    <div class="diary-meal-icon">
                        <i class="${iconClass}"></i>
                    </div>
                    <div class="diary-meal-info">
                        <h5>${title}</h5>
                        <span class="diary-meal-totals">
                            ${timeStr ? `<strong>${timeStr}</strong> • ` : ''}${durationStr ? `${durationStr} • ` : ''}${statusText}
                        </span>
                    </div>
                </div>
            </div>
        `;
    });
    
    missionsContainer.innerHTML = missionsHTML;
}

// Atualizar lista de atividades físicas
function updateActivitiesList(dayExercises) {
    const activitiesContainer = document.getElementById('activities-list');
    if (!activitiesContainer) return;
    
    if (dayExercises.length === 0) {
        activitiesContainer.innerHTML = '<p style="color: var(--secondary-text-color); text-align: center; padding: 20px;">Nenhuma atividade física registrada neste dia</p>';
        return;
    }
    
    let activitiesHTML = '';
    dayExercises.forEach(exercise => {
        activitiesHTML += `
            <div class="activity-item" style="display: flex; align-items: center; padding: 12px; border-bottom: 1px solid var(--border-color);">
                <div class="activity-icon" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: rgba(255, 111, 0, 0.1); border-radius: 50%; margin-right: 12px;">
                    <i class="fa fa-dumbbell" style="color: var(--accent-orange); font-size: 18px;"></i>
                </div>
                <div class="activity-content" style="flex: 1;">
                    <div class="activity-name" style="font-weight: 600; color: var(--primary-text-color);">${exercise.exercise_name}</div>
                    <div class="activity-duration" style="color: var(--secondary-text-color); font-size: 0.9rem;">
                        <i class="fa fa-clock" style="margin-right: 6px;"></i>
                        ${exercise.duration_minutes}min
                    </div>
                </div>
            </div>
        `;
    });
    
    activitiesContainer.innerHTML = activitiesHTML;
}

// Atualizar informações de sono
function updateSleepInfo(daySleep) {
    const sleepContainer = document.getElementById('sleep-info');
    if (!sleepContainer) return;
    
    if (daySleep.length === 0) {
        sleepContainer.innerHTML = '<p style="color: var(--secondary-text-color); text-align: center; padding: 20px;">Nenhum registro de sono neste dia</p>';
        return;
    }
    
    const sleep = daySleep[0];
    const hours = Math.floor(sleep.hours);
    const minutes = Math.round((sleep.hours - hours) * 60);
    const sleepGoal = 8; // Meta de 8 horas
    const goalPercentage = Math.min((sleep.hours / sleepGoal) * 100, 100);
    
    sleepContainer.innerHTML = `
        <div class="sleep-display" style="text-align: center; padding: 20px;">
            <div class="sleep-icon" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; background: rgba(255, 111, 0, 0.1); border-radius: 50%; margin: 0 auto 16px;">
                <i class="fa fa-moon" style="color: var(--accent-orange); font-size: 24px;"></i>
            </div>
            <div class="sleep-time" style="font-size: 1.5rem; font-weight: 600; color: var(--primary-text-color); margin-bottom: 8px;">
                ${hours}h${minutes > 0 ? minutes.toString().padStart(2, '0') : ''}
            </div>
            <div class="sleep-goal" style="color: var(--secondary-text-color); margin-bottom: 16px;">
                Meta: ${sleepGoal}h — ${sleep.hours >= sleepGoal ? 'dentro da média' : 'abaixo da meta'}
            </div>
            <div class="sleep-progress" style="width: 100%; height: 4px; background: var(--glass-bg); border-radius: 2px; overflow: hidden;">
                <div class="sleep-progress-bar" style="width: ${goalPercentage}%; height: 100%; background: var(--accent-orange); transition: width 0.3s ease;"></div>
            </div>
        </div>
    `;
}
// Atualizar resumo da rotina
function updateRoutineSummary() {
    const today = new Date().toISOString().split('T')[0];
    const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    
    // Calcular missões concluídas na semana
    const weekMissions = routineLogData.filter(log => 
        log.date >= weekAgo && log.date <= today && Number(log.is_completed) === 1
    );
    
    // Calcular tempo médio de sono na semana
    const weekSleep = sleepData.filter(sleep => 
        sleep.date >= weekAgo && sleep.date <= today
    );
    const avgSleep = weekSleep.length > 0 ? 
        weekSleep.reduce((sum, sleep) => sum + sleep.hours, 0) / weekSleep.length : 0;
    
    // Calcular dias com treino na semana
    const weekExercises = exerciseData.filter(ex => 
        ex.updated_at.startsWith(weekAgo) || ex.updated_at.startsWith(today)
    );
    const uniqueExerciseDays = new Set(weekExercises.map(ex => ex.updated_at.split(' ')[0])).size;
    
    // Atualizar cards de resumo
    const summaryCards = document.querySelectorAll('.routine-summary .stat-value');
    if (summaryCards.length >= 3) {
        summaryCards[0].textContent = `${weekMissions.length} missões`;
        summaryCards[1].textContent = `${avgSleep.toFixed(1)}h`;
        summaryCards[2].textContent = `${uniqueExerciseDays} dias`;
    }
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>