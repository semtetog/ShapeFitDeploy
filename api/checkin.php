<?php
// api/checkin.php - API endpoint para check-in do usuário

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    $data = $_POST;
}

$action = $data['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

try {
    switch ($action) {
        case 'submit_checkin':
            submitCheckin($data, $user_id);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
            exit;
    }
} catch (Exception $e) {
    error_log("Erro em api/checkin.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

function submitCheckin($data, $user_id) {
    global $conn;
    
    $config_id = (int)($data['config_id'] ?? 0);
    $responses = json_decode($data['responses'] ?? '{}', true);
    
    if ($config_id <= 0) {
        throw new Exception('ID do check-in inválido');
    }
    
    if (empty($responses) || !is_array($responses)) {
        throw new Exception('Nenhuma resposta fornecida');
    }
    
    // Verificar se o check-in está disponível para o usuário
    $stmt_check = $conn->prepare("SELECT id FROM sf_checkin_configs WHERE id = ? AND is_active = 1");
    $stmt_check->bind_param("i", $config_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('Check-in não encontrado ou inativo');
    }
    $stmt_check->close();
    
    $conn->begin_transaction();
    
    try {
        // Salvar cada resposta
        $stmt = $conn->prepare("INSERT INTO sf_checkin_responses (config_id, user_id, question_id, response_text, response_value, submitted_at) VALUES (?, ?, ?, ?, ?, NOW())");
        
        foreach ($responses as $question_id => $response) {
            $question_id = (int)$question_id;
            $response_text = !empty($response['response_text']) ? $response['response_text'] : null;
            $response_value = !empty($response['response_value']) ? $response['response_value'] : null;
            
            $stmt->bind_param("iiiss", $config_id, $user_id, $question_id, $response_text, $response_value);
            $stmt->execute();
        }
        
        $stmt->close();
        
        // Marcar check-in como completo
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $stmt_update = $conn->prepare("UPDATE sf_checkin_availability SET is_completed = 1, completed_at = NOW() WHERE config_id = ? AND user_id = ? AND week_date = ?");
        $stmt_update->bind_param("iis", $config_id, $user_id, $week_start);
        $stmt_update->execute();
        $stmt_update->close();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Check-in salvo com sucesso!'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

