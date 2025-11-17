<?php

header("Access-Control-Allow-Origin: https://localhost"); 
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// VERSÃO FINAL CORRIGIDA - BIND_PARAM AJUSTADO
define('IS_AJAX_REQUEST', true);

// 1. CARREGUE TODAS AS DEPENDÊNCIAS
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin(); 
header('Content-Type: application/json');

// 2. VALIDAÇÃO DE SEGURANÇA
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token CSRF inválido.']);
    exit;
}

// 3. OBTENÇÃO E VALIDAÇÃO DOS DADOS
$user_id = $_SESSION['user_id'];
$routine_id = filter_input(INPUT_POST, 'routine_id', FILTER_VALIDATE_INT);
$status = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT);
$current_date = date('Y-m-d');
$php_timestamp = date('Y-m-d H:i:s');

if ($routine_id === false || $status === false || !in_array($status, [0, 1])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Dados de entrada inválidos.']);
    exit;
}

// 4. LÓGICA PRINCIPAL
try {
    $conn->begin_transaction();

    // Atualiza o log da rotina
    $stmt_log = $conn->prepare("INSERT INTO sf_user_routine_log (user_id, routine_item_id, date, is_completed) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_completed = VALUES(is_completed)");
    $stmt_log->bind_param("iisi", $user_id, $routine_id, $current_date, $status);
    $stmt_log->execute();
    $stmt_log->close();

    $points_awarded = 0;
    
    // Se a tarefa foi marcada como COMPLETA, dá os pontos
    if ($status === 1) {
        $points_to_add = 5.0; // Usar float para consistência
        $action_key = 'ROUTINE_COMPLETE';
        
        $stmt_points_log = $conn->prepare("INSERT IGNORE INTO sf_user_points_log (user_id, points_awarded, action_key, action_context_id, date_awarded, timestamp) VALUES (?, ?, ?, ?, ?, ?)");
        
        // CORREÇÃO AQUI: de "iissis" para "idssis"
        $stmt_points_log->bind_param("idsiss", $user_id, $points_to_add, $action_key, $routine_id, $current_date, $php_timestamp);
        
        $stmt_points_log->execute();
        
        if ($stmt_points_log->affected_rows > 0) {
            addPointsToUser($conn, $user_id, $points_to_add, "Completou rotina ID: {$routine_id}");
            $points_awarded = $points_to_add;
        }
        $stmt_points_log->close();
    }
    // Adicionar lógica de perda de pontos aqui se desejar
    // Ex: if ($status === 0) { ... }

    $conn->commit();

    // Busca os pontos atualizados do usuário
    $stmt_total = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
    $stmt_total->bind_param("i", $user_id);
    $stmt_total->execute();
    $user_data = $stmt_total->get_result()->fetch_assoc();
    $stmt_total->close();
    
    // Garantir que os pontos sejam retornados como número inteiro
    $total_points = isset($user_data['points']) ? (int)round((float)$user_data['points']) : 0;

    echo json_encode([
        'status' => 'success',
        'message' => 'Rotina atualizada.',
        'points_awarded' => (int)$points_awarded, // Garantir inteiro
        'new_total_points' => $total_points
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    error_log("Erro em update_routine_status.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Ocorreu um erro no servidor.']);
}
$conn->close();
?>