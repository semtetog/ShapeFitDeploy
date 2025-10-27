<?php
// actions/uncomplete_onboarding_routine.php

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
$activity_name = trim($_POST['routine_id'] ?? ''); // O ID aqui é o nome da atividade
$current_date = date('Y-m-d');
$points_to_deduct = 5;

if (empty($activity_name)) {
    echo json_encode(['success' => false, 'message' => 'Nome da atividade inválido.']);
    exit;
}

$conn->begin_transaction();

try {
    // 1. Deletar o registro da tabela de conclusão de onboarding
    $stmt_delete = $conn->prepare(
        "DELETE FROM sf_user_onboarding_completion WHERE user_id = ? AND activity_name = ? AND completion_date = ?"
    );
    $stmt_delete->bind_param("iss", $user_id, $activity_name, $current_date);
    $stmt_delete->execute();

    if ($stmt_delete->affected_rows === 0) {
        throw new Exception("Nenhuma atividade de onboarding correspondente encontrada para desfazer.");
    }
    $stmt_delete->close();

    // 2. Remover o log de pontos correspondente
    $stmt_delete_points_log = $conn->prepare(
        "DELETE FROM sf_user_points_log WHERE user_id = ? AND action_key = 'ROUTINE_COMPLETE' AND action_context_id = ? AND date_awarded = ?"
    );
    $stmt_delete_points_log->bind_param("iss", $user_id, $activity_name, $current_date);
    $stmt_delete_points_log->execute();
    $stmt_delete_points_log->close();

    // 3. Deduzir os pontos do usuário (garantindo que não fique negativo)
    $stmt_update_points = $conn->prepare(
        "UPDATE sf_users SET points = GREATEST(points - ?, 0) WHERE id = ?"
    );
    $stmt_update_points->bind_param("ii", $points_to_deduct, $user_id);
    $stmt_update_points->execute();
    $stmt_update_points->close();

    // 3. Buscar o novo total de pontos
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
    error_log("Erro ao desfazer rotina de onboarding para user {$user_id}: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro no servidor ao desfazer a atividade.']);
}
?>