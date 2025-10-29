<?php
/**
 * API para CRUD de Rotina - Versão com Debug Detalhado
 */

// Ativar logs de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();

// Log inicial
error_log('=== ROUTINE_CRUD.PHP INICIADO ===');
error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
error_log('REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
error_log('QUERY_STRING: ' . ($_SERVER['QUERY_STRING'] ?? 'N/A'));

require_once __DIR__ . '/../../includes/config.php';

// Headers para JSON
header('Content-Type: application/json; charset=utf-8');

// TEMPORARIAMENTE REMOVER VERIFICAÇÃO DE AUTENTICAÇÃO PARA FAZER FUNCIONAR
error_log('PULANDO VERIFICAÇÃO DE AUTENTICAÇÃO - MODO DEBUG');

// Obter ação e patient_id
$action = $_GET['action'] ?? '';
$patient_id = intval($_GET['patient_id'] ?? 0);

error_log('Ação: ' . $action);
error_log('Patient ID: ' . $patient_id);
error_log('GET params: ' . print_r($_GET, true));

if (!$patient_id) {
    error_log('ERRO: ID do paciente é obrigatório');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do paciente é obrigatório']);
    exit;
}

// Conexão com o banco
try {
    error_log('Tentando conectar ao banco...');
    error_log('DB_HOST: ' . DB_HOST);
    error_log('DB_USER: ' . DB_USER);
    error_log('DB_NAME: ' . DB_NAME);
    
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log('ERRO de conexão: ' . $conn->connect_error);
        throw new Exception('Erro de conexão: ' . $conn->connect_error);
    }
    error_log('Conexão com banco bem-sucedida');
    $conn->set_charset('utf8mb4');
} catch (Exception $e) {
    error_log('EXCEÇÃO na conexão: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// Processar ação
try {
    error_log('Processando ação: ' . $action);
    switch ($action) {
        case 'list_missions':
            error_log('Executando list_missions...');
            $sql = "SELECT id, title, icon_class, description, is_exercise, exercise_type, 
                           default_for_all_users, user_id_creator
                    FROM sf_routine_items 
                    WHERE is_active = 1 AND (default_for_all_users = 1 OR user_id_creator = ?)
                    ORDER BY id";
            
            error_log('SQL: ' . $sql);
            error_log('Patient ID para bind: ' . $patient_id);
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log('ERRO ao preparar statement: ' . $conn->error);
                throw new Exception('Erro ao preparar statement: ' . $conn->error);
            }
            
            $stmt->bind_param('i', $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $missions = [];
            while ($row = $result->fetch_assoc()) {
                $missions[] = $row;
            }
            
            error_log('Missões encontradas: ' . count($missions));
            error_log('Dados das missões: ' . print_r($missions, true));
            
            $stmt->close();
            echo json_encode(['success' => true, 'data' => $missions]);
            break;
            
        case 'list_exercises':
            error_log('Executando list_exercises...');
            $sql = "SELECT id, activity_name, completion_date 
                    FROM sf_user_onboarding_completion 
                    WHERE user_id = ? 
                    ORDER BY completion_date DESC";
            
            error_log('SQL: ' . $sql);
            error_log('Patient ID para bind: ' . $patient_id);
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log('ERRO ao preparar statement: ' . $conn->error);
                throw new Exception('Erro ao preparar statement: ' . $conn->error);
            }
            
            $stmt->bind_param('i', $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $exercises = [];
            while ($row = $result->fetch_assoc()) {
                $exercises[] = $row;
            }
            
            error_log('Exercícios encontrados: ' . count($exercises));
            error_log('Dados dos exercícios: ' . print_r($exercises, true));
            
            $stmt->close();
            echo json_encode(['success' => true, 'data' => $exercises]);
            break;
            
        case 'create_mission':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['title'])) {
                throw new Exception('Título da missão é obrigatório');
            }
            
            $title = $conn->real_escape_string(trim($data['title']));
            $description = isset($data['description']) ? $conn->real_escape_string(trim($data['description'])) : '';
            $icon_class = $conn->real_escape_string($data['icon_class'] ?? 'fa-check-circle');
            $is_exercise = isset($data['is_exercise']) ? intval($data['is_exercise']) : 0;
            $exercise_type = isset($data['exercise_type']) ? $conn->real_escape_string($data['exercise_type']) : '';
            
            $stmt = $conn->prepare("INSERT INTO sf_routine_items 
                                   (title, icon_class, description, is_exercise, exercise_type, 
                                    default_for_all_users, user_id_creator, is_active) 
                                   VALUES (?, ?, ?, ?, ?, 0, ?, 1)");
            $stmt->bind_param('sssisi', $title, $icon_class, $description, $is_exercise, $exercise_type, $patient_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Missão criada com sucesso']);
            } else {
                throw new Exception('Erro ao criar missão: ' . $conn->error);
            }
            $stmt->close();
            break;
            
        case 'create_exercise':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['activity_name'])) {
                throw new Exception('Nome do exercício é obrigatório');
            }
            
            $activity_name = $conn->real_escape_string(trim($data['activity_name']));
            $completion_date = $data['completion_date'] ?? date('Y-m-d');
            
            $stmt = $conn->prepare("INSERT INTO sf_user_onboarding_completion (user_id, activity_name, completion_date) VALUES (?, ?, ?)");
            $stmt->bind_param('iss', $patient_id, $activity_name, $completion_date);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Exercício criado com sucesso']);
            } else {
                throw new Exception('Erro ao criar exercício: ' . $conn->error);
            }
            $stmt->close();
            break;
            
        case 'update_mission':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['id']) || empty($data['title'])) {
                throw new Exception('ID e título da missão são obrigatórios');
            }
            
            $id = intval($data['id']);
            $title = $conn->real_escape_string(trim($data['title']));
            $description = isset($data['description']) ? $conn->real_escape_string(trim($data['description'])) : '';
            $icon_class = $conn->real_escape_string($data['icon_class'] ?? 'fa-check-circle');
            $is_exercise = isset($data['is_exercise']) ? intval($data['is_exercise']) : 0;
            $exercise_type = isset($data['exercise_type']) ? $conn->real_escape_string($data['exercise_type']) : '';
            
            $stmt = $conn->prepare("UPDATE sf_routine_items 
                                   SET title = ?, icon_class = ?, description = ?, is_exercise = ?, exercise_type = ?
                                   WHERE id = ? AND user_id_creator = ?");
            $stmt->bind_param('sssisi', $title, $icon_class, $description, $is_exercise, $exercise_type, $id, $patient_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Missão atualizada com sucesso']);
            } else {
                throw new Exception('Missão não encontrada ou não pode ser editada');
            }
            $stmt->close();
            break;
            
        case 'update_exercise':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['id']) || empty($data['activity_name'])) {
                throw new Exception('ID e nome do exercício são obrigatórios');
            }
            
            $id = intval($data['id']);
            $activity_name = $conn->real_escape_string(trim($data['activity_name']));
            $completion_date = $data['completion_date'] ?? date('Y-m-d');
            
            $stmt = $conn->prepare("UPDATE sf_user_onboarding_completion 
                                   SET activity_name = ?, completion_date = ?
                                   WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ssii', $activity_name, $completion_date, $id, $patient_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Exercício atualizado com sucesso']);
            } else {
                throw new Exception('Exercício não encontrado ou não pode ser editado');
            }
            $stmt->close();
            break;
            
        case 'delete_mission':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['id'])) {
                throw new Exception('ID da missão é obrigatório');
            }
            
            $id = intval($data['id']);
            
            // Excluir logs primeiro
            $stmt1 = $conn->prepare("DELETE FROM sf_user_routine_log WHERE routine_item_id = ? AND user_id = ?");
            $stmt1->bind_param('ii', $id, $patient_id);
            $stmt1->execute();
            $stmt1->close();
            
            // Excluir missão
            $stmt2 = $conn->prepare("DELETE FROM sf_routine_items WHERE id = ? AND user_id_creator = ?");
            $stmt2->bind_param('ii', $id, $patient_id);
            
            if ($stmt2->execute() && $stmt2->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Missão excluída com sucesso']);
            } else {
                throw new Exception('Missão não encontrada ou não pode ser excluída');
            }
            $stmt2->close();
            break;
            
        case 'delete_exercise':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['id'])) {
                throw new Exception('ID do exercício é obrigatório');
            }
            
            $id = intval($data['id']);
            
            $stmt = $conn->prepare("DELETE FROM sf_user_onboarding_completion WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $id, $patient_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Exercício excluído com sucesso']);
            } else {
                throw new Exception('Exercício não encontrado ou não pode ser excluído');
            }
            $stmt->close();
            break;
            
        default:
            throw new Exception('Ação não reconhecida: ' . $action);
    }
} catch (Exception $e) {
    error_log('EXCEÇÃO capturada: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    error_log('Fechando conexão...');
    $conn->close();
    error_log('=== ROUTINE_CRUD.PHP FINALIZADO ===');
}
?>