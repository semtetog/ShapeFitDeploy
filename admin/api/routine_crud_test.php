<?php
/**
 * API para CRUD de Rotina - Versão de Teste SEM AUTENTICAÇÃO
 */

// Ativar logs de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();

// Log inicial
error_log('=== ROUTINE_CRUD_TEST.PHP INICIADO ===');
error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
error_log('REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
error_log('QUERY_STRING: ' . ($_SERVER['QUERY_STRING'] ?? 'N/A'));

require_once __DIR__ . '/../../includes/config.php';

// Headers para JSON
header('Content-Type: application/json; charset=utf-8');

// PULAR VERIFICAÇÃO DE AUTENTICAÇÃO PARA TESTE
error_log('PULANDO VERIFICAÇÃO DE AUTENTICAÇÃO PARA TESTE');

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
    error_log('=== ROUTINE_CRUD_TEST.PHP FINALIZADO ===');
}
?>

