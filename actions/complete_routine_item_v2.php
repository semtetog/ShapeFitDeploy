<?php
/**
 * Complete Routine Item V2
 * Versão melhorada que suporta registro de duração de exercícios
 */

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
$exercise_duration_minutes = filter_input(INPUT_POST, 'exercise_duration_minutes', FILTER_VALIDATE_INT);
$current_date = date('Y-m-d');
$points_to_award = 5;
$action_key = 'ROUTINE_COMPLETE';

if (!$routine_id) {
    echo json_encode(['success' => false, 'message' => 'ID da rotina inválido.']);
    exit;
}

$conn->begin_transaction();

try {
    // 1. Buscar informações da rotina
    $stmt_routine = $conn->prepare("SELECT is_exercise, exercise_type, title FROM sf_routine_items WHERE id = ?");
    $stmt_routine->bind_param("i", $routine_id);
    $stmt_routine->execute();
    $routine_info = $stmt_routine->get_result()->fetch_assoc();
    $stmt_routine->close();
    
    if (!$routine_info) {
        throw new Exception('Rotina não encontrada.');
    }
    
    $is_exercise = (int)($routine_info['is_exercise'] ?? 0);
    $exercise_type = $routine_info['exercise_type'] ?? null;
    
    // 2. Se é exercício mas não forneceu duração, retornar pedindo a duração
    if ($is_exercise && $exercise_duration_minutes === null) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'needs_duration' => true,
            'message' => 'Por favor, informe quanto tempo durou o exercício.',
            'exercise_type' => $exercise_type,
            'routine_title' => $routine_info['title']
        ]);
        exit;
    }
    
    // 3. Validar duração se for exercício
    if ($is_exercise && ($exercise_duration_minutes < 1 || $exercise_duration_minutes > 600)) {
        throw new Exception('Duração do exercício inválida (1-600 minutos).');
    }
    
    // 4. Verificar se a missão já foi concluída hoje
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

    // 5. Inserir o registro de conclusão na tabela de log de rotinas
    $stmt_insert = $conn->prepare("INSERT INTO sf_user_routine_log (user_id, routine_item_id, date, is_completed, exercise_duration_minutes) VALUES (?, ?, ?, 1, ?)");
    $stmt_insert->bind_param("iisi", $user_id, $routine_id, $current_date, $exercise_duration_minutes);
    $stmt_insert->execute();
    $stmt_insert->close();
    
    // 6. Se for exercício, o TRIGGER vai somar automaticamente no sf_user_daily_tracking
    // Nenhuma ação manual necessária aqui!
    
    // 7. Adicionar os pontos ao usuário
    $stmt_update_points = $conn->prepare("UPDATE sf_users SET points = points + ? WHERE id = ?");
    $stmt_update_points->bind_param("ii", $points_to_award, $user_id);
    $stmt_update_points->execute();
    $stmt_update_points->close();

    // 8. Registrar a ação no log de pontos para o histórico
    $stmt_log = $conn->prepare("INSERT INTO sf_user_points_log (user_id, points_awarded, action_key, action_context_id, date_awarded) VALUES (?, ?, ?, ?, ?)");
    $stmt_log->bind_param("iisss", $user_id, $points_to_award, $action_key, $routine_id, $current_date);
    $stmt_log->execute();
    $stmt_log->close();

    // 9. Buscar o novo total de pontos para retornar ao frontend
    $stmt_get_points = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
    $stmt_get_points->bind_param("i", $user_id);
    $stmt_get_points->execute();
    $result_points = $stmt_get_points->get_result()->fetch_assoc();
    $new_total_points = $result_points['points'];
    $stmt_get_points->close();
    
    $conn->commit();

    // Preparar mensagem de resposta
    $success_message = 'Rotina completada!';
    if ($is_exercise && $exercise_duration_minutes) {
        $duration_hours = round($exercise_duration_minutes / 60, 1);
        $exercise_label = ($exercise_type === 'cardio') ? 'cardio' : 'treino';
        $success_message = "Parabéns! {$duration_hours}h de {$exercise_label} registrado.";
    }

    echo json_encode([
        'success' => true,
        'message' => $success_message,
        'points_awarded' => $points_to_award,
        'new_total_points' => $new_total_points,
        'exercise_logged' => $is_exercise,
        'exercise_duration_minutes' => $exercise_duration_minutes,
        'exercise_type' => $exercise_type
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Erro ao completar rotina para user {$user_id}: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>






