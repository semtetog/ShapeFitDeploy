<?php
// actions/complete_routine_item.php (VERSÃO FINAL E COMPLETA)

require_once '../includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Falha na validação de segurança.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$routine_id = filter_input(INPUT_POST, 'routine_id', FILTER_VALIDATE_INT);
$current_date = date('Y-m-d');
$points_to_award = 5;
$action_key = 'ROUTINE_COMPLETE'; // <<< ADIÇÃO IMPORTANTE

if (!$routine_id) {
    echo json_encode(['success' => false, 'message' => 'ID da rotina inválido.']);
    exit;
}

$conn->begin_transaction();

try {
    // 1. Verificar se a missão já foi concluída hoje
    $stmt_check = $conn->prepare("SELECT id FROM sf_user_routine_log WHERE user_id = ? AND routine_item_id = ? AND date = ?");
    $stmt_check->bind_param("iis", $user_id, $routine_id, $current_date);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        $stmt_check->close();
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Esta missão já foi concluída hoje.']);
        exit;
    }
    $stmt_check->close();

    // 2. Inserir o registro de conclusão na tabela de log de rotinas
    $stmt_insert = $conn->prepare("INSERT INTO sf_user_routine_log (user_id, routine_item_id, date, is_completed) VALUES (?, ?, ?, 1)");
    $stmt_insert->bind_param("iis", $user_id, $routine_id, $current_date);
    $stmt_insert->execute();

    if ($stmt_insert->affected_rows === 0) {
        throw new Exception("Falha ao registrar a conclusão da missão.");
    }
    $stmt_insert->close();
    
    // 3. Verificar se já existe log de pontos para esta ação hoje
    $stmt_check_log = $conn->prepare("SELECT id FROM sf_user_points_log WHERE user_id = ? AND action_key = ? AND action_context_id = ? AND date_awarded = ?");
    $stmt_check_log->bind_param("isss", $user_id, $action_key, $routine_id, $current_date);
    $stmt_check_log->execute();
    $log_exists = $stmt_check_log->get_result()->num_rows > 0;
    $stmt_check_log->close();
    
    // 4. Só adiciona pontos se não existir log para esta ação hoje
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
    
    // SINCRONIZAR PONTOS DE DESAFIO - Atualizar quando rotina é completada
    updateChallengePoints($conn, $user_id, 'routine_complete');

    // 5. Buscar o novo total de pontos para retornar ao frontend
    $stmt_get_points = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
    $stmt_get_points->bind_param("i", $user_id);
    $stmt_get_points->execute();
    $result_points = $stmt_get_points->get_result()->fetch_assoc();
    $new_total_points = $result_points['points'];
    $stmt_get_points->close();
    
    $conn->commit();

    echo json_encode([
        'success' => true,
        'points_awarded' => $points_awarded,
        'new_total_points' => $new_total_points
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Erro ao completar rotina para user {$user_id}: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro no servidor.']);
}
?>