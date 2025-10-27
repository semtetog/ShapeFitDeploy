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
    
    // Inserir/atualizar no log de rotina
    $stmt = $conn->prepare("
        INSERT INTO sf_user_routine_log (user_id, routine_item_id, date, is_completed, activity_key, completed_at) 
        VALUES (?, ?, ?, 1, 'sleep_tracking', NOW())
        ON DUPLICATE KEY UPDATE 
        is_completed = 1, 
        completed_at = NOW()
    ");
    
    $stmt->bind_param("iis", $user_id, $routine_id, $current_date);
    $stmt->execute();
    
    // Atualizar também na tabela de tracking diário (apenas sleep_hours existe)
    $stmt_tracking = $conn->prepare("
        INSERT INTO sf_user_daily_tracking (user_id, date, sleep_hours) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        sleep_hours = VALUES(sleep_hours)
    ");
    
    $stmt_tracking->bind_param("isd", $user_id, $current_date, $sleep_hours);
    $stmt_tracking->execute();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Sono registrado com sucesso!',
        'sleep_hours' => round($sleep_hours, 2),
        'sleep_time' => $sleep_time,
        'wake_time' => $wake_time
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao registrar sono: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>
