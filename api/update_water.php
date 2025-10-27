<?php
/**
 * API Endpoint: /api/update_water.php
 * VERSÃO 2 - CORRIGINDO O ERRO 500
 *
 * Correção:
 * - Re-adicionado o terceiro parâmetro na chamada da função `addPointsToUser`,
 *   que provavelmente estava causando o erro fatal no PHP.
 */

// Permite requisições apenas do seu site
header("Access-Control-Allow-Origin: https://www.appshapefit.com"); 
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// Responde a requisições OPTIONS (pre-flight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

define('IS_AJAX_REQUEST', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
header('Content-Type: application/json');

// Validação de Segurança (CSRF)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Acesso negado. Token CSRF inválido.']));
}

$user_id = $_SESSION['user_id'];
$date = date('Y-m-d');
$new_cup_count = filter_input(INPUT_POST, 'water_consumed', FILTER_VALIDATE_INT);

if ($new_cup_count === false || $new_cup_count < 0) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Quantidade de água inválida.']));
}

try {
    $conn->begin_transaction();

    // 1. DADOS INICIAIS
    $daily_tracking = getDailyTrackingRecord($conn, $user_id, $date);
    if (!$daily_tracking) throw new Exception("Não foi possível obter o registro diário.");
    $old_cup_count = (int)$daily_tracking['water_consumed_cups'];

    $user_profile = getUserProfileData($conn, $user_id);
    if (!$user_profile) throw new Exception("Não foi possível obter o perfil do usuário.");
    
    $water_goal_data = getWaterIntakeSuggestion((float)$user_profile['weight_kg']);
    $water_goal = $water_goal_data['cups'];

    $total_points_change = 0.0;
    
    // 2. LÓGICA DE PONTOS POR COPO
    $points_per_cup = 0.5;
    if ($new_cup_count > $old_cup_count) {
        for ($i = $old_cup_count + 1; $i <= $new_cup_count; $i++) {
            if ($i <= $water_goal) $total_points_change += $points_per_cup;
        }
    } else {
         for ($i = $new_cup_count + 1; $i <= $old_cup_count; $i++) {
             if ($i <= $water_goal) $total_points_change -= $points_per_cup;
        }
    }
    
    // 3. LÓGICA DE BÔNUS
    $points_bonus = 10.0;
    $old_status_met = ($old_cup_count >= $water_goal);
    $new_status_met = ($new_cup_count >= $water_goal);

    if ($new_status_met && !$old_status_met) $total_points_change += $points_bonus;
    elseif (!$new_status_met && $old_status_met) $total_points_change -= $points_bonus;
    
    // 4. ATUALIZA O TOTAL DE PONTOS
    if ($total_points_change != 0) {
        // ===================================================================
        // A CORREÇÃO ESTÁ AQUI. ADICIONAMOS O TERCEIRO PARÂMETRO DE VOLTA.
        // ===================================================================
        addPointsToUser($conn, $user_id, $total_points_change, "Ajuste de hidratação"); 
        
        // NOVA LÓGICA PARA PONTOS DE DESAFIO - Meta de água atingida
        if ($new_status_met && !$old_status_met) {
            updateChallengePoints($conn, $user_id, 'water_goal');
        }
    }

    // 5. ATUALIZA A CONTAGEM DE ÁGUA
    $stmt_update = $conn->prepare("UPDATE sf_user_daily_tracking SET water_consumed_cups = ? WHERE id = ?");
    $stmt_update->bind_param("ii", $new_cup_count, $daily_tracking['id']);
    $stmt_update->execute();
    $stmt_update->close();

    $conn->commit();
    
    // 6. PREPARA A RESPOSTA
    $stmt_total = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
    $stmt_total->bind_param("i", $user_id);
    $stmt_total->execute();
    $total_points = $stmt_total->get_result()->fetch_assoc()['points'];
    $stmt_total->close();

    echo json_encode([
        'success'        => true,
        'points_awarded'   => $total_points_change,
        'new_total_points' => $total_points
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    error_log("Erro em update_water.php para user_id $user_id: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro no servidor. Tente novamente.']);
}

$conn->close();
?>