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
$daysToShow = 7;
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

// Meta de água sugerida
$water_goal_data = getWaterIntakeSuggestion($user_data['weight_kg'] ?? 0);
$water_goal_ml = $water_goal_data['total_ml'];
$water_goal_cups = $water_goal_data['cups'];

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
$total_daily_calories_goal = calculateTargetDailyCalories($gender, $weight_kg, $height_cm, $age_years, $exercise_frequency, $objective);
$macros_goal = calculateMacronutrients($total_daily_calories_goal, $objective);

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

// --- PROCESSAMENTO DE DADOS DE NUTRIENTES ---
// Buscar dados de nutrientes dos últimos 120 dias
$stmt_nutrients = $conn->prepare("
    SELECT 
        date,
        SUM(kcal_consumed) as total_kcal,
        SUM(protein_consumed_g) as total_protein,
        SUM(carbs_consumed_g) as total_carbs,
        SUM(fat_consumed_g) as total_fat
    FROM sf_user_daily_tracking 
    WHERE user_id = ? 
    AND date >= DATE_SUB(CURDATE(), INTERVAL 120 DAY)
    GROUP BY date 
    ORDER BY date DESC
");
$stmt_nutrients->bind_param("i", $user_id);
$stmt_nutrients->execute();
$nutrients_history = $stmt_nutrients->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_nutrients->close();

// Processar dados de nutrientes
$nutrients_data = [];
foreach ($nutrients_history as $day) {
    $kcal = (float)$day['total_kcal'];
    $protein = (float)$day['total_protein'];
    $carbs = (float)$day['total_carbs'];
    $fat = (float)$day['total_fat'];
    
    
    // Calcular porcentagens em relação às metas (NÃO limitar a 100% para nutrientes)
    $kcal_percentage = $total_daily_calories_goal > 0 ? round(($kcal / $total_daily_calories_goal) * 100, 1) : 0;
    $protein_percentage = $macros_goal['protein_g'] > 0 ? round(($protein / $macros_goal['protein_g']) * 100, 1) : 0;
    $carbs_percentage = $macros_goal['carbs_g'] > 0 ? round(($carbs / $macros_goal['carbs_g']) * 100, 1) : 0;
    $fat_percentage = $macros_goal['fat_g'] > 0 ? round(($fat / $macros_goal['fat_g']) * 100, 1) : 0;
    
    // Determinar status geral baseado na média das porcentagens
    $avg_percentage = ($kcal_percentage + $protein_percentage + $carbs_percentage + $fat_percentage) / 4;
    
    $status = 'excellent';
    $status_text = 'Excelente';
    $status_class = 'success';
    
    if ($avg_percentage == 0) {
        $status = 'empty';
        $status_text = 'Sem dados';
        $status_class = 'info';
    } elseif ($avg_percentage >= 100) {
        $status = 'excellent';
        $status_text = 'Meta atingida';
        $status_class = 'success';
    } elseif ($avg_percentage >= 90) {
        $status = 'good';
        $status_text = 'Quase na meta';
        $status_class = 'info';
    } elseif ($avg_percentage >= 70) {
        $status = 'fair';
        $status_text = 'Abaixo da meta';
        $status_class = 'warning';
    } elseif ($avg_percentage >= 50) {
        $status = 'poor';
        $status_text = 'Muito abaixo';
        $status_class = 'warning';
    } else {
        $status = 'critical';
        $status_text = 'Crítico';
        $status_class = 'error';
    }
    
    $nutrients_data[] = [
        'date' => $day['date'],
        'kcal' => $kcal,
        'protein' => $protein,
        'carbs' => $carbs,
        'fat' => $fat,
        'kcal_percentage' => $kcal_percentage,
        'protein_percentage' => $protein_percentage,
        'carbs_percentage' => $carbs_percentage,
        'fat_percentage' => $fat_percentage,
        'avg_percentage' => round($avg_percentage, 1),
        'status' => $status,
        'status_text' => $status_text,
        'status_class' => $status_class
    ];
}

// Calcular estatísticas de nutrientes por data específica
function calculateNutrientsStatsByDate($data, $target_date) {
    $filtered_data = array_filter($data, function($day) use ($target_date) {
        return $day['date'] === $target_date;
    });
    
    if (empty($filtered_data)) {
        return [
            'avg_kcal' => 0,
            'avg_protein' => 0,
            'avg_carbs' => 0,
            'avg_fat' => 0,
            'avg_kcal_percentage' => 0,
            'avg_protein_percentage' => 0,
            'avg_carbs_percentage' => 0,
            'avg_fat_percentage' => 0,
            'avg_overall_percentage' => 0,
            'total_days' => 0,
            'excellent_days' => 0,
            'good_days' => 0,
            'fair_days' => 0,
            'poor_days' => 0,
            'critical_days' => 0
        ];
    }
    
    return calculateNutrientsStats($filtered_data);
}

// Calcular estatísticas de nutrientes
function calculateNutrientsStats($data, $days = null, $offset = 0) {
    if (empty($data)) return [
        'avg_kcal' => 0,
        'avg_protein' => 0,
        'avg_carbs' => 0,
        'avg_fat' => 0,
        'avg_kcal_percentage' => 0,
        'avg_protein_percentage' => 0,
        'avg_carbs_percentage' => 0,
        'avg_fat_percentage' => 0,
        'avg_overall_percentage' => 0,
        'total_days' => 0,
        'excellent_days' => 0,
        'good_days' => 0,
        'fair_days' => 0,
        'poor_days' => 0,
        'critical_days' => 0
    ];
    
    $filtered_data = $days ? array_slice($data, $offset, $days) : $data;
    
    $avg_kcal = array_sum(array_column($filtered_data, 'kcal')) / count($filtered_data);
    $avg_protein = array_sum(array_column($filtered_data, 'protein')) / count($filtered_data);
    $avg_carbs = array_sum(array_column($filtered_data, 'carbs')) / count($filtered_data);
    $avg_fat = array_sum(array_column($filtered_data, 'fat')) / count($filtered_data);
    
    $avg_kcal_percentage = array_sum(array_column($filtered_data, 'kcal_percentage')) / count($filtered_data);
    $avg_protein_percentage = array_sum(array_column($filtered_data, 'protein_percentage')) / count($filtered_data);
    $avg_carbs_percentage = array_sum(array_column($filtered_data, 'carbs_percentage')) / count($filtered_data);
    $avg_fat_percentage = array_sum(array_column($filtered_data, 'fat_percentage')) / count($filtered_data);
    $avg_overall_percentage = array_sum(array_column($filtered_data, 'avg_percentage')) / count($filtered_data);
    
    // Contar status
    $excellent_days = count(array_filter($filtered_data, fn($d) => $d['status'] === 'excellent'));
    $good_days = count(array_filter($filtered_data, fn($d) => $d['status'] === 'good'));
    $fair_days = count(array_filter($filtered_data, fn($d) => $d['status'] === 'fair'));
    $poor_days = count(array_filter($filtered_data, fn($d) => $d['status'] === 'poor'));
    $critical_days = count(array_filter($filtered_data, fn($d) => $d['status'] === 'critical'));
    
    return [
        'avg_kcal' => round($avg_kcal, 0),
        'avg_protein' => round($avg_protein, 1),
        'avg_carbs' => round($avg_carbs, 1),
        'avg_fat' => round($avg_fat, 1),
        'avg_kcal_percentage' => round($avg_kcal_percentage, 1),
        'avg_protein_percentage' => round($avg_protein_percentage, 1),
        'avg_carbs_percentage' => round($avg_carbs_percentage, 1),
        'avg_fat_percentage' => round($avg_fat_percentage, 1),
        'avg_overall_percentage' => round($avg_overall_percentage, 1),
        'total_days' => count($filtered_data),
        'excellent_days' => $excellent_days,
        'good_days' => $good_days,
        'fair_days' => $fair_days,
        'poor_days' => $poor_days,
        'critical_days' => $critical_days
    ];
}

$nutrients_stats_all = calculateNutrientsStats($nutrients_data);
$nutrients_stats_90 = calculateNutrientsStats($nutrients_data, 90);
$nutrients_stats_30 = calculateNutrientsStats($nutrients_data, 30);
$nutrients_stats_15 = calculateNutrientsStats($nutrients_data, 15);
$nutrients_stats_7 = calculateNutrientsStats($nutrients_data, 7);
$nutrients_stats_today = calculateNutrientsStatsByDate($nutrients_data, $today);
$nutrients_stats_yesterday = calculateNutrientsStatsByDate($nutrients_data, $yesterday);

// Debug: Verificar datas
error_log("DEBUG - Data de hoje: " . $today);
error_log("DEBUG - Data de ontem: " . $yesterday);
error_log("DEBUG - Primeiras 3 datas de hidratação: " . json_encode(array_slice(array_column($hydration_data, 'date'), 0, 3)));
error_log("DEBUG - Primeiras 3 datas de nutrientes: " . json_encode(array_slice(array_column($nutrients_data, 'date'), 0, 3)));

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
$sleep_alert_html = '';
if (!empty($user_data['sleep_time_bed']) && !empty($user_data['sleep_time_wake'])) {
    $bed_time = new DateTime($user_data['sleep_time_bed']);
    $wake_time = new DateTime($user_data['sleep_time_wake']);
    if ($wake_time < $bed_time) { $wake_time->modify('+1 day'); }
    $interval = $bed_time->diff($wake_time);
    $sleep_duration_hours = $interval->h + ($interval->i / 60);
    $sleep_html = $interval->format('%H:%I');
    if ($sleep_duration_hours < 6) { $sleep_alert_html = '<span class="status-badge error">Sono Ruim</span>'; } 
    elseif ($sleep_duration_hours < 7.5) { $sleep_alert_html = '<span class="status-badge warning">Sono Regular</span>'; } 
    else { $sleep_alert_html = '<span class="status-badge success">Sono Bom</span>'; }
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
        <h3>Meta Calórica e Macros</h3>
        <div class="meta-card-main">
            <span class="meta-value"><?php echo $total_daily_calories_goal; ?></span>
            <span class="meta-label">Kcal / dia</span>
        </div>
        <div class="meta-card-macros">
            <div><span><?php echo $macros_goal['carbs_g']; ?>g</span>Carboidratos</div>
            <div><span><?php echo $macros_goal['protein_g']; ?>g</span>Proteínas</div>
            <div><span><?php echo $macros_goal['fat_g']; ?>g</span>Gorduras</div>
        </div>
    </div>

    <div class="dashboard-card">
        <h3>Dados Físicos</h3>
        <div class="physical-data-grid">
            <div class="data-item"><i class="fas fa-birthday-cake icon"></i><label>Idade</label><span><?php echo $age_years; ?> anos</span></div>
            <div class="data-item"><i class="fas fa-weight icon"></i><label>Peso Atual</label><span><?php echo number_format((float)($user_data['weight_kg'] ?? 0), 1, ',', '.'); ?> kg</span></div>
            <div class="data-item"><i class="fas fa-ruler-vertical icon"></i><label>Altura</label><span><?php echo htmlspecialchars($user_data['height_cm'] ?? 'N/A'); ?> cm</span></div>
            <div class="data-item"><i class="fas fa-venus-mars icon"></i><label>Gênero</label><span><?php echo $gender_names[$user_data['gender']] ?? 'Não informado'; ?></span></div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <h3>Anamnese e Hábitos</h3>
        <div class="physical-data-grid">
            <div class="data-item"><i class="fas fa-dumbbell icon"></i><label>Tipo de Treino</label><span><?php echo htmlspecialchars($user_data['exercise_type'] ?? 'N/I'); ?></span></div>
            <div class="data-item"><i class="fas fa-calendar-check icon"></i><label>Frequência</label><span><?php echo $exercise_freq_names[$user_data['exercise_frequency']] ?? 'N/I'; ?></span></div>
            <div class="data-item"><i class="fas fa-tint icon"></i><label>Consumo de Água</label><span><?php echo $water_intake_names[$user_data['water_intake_liters']] ?? 'N/I'; ?></span></div>
            <div class="data-item"><i class="fas fa-bed icon"></i><label>Duração do Sono</label><span><?php echo $sleep_html . ' ' . $sleep_alert_html; ?></span></div>
        </div>
        
        <?php if (!empty($exercise_durations)): ?>
        <div class="exercise-durations-section" style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-color);">
            <h4 style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px; color: var(--text-primary);">
                <i class="fas fa-clock" style="color: var(--accent-orange);"></i>
                Durações dos Exercícios
            </h4>
            <div class="durations-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">
                <?php foreach ($exercise_durations as $duration): ?>
                <div class="duration-card" style="background: var(--bg-color); padding: 16px; border-radius: 12px; border: 1px solid var(--border-color);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-weight: 600; color: var(--text-primary); font-size: 0.95rem;">
                            <?php echo htmlspecialchars($duration['exercise_name']); ?>
                        </span>
                        <span style="color: var(--accent-orange); font-weight: 600; font-size: 0.9rem;">
                            <?php echo $duration['duration_minutes']; ?> min
                        </span>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-secondary);">
                        <i class="fas fa-clock" style="margin-right: 4px;"></i>
                        Atualizado: <?php echo date('d/m/Y', strtotime($duration['updated_at'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="details-grid-1-col" style="margin-top:24px;">
    <div class="dashboard-card">
        <h3>Plano e Preferências</h3>
         <div class="physical-data-grid-pref">
            <div class="data-item"><i class="fas fa-bullseye icon"></i><label>Objetivo</label><span><?php echo $objective_names[$user_data['objective']] ?? 'N/I'; ?></span></div>
            <div class="data-item"><i class="fas fa-drumstick-bite icon"></i><label>Consumo de Carne</label>
                <span>
                    <?php 
                        if (isset($user_data['meat_consumption'])) {
                            echo $user_data['meat_consumption'] ? 'Sim' : 'Não (' . ($vegetarian_type_names[$user_data['vegetarian_type']] ?? 'N/E') . ')';
                        } else { echo 'Não informado'; }
                    ?>
                </span>
            </div>
             <div class="data-item"><i class="fas fa-ban icon"></i><label>Intolerâncias</label>
                <span>
                    <?php 
                        $intolerances = [];
                        if (!empty($user_data['lactose_intolerance'])) $intolerances[] = 'Lactose';
                        if (!empty($user_data['gluten_intolerance'])) $intolerances[] = 'Glúten';
                        echo !empty($intolerances) ? implode(', ', $intolerances) : 'Nenhuma informada.';
                    ?>
                </span>
            </div>
            <div class="data-item"><i class="fas fa-leaf icon"></i><label>Restrições Alimentares</label>
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

<div class="tabs-container">
    <div class="tab-link active" data-tab="diary">Diário</div>
    <div class="tab-link" data-tab="hydration">Hidratação</div>
    <div class="tab-link" data-tab="nutrients">Nutrientes</div>
    <div class="tab-link" data-tab="weekly_analysis">Análise Semanal</div>
    <div class="tab-link" data-tab="feedback_analysis">Análise de Feedback</div>
    <div class="tab-link" data-tab="diet_comparison">Comparação Dieta</div>
    <div class="tab-link" data-tab="weekly_tracking">Rastreio Semanal</div>
    <div class="tab-link" data-tab="personalized_goals">Metas Personalizadas</div>
    <div class="tab-link" data-tab="progress">Progresso</div>
    <div class="tab-link" data-tab="measurements">Medidas</div>
</div>

<div id="tab-diary" class="tab-content active">
    <div class="dashboard-card">
        <div class="card-header-flex">
            <h3>Histórico do Diário</h3>
            <form method="GET" class="date-filter-form">
                <input type="hidden" name="id" value="<?php echo $user_id; ?>">
                <label for="end_date">Mostrar semana terminando em:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" onchange="this.form.submit()">
            </form>
        </div>
        <div class="diary-history-container">
            <?php if (empty($meal_history)): ?>
                <p class="empty-state">O paciente ainda não registrou nenhuma refeição neste período.</p>
            <?php else: ?>
                <?php foreach ($meal_history as $date => $meals): ?>
                    <div class="diary-day-group">
                        <h4 class="day-header"><?php echo date('d/m/Y', strtotime($date)); ?></h4>
                        <?php foreach ($meals as $meal_type_slug => $items): 
                            $total_kcal = array_sum(array_column($items, 'kcal_consumed'));
                            $total_prot = array_sum(array_column($items, 'protein_consumed_g'));
                            $total_carb = array_sum(array_column($items, 'carbs_consumed_g'));
                            $total_fat  = array_sum(array_column($items, 'fat_consumed_g'));
                        ?>
                            <div class="meal-card">
                                <div class="meal-card-header">
                                    <h5><?php echo $meal_type_names[$meal_type_slug] ?? ucfirst($meal_type_slug); ?></h5>
                                    <div class="meal-card-totals">
                                        <strong><?php echo round($total_kcal); ?> kcal</strong>
                                        (P:<?php echo round($total_prot); ?>g, C:<?php echo round($total_carb); ?>g, G:<?php echo round($total_fat); ?>g)
                                    </div>
                                </div>
                                <ul class="food-item-list">
                                    <?php foreach ($items as $item): ?>
                                        <li>
                                            <span class="food-name"><?php echo htmlspecialchars($item['food_name']); ?></span>
                                            <span class="food-quantity"><?php echo htmlspecialchars($item['quantity_display']); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="tab-hydration" class="tab-content">
    <div class="hydration-container">
        <!-- Cabeçalho com Meta e Filtros -->
        <div class="hydration-header">
            <div class="hydration-meta-info">
                <h3><i class="fas fa-tint"></i> Hidratação</h3>
                <div class="meta-display">
                    <span class="meta-goal">Meta: <?php echo $water_goal_ml; ?>ml/dia</span>
                    <span class="meta-weight">(<?php echo number_format($user_data['weight_kg'] ?? 0, 1); ?>kg)</span>
                </div>
            </div>
            <div class="hydration-filters">
                <button class="filter-btn" data-period="today">Hoje</button>
                <button class="filter-btn" data-period="yesterday">Ontem</button>
                <button class="filter-btn active" data-period="7">Últimos 7 dias</button>
                <button class="filter-btn" data-period="15">Últimos 15 dias</button>
                <button class="filter-btn" data-period="30">Últimos 30 dias</button>
                <button class="filter-btn" data-period="90">Últimos 3 meses</button>
                <button class="filter-btn" data-period="all">Todos os registros</button>
            </div>
        </div>

        <!-- Estatísticas Detalhadas -->
        <div class="hydration-stats-detailed">
            <div class="main-stat">
                <div class="stat-visual">
                    <div class="stat-circle" id="avg-percentage-circle" data-percentage="<?php echo $water_stats_7['avg_percentage']; ?>">
                        <div class="stat-percentage" id="avg-percentage"><?php echo $water_stats_7['avg_percentage']; ?>%</div>
                        <div class="stat-label">da Meta</div>
                    </div>
                </div>
                <div class="stat-details">
                    <div class="stat-main-value" id="avg-consumption"><?php echo $water_stats_7['avg_ml']; ?>ml</div>
                    <div class="stat-main-label">Média Diária de Consumo</div>
                    <div class="stat-period" id="period-info">Período: Últimos 7 dias</div>
                </div>
            </div>
            
            <div class="averages-grid">
                <div class="average-card">
                    <div class="average-header">
                        <i class="fas fa-calendar-week"></i>
                        <span>Média Semanal</span>
                    </div>
                    <div class="average-content">
                        <div class="average-value" id="weekly-avg-ml"><?php echo $water_stats_7['avg_ml']; ?>ml</div>
                        <div class="average-percentage" id="weekly-avg-percentage"><?php echo $water_stats_7['avg_percentage']; ?>% da meta</div>
                    </div>
                </div>
                
                <div class="average-card">
                    <div class="average-header">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Média Quinzenal</span>
                    </div>
                    <div class="average-content">
                        <div class="average-value" id="biweekly-avg-ml"><?php echo $water_stats_15['avg_ml']; ?>ml</div>
                        <div class="average-percentage" id="biweekly-avg-percentage"><?php echo $water_stats_15['avg_percentage']; ?>% da meta</div>
                    </div>
                </div>
                
                <div class="average-card">
                    <div class="average-header">
                        <i class="fas fa-chart-line"></i>
                        <span>Taxa de Aderência</span>
                    </div>
                    <div class="average-content">
                        <div class="average-value" id="compliance-rate"><?php echo $water_stats_7['compliance_rate']; ?>%</div>
                        <div class="average-percentage">Dias que atingiram a meta</div>
                    </div>
                </div>
                
                <div class="average-card">
                    <div class="average-header">
                        <i class="fas fa-calendar-check"></i>
                        <span>Período Analisado</span>
                    </div>
                    <div class="average-content">
                        <div class="average-value" id="total-days"><?php echo $water_stats_7['total_days']; ?></div>
                        <div class="average-percentage">Dias com registros</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico Visual Melhorado -->
        <div class="chart-section">
            <div class="chart-section-header">
                <h4>Consumo dos Últimos Dias</h4>
                <div class="compact-legend">
                    <span class="legend-item">
                        <div class="legend-dot excellent"></div>
                        <span>Excelente</span>
                    </span>
                    <span class="legend-item">
                        <div class="legend-dot good"></div>
                        <span>Bom</span>
                    </span>
                    <span class="legend-item">
                        <div class="legend-dot fair"></div>
                        <span>Regular</span>
                    </span>
                    <span class="legend-item">
                        <div class="legend-dot poor"></div>
                        <span>Baixo</span>
                    </span>
                    <span class="legend-item">
                        <div class="legend-dot critical"></div>
                        <span>Crítico</span>
                    </span>
                </div>
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
                            // Calcular altura da barra: 0% = 0px, 100% = 160px (altura total), outros valores proporcionais
                            $limitedPercentage = min($day['percentage'], 100);
                            $barHeight = 0;
                            if ($limitedPercentage === 0) {
                                $barHeight = 0; // Sem altura para 0%
                            } else if ($limitedPercentage === 100) {
                                $barHeight = 160; // Altura total do wrapper
                            } else {
                                // Proporcional: 0px (mínimo) + (porcentagem * 160px)
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

        <!-- Lista Simples -->
        <div class="hydration-list-simple">
            <div class="list-header">
                <h4>Registros Recentes</h4>
            </div>
            <div class="simple-list" id="simple-list">
                <?php if (empty($hydration_data)): ?>
                    <div class="empty-state">
                        <i class="fas fa-tint"></i>
                        <p>Nenhum registro de hidratação</p>
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($hydration_data, 0, 7) as $day): ?>
                        <div class="simple-item">
                            <div class="simple-date"><?php echo date('d/m/Y', strtotime($day['date'])); ?></div>
                            <div class="simple-amount">
                                <span class="simple-ml-value"><?php echo $day['ml']; ?>ml</span>
                                <span class="simple-percentage">(<?php echo $day['percentage']; ?>%)</span>
                            </div>
                            <div class="simple-status <?php echo $day['status']; ?>">
                                <?php 
                                $icon = match($day['status']) {
                                    'excellent' => 'fa-check-circle',
                                    'good' => 'fa-check',
                                    'fair' => 'fa-exclamation-triangle',
                                    'poor' => 'fa-exclamation',
                                    'critical' => 'fa-times-circle',
                                    default => 'fa-question'
                                };
                                ?>
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="tab-nutrients" class="tab-content">
    <div class="nutrients-container">
        <!-- Cabeçalho com Metas e Filtros -->
        <div class="nutrients-header">
            <div class="nutrients-meta-info">
                <div class="section-header">
                    <h3><i class="fas fa-utensils"></i> Consumo de Nutrientes</h3>
                    <button class="edit-goals-btn" onclick="openEditGoalsModal()">
                        <i class="fas fa-edit"></i> Editar Metas
                    </button>
                </div>
                <div class="meta-display">
                    <span class="meta-goal">Meta: <?php echo $total_daily_calories_goal; ?> kcal/dia</span>
                    <span class="meta-macros">(P:<?php echo $macros_goal['protein_g']; ?>g, C:<?php echo $macros_goal['carbs_g']; ?>g, G:<?php echo $macros_goal['fat_g']; ?>g)</span>
                </div>
            </div>
            <div class="nutrients-filters">
                <button class="filter-btn" data-period="today">Hoje</button>
                <button class="filter-btn" data-period="yesterday">Ontem</button>
                <button class="filter-btn active" data-period="7">Últimos 7 dias</button>
                <button class="filter-btn" data-period="15">Últimos 15 dias</button>
                <button class="filter-btn" data-period="30">Últimos 30 dias</button>
                <button class="filter-btn" data-period="90">Últimos 3 meses</button>
                <button class="filter-btn" data-period="all">Todos os registros</button>
            </div>
        </div>

        <!-- Estatísticas Detalhadas -->
        <div class="nutrients-stats-detailed">
            <div class="main-stat">
                <div class="stat-visual">
                    <div class="stat-circle" id="nutrients-percentage-circle" data-percentage="<?php echo $nutrients_stats_7['avg_overall_percentage']; ?>">
                        <div class="stat-percentage" id="nutrients-percentage"><?php echo $nutrients_stats_7['avg_overall_percentage']; ?>%</div>
                        <div class="stat-label">da Meta</div>
                    </div>
                </div>
                <div class="stat-details">
                    <div class="stat-main-value" id="nutrients-avg-kcal"><?php echo $nutrients_stats_7['avg_kcal']; ?> kcal</div>
                    <div class="stat-main-label">Média Diária de Calorias</div>
                    <div class="stat-period" id="nutrients-period-info">Período: Últimos 7 dias</div>
                </div>
            </div>
            
            <div class="averages-grid">
                <div class="average-card">
                    <div class="average-header">
                        <i class="fas fa-fire"></i>
                        <span>Calorias</span>
                    </div>
                    <div class="average-content">
                        <div class="average-value" id="nutrients-kcal-avg"><?php echo $nutrients_stats_7['avg_kcal']; ?> kcal</div>
                        <div class="average-percentage" id="nutrients-kcal-percentage"><?php echo $nutrients_stats_7['avg_kcal_percentage']; ?>% da meta</div>
                    </div>
                </div>
                
                <div class="average-card">
                    <div class="average-header">
                        <i class="fas fa-drumstick-bite"></i>
                        <span>Proteínas</span>
                    </div>
                    <div class="average-content">
                        <div class="average-value" id="nutrients-protein-avg"><?php echo $nutrients_stats_7['avg_protein']; ?>g</div>
                        <div class="average-percentage" id="nutrients-protein-percentage"><?php echo $nutrients_stats_7['avg_protein_percentage']; ?>% da meta</div>
                    </div>
                </div>
                
                <div class="average-card">
                    <div class="average-header">
                        <i class="fas fa-bread-slice"></i>
                        <span>Carboidratos</span>
                    </div>
                    <div class="average-content">
                        <div class="average-value" id="nutrients-carbs-avg"><?php echo $nutrients_stats_7['avg_carbs']; ?>g</div>
                        <div class="average-percentage" id="nutrients-carbs-percentage"><?php echo $nutrients_stats_7['avg_carbs_percentage']; ?>% da meta</div>
                    </div>
                </div>
                
                <div class="average-card">
                    <div class="average-header">
                        <i class="fas fa-seedling"></i>
                        <span>Gorduras</span>
                    </div>
                    <div class="average-content">
                        <div class="average-value" id="nutrients-fat-avg"><?php echo $nutrients_stats_7['avg_fat']; ?>g</div>
                        <div class="average-percentage" id="nutrients-fat-percentage"><?php echo $nutrients_stats_7['avg_fat_percentage']; ?>% da meta</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico Visual Melhorado -->
        <div class="chart-section">
            <div class="chart-section-header">
                <h4>Consumo dos Últimos Dias</h4>
                <div class="compact-legend">
                    <span class="legend-item">
                        <div class="legend-dot excellent"></div>
                        <span>Excelente</span>
                    </span>
                    <span class="legend-item">
                        <div class="legend-dot good"></div>
                        <span>Bom</span>
                    </span>
                    <span class="legend-item">
                        <div class="legend-dot fair"></div>
                        <span>Regular</span>
                    </span>
                    <span class="legend-item">
                        <div class="legend-dot poor"></div>
                        <span>Baixo</span>
                    </span>
                    <span class="legend-item">
                        <div class="legend-dot critical"></div>
                        <span>Crítico</span>
                    </span>
                </div>
            </div>
            <div class="nutrients-chart-improved">
                <div class="improved-chart" id="nutrients-improved-chart">
                <?php if (empty($nutrients_data)): ?>
                    <div class="empty-chart">
                        <i class="fas fa-utensils"></i>
                        <p>Nenhum registro encontrado</p>
                    </div>
                <?php else: ?>
                    <div class="improved-bars" id="nutrients-improved-bars">
                        <?php 
                        $display_data = array_slice($nutrients_data, 0, 7);
                        foreach ($display_data as $day): 
                            // Calcular altura da barra: 0% = 0px, 100% = 160px, >100% pode ir até 200px
                            $percentage = $day['avg_percentage'];
                            $barHeight = 0;
                            if ($percentage === 0) {
                                $barHeight = 0; // Sem altura para 0%
                            } else if ($percentage >= 100) {
                                $barHeight = 160 + min(($percentage - 100) * 0.4, 40); // 100% = 160px, máximo 200px
                            } else {
                                $barHeight = ($percentage / 100) * 160; // Proporcional entre 0px e 160px
                            }
                        ?>
                            <div class="improved-bar-container">
                                <div class="improved-bar-wrapper">
                                    <div class="improved-bar <?php echo $day['status']; ?>" style="height: <?php echo $barHeight; ?>px"></div>
                                    <div class="bar-percentage-text"><?php echo $percentage; ?>%</div>
                                    <div class="improved-goal-line"></div>
                                </div>
                                <div class="improved-bar-info">
                                    <span class="improved-date"><?php echo date('d/m', strtotime($day['date'])); ?></span>
                                    <span class="improved-ml"><?php echo $day['kcal']; ?> kcal</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Lista Simples -->
        <div class="nutrients-list-simple">
            <div class="list-header">
                <h4>Registros Recentes</h4>
            </div>
            <div class="simple-list" id="nutrients-simple-list">
                <?php if (empty($nutrients_data)): ?>
                    <div class="empty-state">
                        <i class="fas fa-utensils"></i>
                        <p>Nenhum registro de nutrientes</p>
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($nutrients_data, 0, 7) as $day): ?>
                        <div class="simple-item">
                            <div class="simple-date"><?php echo date('d/m/Y', strtotime($day['date'])); ?></div>
                            <div class="simple-amount">
                                <span class="simple-ml-value"><?php echo $day['kcal']; ?> kcal</span>
                                <span class="simple-percentage">(<?php echo $day['avg_percentage']; ?>%)</span>
                            </div>
                            <div class="simple-status <?php echo $day['status']; ?>">
                                <?php 
                                $icon = match($day['status']) {
                                    'excellent' => 'fa-check-circle',
                                    'good' => 'fa-check',
                                    'fair' => 'fa-exclamation-triangle',
                                    'poor' => 'fa-exclamation',
                                    'critical' => 'fa-times-circle',
                                    default => 'fa-question'
                                };
                                ?>
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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

const nutrientsData = <?php echo json_encode($nutrients_data); ?>;
const nutrientsStats = {
    'today': <?php echo json_encode($nutrients_stats_today); ?>,
    'yesterday': <?php echo json_encode($nutrients_stats_yesterday); ?>,
    '7': <?php echo json_encode($nutrients_stats_7); ?>,
    '15': <?php echo json_encode($nutrients_stats_15); ?>,
    '30': <?php echo json_encode($nutrients_stats_30); ?>,
    '90': <?php echo json_encode($nutrients_stats_90); ?>,
    'all': <?php echo json_encode($nutrients_stats_all); ?>
};

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

<!-- Modal de Edição de Metas -->
<div id="editGoalsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Editar Metas Nutricionais</h3>
            <span class="close" onclick="closeEditGoalsModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editGoalsForm">
                <div class="form-group">
                    <label for="calories_goal">Meta de Calorias (kcal/dia)</label>
                    <input type="number" id="calories_goal" name="calories_goal" value="<?php echo $total_daily_calories_goal; ?>" min="800" max="5000" required>
                </div>
                
                <div class="form-group">
                    <label for="protein_goal">Meta de Proteínas (g/dia)</label>
                    <input type="number" id="protein_goal" name="protein_goal" value="<?php echo $macros_goal['protein_g']; ?>" min="20" max="300" step="0.1" required>
                </div>
                
                <div class="form-group">
                    <label for="carbs_goal">Meta de Carboidratos (g/dia)</label>
                    <input type="number" id="carbs_goal" name="carbs_goal" value="<?php echo $macros_goal['carbs_g']; ?>" min="20" max="500" step="0.1" required>
                </div>
                
                <div class="form-group">
                    <label for="fat_goal">Meta de Gorduras (g/dia)</label>
                    <input type="number" id="fat_goal" name="fat_goal" value="<?php echo $macros_goal['fat_g']; ?>" min="10" max="200" step="0.1" required>
                </div>
                
                <div class="form-group">
                    <label for="water_goal">Meta de Hidratação (ml/dia)</label>
                    <input type="number" id="water_goal" name="water_goal" value="<?php echo $water_goal_ml; ?>" min="1000" max="5000" required>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeEditGoalsModal()">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="saveGoals()">Salvar Metas</button>
        </div>
    </div>
</div>

<script>
const userViewData = {
    weightHistory: <?php echo json_encode($weight_chart_data); ?>
};

// Funções do Modal de Edição de Metas
function openEditGoalsModal() {
    document.getElementById('editGoalsModal').style.display = 'block';
}

function closeEditGoalsModal() {
    document.getElementById('editGoalsModal').style.display = 'none';
}

function saveGoals() {
    const form = document.getElementById('editGoalsForm');
    const formData = new FormData(form);
    
    // Adicionar ID do usuário
    formData.append('user_id', <?php echo $user_id; ?>);
    formData.append('action', 'update_goals');
    
    // Mostrar loading
    const saveBtn = document.querySelector('.btn-primary');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
    saveBtn.disabled = true;
    
    fetch('ajax_update_goals.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Atualizar a página para mostrar as novas metas
            location.reload();
        } else {
            alert('Erro ao salvar metas: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar metas. Tente novamente.');
    })
    .finally(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

// Fechar modal ao clicar fora dele
window.onclick = function(event) {
    const modal = document.getElementById('editGoalsModal');
    if (event.target == modal) {
        closeEditGoalsModal();
    }
}

// --- FUNCIONALIDADES DO RASTREIO SEMANAL ---

let currentWeekOffset = 0;
let weeklyChart = null;

// Dados para o rastreio semanal (serão preenchidos via PHP)
const weeklyData = <?php echo json_encode($nutrients_data); ?>;
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
let weeklyChart = null;

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
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>