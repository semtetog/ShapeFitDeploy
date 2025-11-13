<?php
// api/checkin.php - API endpoint para check-in do usuário

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

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
        case 'save_progress':
            saveProgress($data, $user_id);
            break;
        case 'load_progress':
            loadProgress($data, $user_id);
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
    
    // Verificar se já existem respostas salvas (já foram salvas individualmente)
    $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM sf_checkin_responses WHERE config_id = ? AND user_id = ?");
    $stmt_check->bind_param("ii", $config_id, $user_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt_check->close();
    
    // Se não há respostas salvas, salvar agora (fallback)
    if ($count == 0) {
        $conn->begin_transaction();
        
        try {
            $stmt = $conn->prepare("INSERT INTO sf_checkin_responses (config_id, user_id, question_id, response_text, response_value, submitted_at) VALUES (?, ?, ?, ?, ?, NOW())");
            
            foreach ($responses as $question_id => $response) {
                $question_id = (int)$question_id;
                $response_text = !empty($response['response_text']) ? $response['response_text'] : null;
                $response_value = !empty($response['response_value']) ? $response['response_value'] : null;
                
                $stmt->bind_param("iiiss", $config_id, $user_id, $question_id, $response_text, $response_value);
                $stmt->execute();
            }
            
            $stmt->close();
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
    
    // Marcar check-in como completo (domingo da semana)
    $week_start = date('Y-m-d', strtotime('sunday this week'));
    
    // Verificar se o popup de congratulação já foi mostrado
    $stmt_check_congrats = $conn->prepare("SELECT congrats_shown FROM sf_checkin_availability WHERE config_id = ? AND user_id = ? AND week_date = ?");
    $stmt_check_congrats->bind_param("iis", $config_id, $user_id, $week_start);
    $stmt_check_congrats->execute();
    $result_congrats = $stmt_check_congrats->get_result();
    $availability_data = $result_congrats->fetch_assoc();
    $stmt_check_congrats->close();
    
    $points_awarded = 0;
    $congrats_shown = $availability_data['congrats_shown'] ?? 0;
    
    // Se o popup ainda não foi mostrado, adicionar pontos e marcar como mostrado
    if ($congrats_shown == 0) {
        $points_to_add = 10.0;
        $success = addPointsToUser($conn, $user_id, $points_to_add, "Check-in semanal completado - Config ID: {$config_id}");
        
        if ($success) {
            $points_awarded = $points_to_add;
            // Marcar check-in como completo e popup como mostrado
            $stmt_update = $conn->prepare("UPDATE sf_checkin_availability SET is_completed = 1, completed_at = NOW(), congrats_shown = 1 WHERE config_id = ? AND user_id = ? AND week_date = ?");
        } else {
            // Se falhar ao adicionar pontos, ainda marcar como completo, mas sem mostrar popup
            $stmt_update = $conn->prepare("UPDATE sf_checkin_availability SET is_completed = 1, completed_at = NOW() WHERE config_id = ? AND user_id = ? AND week_date = ?");
        }
    } else {
        // Popup já foi mostrado, apenas marcar como completo
        $stmt_update = $conn->prepare("UPDATE sf_checkin_availability SET is_completed = 1, completed_at = NOW() WHERE config_id = ? AND user_id = ? AND week_date = ?");
    }
    
    $stmt_update->bind_param("iis", $config_id, $user_id, $week_start);
    $stmt_update->execute();
    $stmt_update->close();
    
    // Buscar pontos atualizados do usuário
    $stmt_points = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
    $stmt_points->bind_param("i", $user_id);
    $stmt_points->execute();
    $result_points = $stmt_points->get_result();
    $user_data = $result_points->fetch_assoc();
    $new_total_points = $user_data['points'] ?? 0;
    $stmt_points->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Check-in salvo com sucesso!',
        'points_awarded' => $points_awarded,
        'new_total_points' => $new_total_points
    ]);
}

function saveProgress($data, $user_id) {
    global $conn;
    
    $config_id = (int)($data['config_id'] ?? 0);
    $question_id = (int)($data['question_id'] ?? 0);
    $response_text = $data['response_text'] ?? null;
    $response_value = $data['response_value'] ?? null;
    
    if ($config_id <= 0 || $question_id <= 0) {
        throw new Exception('IDs inválidos');
    }
    
    // Verificar se já existe resposta para esta pergunta
    $stmt_check = $conn->prepare("SELECT id FROM sf_checkin_responses WHERE config_id = ? AND user_id = ? AND question_id = ?");
    $stmt_check->bind_param("iii", $config_id, $user_id, $question_id);
    $stmt_check->execute();
    $existing = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();
    
    if ($existing) {
        // Atualizar resposta existente
        $stmt = $conn->prepare("UPDATE sf_checkin_responses SET response_text = ?, response_value = ?, submitted_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssi", $response_text, $response_value, $existing['id']);
    } else {
        // Criar nova resposta
        $stmt = $conn->prepare("INSERT INTO sf_checkin_responses (config_id, user_id, question_id, response_text, response_value, submitted_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiiss", $config_id, $user_id, $question_id, $response_text, $response_value);
    }
    
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Progresso salvo'
    ]);
}

function loadProgress($data, $user_id) {
    global $conn;
    
    $config_id = (int)($data['config_id'] ?? 0);
    
    if ($config_id <= 0) {
        throw new Exception('ID do check-in inválido');
    }
    
    // Buscar todas as respostas salvas para este check-in
    $stmt = $conn->prepare("SELECT question_id, response_text, response_value FROM sf_checkin_responses WHERE config_id = ? AND user_id = ? ORDER BY question_id ASC");
    $stmt->bind_param("ii", $config_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $responses = [];
    $answered_questions = [];
    while ($row = $result->fetch_assoc()) {
        $responses[$row['question_id']] = [
            'response_text' => $row['response_text'],
            'response_value' => $row['response_value']
        ];
        $answered_questions[] = (int)$row['question_id'];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'responses' => $responses,
        'answered_questions' => $answered_questions
    ]);
}

