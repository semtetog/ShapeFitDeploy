<?php
/**
 * API para CRUD de Missões de Rotina
 * Permite ao nutricionista criar, editar, listar e excluir missões personalizadas
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

// Processar ação
try {
    switch ($action) {
        case 'list':
            listMissions($conn);
            break;
        
        case 'get':
            getMission($conn);
            break;
        
        case 'create':
            createMission($conn);
            break;
        
        case 'update':
            updateMission($conn);
            break;
        
        case 'delete':
            deleteMission($conn);
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
 * Listar todas as missões ativas
 */
function listMissions($conn) {
    $sql = "SELECT id, name, description, icon_name, mission_type, 
            default_duration_minutes, is_active, created_at, updated_at 
            FROM sf_routine_missions 
            WHERE is_active = 1 
            ORDER BY name ASC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Erro ao buscar missões: ' . $conn->error);
    }
    
    $missions = [];
    while ($row = $result->fetch_assoc()) {
        $missions[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $missions]);
}

/**
 * Obter uma missão específica
 */
function getMission($conn) {
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT id, name, description, icon_name, mission_type, 
                            default_duration_minutes, is_active, created_at, updated_at 
                            FROM sf_routine_missions WHERE id = ?");
    $stmt->bind_param('i', $id);
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
function createMission($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validações
    if (empty($data['name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nome da missão é obrigatório']);
        return;
    }
    
    $name = $conn->real_escape_string(trim($data['name']));
    $description = isset($data['description']) ? $conn->real_escape_string(trim($data['description'])) : null;
    $icon_name = $conn->real_escape_string($data['icon_name'] ?? 'clock');
    $mission_type = in_array($data['mission_type'], ['binary', 'duration']) ? $data['mission_type'] : 'binary';
    $default_duration = $mission_type === 'duration' && isset($data['default_duration_minutes']) 
                        ? intval($data['default_duration_minutes']) 
                        : null;
    
    $stmt = $conn->prepare("INSERT INTO sf_routine_missions 
                           (name, description, icon_name, mission_type, default_duration_minutes) 
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('ssssi', $name, $description, $icon_name, $mission_type, $default_duration);
    
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
function updateMission($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = intval($data['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    
    // Validações
    if (empty($data['name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nome da missão é obrigatório']);
        return;
    }
    
    $name = $conn->real_escape_string(trim($data['name']));
    $description = isset($data['description']) ? $conn->real_escape_string(trim($data['description'])) : null;
    $icon_name = $conn->real_escape_string($data['icon_name'] ?? 'clock');
    $mission_type = in_array($data['mission_type'], ['binary', 'duration']) ? $data['mission_type'] : 'binary';
    $default_duration = $mission_type === 'duration' && isset($data['default_duration_minutes']) 
                        ? intval($data['default_duration_minutes']) 
                        : null;
    
    $stmt = $conn->prepare("UPDATE sf_routine_missions 
                           SET name = ?, description = ?, icon_name = ?, 
                               mission_type = ?, default_duration_minutes = ? 
                           WHERE id = ?");
    $stmt->bind_param('ssssii', $name, $description, $icon_name, $mission_type, $default_duration, $id);
    
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
 * Excluir missão (soft delete)
 */
function deleteMission($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? $_GET['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    
    // Soft delete - apenas marca como inativa
    $stmt = $conn->prepare("UPDATE sf_routine_missions SET is_active = 0 WHERE id = ?");
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Missão excluída com sucesso'
        ]);
    } else {
        throw new Exception('Erro ao excluir missão: ' . $conn->error);
    }
}
?>

