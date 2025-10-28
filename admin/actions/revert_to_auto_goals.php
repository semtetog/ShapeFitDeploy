<?php
ob_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/auth_admin.php';
$conn = require __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception("Acesso não autorizado.");
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método inválido.");
    }

    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    
    error_log("revert_to_auto_goals.php - user_id recebido: " . var_export($user_id, true));
    error_log("revert_to_auto_goals.php - POST data: " . print_r($_POST, true));
    
    if ($user_id === false || $user_id <= 0) {
        throw new Exception("ID de usuário inválido. Recebido: " . var_export($user_id, true));
    }

    // Limpar (set NULL) todas as metas customizadas
    $stmt = $conn->prepare("
        UPDATE sf_user_profiles 
        SET custom_calories_goal = NULL,
            custom_protein_goal_g = NULL,
            custom_carbs_goal_g = NULL,
            custom_fat_goal_g = NULL,
            custom_water_goal_ml = NULL
        WHERE user_id = ?
    ");

    if (!$stmt) {
        throw new Exception("Erro ao preparar query: " . $conn->error);
    }

    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Metas revertidas para cálculo automático com sucesso!';
    } else {
        throw new Exception("Erro ao executar query: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Erro em revert_to_auto_goals.php: " . $e->getMessage());
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
    ob_clean();
    echo json_encode($response);
    ob_end_flush();
    exit;
}

