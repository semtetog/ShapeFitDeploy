<?php
// Arquivo: api/get_dashboard_data.php (VERSÃO FINAL COMPLETA COM TOKEN)

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
    $calories_by_meal_type = getCaloriesByMealType($conn, $user_id, $current_date);

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
    $routine_items = getRoutineItemsForUser($conn, $user_id, $current_date);
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

    // --- MONTAGEM DO OBJETO DE DADOS FINAL PARA O APP ---
    $response['success'] = true;
    $response['data'] = [
        'greeting' => 'Olá, ' . htmlspecialchars(explode(' ', $user_profile_data['name'])[0]),
        'points' => (float)($user_profile_data['points'] ?? 0),
        'weight_banner' => [
            'show_edit_button' => $show_edit_button,
            'current_weight' => number_format((float)$user_profile_data['weight_kg'], 1) . "kg",
            'days_until_update' => $days_until_next_weight_update
        ],
        'daily_summary' => [
            'kcal' => ['consumed' => (float)($daily_tracking['kcal_consumed'] ?? 0), 'goal' => $total_daily_calories_goal],
            'carbs' => ['consumed' => (float)($daily_tracking['carbs_consumed_g'] ?? 0), 'goal' => $macros_goal['carbs_g']],
            'protein' => ['consumed' => (float)($daily_tracking['protein_consumed_g'] ?? 0), 'goal' => $macros_goal['protein_g']],
            'fat' => ['consumed' => (float)($daily_tracking['fat_consumed_g'] ?? 0), 'goal' => $macros_goal['fat_g']],
        ],
        'water' => [
            'consumed_cups' => (int)($daily_tracking['water_consumed_cups'] ?? 0),
            'goal_cups' => $water_goal_data['cups'],
            'goal_ml' => $water_goal_data['total_ml'],
            'cup_size_ml' => $water_goal_data['cup_size_ml']
        ],
        'routine' => [
            'progress_percentage' => $routine_progress_percentage,
            'completed_missions' => $completed_missions,
            'total_missions' => $total_missions
        ],
        'meal_suggestion' => $meal_suggestion_data,
        'calories_by_meal' => $calories_by_meal_type
    ];

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = "Erro no servidor: " . $e->getMessage();
    error_log("Erro em get_dashboard_data.php para user_id {$user_id}: " . $e->getMessage());
}

// Envia a resposta JSON final
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
$conn->close();
?>