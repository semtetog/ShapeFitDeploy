<?php
/**
 * API para CRUD de Rotina - Usando tabelas reais do ShapeFit
 * sf_routine_items, sf_user_routine_log, sf_user_onboarding_completion
 */

session_start();
require_once __DIR__ . '/../config.php';

// Verificar se o usuário está autenticado e é admin/nutricionista
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

// Headers para JSON
header('Content-Type: application/json');

// Obter método e ação
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Conexão com o banco
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro de conexão com o banco']);
    exit;
}

$conn->set_charset('utf8mb4');

// Obter user_id do paciente
$patient_id = intval($_GET['patient_id'] ?? 0);
if ($patient_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do paciente inválido']);
    exit;
}

// Processar ação
try {
    switch ($action) {
        case 'list_missions':
            listMissions($conn, $patient_id);
            break;
        
        case 'get_mission':
            getMission($conn, $patient_id);
            break;
        
        case 'create_mission':
            createMission($conn, $patient_id);
            break;
        
        case 'update_mission':
            updateMission($conn, $patient_id);
            break;
        
        case 'delete_mission':
            deleteMission($conn, $patient_id);
            break;
        
        case 'list_exercises':
            listExercises($conn, $patient_id);
            break;
        
        case 'create_exercise':
            createExercise($conn, $patient_id);
            break;
        
        case 'update_exercise':
            updateExercise($conn, $patient_id);
            break;
        
        case 'delete_exercise':
            deleteExercise($conn, $patient_id);
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();

/**
 * Listar missões do paciente
 */
function listMissions($conn, $patient_id) {
    $sql = "SELECT id, title, icon_class, description, is_exercise, exercise_type, 
            default_for_all_users, user_id_creator
            FROM sf_routine_items 
            WHERE is_active = 1 AND (default_for_all_users = 1 OR user_id_creator = ?)
            ORDER BY id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $missions = [];
    while ($row = $result->fetch_assoc()) {
        $missions[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $missions]);
}

/**
 * Obter uma missão específica
 */
function getMission($conn, $patient_id) {
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT id, title, icon_class, description, is_exercise, exercise_type
                            FROM sf_routine_items 
                            WHERE id = ? AND is_active = 1 AND (default_for_all_users = 1 OR user_id_creator = ?)");
    $stmt->bind_param('ii', $id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Missão não encontrada']);
        return;
    }
    
    $mission = $result->fetch_assoc();
    echo json_encode(['success' => true, 'data' => $mission]);
}

/**
 * Criar nova missão
 */
function createMission($conn, $patient_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validações
    if (empty($data['title'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Título da missão é obrigatório']);
        return;
    }
    
    $title = $conn->real_escape_string(trim($data['title']));
    $description = isset($data['description']) ? $conn->real_escape_string(trim($data['description'])) : null;
    $icon_class = $conn->real_escape_string($data['icon_class'] ?? 'fa-check-circle');
    $is_exercise = isset($data['is_exercise']) ? intval($data['is_exercise']) : 0;
    $exercise_type = isset($data['exercise_type']) ? $conn->real_escape_string($data['exercise_type']) : null;
    
    $stmt = $conn->prepare("INSERT INTO sf_routine_items 
                           (title, icon_class, description, is_exercise, exercise_type, 
                            default_for_all_users, user_id_creator, is_active) 
                           VALUES (?, ?, ?, ?, ?, 0, ?, 1)");
    $stmt->bind_param('sssisi', $title, $icon_class, $description, $is_exercise, $exercise_type, $patient_id);
    
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        echo json_encode([
            'success' => true, 
            'message' => 'Missão criada com sucesso',
            'id' => $new_id
        ]);
    } else {
        throw new Exception('Erro ao criar missão: ' . $conn->error);
    }
}

/**
 * Atualizar missão existente
 */
function updateMission($conn, $patient_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = intval($data['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    
    // Validações
    if (empty($data['title'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Título da missão é obrigatório']);
        return;
    }
    
    $title = $conn->real_escape_string(trim($data['title']));
    $description = isset($data['description']) ? $conn->real_escape_string(trim($data['description'])) : null;
    $icon_class = $conn->real_escape_string($data['icon_class'] ?? 'fa-check-circle');
    $is_exercise = isset($data['is_exercise']) ? intval($data['is_exercise']) : 0;
    $exercise_type = isset($data['exercise_type']) ? $conn->real_escape_string($data['exercise_type']) : null;
    
    $stmt = $conn->prepare("UPDATE sf_routine_items 
                           SET title = ?, icon_class = ?, description = ?, 
                               is_exercise = ?, exercise_type = ?
                           WHERE id = ? AND user_id_creator = ?");
    $stmt->bind_param('sssisi', $title, $icon_class, $description, $is_exercise, $exercise_type, $id, $patient_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Missão atualizada com sucesso'
        ]);
    } else {
        throw new Exception('Erro ao atualizar missão: ' . $conn->error);
    }
}

/**
 * Excluir missão
 */
function deleteMission($conn, $patient_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? $_GET['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // Excluir registros de log do usuário
        $stmt1 = $conn->prepare("DELETE FROM sf_user_routine_log WHERE routine_item_id = ? AND user_id = ?");
        $stmt1->bind_param('ii', $id, $patient_id);
        $stmt1->execute();
        
        // Excluir a missão (apenas se foi criada pelo usuário)
        $stmt2 = $conn->prepare("DELETE FROM sf_routine_items WHERE id = ? AND user_id_creator = ?");
        $stmt2->bind_param('ii', $id, $patient_id);
        $stmt2->execute();
        
        if ($stmt2->affected_rows === 0) {
            throw new Exception('Missão não encontrada ou não pode ser excluída');
        }
        
        $conn->commit();
        echo json_encode([
            'success' => true, 
            'message' => 'Missão excluída com sucesso'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Listar exercícios do onboarding
 */
function listExercises($conn, $patient_id) {
    $sql = "SELECT id, activity_name, completion_date 
            FROM sf_user_onboarding_completion 
            WHERE user_id = ? 
            ORDER BY completion_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $exercises = [];
    while ($row = $result->fetch_assoc()) {
        $exercises[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $exercises]);
}

/**
 * Criar novo exercício
 */
function createExercise($conn, $patient_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['activity_name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nome do exercício é obrigatório']);
        return;
    }
    
    $activity_name = $conn->real_escape_string(trim($data['activity_name']));
    $completion_date = $data['completion_date'] ?? date('Y-m-d');
    
    $stmt = $conn->prepare("INSERT INTO sf_user_onboarding_completion 
                           (user_id, activity_name, completion_date) 
                           VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $patient_id, $activity_name, $completion_date);
    
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        echo json_encode([
            'success' => true, 
            'message' => 'Exercício adicionado com sucesso',
            'id' => $new_id
        ]);
    } else {
        throw new Exception('Erro ao adicionar exercício: ' . $conn->error);
    }
}

/**
 * Atualizar exercício
 */
function updateExercise($conn, $patient_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = intval($data['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    
    if (empty($data['activity_name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nome do exercício é obrigatório']);
        return;
    }
    
    $activity_name = $conn->real_escape_string(trim($data['activity_name']));
    $completion_date = $data['completion_date'] ?? date('Y-m-d');
    
    $stmt = $conn->prepare("UPDATE sf_user_onboarding_completion 
                           SET activity_name = ?, completion_date = ?
                           WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ssii', $activity_name, $completion_date, $id, $patient_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Exercício atualizado com sucesso'
        ]);
    } else {
        throw new Exception('Erro ao atualizar exercício: ' . $conn->error);
    }
}

/**
 * Excluir exercício
 */
function deleteExercise($conn, $patient_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? $_GET['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM sf_user_onboarding_completion WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $id, $patient_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Exercício excluído com sucesso'
        ]);
    } else {
        throw new Exception('Erro ao excluir exercício: ' . $conn->error);
    }
}
?>
