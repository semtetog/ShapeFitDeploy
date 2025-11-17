<?php
// Arquivo: api/get_dashboard_data2.php (VERSÃO FINAL COMPLETA COM TOKEN)

// Headers de CORS que permitem a autenticação por token
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header('Content-Type: application/json; charset=utf-8');

// Responde a requisições de pré-verificação (preflight) do navegador/app
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Carrega todas as dependências
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php'; // Para a função getUserByAuthToken
require_once '../includes/functions.php';

// --- NOVA AUTENTICAÇÃO POR TOKEN ---
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
// Remove o "Bearer " do início do cabeçalho para obter o token puro
$token = $auth_header ? str_replace('Bearer ', '', $auth_header) : null;

$user = getUserByAuthToken($conn, $token);

if (!$user) {
    http_response_code(401); // 401 = Não Autorizado
    echo json_encode(['success' => false, 'message' => 'Token inválido ou expirado. Por favor, faça o login novamente.']);
    exit();
}
$user_id = $user['id']; // Temos o ID do usuário validado!
// --- FIM DA NOVA AUTENTICAÇÃO ---

// Prepara o array de resposta padrão
$response = ['success' => false, 'data' => []];
$current_date = date('Y-m-d');

try {
    // --- COLETA DE TODOS OS DADOS PARA O DASHBOARD ---

    // 1. Dados do Perfil e Metas
    $user_profile_data = getUserProfileData($conn, $user_id);
    if (!$user_profile_data) {
        throw new Exception("Perfil de usuário não encontrado.");
    }

    $age_years = calculateAge($user_profile_data['dob']);
    $total_daily_calories_goal = calculateTargetDailyCalories($user_profile_data['gender'], (float)$user_profile_data['weight_kg'], (int)$user_profile_data['height_cm'], $age_years, $user_profile_data['exercise_frequency'], $user_profile_data['objective']);
    $macros_goal = calculateMacronutrients($total_daily_calories_goal, $user_profile_data['objective']);
    $water_goal_data = getWaterIntakeSuggestion((float)$user_profile_data['weight_kg']);
    
    // 2. Dados de Consumo Diário
    $daily_tracking = getDailyTrackingRecord($conn, $user_id, $current_date);

    // 3. Lógica do Banner de Peso
    $stmt_last_weight = $conn->prepare("SELECT MAX(date_recorded) AS last_date FROM sf_user_weight_history WHERE user_id = ?");
    $stmt_last_weight->bind_param("i", $user_id);
    $stmt_last_weight->execute();
    $result_weight = $stmt_last_weight->get_result()->fetch_assoc();
    $stmt_last_weight->close();

    $show_edit_button = true;
    $days_until_next_weight_update = 0;
    if ($result_weight && !empty($result_weight['last_date'])) {
        $unlock_date = (new DateTime($result_weight['last_date']))->modify('+7 days');
        if (new DateTime('today') < $unlock_date) {
            $show_edit_button = false;
            $days_until_next_weight_update = (int)(new DateTime('today'))->diff($unlock_date)->days + 1;
        }
    }

    // 4. Lógica de Rotina / Missões
    $routine_items = getRoutineItemsForUser($conn, $user_id, $current_date, $user_profile_data); // Pass user_profile_data
    $completed_missions = 0;
    foreach($routine_items as $item) {
        if ($item['completion_status'] == 1) {
            $completed_missions++;
        }
    }
    $total_missions = count($routine_items);
    $routine_progress_percentage = ($total_missions > 0) ? round(($completed_missions / $total_missions) * 100) : 0;
    
    // 5. Lógica de Sugestão de Refeição
    $meal_suggestion_data = getMealSuggestions($conn);

    // 6. Lógica do Card de Ranking
    $stmt_my_rank = $conn->prepare("SELECT rank, points FROM (SELECT id, points, RANK() OVER (ORDER BY points DESC) as rank FROM sf_users) as r WHERE id = ?");
    $stmt_my_rank->bind_param("i", $user_id);
    $stmt_my_rank->execute();
    $my_rank_result = $stmt_my_rank->get_result()->fetch_assoc();
    $my_rank = $my_rank_result['rank'] ?? 'N/A';
    $my_points = $my_rank_result['points'] ?? 0;

    $opponent_rank = ($my_rank > 1) ? $my_rank - 1 : 2;
    $opponent_data = null;
    if ($opponent_rank > 0) {
        $stmt_opponent = $conn->prepare("SELECT u.id, u.name, u.points, up.profile_image_filename, up.gender FROM (SELECT u.id, u.name, u.points, up.profile_image_filename, up.gender, RANK() OVER (ORDER BY u.points DESC) as rank FROM sf_users u LEFT JOIN sf_user_profiles up ON u.id = up.user_id) as ranked_users WHERE rank = ? LIMIT 1");
        $stmt_opponent->bind_param("i", $opponent_rank);
        $stmt_opponent->execute();
        $opponent_data = $stmt_opponent->get_result()->fetch_assoc();
        $stmt_opponent->close();
    }

    $user_progress_percentage_ranking = 0;
    if ($my_rank > 1 && isset($opponent_data['points']) && $opponent_data['points'] > 0) { $user_progress_percentage_ranking = min(100, round(($my_points / $opponent_data['points']) * 100)); }
    elseif ($my_rank == 1) { $user_progress_percentage_ranking = 100; }

    // 7. Buscar Grupos de Desafio do Usuário (apenas ativos)
    $challenge_groups_query = "
        SELECT 
            cg.*,
            COUNT(DISTINCT cgm.user_id) as total_participants
        FROM sf_challenge_groups cg
        INNER JOIN sf_challenge_group_members cgm ON cg.id = cgm.group_id
        WHERE cgm.user_id = ? AND cg.status != 'inactive'
        GROUP BY cg.id
        ORDER BY cg.start_date DESC, cg.created_at DESC
        LIMIT 5
    ";
    $stmt_challenges = $conn->prepare($challenge_groups_query);
    $stmt_challenges->bind_param("i", $user_id);
    $stmt_challenges->execute();
    $challenge_groups_result = $stmt_challenges->get_result();
    $user_challenge_groups = [];
    while ($row = $challenge_groups_result->fetch_assoc()) {
        $row['goals'] = json_decode($row['goals'] ?? '[]', true);
        $user_challenge_groups[] = $row;
    }
    $stmt_challenges->close();

    // 8. Buscar Notificações de Desafios
    $challenge_notifications = getChallengeNotifications($conn, $user_id, 5);
    $unread_notifications_count = count($challenge_notifications);

    // 9. Buscar Check-in Disponível
    $available_checkin = null;
    $today_day_of_week = (int)date('w'); // 0=Domingo, 6=Sábado
    $week_start = date('Y-m-d', strtotime('sunday this week'));

    $checkin_query = "
        SELECT DISTINCT cc.*
        FROM sf_checkin_configs cc
        LEFT JOIN sf_checkin_distribution cd ON cc.id = cd.config_id
        LEFT JOIN sf_user_group_members ugm ON cd.target_type = 'group' AND cd.target_id = ugm.group_id
        WHERE cc.is_active = 1 
        AND cc.day_of_week = ?
        AND (
            NOT EXISTS (SELECT 1 FROM sf_checkin_distribution WHERE config_id = cc.id)
            OR
            (
                (cd.target_type = 'user' AND cd.target_id = ?)
                OR (cd.target_type = 'group' AND ugm.user_id = ?)
            )
        )
        AND NOT EXISTS (
            SELECT 1 FROM sf_checkin_availability ca
            WHERE ca.config_id = cc.id 
            AND ca.user_id = ?
            AND ca.week_date = ?
            AND ca.is_completed = 1
        )
        LIMIT 1
    ";

    $stmt_checkin = $conn->prepare($checkin_query);
    if ($stmt_checkin) {
        $stmt_checkin->bind_param("iiiis", $today_day_of_week, $user_id, $user_id, $user_id, $week_start);
        $stmt_checkin->execute();
        $checkin_result = $stmt_checkin->get_result();
        if ($checkin_result->num_rows > 0) {
            $available_checkin = $checkin_result->fetch_assoc();
            
            $questions_query = "SELECT * FROM sf_checkin_questions WHERE config_id = ? ORDER BY order_index ASC";
            $stmt_questions = $conn->prepare($questions_query);
            $stmt_questions->bind_param("i", $available_checkin['id']);
            $stmt_questions->execute();
            $questions_result = $stmt_questions->get_result();
            $available_checkin['questions'] = [];
            while ($q = $questions_result->fetch_assoc()) {
                $q['options'] = !empty($q['options']) ? json_decode($q['options'], true) : null;
                $q['conditional_logic'] = !empty($q['conditional_logic']) ? json_decode($q['conditional_logic'], true) : null;
                $available_checkin['questions'][] = $q;
            }
            $stmt_questions->close();
            
            $availability_query = "SELECT * FROM sf_checkin_availability WHERE config_id = ? AND user_id = ? AND week_date = ?";
            $stmt_avail = $conn->prepare($availability_query);
            $stmt_avail->bind_param("iis", $available_checkin['id'], $user_id, $week_start);
            $stmt_avail->execute();
            $avail_result = $stmt_avail->get_result();
            
            if ($avail_result->num_rows === 0) {
                $insert_avail = "INSERT INTO sf_checkin_availability (config_id, user_id, week_date, is_available, available_at) VALUES (?, ?, ?, 1, NOW())";
                $stmt_insert = $conn->prepare($insert_avail);
                $stmt_insert->bind_param("iis", $available_checkin['id'], $user_id, $week_start);
                $stmt_insert->execute();
                $stmt_insert->close();
            }
            $stmt_avail->close();
        }
        $stmt_checkin->close();
    }

    // --- MONTAGEM DO OBJETO DE DADOS FINAL PARA O APP ---
    $response['success'] = true;
    $response['data'] = [
        'greeting' => 'Olá, ' . htmlspecialchars(explode(' ', $user_profile_data['name'])[0]),
        'user_name' => htmlspecialchars($user_profile_data['name']),
        'user_profile_image_filename' => htmlspecialchars($user_profile_data['profile_image_filename'] ?? ''),
        'points' => (float)($user_profile_data['points'] ?? 0),
        'weight_banner' => [
            'show_edit_button' => $show_edit_button,
            'current_weight' => number_format((float)$user_profile_data['weight_kg'], 1, ',', '.') . "kg",
            'days_until_update' => $days_until_next_weight_update
        ],
        'daily_summary' => [
            'kcal' => ['consumed' => (float)($daily_tracking['kcal_consumed'] ?? 0), 'goal' => $total_daily_calories_goal],
            'carbs' => ['consumed' => (float)($daily_tracking['carbs_consumed_g'] ?? 0), 'goal' => $macros_goal['carbs_g']],
            'protein' => ['consumed' => (float)($daily_tracking['protein_consumed_g'] ?? 0), 'goal' => $macros_goal['protein_g']],
            'fat' => ['consumed' => (float)($daily_tracking['fat_consumed_g'] ?? 0), 'goal' => $macros_goal['fat_g']],
        ],
        'water' => [
            'consumed_ml' => (float)($daily_tracking['water_consumed_cups'] ?? 0) * $water_goal_data['cup_size_ml'],
            'goal_ml' => $water_goal_data['total_ml'],
            'cup_size_ml' => $water_goal_data['cup_size_ml']
        ],
        'routine' => [
            'progress_percentage' => $routine_progress_percentage,
            'completed_missions' => $completed_missions,
            'total_missions' => $total_missions,
            'items' => $routine_items
        ],
        'meal_suggestion' => $meal_suggestion_data,
        'ranking' => [
            'my_rank' => $my_rank,
            'my_points' => $my_points,
            'opponent_data' => $opponent_data ? [
                'name' => htmlspecialchars(explode(' ', $opponent_data['name'])[0]),
                'profile_image_filename' => htmlspecialchars($opponent_data['profile_image_filename'] ?? '')
            ] : null,
            'user_progress_percentage' => $user_progress_percentage_ranking
        ],
        'challenge_groups' => $user_challenge_groups,
        'challenge_notifications' => $challenge_notifications,
        'unread_notifications_count' => $unread_notifications_count,
        'available_checkin' => $available_checkin,
        'current_date' => $current_date
    ];

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = "Erro no servidor: " . $e->getMessage();
    error_log("Erro em get_dashboard_data2.php para user_id {$user_id}: " . $e->getMessage());
}

// Envia a resposta JSON final
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
$conn->close();
?>
