<?php
// actions/uncomplete_routine_item.php

require_once '../includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';

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
$points_to_deduct = 5; // A mesma quantidade que foi adicionada

if (!$routine_id) {
    echo json_encode(['success' => false, 'message' => 'ID da rotina inválido.']);
    exit;
}

$conn->begin_transaction();

try {
    // 1. Deletar o registro de conclusão da tabela de logs
    $stmt_delete = $conn->prepare(
        "DELETE FROM sf_user_routine_log WHERE user_id = ? AND routine_item_id = ? AND date = ?"
    );
    $stmt_delete->bind_param("iis", $user_id, $routine_id, $current_date);
    $stmt_delete->execute();

    // Se nenhuma linha foi afetada, a tarefa não estava completa para começar.
    if ($stmt_delete->affected_rows === 0) {
        throw new Exception("Nenhuma tarefa correspondente encontrada para desfazer.");
    }
    $stmt_delete->close();

    // 2. Remover o log de pontos correspondente
    $stmt_delete_points_log = $conn->prepare(
        "DELETE FROM sf_user_points_log WHERE user_id = ? AND action_key = 'ROUTINE_COMPLETE' AND action_context_id = ? AND date_awarded = ?"
    );
    $stmt_delete_points_log->bind_param("iss", $user_id, $routine_id, $current_date);
    $stmt_delete_points_log->execute();
    $stmt_delete_points_log->close();

    // 3. Deduzir os pontos do usuário (garantindo que não fique negativo)
    $stmt_update_points = $conn->prepare(
        "UPDATE sf_users SET points = GREATEST(points - ?, 0) WHERE id = ?"
    );
    $stmt_update_points->bind_param("ii", $points_to_deduct, $user_id);
    $stmt_update_points->execute();
    $stmt_update_points->close();

    // 4. Se for o item de sono (ID 8), remover também os dados de sono do tracking diário
    if ($routine_id == 8) {
        $stmt_remove_sleep = $conn->prepare(
            "UPDATE sf_user_daily_tracking SET sleep_hours = 0 WHERE user_id = ? AND date = ?"
        );
        $stmt_remove_sleep->bind_param("is", $user_id, $current_date);
        $stmt_remove_sleep->execute();
        $stmt_remove_sleep->close();
    }

    // 5. Buscar o novo total de pontos
    $stmt_get_points = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
    $stmt_get_points->bind_param("i", $user_id);
    $stmt_get_points->execute();
    $result_points = $stmt_get_points->get_result()->fetch_assoc();
    $new_total_points = $result_points['points'];
    $stmt_get_points->close();
    
    $conn->commit();

    echo json_encode([
        'success' => true,
        'points_deducted' => $points_to_deduct,
        'new_total_points' => $new_total_points
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Erro ao desfazer rotina para user {$user_id}: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro no servidor ao desfazer a tarefa.']);
}
?>