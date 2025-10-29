<?php
/**
 * API para CRUD de Rotina - Usando tabelas reais do ShapeFit
 * sf_routine_items, sf_user_routine_log, sf_user_onboarding_completion
 */

session_start();
require_once __DIR__ . '/../config.php';

// Debug: verificar se as constantes estão definidas
if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_NAME')) {
    error_log('DEBUG - Constantes de banco não definidas');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuração de banco não encontrada']);
    exit;
}

// Verificar se o usuário está autenticado e é admin/nutricionista
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

// Headers para JSON
header('Content-Type: application/json; charset=utf-8');

// Obter método e ação
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Conexão com o banco
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    error_log('DEBUG - Erro de conexão: ' . $conn->connect_error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro de conexão com o banco: ' . $conn->connect_error]);
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
    error_log('DEBUG - Ação: ' . $action . ', Patient ID: ' . $patient_id);
    switch ($action) {
        case 'list_missions':
            $result = listMissions($conn, $patient_id);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'get_mission':
            $id = intval($_GET['id'] ?? 0);
            if (!$id) {
                throw new Exception('ID da missão é obrigatório');
            }
            $result = getMission($conn, $id, $patient_id);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'create_mission':
            if ($method !== 'POST') {
                throw new Exception('Método não permitido');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Dados inválidos');
            }
            createMission($conn, $data, $patient_id);
            break;
            
        case 'update_mission':
            if ($method !== 'POST') {
                throw new Exception('Método não permitido');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Dados inválidos');
            }
            updateMission($conn, $data, $patient_id);
            break;
            
        case 'delete_mission':
            if ($method !== 'POST') {
                throw new Exception('Método não permitido');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !isset($data['id'])) {
                throw new Exception('ID da missão é obrigatório');
            }
            deleteMission($conn, intval($data['id']), $patient_id);
            break;
            
        case 'list_exercises':
            $result = listExercises($conn, $patient_id);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'get_exercise':
            $id = intval($_GET['id'] ?? 0);
            if (!$id) {
                throw new Exception('ID do exercício é obrigatório');
            }
            $result = getExercise($conn, $id, $patient_id);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'create_exercise':
            if ($method !== 'POST') {
                throw new Exception('Método não permitido');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Dados inválidos');
            }
            createExercise($conn, $data, $patient_id);
            break;
            
        case 'update_exercise':
            if ($method !== 'POST') {
                throw new Exception('Método não permitido');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Dados inválidos');
            }
            updateExercise($conn, $data, $patient_id);
            break;
            
        case 'delete_exercise':
            if ($method !== 'POST') {
                throw new Exception('Método não permitido');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !isset($data['id'])) {
                throw new Exception('ID do exercício é obrigatório');
            }
            deleteExercise($conn, intval($data['id']), $patient_id);
            break;
            
        default:
            throw new Exception('Ação não reconhecida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}

/**
 * Listar missões do paciente
 */
function listMissions($conn, $patient_id) {
    $sql = "SELECT i.id, i.title, i.icon_class, i.description, i.is_exercise, i.exercise_type, 
                   i.default_for_all_users, i.user_id_creator
            FROM sf_routine_items i 
            WHERE i.is_active = 1 AND (i.default_for_all_users = 1 OR i.user_id_creator = ?)
            ORDER BY i.id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $missions = [];
    while ($row = $result->fetch_assoc()) {
        $missions[] = $row;
    }
    
    $stmt->close();
    return $missions;
}

/**
 * Obter missão específica
 */
function getMission($conn, $id, $patient_id) {
    $sql = "SELECT i.id, i.title, i.icon_class, i.description, i.is_exercise, i.exercise_type, 
                   i.default_for_all_users, i.user_id_creator
            FROM sf_routine_items i 
            WHERE i.id = ? AND i.is_active = 1 AND (i.default_for_all_users = 1 OR i.user_id_creator = ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row;
    } else {
        $stmt->close();
        throw new Exception('Missão não encontrada');
    }
}

/**
 * Criar nova missão
 */
function createMission($conn, $data, $patient_id) {
    // Validações
    if (empty($data['title'])) {
        throw new Exception('Título da missão é obrigatório');
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
    
    $stmt->close();
}

/**
 * Atualizar missão
 */
function updateMission($conn, $data, $patient_id) {
    if (empty($data['id'])) {
        throw new Exception('ID da missão é obrigatório');
    }
    
    $id = intval($data['id']);
    $title = $conn->real_escape_string(trim($data['title']));
    $description = isset($data['description']) ? $conn->real_escape_string(trim($data['description'])) : null;
    $icon_class = $conn->real_escape_string($data['icon_class'] ?? 'fa-check-circle');
    $is_exercise = isset($data['is_exercise']) ? intval($data['is_exercise']) : 0;
    $exercise_type = isset($data['exercise_type']) ? $conn->real_escape_string($data['exercise_type']) : null;
    
    $stmt = $conn->prepare("UPDATE sf_routine_items 
                           SET title = ?, icon_class = ?, description = ?, is_exercise = ?, exercise_type = ?
                           WHERE id = ? AND (default_for_all_users = 1 OR user_id_creator = ?)");
    $stmt->bind_param('sssisi', $title, $icon_class, $description, $is_exercise, $exercise_type, $id, $patient_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows === 0) {
            throw new Exception('Missão não encontrada ou não pode ser editada');
        }
        echo json_encode([
            'success' => true, 
            'message' => 'Missão atualizada com sucesso'
        ]);
    } else {
        throw new Exception('Erro ao atualizar missão: ' . $conn->error);
    }
    
    $stmt->close();
}

/**
 * Excluir missão
 */
function deleteMission($conn, $id, $patient_id) {
    $conn->begin_transaction();
    
    try {
        // Excluir logs da missão
        $stmt1 = $conn->prepare("DELETE FROM sf_user_routine_log WHERE routine_item_id = ? AND user_id = ?");
        $stmt1->bind_param('ii', $id, $patient_id);
        $stmt1->execute();
        $stmt1->close();
        
        // Excluir a missão
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
    
    $stmt2->close();
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
    
    $stmt->close();
    return $exercises;
}

/**
 * Obter exercício específico
 */
function getExercise($conn, $id, $patient_id) {
    $sql = "SELECT id, activity_name, completion_date 
            FROM sf_user_onboarding_completion 
            WHERE id = ? AND user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row;
    } else {
        $stmt->close();
        throw new Exception('Exercício não encontrado');
    }
}

/**
 * Criar novo exercício
 */
function createExercise($conn, $data, $patient_id) {
    if (empty($data['activity_name'])) {
        throw new Exception('Nome do exercício é obrigatório');
    }
    
    $activity_name = $conn->real_escape_string(trim($data['activity_name']));
    $completion_date = $data['completion_date'] ?? date('Y-m-d');
    
    $stmt = $conn->prepare("INSERT INTO sf_user_onboarding_completion (user_id, activity_name, completion_date) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $patient_id, $activity_name, $completion_date);
    
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        echo json_encode([
            'success' => true, 
            'message' => 'Exercício criado com sucesso',
            'id' => $new_id
        ]);
    } else {
        throw new Exception('Erro ao criar exercício: ' . $conn->error);
    }
    
    $stmt->close();
}

/**
 * Atualizar exercício
 */
function updateExercise($conn, $data, $patient_id) {
    if (empty($data['id'])) {
        throw new Exception('ID do exercício é obrigatório');
    }
    
    $id = intval($data['id']);
    $activity_name = $conn->real_escape_string(trim($data['activity_name']));
    $completion_date = $data['completion_date'] ?? date('Y-m-d');
    
    $stmt = $conn->prepare("UPDATE sf_user_onboarding_completion 
                           SET activity_name = ?, completion_date = ?
                           WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ssii', $activity_name, $completion_date, $id, $patient_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows === 0) {
            throw new Exception('Exercício não encontrado ou não pode ser editado');
        }
        echo json_encode([
            'success' => true, 
            'message' => 'Exercício atualizado com sucesso'
        ]);
    } else {
        throw new Exception('Erro ao atualizar exercício: ' . $conn->error);
    }
    
    $stmt->close();
}

/**
 * Excluir exercício
 */
function deleteExercise($conn, $id, $patient_id) {
    $stmt = $conn->prepare("DELETE FROM sf_user_onboarding_completion WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $id, $patient_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows === 0) {
            throw new Exception('Exercício não encontrado ou não pode ser excluído');
        }
        echo json_encode([
            'success' => true, 
            'message' => 'Exercício excluído com sucesso'
        ]);
    } else {
        throw new Exception('Erro ao excluir exercício: ' . $conn->error);
    }
    
    $stmt->close();
}
?>