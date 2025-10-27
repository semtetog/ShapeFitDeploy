<?php
// actions/complete_onboarding_routine.php (VERSÃO FINAL E COMPLETA)

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
$activity_name = trim($_POST['routine_id'] ?? '');
$duration_minutes = filter_input(INPUT_POST, 'duration_minutes', FILTER_VALIDATE_INT);
$current_date = date('Y-m-d');
$points_to_award = 5;
$action_key = 'ROUTINE_COMPLETE'; // <<< ADIÇÃO IMPORTANTE

// Validar duração se fornecida
if ($duration_minutes && ($duration_minutes < 15 || $duration_minutes > 300)) {
    echo json_encode(['success' => false, 'message' => 'Duração deve estar entre 15 e 300 minutos.']);
    exit;
}

if (empty($activity_name)) {
    echo json_encode(['success' => false, 'message' => 'Nome da atividade inválido.']);
    exit;
}

$conn->begin_transaction();

try {
    // 1. Verifica se esta atividade de onboarding já foi concluída hoje
    $stmt_check = $conn->prepare("SELECT id FROM sf_user_onboarding_completion WHERE user_id = ? AND activity_name = ? AND completion_date = ?");
    $stmt_check->bind_param("iss", $user_id, $activity_name, $current_date);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        $stmt_check->close();
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Esta atividade já foi concluída hoje.']);
        exit;
    }
    $stmt_check->close();

    // 2. Insere na nova tabela de conclusão de onboarding
    $stmt_insert = $conn->prepare("INSERT INTO sf_user_onboarding_completion (user_id, activity_name, completion_date) VALUES (?, ?, ?)");
    $stmt_insert->bind_param("iss", $user_id, $activity_name, $current_date);
    $stmt_insert->execute();

    if ($stmt_insert->affected_rows === 0) {
        throw new Exception("Falha ao registrar a conclusão da atividade.");
    }
    $stmt_insert->close();

    // 2.5. Se foi fornecida duração, salvar na tabela de durações
    if ($duration_minutes) {
        $stmt_duration = $conn->prepare("INSERT INTO sf_user_exercise_durations (user_id, exercise_name, duration_minutes) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE duration_minutes = VALUES(duration_minutes)");
        $stmt_duration->bind_param("isi", $user_id, $activity_name, $duration_minutes);
        $stmt_duration->execute();
        $stmt_duration->close();
    }

    // 3. Verificar se já existe log de pontos para esta ação hoje
    $stmt_check_log = $conn->prepare("SELECT id FROM sf_user_points_log WHERE user_id = ? AND action_key = ? AND action_context_id = ? AND date_awarded = ?");
    $stmt_check_log->bind_param("isss", $user_id, $action_key, $activity_name, $current_date);
    $stmt_check_log->execute();
    $log_exists = $stmt_check_log->get_result()->num_rows > 0;
    $stmt_check_log->close();
    
    // 4. Só adiciona pontos se não existir log para esta ação hoje
    $points_awarded = 0;
    if (!$log_exists) {
        // Registrar a ação no log de pontos
        $stmt_log = $conn->prepare("INSERT INTO sf_user_points_log (user_id, points_awarded, action_key, action_context_id, date_awarded, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt_log->bind_param("iisss", $user_id, $points_to_award, $action_key, $activity_name, $current_date);
        $stmt_log->execute();
        $stmt_log->close();
        
        // Adicionar pontos ao usuário
        addPointsToUser($conn, $user_id, $points_to_award, "Completou atividade: {$activity_name}");
        $points_awarded = $points_to_award;
    }

    // 5. Busca o novo total de pontos
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
    error_log("Erro ao completar rotina de onboarding para user {$user_id}: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro no servidor.']);
}
?>