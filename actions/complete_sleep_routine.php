<?php
// actions/complete_sleep_routine.php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();
require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$user_id = $_SESSION['user_id'];
$sleep_time = $_POST['sleep_time'] ?? '';
$wake_time = $_POST['wake_time'] ?? '';
$routine_id = $_POST['routine_id'] ?? '';

// Validar dados
if (empty($sleep_time) || empty($wake_time) || empty($routine_id)) {
    echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não fornecidos']);
    exit;
}

// Validar formato das horas (HH:MM)
if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $sleep_time) || 
    !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $wake_time)) {
    echo json_encode(['success' => false, 'message' => 'Formato de hora inválido']);
    exit;
}

try {
    // Calcular horas de sono
    $sleep_timestamp = strtotime($sleep_time);
    $wake_timestamp = strtotime($wake_time);
    
    // Se acordou no dia seguinte
    if ($wake_timestamp <= $sleep_timestamp) {
        $wake_timestamp += 24 * 3600; // Adicionar 24 horas
    }
    
    $sleep_hours = ($wake_timestamp - $sleep_timestamp) / 3600;
    
    // Verificar se as horas de sono são razoáveis (entre 3 e 15 horas)
    if ($sleep_hours < 3 || $sleep_hours > 15) {
        echo json_encode(['success' => false, 'message' => 'Horas de sono devem estar entre 3 e 15 horas']);
        exit;
    }
    
    $current_date = date('Y-m-d');
    
    // Verificar se a missão já foi concluída hoje
    $stmt_check = $conn->prepare("SELECT id FROM sf_user_routine_log WHERE user_id = ? AND routine_item_id = ? AND date = ?");
    $stmt_check->bind_param("iis", $user_id, $routine_id, $current_date);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    // Se já existe, atualizar; se não, inserir
    if ($result_check->num_rows > 0) {
        $stmt_update = $conn->prepare("
            UPDATE sf_user_routine_log 
            SET is_completed = 1, activity_key = 'sleep_tracking', completed_at = NOW() 
            WHERE user_id = ? AND routine_item_id = ? AND date = ?
        ");
        $stmt_update->bind_param("iis", $user_id, $routine_id, $current_date);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        // Inserir no log de rotina
        $stmt = $conn->prepare("
            INSERT INTO sf_user_routine_log (user_id, routine_item_id, date, is_completed, activity_key, completed_at) 
            VALUES (?, ?, ?, 1, 'sleep_tracking', NOW())
        ");
        $stmt->bind_param("iis", $user_id, $routine_id, $current_date);
        $stmt->execute();
        $stmt->close();
    }
    $stmt_check->close();
    
    // Atualizar também na tabela de tracking diário (apenas sleep_hours existe)
    $stmt_tracking = $conn->prepare("
        INSERT INTO sf_user_daily_tracking (user_id, date, sleep_hours) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        sleep_hours = VALUES(sleep_hours)
    ");
    
    $stmt_tracking->bind_param("isd", $user_id, $current_date, $sleep_hours);
    $stmt_tracking->execute();
    $stmt_tracking->close();
    
    // Registrar pontos (se não existir log para esta ação hoje)
    require_once APP_ROOT_PATH . '/includes/functions.php';
    $action_key = 'ROUTINE_COMPLETE';
    $points_to_award = 5;
    
    $stmt_check_log = $conn->prepare("SELECT id FROM sf_user_points_log WHERE user_id = ? AND action_key = ? AND action_context_id = ? AND date_awarded = ?");
    $stmt_check_log->bind_param("isss", $user_id, $action_key, $routine_id, $current_date);
    $stmt_check_log->execute();
    $log_exists = $stmt_check_log->get_result()->num_rows > 0;
    $stmt_check_log->close();
    
    $points_awarded = 0;
    if (!$log_exists) {
        // Registrar a ação no log de pontos
        $stmt_log = $conn->prepare("INSERT INTO sf_user_points_log (user_id, points_awarded, action_key, action_context_id, date_awarded, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt_log->bind_param("iisss", $user_id, $points_to_award, $action_key, $routine_id, $current_date);
        $stmt_log->execute();
        $stmt_log->close();
        
        // Adicionar pontos ao usuário
        addPointsToUser($conn, $user_id, $points_to_award, "Completou rotina ID: {$routine_id}");
        $points_awarded = $points_to_award;
    }
    
    // SINCRONIZAR PONTOS DE DESAFIO - Atualizar quando sono é registrado
    updateChallengePoints($conn, $user_id, 'sleep_complete');
    
    // Buscar o novo total de pontos para retornar ao frontend
    $stmt_get_points = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
    $stmt_get_points->bind_param("i", $user_id);
    $stmt_get_points->execute();
    $result_points = $stmt_get_points->get_result()->fetch_assoc();
    $stmt_get_points->close();
    
    // Garantir que os pontos sejam retornados como número inteiro
    $new_total_points = isset($result_points['points']) ? (int)round((float)$result_points['points']) : 0;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Sono registrado com sucesso!',
        'sleep_hours' => round($sleep_hours, 2),
        'sleep_time' => $sleep_time,
        'wake_time' => $wake_time,
        'points_awarded' => (int)$points_awarded, // Garantir inteiro
        'new_total_points' => $new_total_points
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao registrar sono: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>
